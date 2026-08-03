<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseFundingSource;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseUnitStockBehaviorTest extends TestCase
{
    use RefreshDatabase;

    private Store $storeA;
    private Store $storeB;
    private Customer $customer;
    private Supplier $supplier;
    private PaymentMode $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsRole('admin');

        $this->storeA = Store::create(['name' => 'Store A', 'is_active' => true]);
        $this->storeB = Store::create(['name' => 'Store B', 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'Base Unit Customer', 'is_active' => true]);
        $this->supplier = Supplier::create(['name' => 'Base Unit Supplier', 'is_active' => true]);
        $this->cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
    }

    public function test_purchase_carton_then_sell_piece_controls_stock_in_base_units(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct();

        $this->postPurchase($this->storeA, $carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeA, 24, '1 purchased carton should create 24 base pieces.');

        $this->postSale($this->storeA, $piece, 3, 1500)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeA, 21, 'Selling 3 pieces should leave 21 base pieces.');
    }

    public function test_purchase_carton_then_sell_half_carton_controls_stock_in_base_units(): void
    {
        [$product, , $carton, $halfCarton] = $this->pieceCartonProduct();

        $this->postPurchase($this->storeA, $carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeA, 24, '1 purchased carton should create 24 base pieces.');

        $this->postSale($this->storeA, $halfCarton, 1, 12500)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeA, 12, 'Selling 1 half carton should leave 12 base pieces.');
    }

    public function test_purchase_sack_then_sell_kg_controls_stock_in_base_units(): void
    {
        [$product, $kg, $sack] = $this->kgSackProduct();

        $this->postPurchase($this->storeA, $sack, 2, 100000)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeA, 100, '2 purchased sacks should create 100 base kg.');

        $this->postSale($this->storeA, $kg, 10, 2500)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeA, 90, 'Selling 10 kg should leave 90 base kg.');
    }

    public function test_sale_is_rejected_when_base_stock_is_insufficient(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct();

        $this->postPurchase($this->storeA, $carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeA, 24, '1 purchased carton should create 24 base pieces.');

        $this->postSale($this->storeA, $piece, 25, 1500)->assertSessionHasErrors('items');
        $this->assertBaseBalance($product, $this->storeA, 24, 'Rejected sale should not change base stock.');
    }

    public function test_sale_return_restores_base_stock(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct();

        $this->postPurchase($this->storeA, $carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->postSale($this->storeA, $piece, 5, 1500)->assertRedirect()->assertSessionHasNoErrors();

        $sale = Sale::query()->with('items')->latest('id')->firstOrFail();
        $this->post('/sales/'.$sale->id.'/returns', [
            'return_date' => '2026-06-03',
            'return_type' => 'credit_note',
            'payment_mode_id' => $this->cash->id,
            'items' => [
                ['sale_item_id' => $sale->items->first()->id, 'quantity' => 2],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertBaseBalance($product, $this->storeA, 21, 'Returning 2 of 5 sold pieces from a 24-piece carton should leave 21 pieces.');
    }

    public function test_purchase_return_can_reduce_base_stock_by_a_different_return_unit(): void
    {
        [$product, , $carton, $halfCarton] = $this->pieceCartonProduct();

        $this->postPurchase($this->storeA, $carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $purchase = Purchase::query()->with('items')->latest('id')->firstOrFail();

        $this->post('/purchases/'.$purchase->id.'/returns', [
            'return_date' => '2026-06-03',
            'return_type' => 'supplier_credit',
            'payment_mode_id' => $this->cash->id,
            'items' => [
                [
                    'purchase_item_id' => $purchase->items->first()->id,
                    'product_unit_id' => $halfCarton->id,
                    'quantity' => 1,
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertBaseBalance($product, $this->storeA, 12, 'Returning 1 half carton should reduce a 24-piece carton balance to 12 pieces.');
    }

    public function test_transfer_carton_then_sell_pieces_at_destination_controls_base_stock_per_store(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct();

        $this->postPurchase($this->storeA, $carton, 2, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeA, 48, '2 purchased cartons should create 48 base pieces at Store A.');

        $this->post('/stock/transfers', [
            'transfer_date' => '2026-06-02',
            'from_store_id' => $this->storeA->id,
            'to_store_id' => $this->storeB->id,
            'items' => [
                ['product_unit_id' => $carton->id, 'quantity' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertBaseBalance($product, $this->storeB, 24, 'Transferred carton should create 24 base pieces at Store B.');

        $this->postSale($this->storeB, $piece, 5, 1500)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertBaseBalance($product, $this->storeB, 19, 'Selling 5 pieces at Store B should leave 19 base pieces.');
    }

    public function test_fractional_quantity_rules_are_applied_before_posting(): void
    {
        [, $kg, $sack] = $this->kgSackProduct();

        $this->postPurchase($this->storeA, $sack, 1, 100000)->assertRedirect()->assertSessionHasNoErrors();

        $this->postSale($this->storeA, $kg, 1.5, 2500)->assertRedirect()->assertSessionHasNoErrors();
        $saleItem = Sale::query()->with('items')->latest('id')->firstOrFail()->items->first();
        $this->assertEquals(1.5, (float) $saleItem->base_quantity);

        $this->postSale($this->storeA, $sack, 1.5, 100000)->assertSessionHasErrors('items');
    }

    public function test_fractional_wholesale_carton_sale_uses_selected_pack_price_and_base_stock(): void
    {
        [$product, , $carton] = $this->pieceCartonProduct();
        $carton->update([
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.5,
        ]);

        $this->postPurchase($this->storeA, $carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();

        $this->postSale($this->storeA, $carton->fresh(), 0.5, 30000)->assertRedirect()->assertSessionHasNoErrors();

        $saleItem = Sale::query()->with('items')->latest('id')->firstOrFail()->items->first();
        $this->assertEquals(0.5, (float) $saleItem->quantity);
        $this->assertEquals(12.0, (float) $saleItem->base_quantity);
        $this->assertEquals(15000.0, (float) $saleItem->line_total);
        $this->assertBaseBalance($product, $this->storeA, 12, 'Half carton should deduct 12 base pieces.');
    }

    public function test_fractional_wholesale_sale_supports_three_quarter_pack_price(): void
    {
        [$product, , $carton] = $this->pieceCartonProduct();
        $carton->update([
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.5,
        ]);

        $this->postPurchase($this->storeA, $carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();

        $this->postSale($this->storeA, $carton->fresh(), 0.75, 30000)->assertRedirect()->assertSessionHasNoErrors();

        $saleItem = Sale::query()->with('items')->latest('id')->firstOrFail()->items->first();
        $this->assertEquals(0.75, (float) $saleItem->quantity);
        $this->assertEquals(18.0, (float) $saleItem->base_quantity);
        $this->assertEquals(22500.0, (float) $saleItem->line_total);
        $this->assertBaseBalance($product, $this->storeA, 6, 'Three-quarter carton should deduct 18 base pieces.');
    }

    public function test_fractional_wholesale_sale_below_minimum_is_rejected(): void
    {
        [$product, , $carton] = $this->pieceCartonProduct();
        $carton->update([
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.5,
        ]);

        $this->postPurchase($this->storeA, $carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();

        $this->from('/sales/create')
            ->post('/sales', [
                'sale_date' => '2026-06-02',
                'store_id' => $this->storeA->id,
                'sale_type' => 'cash',
                'customer_id' => $this->customer->id,
                'payment_mode_id' => $this->cash->id,
                'amount_paid' => 7500,
                'items' => [
                    ['product_unit_id' => $carton->id, 'quantity' => 0.25, 'unit_price' => 30000],
                ],
            ])
            ->assertRedirect('/sales/create')
            ->assertSessionHasErrors([
                'items.0.quantity' => 'Quantities below 0.5 Carton should be sold using Piece / retail unit.',
            ]);

        $this->assertBaseBalance($product, $this->storeA, 24, 'Rejected quarter carton sale should not change stock.');
    }

    public function test_stock_adjustment_supports_fractional_quantity_only_for_fractional_units(): void
    {
        [$product, $kg, $sack] = $this->kgSackProduct();

        $this->post('/stock/adjustments', [
            'adjustment_date' => '2026-06-02',
            'store_id' => $this->storeA->id,
            'adjustment_type' => 'increase',
            'remarks' => 'Fractional stock correction',
            'items' => [
                ['product_unit_id' => $kg->id, 'quantity' => 1.5],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertBaseBalance($product, $this->storeA, 1.5, 'Fractional kg adjustment should add 1.5 base kg.');

        $this->post('/stock/adjustments', [
            'adjustment_date' => '2026-06-02',
            'store_id' => $this->storeA->id,
            'adjustment_type' => 'increase',
            'remarks' => 'Invalid fractional sack correction',
            'items' => [
                ['product_unit_id' => $sack->id, 'quantity' => 1.5],
            ],
        ])->assertSessionHasErrors('items.0.quantity');
    }

    public function test_existing_single_unit_product_workflow_remains_unchanged(): void
    {
        $product = Product::create(['name' => 'Single Unit Product', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'conversion_factor' => 1,
            'selling_price' => 5000,
            'cost_price' => 3000,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $unit->id, 'base_unit_label' => 'Pack']);

        $this->postPurchase($this->storeA, $unit, 10, 3000)->assertRedirect()->assertSessionHasNoErrors();
        $this->postSale($this->storeA, $unit, 4, 5000)->assertRedirect()->assertSessionHasNoErrors();

        $sale = Sale::query()->with('items')->latest('id')->firstOrFail();
        $this->post('/sales/'.$sale->id.'/returns', [
            'return_date' => '2026-06-03',
            'return_type' => 'credit_note',
            'payment_mode_id' => $this->cash->id,
            'items' => [
                ['sale_item_id' => $sale->items->first()->id, 'quantity' => 2],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertUnitBalance($unit, $this->storeA, 8, 'Current single-unit quantity behavior should remain unchanged.');
    }

    /**
     * @return array{0: Product, 1: ProductUnit, 2: ProductUnit, 3: ProductUnit}
     */
    private function pieceCartonProduct(): array
    {
        $product = Product::create(['name' => 'Carton Piece Product '.uniqid(), 'is_active' => true]);
        $piece = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Piece',
            'conversion_factor' => 1,
            'selling_price' => 1500,
            'cost_price' => 1000,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $carton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Carton',
            'conversion_factor' => 24,
            'selling_price' => 30000,
            'cost_price' => 24000,
            'is_active' => true,
        ]);
        $halfCarton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Half Carton',
            'conversion_factor' => 12,
            'selling_price' => 15500,
            'cost_price' => 12000,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $piece->id, 'base_unit_label' => 'Piece']);

        return [$product, $piece, $carton, $halfCarton];
    }

    /**
     * @return array{0: Product, 1: ProductUnit, 2: ProductUnit}
     */
    private function kgSackProduct(): array
    {
        $product = Product::create(['name' => 'Kg Sack Product '.uniqid(), 'is_active' => true]);
        $kg = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Kg',
            'conversion_factor' => 1,
            'selling_price' => 2500,
            'cost_price' => 2000,
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $sack = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Sack',
            'conversion_factor' => 50,
            'selling_price' => 125000,
            'cost_price' => 100000,
            'allow_fractional_quantity' => false,
            'quantity_precision' => 0,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $kg->id, 'base_unit_label' => 'Kg']);

        return [$product, $kg, $sack];
    }

    private function postPurchase(Store $store, ProductUnit $unit, float $quantity, float $unitCost)
    {
        return $this->post('/purchases', [
            'purchase_date' => '2026-06-01',
            'store_id' => $store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $this->cash->id,
            'purchase_funding_source_id' => PurchaseFundingSource::query()->firstOrCreate([
                'name' => 'Business Cash / Shop Cash',
            ], [
                'description' => 'Cash available in the shop or business till.',
                'is_active' => true,
                'sort_order' => 10,
            ])->id,
            'amount_paid' => $quantity * $unitCost,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => $quantity, 'unit_cost' => $unitCost],
            ],
        ]);
    }

    private function postSale(Store $store, ProductUnit $unit, float $quantity, float $unitPrice)
    {
        return $this->post('/sales', [
            'sale_date' => '2026-06-02',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $this->customer->id,
            'payment_mode_id' => $this->cash->id,
            'amount_paid' => $quantity * $unitPrice,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
            ],
        ]);
    }

    private function assertBaseBalance(Product $product, Store $store, float $expected, string $message): void
    {
        $actual = InventoryTransaction::query()
            ->where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->selectRaw('COALESCE(SUM(base_quantity_in), 0) - COALESCE(SUM(base_quantity_out), 0) as balance')
            ->value('balance');

        $this->assertEquals($expected, round((float) $actual, 3), $message);
    }

    private function assertUnitBalance(ProductUnit $unit, Store $store, float $expected, string $message): void
    {
        $actual = InventoryTransaction::query()
            ->where('product_unit_id', $unit->id)
            ->where('store_id', $store->id)
            ->selectRaw('COALESCE(SUM(quantity_in), 0) - COALESCE(SUM(quantity_out), 0) as balance')
            ->value('balance');

        $this->assertEquals($expected, round((float) $actual, 3), $message);
    }
}
