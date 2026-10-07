<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryHistory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_cash_sale_ignores_empty_qr_confirmation_and_records_inventory_movement(): void
    {
        $cashier = User::query()->create([
            'name' => 'Cashier Test',
            'username' => 'cashier-test',
            'password' => 'password',
            'role' => User::ROLE_POS,
            'active' => true,
        ]);
        $this->actingAs($cashier)->withSession(['login_completed' => true]);
        $product = $this->product('Cash Sale Test', '87654321');
        InventoryBalance::query()->where('product_id', $product->id)->update([
            'warehouse_quantity' => 7,
            'pos_quantity' => 4,
        ]);

        $this->post(route('pos.sale'), [
            'submission_key' => fake()->uuid(),
            'items' => [$product->id => 2],
            'payment_method' => 'cash',
            'qr_confirmed' => '',
        ])->assertRedirect(route('pos', ['tab' => 'sell']))
            ->assertSessionHasNoErrors();

        $balance = InventoryBalance::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(7, $balance->warehouse_quantity);
        $this->assertSame(2, $balance->pos_quantity);

        $transaction = InventoryTransaction::query()->where('type', 'pos_sale')->firstOrFail();
        $this->assertSame('cash', $transaction->payment_method);
        $this->assertSame('completed', $transaction->status);
        $this->assertSame(998.0, (float) $transaction->total);
        $this->assertSame(2, $transaction->lines()->firstOrFail()->quantity);
        $this->assertDatabaseHas('inventory_histories', [
            'inventory_transaction_id' => $transaction->id,
            'movement_type' => 'POS Sale',
            'product_id' => $product->id,
            'barcode' => '87654321',
            'location' => 'POS',
            'quantity_change' => -2,
            'user_id' => $cashier->id,
        ]);
    }

    public function test_pos_qr_sale_still_requires_payment_confirmation(): void
    {
        $cashier = User::query()->create([
            'name' => 'QR Cashier Test',
            'username' => 'qr-cashier-test',
            'password' => 'password',
            'role' => User::ROLE_POS,
            'active' => true,
        ]);
        $this->actingAs($cashier)->withSession(['login_completed' => true]);
        $product = $this->product('QR Sale Test', '87654322');
        InventoryBalance::query()->where('product_id', $product->id)->update(['pos_quantity' => 4]);

        $this->post(route('pos.sale'), [
            'submission_key' => fake()->uuid(),
            'items' => [$product->id => 1],
            'payment_method' => 'qr',
            'qr_confirmed' => '',
        ])->assertSessionHasErrors('qr_confirmed');

        $this->assertSame(4, InventoryBalance::query()->where('product_id', $product->id)->value('pos_quantity'));
        $this->assertSame(0, InventoryTransaction::query()->count());
        $this->assertSame(0, InventoryHistory::query()->count());
    }

    public function test_sequential_receipts_and_movements_match_the_exact_warehouse_and_pos_balances(): void
    {
        $admin = User::query()->create([
            'name' => 'Test Admin',
            'username' => 'inventory-sequence-admin',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);
        $this->actingAs($admin)->withSession(['login_completed' => true]);
        $product = $this->product('Sequence Test', '87654321');

        $this->postWarehouse('receive', [$product->id => 20]);
        $this->assertStock($product, warehouse: 20, pos: 0);
        $this->assertDisplayedStock($product, warehouse: 20, pos: 0);

        $this->postWarehouse('receive', [$product->id => 10]);
        $this->assertStock($product, warehouse: 30, pos: 0);
        $this->assertDisplayedStock($product, warehouse: 30, pos: 0);

        $this->postWarehouse('transfer', [$product->id => 8]);
        $this->assertStock($product, warehouse: 22, pos: 8);
        $this->assertDisplayedStock($product, warehouse: 22, pos: 8);

        $this->postWarehouse('offline_sale', [$product->id => 2], [
            'customer_name' => 'Sequence Test Customer',
            'payment_method' => 'cash',
        ]);
        $this->assertStock($product, warehouse: 20, pos: 8);
        $this->assertDisplayedStock($product, warehouse: 20, pos: 8);

        $this->postWarehouse('return', [$product->id => 3]);
        $this->assertStock($product, warehouse: 23, pos: 5);
        $this->assertDisplayedStock($product, warehouse: 23, pos: 5);
    }

    public function test_inventory_movements_persist_balances_and_history_across_warehouse_and_pos(): void
    {
        $admin = User::query()->create([
            'name' => 'Test Admin',
            'username' => 'inventory-admin',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);
        $this->actingAs($admin)->withSession(['login_completed' => true]);

        $returnedProduct = $this->product('Strike', '12691234');
        $replacementProduct = $this->product('Volt', '12691241');
        $additionalProduct = $this->product('Strike', '12691242', 'S');

        $this->postWarehouse('receive', [$returnedProduct->id => 10]);
        $this->postWarehouse('receive', [$replacementProduct->id => 5]);
        $this->assertStock($returnedProduct, warehouse: 10, pos: 0);
        $this->assertStock($replacementProduct, warehouse: 5, pos: 0);

        $this->postWarehouse('transfer', [$returnedProduct->id => 6]);
        $this->postWarehouse('transfer', [$replacementProduct->id => 3]);
        $this->assertStock($returnedProduct, warehouse: 4, pos: 6);
        $this->assertStock($replacementProduct, warehouse: 2, pos: 3);

        $this->postWarehouse('return', [$returnedProduct->id => 2]);
        $this->assertStock($returnedProduct, warehouse: 6, pos: 4);

        $this->postWarehouse('offline_sale', [$returnedProduct->id => 1], [
            'customer_name' => 'Test Customer',
            'payment_method' => 'cash',
        ]);
        $this->assertStock($returnedProduct, warehouse: 5, pos: 4);

        $this->postWarehouse('withdrawal', [$returnedProduct->id => 1], ['reason' => 'Marketing']);
        $this->assertStock($returnedProduct, warehouse: 4, pos: 4);

        $this->post(route('warehouse.store', ['operation' => 'adjustment']), [
            'submission_key' => fake()->uuid(),
            'items' => [$returnedProduct->id => 9, $additionalProduct->id => 7],
        ])->assertRedirect(route('warehouse', ['tab' => 'adjustment']))->assertSessionHasNoErrors();
        $this->assertStock($returnedProduct, warehouse: 9, pos: 4);
        $this->assertStock($additionalProduct, warehouse: 7, pos: 0);
        $adjustment = InventoryTransaction::query()->where('type', 'adjustment')->firstOrFail();
        $this->assertCount(2, $adjustment->lines);
        $this->assertSame(2, count($adjustment->metadata['adjustments']));

        $this->post(route('pos.sale'), [
            'submission_key' => fake()->uuid(),
            'items' => [$returnedProduct->id => 1],
            'payment_method' => 'cash',
        ])->assertRedirect(route('pos', ['tab' => 'sell']))->assertSessionHasNoErrors();
        $this->assertStock($returnedProduct, warehouse: 9, pos: 3);

        $this->post(route('pos.withdrawal'), [
            'submission_key' => fake()->uuid(),
            'items' => [$returnedProduct->id => 1],
            'reason' => 'Freebies',
        ])->assertRedirect(route('pos'))->assertSessionHasNoErrors();
        $this->assertStock($returnedProduct, warehouse: 9, pos: 2);

        $this->post(route('pos.exchange'), [
            'submission_key' => fake()->uuid(),
            'location' => 'pos',
            'returned_product_id' => $returnedProduct->id,
            'returned_quantity' => 1,
            'condition' => 'sellable',
            'replacement_product_id' => $replacementProduct->id,
            'replacement_quantity' => 2,
        ])->assertRedirect(route('pos', ['tab' => 'exchanges']))->assertSessionHasNoErrors();
        $this->assertStock($returnedProduct, warehouse: 9, pos: 3);
        $this->assertStock($replacementProduct, warehouse: 2, pos: 1);

        $this->assertSame(11, InventoryTransaction::query()->count());
        $this->assertSame(16, InventoryHistory::query()->count());
        $this->assertDatabaseHas('inventory_histories', [
            'movement_type' => 'Stock Received',
            'product_id' => $returnedProduct->id,
            'quantity_change' => 10,
            'location' => 'Warehouse',
        ]);
        foreach ([
            ['movement_type' => 'Warehouse → POS Transfer', 'product_id' => $returnedProduct->id, 'quantity_change' => -6, 'location' => 'Warehouse'],
            ['movement_type' => 'Warehouse → POS Transfer', 'product_id' => $returnedProduct->id, 'quantity_change' => 6, 'location' => 'POS'],
            ['movement_type' => 'POS → Warehouse Return', 'product_id' => $returnedProduct->id, 'quantity_change' => 2, 'location' => 'Warehouse'],
            ['movement_type' => 'Offline/Warehouse Sale', 'product_id' => $returnedProduct->id, 'quantity_change' => -1, 'location' => 'Warehouse'],
            ['movement_type' => 'Withdrawal', 'product_id' => $returnedProduct->id, 'quantity_change' => -1, 'location' => 'Warehouse'],
            ['movement_type' => 'Adjustment', 'product_id' => $returnedProduct->id, 'quantity_change' => 5, 'location' => 'Warehouse'],
            ['movement_type' => 'POS Sale', 'product_id' => $returnedProduct->id, 'quantity_change' => -1, 'location' => 'POS'],
            ['movement_type' => 'POS Withdrawal', 'product_id' => $returnedProduct->id, 'quantity_change' => -1, 'location' => 'POS'],
        ] as $historyEntry) {
            $this->assertDatabaseHas('inventory_histories', $historyEntry);
        }
        $this->assertDatabaseHas('inventory_histories', [
            'movement_type' => 'Exchange Return',
            'product_id' => $returnedProduct->id,
            'quantity_change' => 1,
            'location' => 'POS',
        ]);
        $this->assertDatabaseHas('inventory_histories', [
            'movement_type' => 'Exchange Replacement',
            'product_id' => $replacementProduct->id,
            'quantity_change' => -2,
            'location' => 'POS',
        ]);

        $this->get(route('warehouse'))->assertOk()->assertViewHas('products', function ($products) use ($returnedProduct): bool {
            return (int) $products->firstWhere('id', $returnedProduct->id)->balance->warehouse_quantity === 9;
        });
        $this->get(route('pos'))->assertOk()->assertViewHas('products', function ($products) use ($returnedProduct): bool {
            return (int) $products->firstWhere('id', $returnedProduct->id)->balance->pos_quantity === 3;
        });
    }

    /** @param array<int, int> $items @param array<string, mixed> $extra */
    private function postWarehouse(string $operation, array $items, array $extra = []): void
    {
        $this->post(route('warehouse.store', ['operation' => $operation]), array_merge([
            'submission_key' => fake()->uuid(),
            'items' => $items,
        ], $extra))->assertRedirect(route('warehouse', ['tab' => $operation]))->assertSessionHasNoErrors();
    }

    private function product(string $design, string $barcode, string $size = 'M'): Product
    {
        $product = Product::query()->create([
            'design_name' => $design,
            'category' => 'Court Series',
            'variant' => 'Unisex Dri-FIT Top',
            'size' => $size,
            'barcode' => $barcode,
            'standard_price' => 499,
            'active' => true,
        ]);
        InventoryBalance::query()->create(['product_id' => $product->id]);

        return $product;
    }

    private function assertStock(Product $product, int $warehouse, int $pos): void
    {
        $balance = InventoryBalance::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame($warehouse, $balance->warehouse_quantity, $product->design_name.' Warehouse stock');
        $this->assertSame($pos, $balance->pos_quantity, $product->design_name.' POS stock');
        $this->assertSame($warehouse, (int) InventoryHistory::query()->where('product_id', $product->id)->where('location', 'Warehouse')->sum('quantity_change'), $product->design_name.' Warehouse ledger');
        $this->assertSame($pos, (int) InventoryHistory::query()->where('product_id', $product->id)->where('location', 'POS')->sum('quantity_change'), $product->design_name.' POS ledger');
    }

    private function assertDisplayedStock(Product $product, int $warehouse, int $pos): void
    {
        $this->get(route('warehouse'))->assertOk()->assertViewHas('products', function ($products) use ($product, $warehouse): bool {
            return (int) $products->firstWhere('id', $product->id)->balance->warehouse_quantity === $warehouse;
        });
        $this->get(route('pos'))->assertOk()->assertViewHas('products', function ($products) use ($product, $pos): bool {
            return (int) $products->firstWhere('id', $product->id)->balance->pos_quantity === $pos;
        });
        $this->get(route('admin.index'))->assertOk()
            ->assertViewHas('warehouseTotal', $warehouse)
            ->assertViewHas('posTotal', $pos);
    }
}
