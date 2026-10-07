<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\InventoryHistory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function warehouse(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'movement' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $historyQuery = InventoryHistory::query()
            ->where('location', 'Warehouse')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('barcode', 'like', '%'.$search.'%')
                        ->orWhere('product_name', 'like', '%'.$search.'%')
                        ->orWhere('reference', 'like', '%'.$search.'%')
                        ->orWhere('movement_type', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['movement'] ?? null, fn ($query, string $movement) => $query->where('movement_type', $movement))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('created_at');

        $visibleTransactionTypes = $request->user()->isAdmin()
            ? ['beginning', 'receive', 'transfer', 'return', 'offline_sale', 'withdrawal', 'adjustment', 'exchange']
            : ['receive', 'transfer', 'return', 'offline_sale'];
        $transactionQuery = InventoryTransaction::query()
            ->with('lines')
            ->whereIn('type', $visibleTransactionTypes)
            ->whereIn('status', ['completed', 'void'])
            ->where(function ($query): void {
                $query->whereNotIn('type', ['exchange', 'adjustment', 'withdrawal'])
                    ->orWhere(function ($query): void {
                        $query->where('type', 'exchange')->where('location', 'warehouse');
                    })
                    ->orWhere(function ($query): void {
                        $query->where('type', 'adjustment')->where('location', 'warehouse');
                    })
                    ->orWhere(function ($query): void {
                        $query->where('type', 'withdrawal')->where('location', 'warehouse');
                    });
            })
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhereHas('lines', function ($query) use ($search): void {
                            $query->where('barcode', 'like', '%'.$search.'%')
                                ->orWhere('product_name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['movement'] ?? null, function ($query, string $movement): void {
                $types = match ($movement) {
                    'Beginning Inventory' => ['beginning'],
                    'Stock Received' => ['receive'],
                    'Warehouse → POS Transfer' => ['transfer'],
                    'POS → Warehouse Return' => ['return'],
                    'Offline/Warehouse Sale' => ['offline_sale'],
                    'Withdrawal' => ['withdrawal'],
                    'Adjustment' => ['adjustment'],
                    'Void/Reversal' => ['offline_sale'],
                    default => [],
                };
                $query->whereIn('type', $types);
                if ($movement === 'Void/Reversal') {
                    $query->where('status', 'void');
                }
            })
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('completed_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('completed_at', '<=', $to))
            ->latest('completed_at');

        $histories = $historyQuery->paginate(50)->withQueryString();
        $transactions = $transactionQuery->paginate(30, ['*'], 'transaction_page')->withQueryString();

        return view('warehouse', [
            'user' => $request->user(),
            'products' => Product::query()->with('balance')->where('active', true)->orderedForDisplay()->get(),
            'histories' => $histories,
            'transactions' => $transactions,
            'tab' => $request->input('tab', 'inventory'),
            'admin' => $request->user()->isAdmin(),
            'qrImage' => AppSetting::query()->where('key', 'qr_image')->value('value'),
        ]);
    }

    public function pos(Request $request): View
    {
        return view('pos', [
            'user' => $request->user(),
            'products' => Product::query()->with(['balance', 'promotion'])->where('active', true)->orderedForDisplay()->get(),
            'transactions' => InventoryTransaction::query()->with('lines')->whereIn('status', ['completed', 'void'])->where(function ($query) use ($request): void {
                $query->where('type', 'pos_sale')->orWhere(function ($query): void {
                    $query->where('type', 'exchange')->where('location', 'pos');
                });
                if ($request->user()->isAdmin()) {
                    $query->orWhere(function ($query): void {
                        $query->where('type', 'withdrawal')->where('location', 'pos');
                    });
                }
            })->latest('completed_at')->limit(100)->get(),
            'tab' => $request->input('tab', 'sell'),
            'qrImage' => AppSetting::query()->where('key', 'qr_image')->value('value'),
        ]);
    }

    public function storeWarehouse(Request $request, string $operation, InventoryService $service): RedirectResponse
    {
        $allowed = ['receive', 'transfer', 'return', 'offline_sale', 'withdrawal', 'adjustment'];
        abort_unless(in_array($operation, $allowed, true), 404);
        if (in_array($operation, ['withdrawal', 'adjustment'], true)) {
            abort_unless($request->user()->isAdmin(), 403);
        }
        $data = $request->validate([
            'submission_key' => ['required', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'min:'.($operation === 'adjustment' ? '0' : '1')],
            'prices' => [$operation === 'offline_sale' ? 'sometimes' : 'prohibited', 'array'],
            'prices.*' => [$operation === 'offline_sale' ? 'numeric' : 'prohibited', 'min:0', 'decimal:0,2'],
            'customer_name' => [$operation === 'offline_sale' ? 'required' : 'prohibited', 'string', 'max:255'],
            'payment_method' => [$operation === 'offline_sale' ? 'required' : 'prohibited', 'in:cash,qr'],
            'qr_confirmed' => $operation === 'offline_sale' ? ['sometimes', 'accepted'] : ['prohibited'],
            'reason' => [$operation === 'withdrawal' ? 'required' : ($operation === 'adjustment' ? 'prohibited' : 'nullable'), 'string', 'max:100'],
            'notes' => [in_array($operation, ['offline_sale', 'withdrawal'], true) ? 'nullable' : 'prohibited', 'string', 'max:2000'],
            'location' => ['prohibited'],
            'product_id' => ['prohibited'],
            'actual_stock' => ['prohibited'],
        ]);
        if ($operation === 'withdrawal' && ($data['reason'] ?? null) === 'Others' && blank($data['notes'] ?? null)) {
            throw ValidationException::withMessages(['notes' => 'Notes are required when Others is selected.']);
        }
        if ($operation === 'adjustment') {
            $data['location'] = 'warehouse';
        }
        if ($operation === 'withdrawal') {
            $data['location'] = 'warehouse';
        }
        if ($operation === 'offline_sale') {
            $data['metadata'] = ['source' => 'warehouse'];
        }
        $tx = $service->complete($request->user(), $operation, $data);

        return redirect()->route('warehouse', ['tab' => $operation])->with('success', $tx->reference.' completed.');
    }

    public function storePosSale(Request $request, InventoryService $service): RedirectResponse
    {
        $data = $request->validate([
            'submission_key' => ['required', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:cash,qr'],
            'qr_confirmed' => ['exclude_unless:payment_method,qr', 'required', 'accepted'],
        ]);
        $tx = $service->complete($request->user(), 'pos_sale', $data);

        return redirect()->route('pos', ['tab' => 'sell'])->with('success', $tx->reference.' completed. Total: ₱'.number_format((float) $tx->total, 2));
    }

    public function storePosWithdrawal(Request $request, InventoryService $service): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'submission_key' => ['required', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'in:Freebies,Giveaways,Sponsorship,Marketing,Samples,Damaged Items,Others'],
            'notes' => ['required_if:reason,Others', 'nullable', 'string', 'max:2000'],
        ]);
        $data['location'] = 'pos';
        $transaction = $service->complete($request->user(), 'withdrawal', $data);

        return redirect()->route('pos')->with('success', $transaction->reference.' POS withdrawal completed.');
    }

    public function storeExchange(Request $request, InventoryService $service): RedirectResponse
    {
        $data = $request->validate([
            'submission_key' => ['required', 'string', 'max:64'],
            'location' => ['required', 'in:pos,warehouse'],
            'returned_product_id' => ['required', 'integer', 'exists:products,id'],
            'returned_quantity' => ['required', 'integer', 'min:1'],
            'condition' => ['required', 'in:sellable,damaged'],
            'replacement_product_id' => ['required', 'integer', 'exists:products,id'],
            'replacement_quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'in:cash,qr'],
            'qr_confirmed' => ['sometimes', 'accepted'],
        ]);
        if ($data['location'] === 'warehouse' && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $tx = $service->complete($request->user(), 'exchange', $data);

        return redirect()->route($data['location'] === 'warehouse' ? 'warehouse' : 'pos', ['tab' => 'exchanges'])->with('success', $tx->reference.' completed.');
    }
}
