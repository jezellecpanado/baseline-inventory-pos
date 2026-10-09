<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\InventoryBalance;
use App\Models\InventoryHistory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\TransactionLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /** @param array<string, mixed> $data */
    public function complete(User $user, string $type, array $data): InventoryTransaction
    {
        if (! in_array($type, ['receive', 'transfer', 'return', 'offline_sale', 'pos_sale', 'exchange', 'withdrawal', 'adjustment'], true)) {
            throw ValidationException::withMessages(['operation' => 'This inventory operation is unavailable.']);
        }

        return DB::transaction(function () use ($user, $type, $data): InventoryTransaction {
            $submissionKey = (string) ($data['submission_key'] ?? '');
            if ($submissionKey !== '') {
                $existing = InventoryTransaction::query()->where('submission_key', $submissionKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $now = now('Asia/Manila');
            $transaction = InventoryTransaction::query()->create([
                'reference' => $this->reference($type, $now),
                'submission_key' => $submissionKey ?: null,
                'type' => $type,
                'status' => 'completed',
                'location' => $data['location'] ?? match ($type) {
                    'receive', 'transfer', 'offline_sale', 'adjustment', 'withdrawal' => 'warehouse',
                    'return', 'pos_sale' => 'pos',
                    default => null,
                },
                'customer_name' => $data['customer_name'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $user->id,
                'created_by_name' => $user->name,
                'completed_at' => $now,
            ]);

            if ($type === 'adjustment') {
                $this->adjust($transaction, $user, $data, $now);
            } elseif ($type === 'exchange') {
                $this->exchange($transaction, $user, $data, $now);
            } else {
                $this->standardItems($transaction, $user, $type, $data, $now);
            }

            return $transaction->load('lines');
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    private function standardItems(InventoryTransaction $transaction, User $user, string $type, array $data, Carbon $now): void
    {
        $quantities = $this->normalizeItems($data['items'] ?? []);
        $products = $this->lockProducts(array_keys($quantities));
        $eligibleQuantity = 0;
        $promotionGroups = 0;
        if (in_array($type, ['pos_sale', 'offline_sale'], true) && ! $quantities) {
            throw ValidationException::withMessages(['items' => 'Add at least one item.']);
        }
        if (in_array($type, ['receive', 'transfer', 'return', 'withdrawal'], true) && ! $quantities) {
            throw ValidationException::withMessages(['items' => 'Add at least one item.']);
        }
        if ($type === 'pos_sale') {
            $eligibleQuantity = collect($quantities)->sum(
                fn (int $quantity, int $productId): int => $products->get($productId)?->promotion_id !== null ? $quantity : 0,
            );
            $promotionGroups = intdiv($eligibleQuantity, 2);
            if ($promotionGroups > 0) {
                $eligibleProduct = $products->first(fn (Product $product): bool => $product->promotion_id !== null);
                $transaction->promotion_id = $eligibleProduct?->promotion_id;
            }
        }
        if (($data['payment_method'] ?? null) === 'qr') {
            if (! AppSetting::query()->where('key', 'qr_image')->whereNotNull('value')->exists()) {
                throw ValidationException::withMessages(['payment_method' => 'Configure a QR image before accepting QR payment.']);
            }
            if (empty($data['qr_confirmed'])) {
                throw ValidationException::withMessages(['qr_confirmed' => 'Confirm that the QR payment was received.']);
            }
        }

        $location = match ($type) {
            'transfer' => 'warehouse',
            'return', 'pos_sale', 'exchange' => 'pos',
            'withdrawal' => $data['location'] ?? 'warehouse',
            default => 'warehouse',
        };
        $outgoing = in_array($type, ['transfer', 'return', 'withdrawal', 'offline_sale', 'pos_sale'], true);
        $subtotalCents = 0;
        $totalCents = 0;
        $promoUnitsByProduct = [];
        $bundleSharesByProduct = [];
        if ($type === 'pos_sale' && $promotionGroups > 0) {
            $remainingPromotionUnits = $promotionGroups * 2;
            foreach ($quantities as $productId => $quantity) {
                if ($products->get($productId)?->promotion_id === null || $remainingPromotionUnits < 1) {
                    continue;
                }
                $promoUnitsByProduct[$productId] = min($quantity, $remainingPromotionUnits);
                $remainingPromotionUnits -= $promoUnitsByProduct[$productId];
            }
            $coveredRetailCents = 0;
            foreach ($promoUnitsByProduct as $productId => $promoUnits) {
                $coveredRetailCents += $promoUnits * (int) round((float) $products->get($productId)->standard_price * 100);
            }
            $bundleTotalCents = min(
                $promotionGroups * 89900,
                $coveredRetailCents,
            );
            $allocatedBundleCents = 0;
            $coveredProductIds = array_keys($promoUnitsByProduct);
            $lastCoveredProductId = end($coveredProductIds);
            foreach ($promoUnitsByProduct as $productId => $promoUnits) {
                $coveredProductRetailCents = $promoUnits * (int) round((float) $products->get($productId)->standard_price * 100);
                $bundleSharesByProduct[$productId] = $productId === $lastCoveredProductId
                    ? $bundleTotalCents - $allocatedBundleCents
                    : (int) round($bundleTotalCents * $coveredProductRetailCents / max(1, $coveredRetailCents));
                $allocatedBundleCents += $bundleSharesByProduct[$productId];
            }
        }

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            $balance = $this->balance($productId, true);
            $stockColumn = $location === 'warehouse' ? 'warehouse_quantity' : 'pos_quantity';
            if ($outgoing && $balance->{$stockColumn} < $quantity) {
                throw ValidationException::withMessages(['items' => "Insufficient {$location} stock for {$product->design_name} / {$product->variant} / {$product->size}."]);
            }

            $unitPrice = match ($type) {
                'pos_sale' => (float) $product->standard_price,
                'offline_sale' => (float) ($data['prices'][$productId] ?? $product->standard_price),
                default => 0.0,
            };
            $unitPriceCents = (int) round($unitPrice * 100);
            $lineTotalCents = $quantity * $unitPriceCents;
            if ($type === 'pos_sale' && $promotionGroups > 0 && isset($promoUnitsByProduct[$productId])) {
                $promoUnits = $promoUnitsByProduct[$productId];
                $lineTotalCents = ($quantity - $promoUnits) * $unitPriceCents + $bundleSharesByProduct[$productId];
            }
            $subtotalCents += $quantity * $unitPriceCents;
            $totalCents += $lineTotalCents;

            TransactionLine::query()->create($this->lineData($transaction, $product, $quantity, $unitPrice, $lineTotalCents / 100));
            $this->applyStandardMovement($transaction, $user, $product, $balance, $type, $quantity, $now, $data);
        }

        if (in_array($type, ['pos_sale', 'offline_sale'], true)) {
            $transaction->subtotal = $subtotalCents / 100;
            $transaction->total = $totalCents / 100;
            $transaction->discount = ($subtotalCents - $totalCents) / 100;
            $transaction->save();
        }
    }

    /** @param array<string, mixed> $data */
    private function applyStandardMovement(InventoryTransaction $transaction, User $user, Product $product, InventoryBalance $balance, string $type, int $quantity, Carbon $now, array $data): void
    {
        $reason = $data['reason'] ?? null;
        $notes = $data['notes'] ?? null;
        if ($type === 'receive') {
            InventoryBalance::query()
                ->whereKey($balance->getKey())
                ->increment('warehouse_quantity', $quantity, ['updated_at' => $now]);
            $this->history($transaction, $user, $product, 'Warehouse', $quantity, 'Stock Received', $now, $reason, $notes);

            return;
        }
        if ($type === 'transfer') {
            $balance->warehouse_quantity -= $quantity;
            $balance->pos_quantity += $quantity;
            $balance->save();
            $this->history($transaction, $user, $product, 'Warehouse', -$quantity, 'Warehouse → POS Transfer', $now, $reason, $notes);
            $this->history($transaction, $user, $product, 'POS', $quantity, 'Warehouse → POS Transfer', $now, $reason, $notes);

            return;
        }
        if ($type === 'return') {
            $balance->pos_quantity -= $quantity;
            $balance->warehouse_quantity += $quantity;
            $balance->save();
            $this->history($transaction, $user, $product, 'POS', -$quantity, 'POS → Warehouse Return', $now, $reason, $notes);
            $this->history($transaction, $user, $product, 'Warehouse', $quantity, 'POS → Warehouse Return', $now, $reason, $notes);

            return;
        }
        if ($type === 'withdrawal') {
            $location = $transaction->location === 'pos' ? 'POS' : 'Warehouse';
            $stockColumn = $location === 'POS' ? 'pos_quantity' : 'warehouse_quantity';
            $balance->{$stockColumn} -= $quantity;
            $balance->save();
            $this->history($transaction, $user, $product, $location, -$quantity, $location === 'POS' ? 'POS Withdrawal' : 'Withdrawal', $now, $reason, $notes);

            return;
        }
        if ($type === 'offline_sale' || $type === 'pos_sale') {
            $stockColumn = $type === 'offline_sale' ? 'warehouse_quantity' : 'pos_quantity';
            $balance->{$stockColumn} -= $quantity;
            $balance->save();
            $this->history($transaction, $user, $product, $type === 'offline_sale' ? 'Warehouse' : 'POS', -$quantity, $type === 'offline_sale' ? 'Offline/Warehouse Sale' : 'POS Sale', $now, $reason, $notes);
        }
    }

    /** @param array<string, mixed> $data */
    private function adjust(InventoryTransaction $transaction, User $user, array $data, Carbon $now): void
    {
        $location = $data['location'];
        $column = $location === 'warehouse' ? 'warehouse_quantity' : 'pos_quantity';
        $actualStocks = [];
        foreach (($data['items'] ?? []) as $productId => $actual) {
            if (! ctype_digit((string) $productId) || ! is_numeric($actual) || (int) $actual < 0 || (float) $actual !== (float) (int) $actual) {
                throw ValidationException::withMessages(['items' => 'Actual stock must be a non-negative whole number for each product.']);
            }
            $actualStocks[(int) $productId] = (int) $actual;
        }
        ksort($actualStocks);
        if (! $actualStocks) {
            throw ValidationException::withMessages(['items' => 'Add at least one product size to adjust.']);
        }
        $products = $this->lockProducts(array_keys($actualStocks));
        if ($products->count() !== count($actualStocks)) {
            throw ValidationException::withMessages(['items' => 'One or more selected products are unavailable.']);
        }
        $adjustments = [];
        foreach ($actualStocks as $productId => $actual) {
            $product = $products->get($productId);
            $balance = $this->balance($productId, true);
            $previous = (int) $balance->{$column};
            $delta = $actual - $previous;
            $balance->{$column} = $actual;
            $balance->save();
            TransactionLine::query()->create($this->lineData($transaction, $product, abs($delta), 0, 0));
            $this->history($transaction, $user, $product, $this->locationLabel($location), $delta, 'Adjustment', $now, null, null);
            $adjustments[$productId] = ['previous_quantity' => $previous, 'new_quantity' => $actual];
        }
        $transaction->location = $location;
        $transaction->metadata = ['adjustments' => $adjustments];
        $transaction->save();
    }

    /** @param array<string, mixed> $data */
    private function exchange(InventoryTransaction $transaction, User $user, array $data, Carbon $now): void
    {
        $location = $data['location'];
        $returned = $this->lockProducts([(int) $data['returned_product_id']])->first();
        $replacement = $this->lockProducts([(int) $data['replacement_product_id']])->first();
        $returnQuantity = (int) $data['returned_quantity'];
        $replacementQuantity = (int) $data['replacement_quantity'];
        $condition = $data['condition'];
        $returnBalance = $this->balance($returned->id, true);
        $replacementBalance = $returned->id === $replacement->id ? $returnBalance : $this->balance($replacement->id, true);
        $column = $location === 'warehouse' ? 'warehouse_quantity' : 'pos_quantity';
        if ($replacementBalance->{$column} < $replacementQuantity) {
            throw ValidationException::withMessages(['replacement_quantity' => 'Not enough stock for the replacement item.']);
        }
        $returnValue = (float) $returned->standard_price * $returnQuantity;
        $replacementValue = (float) $replacement->standard_price * $replacementQuantity;
        $difference = $replacementValue - $returnValue;
        if ($difference > 0 && ($data['payment_method'] ?? null) === 'qr') {
            if (! AppSetting::query()->where('key', 'qr_image')->whereNotNull('value')->exists() || empty($data['qr_confirmed'])) {
                throw ValidationException::withMessages(['payment_method' => 'Configure and confirm QR payment before completing this exchange.']);
            }
        }
        $returnBalanceField = $column;
        if ($condition === 'sellable') {
            $returnBalance->{$returnBalanceField} += $returnQuantity;
            $returnBalance->save();
            $this->history($transaction, $user, $returned, $this->locationLabel($location), $returnQuantity, 'Exchange Return', $now, null, null);
        } else {
            $this->history($transaction, $user, $returned, $this->locationLabel($location), 0, 'Exchange Return', $now, 'Damaged item; excluded from sellable stock.', null);
        }
        $replacementBalance->{$column} -= $replacementQuantity;
        $replacementBalance->save();
        $this->history($transaction, $user, $replacement, $this->locationLabel($location), -$replacementQuantity, 'Exchange Replacement', $now, null, null);
        TransactionLine::query()->create($this->lineData($transaction, $returned, $returnQuantity, (float) $returned->standard_price, $returnValue, $condition));
        TransactionLine::query()->create($this->lineData($transaction, $replacement, $replacementQuantity, (float) $replacement->standard_price, $replacementValue, 'replacement'));
        $transaction->location = $location;
        $transaction->subtotal = $returnValue;
        $transaction->total = $replacementValue;
        $transaction->discount = max(0, -$difference);
        $transaction->payment_method = $difference > 0 ? ($data['payment_method'] ?? null) : null;
        $transaction->metadata = ['amount_paid' => max(0, $difference), 'amount_forfeited' => max(0, -$difference), 'returned_condition' => $condition];
        $transaction->save();
    }

    public function void(User $admin, InventoryTransaction $transaction, string $reason): InventoryTransaction
    {
        return DB::transaction(function () use ($admin, $transaction, $reason): InventoryTransaction {
            $locked = InventoryTransaction::query()->with('lines')->lockForUpdate()->findOrFail($transaction->id);
            if (! in_array($locked->type, ['pos_sale', 'offline_sale'], true) || $locked->status === 'void') {
                throw ValidationException::withMessages(['transaction' => 'This transaction cannot be voided.']);
            }
            $now = now('Asia/Manila');
            $locked->status = 'void';
            $locked->voided_by = $admin->id;
            $locked->voided_by_name = $admin->name;
            $locked->voided_at = $now;
            $locked->void_reason = $reason;
            $locked->save();
            $location = $locked->type === 'pos_sale' ? 'POS' : 'Warehouse';
            foreach ($locked->lines as $line) {
                $balance = $this->balance((int) $line->product_id, true);
                $column = $location === 'POS' ? 'pos_quantity' : 'warehouse_quantity';
                $balance->{$column} += $line->quantity;
                $balance->save();
                InventoryHistory::query()->create([
                    'reference' => $locked->reference,
                    'movement_type' => 'Void/Reversal',
                    'product_id' => $line->product_id,
                    'product_name' => $line->product_name,
                    'category' => $line->category,
                    'variant' => $line->variant,
                    'size' => $line->size,
                    'barcode' => $line->barcode,
                    'location' => $location,
                    'quantity_change' => $line->quantity,
                    'inventory_transaction_id' => $locked->id,
                    'user_id' => $admin->id,
                    'user_name' => $admin->name,
                    'reason' => $reason,
                    'created_at' => $now,
                ]);
            }

            return $locked;
        }, attempts: 3);
    }

    /** @param array<int|string, mixed> $items @return array<int, int> */
    private function normalizeItems(array $items): array
    {
        $quantities = [];
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $productId = (int) ($item['product_id'] ?? $key);
                $quantity = (int) ($item['quantity'] ?? 0);
            } else {
                $productId = (int) $key;
                $quantity = (int) $item;
            }
            if ($quantity < 1) {
                throw ValidationException::withMessages(['items' => 'Quantities must be positive whole numbers.']);
            }
            $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        }
        ksort($quantities);

        return $quantities;
    }

    /** @param array<int, int> $ids */
    private function lockProducts(array $ids): Collection
    {
        $products = Product::query()->whereIn('id', $ids)->where('active', true)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($products->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['items' => 'One or more selected products are inactive or unavailable.']);
        }

        return $products;
    }

    private function balance(int $productId, bool $lock): InventoryBalance
    {
        $balance = InventoryBalance::query()->where('product_id', $productId);
        if ($lock) {
            $balance->lockForUpdate();
        }

        return $balance->firstOrCreate(['product_id' => $productId]);
    }

    /** @return array<string, mixed> */
    private function lineData(InventoryTransaction $transaction, Product $product, int $quantity, float $price, float $total, ?string $condition = null): array
    {
        return [
            'inventory_transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'product_name' => $product->design_name,
            'category' => $product->category,
            'variant' => $product->variant,
            'size' => $product->size,
            'barcode' => $product->barcode,
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => $total,
            'condition' => $condition,
        ];
    }

    private function history(InventoryTransaction $transaction, User $user, Product $product, string $location, int $change, string $movement, Carbon $now, ?string $reason, ?string $notes): void
    {
        InventoryHistory::query()->create([
            'reference' => $transaction->reference,
            'movement_type' => $movement,
            'product_id' => $product->id,
            'product_name' => $product->design_name,
            'category' => $product->category,
            'variant' => $product->variant,
            'size' => $product->size,
            'barcode' => $product->barcode,
            'location' => $location,
            'quantity_change' => $change,
            'inventory_transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'reason' => $reason,
            'notes' => $notes,
            'created_at' => $now,
        ]);
    }

    private function reference(string $type, Carbon $now): string
    {
        $prefix = ['receive' => 'RCV', 'transfer' => 'TRF', 'return' => 'RET', 'offline_sale' => 'OFF', 'pos_sale' => 'POS', 'exchange' => 'EXC', 'withdrawal' => 'WDL', 'adjustment' => 'ADJ'][$type] ?? 'TXN';
        $stem = $prefix.'-'.$now->format('Ymd').'-';
        $last = InventoryTransaction::query()->where('reference', 'like', $stem.'%')->orderByDesc('reference')->value('reference');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $stem.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function locationLabel(string $location): string
    {
        return $location === 'pos' ? 'POS' : 'Warehouse';
    }
}
