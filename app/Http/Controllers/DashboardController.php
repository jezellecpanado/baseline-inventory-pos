<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\InventoryBalance;
use App\Models\InventoryHistory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        [$from, $to] = $this->range($request);
        $sales = InventoryTransaction::query()
            ->whereIn('type', ['pos_sale', 'offline_sale'])
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->with('lines')
            ->get();
        $sumLocation = fn (string $type): float => (float) $sales->where('type', $type)->sum('total');
        $inventoryProducts = Product::query()->with('balance')->get()->keyBy('id');
        $variantRows = [];
        foreach ($sales as $sale) {
            foreach ($sale->lines as $line) {
                $key = $line->product_id ?? 'barcode:'.$line->barcode;
                $product = $line->product_id ? $inventoryProducts->get($line->product_id) : null;
                $variantRows[$key] ??= [
                    'name' => $line->product_name,
                    'variant' => $line->variant,
                    'size' => $line->size,
                    'barcode' => $line->barcode,
                    'photo' => $product?->photo_path,
                    'units' => 0,
                    'sales' => 0.0,
                    'warehouse' => $product?->balance?->warehouse_quantity ?? 0,
                    'pos' => $product?->balance?->pos_quantity ?? 0,
                ];
                $variantRows[$key]['units'] += $line->quantity;
                $variantRows[$key]['sales'] += (float) $line->line_total;
            }
        }
        $variantRows = array_values($variantRows);
        $sort = in_array($request->input('sort'), ['units', 'sales'], true) ? $request->input('sort') : 'units';
        $performance = $variantRows;
        $sizeOrder = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
        $sizeRank = static function (string $size) use ($sizeOrder): int {
            $rank = array_search($size, $sizeOrder, true);

            return $rank === false ? count($sizeOrder) : $rank;
        };
        usort($performance, static fn (array $a, array $b): int => (($b[$sort] <=> $a[$sort]) ?: strcmp($a['name'], $b['name']) ?: strcmp($a['variant'], $b['variant']) ?: ($sizeRank($a['size']) <=> $sizeRank($b['size']))));
        $performance = array_slice($performance, 0, 10);

        $recentQuery = InventoryHistory::query()->latest('created_at');
        if (! $user->isAdmin()) {
            $recentQuery->where('location', $user->role === User::ROLE_WAREHOUSE ? 'Warehouse' : 'POS');
        }
        $recent = $recentQuery->limit(8)->get();

        $historyQuery = InventoryHistory::query()->with('transaction')->latest('created_at');
        if (filled($request->input('history_search'))) {
            $term = '%'.$request->input('history_search').'%';
            $historyQuery->where(function ($query) use ($term): void {
                $query->where('reference', 'like', $term)->orWhere('barcode', 'like', $term)->orWhere('product_name', 'like', $term)->orWhere('movement_type', 'like', $term);
            });
        }
        if (in_array($request->input('location'), ['Warehouse', 'POS'], true)) {
            $historyQuery->where('location', $request->input('location'));
        }
        if ($request->filled('movement_type')) {
            $historyQuery->where('movement_type', $request->input('movement_type'));
        }
        if ($request->filled('history_from')) {
            $historyQuery->whereDate('created_at', '>=', $request->input('history_from'));
        }
        if ($request->filled('history_to')) {
            $historyQuery->whereDate('created_at', '<=', $request->input('history_to'));
        }

        return view('dashboard', [
            'user' => $user,
            'adminWorkspace' => $request->routeIs('admin.index'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'salesTotal' => (float) $sales->sum('total'),
            'posSales' => (float) $sales->where('type', 'pos_sale')->sum('total'),
            'warehouseSales' => (float) $sales->where('type', 'offline_sale')->sum('total'),
            'salesTransactions' => $sales->count(),
            'unitsSold' => (int) $sales->sum(fn ($sale) => $sale->lines->sum('quantity')),
            'cashSales' => (float) $sales->where('payment_method', 'cash')->sum('total'),
            'qrSales' => (float) $sales->where('payment_method', 'qr')->sum('total'),
            'warehouseTotal' => (int) InventoryBalance::query()->sum('warehouse_quantity'),
            'posTotal' => (int) InventoryBalance::query()->sum('pos_quantity'),
            'performance' => $performance,
            'recent' => $recent,
            'adminTab' => $request->input('tab', 'products'),
            'products' => $user->isAdmin() ? Product::query()->with(['balance', 'promotion'])->orderedForDisplay()->get() : collect(),
            'eventPromotionId' => Promotion::query()->where('required_quantity', 2)->where('bundle_price', 899)->orderBy('id')->value('id'),
            'users' => $user->isAdmin() ? User::query()->orderBy('name')->get() : collect(),
            'transactions' => $user->isAdmin() ? InventoryTransaction::query()->with('lines')->latest('completed_at')->get() : collect(),
            'history' => $user->isAdmin() ? $historyQuery->paginate(50)->withQueryString() : collect(),
            'qrImage' => $user->isAdmin() ? AppSetting::query()->where('key', 'qr_image')->value('value') : null,
            'editingProduct' => $user->isAdmin() && $request->integer('edit') ? Product::query()->find($request->integer('edit')) : null,
            'sales' => $sales,
            'activeProducts' => $user->isAdmin() ? Product::query()->where('active', true)->orderedForDisplay()->get() : collect(),
            'currentRange' => $request->input('range', 'today'),
            'currentSort' => $sort,
        ]);
    }

    /** @return array{Carbon, Carbon} */
    private function range(Request $request): array
    {
        $now = now('Asia/Manila');

        return match ($request->input('range', 'today')) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'custom' => $this->customRange($request, $now),
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /** @return array{Carbon, Carbon} */
    private function customRange(Request $request, Carbon $now): array
    {
        $from = Carbon::parse($request->input('from', $now->toDateString()), 'Asia/Manila')->startOfDay();
        $to = Carbon::parse($request->input('to', $now->toDateString()), 'Asia/Manila')->endOfDay();

        return $from->greaterThan($to) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }
}
