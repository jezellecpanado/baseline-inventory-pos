<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_two_promo_applies_across_all_eligible_products_and_quantities(): void
    {
        $cashier = User::query()->create([
            'name' => 'Promo Cashier',
            'username' => 'promo-cashier',
            'password' => 'password',
            'role' => User::ROLE_POS,
            'active' => true,
        ]);
        $this->actingAs($cashier)->withSession(['login_completed' => true]);

        // The product checkbox is the eligibility source. Legacy promotion metadata
        // must not silently make a checked product stop qualifying in the POS.
        $inactivePromo = Promotion::query()->create([
            'name' => 'Legacy Buy Two',
            'required_quantity' => 2,
            'bundle_price' => 899,
            'active' => false,
        ]);
        $otherPromo = Promotion::query()->create([
            'name' => 'Legacy Offer',
            'required_quantity' => 3,
            'bundle_price' => 1200,
            'active' => true,
        ]);

        $eligibleA = $this->product('Eligible A', '87650001', 499, $inactivePromo->id);
        $eligibleB = $this->product('Eligible B', '87650002', 499, $otherPromo->id);
        $regular = $this->product('Regular Item', '87650003', 499, null);
        $toteA = $this->product('Tote A', '87650004', 449, $inactivePromo->id);
        $toteB = $this->product('Tote B', '87650005', 449, $otherPromo->id);

        foreach ([$eligibleA, $eligibleB, $regular, $toteA, $toteB] as $product) {
            InventoryBalance::query()->where('product_id', $product->id)->update(['pos_quantity' => 100]);
        }

        $this->get(route('pos'))->assertOk()->assertSee('promo_eligible');

        $this->assertSale($eligibleA, $eligibleB, 1, 1, subtotal: 998, discount: 99, total: 899, key: 'promo-pair');
        $this->assertSale($eligibleA, $eligibleB, 1, 2, subtotal: 1497, discount: 99, total: 1398, key: 'promo-three');
        $this->assertSale($eligibleA, $eligibleB, 2, 2, subtotal: 1996, discount: 198, total: 1798, key: 'promo-four');
        $this->assertSale($eligibleA, $eligibleB, 2, 3, subtotal: 2495, discount: 198, total: 2297, key: 'promo-five');

        $this->postSale([$eligibleA->id => 1, $eligibleB->id => 1, $regular->id => 1], 'promo-mixed-with-regular');
        $mixed = InventoryTransaction::query()->where('submission_key', 'promo-mixed-with-regular')->firstOrFail();
        $this->assertSame(1497.0, (float) $mixed->subtotal);
        $this->assertSame(99.0, (float) $mixed->discount);
        $this->assertSame(1398.0, (float) $mixed->total);

        $this->postSale([$eligibleA->id => 1, $regular->id => 1], 'promo-single-eligible');
        $single = InventoryTransaction::query()->where('submission_key', 'promo-single-eligible')->firstOrFail();
        $this->assertSame(998.0, (float) $single->subtotal);
        $this->assertSame(0.0, (float) $single->discount);
        $this->assertSame(998.0, (float) $single->total);

        // Two ₱449 items already cost less than the ₱899 bundle price, so the
        // fixed offer must not increase the customer's total.
        $this->postSale([$toteA->id => 1, $toteB->id => 1], 'promo-low-price-pair');
        $lowPricePair = InventoryTransaction::query()->where('submission_key', 'promo-low-price-pair')->firstOrFail();
        $this->assertSame(898.0, (float) $lowPricePair->subtotal);
        $this->assertSame(0.0, (float) $lowPricePair->discount);
        $this->assertSame(898.0, (float) $lowPricePair->total);
    }

    private function assertSale(Product $first, Product $second, int $firstQuantity, int $secondQuantity, float $subtotal, float $discount, float $total, string $key): void
    {
        $this->postSale([$first->id => $firstQuantity, $second->id => $secondQuantity], $key);
        $transaction = InventoryTransaction::query()->where('submission_key', $key)->firstOrFail();

        $this->assertSame($subtotal, (float) $transaction->subtotal);
        $this->assertSame($discount, (float) $transaction->discount);
        $this->assertSame($total, (float) $transaction->total);
        $this->assertNotNull($transaction->promotion_id);
    }

    /** @param array<int, int> $items */
    private function postSale(array $items, string $key): void
    {
        $this->post(route('pos.sale'), [
            'submission_key' => $key,
            'items' => $items,
            'payment_method' => 'cash',
            'qr_confirmed' => '',
        ])->assertRedirect(route('pos', ['tab' => 'sell']))
            ->assertSessionHasNoErrors();
    }

    private function product(string $name, string $barcode, float $price, ?int $promotionId): Product
    {
        $product = Product::query()->create([
            'design_name' => $name,
            'category' => 'Court Series',
            'variant' => 'Unisex Dri-FIT Top',
            'size' => 'M',
            'barcode' => $barcode,
            'standard_price' => $price,
            'active' => true,
            'promotion_id' => $promotionId,
        ]);
        InventoryBalance::query()->create(['product_id' => $product->id, 'pos_quantity' => 100]);

        return $product;
    }
}
