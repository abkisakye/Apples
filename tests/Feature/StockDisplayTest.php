<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseFundingSource;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private Customer $customer;
    private Supplier $supplier;
    private PaymentMode $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsRole('admin');

        $this->store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'Display Customer', 'is_active' => true]);
        $this->supplier = Supplier::create(['name' => 'Display Supplier', 'is_active' => true]);
        $this->cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
    }

    public function test_stock_balance_groups_by_product_and_shows_base_stock_after_carton_purchase_and_piece_sale(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Display Crisps');

        $this->postPurchase($carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->postSale($piece, 3, 1500)->assertRedirect()->assertSessionHasNoErrors();

        $response = $this->get('/stock/balances?store_id='.$this->store->id);

        $response->assertOk()
            ->assertSee('Display Crisps')
            ->assertSee('Base Stock 21 pieces')
            ->assertSee('Base unit: Piece')
            ->assertSee('Units: Carton 24, Half Carton 12, Piece 1')
            ->assertDontSee('System Count 1')
            ->assertDontSee('System Count -3');

        $this->assertSame(1, substr_count($response->getContent(), 'Display Crisps'));
    }

    public function test_stock_balance_friendly_breakdown_uses_largest_pack_and_base_remainder(): void
    {
        [$product, $piece] = $this->pieceCartonProduct('Breakdown Crisps');

        $this->postBaseStock($product, $piece, 57);

        $this->get('/stock/balances?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('Base Stock 57 pieces')
            ->assertSee('Breakdown: 2 cartons + 9 pieces')
            ->assertDontSee('2 cartons + 1 half carton + 9 pieces');
    }

    public function test_stock_balance_displays_sack_and_kg_breakdown(): void
    {
        [$product, $kg] = $this->kgSackProduct('Display Rice');

        $this->postBaseStock($product, $kg, 90);

        $this->get('/stock/balances?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('Display Rice')
            ->assertSee('Base Stock 90 kg')
            ->assertSee('Breakdown: 1 sack + 40 kg');
    }

    public function test_reorder_uses_base_stock_and_base_reorder_level(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Low Crisps', 25);

        $this->postPurchase($carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->postSale($piece, 3, 1500)->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/stock/reorder?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('Low Crisps')
            ->assertSee('Base Stock 21 pieces')
            ->assertSee('Reorder Level: 25 pieces')
            ->assertSee('Shortage: 4 pieces');
    }

    public function test_single_unit_product_displays_normally(): void
    {
        $product = Product::create(['name' => 'Single Display Product', 'reorder_level' => 3, 'is_active' => true]);
        $pack = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'conversion_factor' => 1,
            'cost_price' => 3000,
            'selling_price' => 5000,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $pack->id, 'base_unit_label' => 'Pack']);

        $this->postBaseStock($product, $pack, 15);

        $this->get('/stock/balances?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('Single Display Product')
            ->assertSee('Base Stock 15 packs')
            ->assertSee('Breakdown: 15 packs');
    }

    public function test_product_history_shows_carton_purchase_and_piece_sale_together_with_base_impacts(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('History Crisps');

        $this->postPurchase($carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->postSale($piece, 3, 1500)->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/stock/products/'.$product->id.'/history?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('History Crisps')
            ->assertSee('Product Stock History')
            ->assertSee('Current Base Stock 21 pieces')
            ->assertSee('Breakdown: 21 pieces')
            ->assertSee('Purchase')
            ->assertSee('1 carton')
            ->assertSee('Base +24 pieces')
            ->assertSee('Balance 24 pieces')
            ->assertSee('Sale')
            ->assertSee('3 pieces')
            ->assertSee('Base -3 pieces')
            ->assertSee('Balance 21 pieces');
    }

    public function test_stock_balances_page_links_to_product_history(): void
    {
        [$product, $piece] = $this->pieceCartonProduct('Linked History Crisps');
        $this->postBaseStock($product, $piece, 12);

        $this->get('/stock/balances?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee(route('stock.product-history', $product, false), false)
            ->assertSee('View History');
    }

    public function test_stock_balances_are_paginated_but_search_still_checks_all_products(): void
    {
        foreach (range(1, 55) as $number) {
            $product = Product::create([
                'name' => sprintf('Paged Stock Product %03d', $number),
                'is_active' => true,
            ]);

            ProductUnit::create([
                'product_id' => $product->id,
                'unit_name' => 'Piece',
                'conversion_factor' => 1,
                'selling_price' => 1000,
                'cost_price' => 600,
                'is_base_unit' => true,
                'is_active' => true,
            ]);
        }

        $this->get('/stock/balances?store_id='.$this->store->id.'&per_page=20')
            ->assertOk()
            ->assertSee('Paged Stock Product 001')
            ->assertDontSee('Paged Stock Product 055')
            ->assertSee('page=2', false);

        $this->get('/stock/balances?store_id='.$this->store->id.'&per_page=20&q=Paged Stock Product 055')
            ->assertOk()
            ->assertSee('Paged Stock Product 055')
            ->assertDontSee('Paged Stock Product 001');
    }

    public function test_old_unit_level_history_route_still_works_and_links_to_product_history(): void
    {
        [$product, $piece] = $this->pieceCartonProduct('Unit History Crisps');
        $this->postBaseStock($product, $piece, 12);

        $this->get('/stock/items/'.$piece->id.'/history?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('This is unit-specific history.')
            ->assertSee(route('stock.product-history', $product, false), false);
    }

    public function test_single_unit_product_history_displays_normally(): void
    {
        $product = Product::create(['name' => 'Single History Product', 'is_active' => true]);
        $pack = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'conversion_factor' => 1,
            'cost_price' => 3000,
            'selling_price' => 5000,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $pack->id, 'base_unit_label' => 'Pack']);

        $this->postBaseStock($product, $pack, 15);

        $this->get('/stock/products/'.$product->id.'/history?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('Single History Product')
            ->assertSee('Current Base Stock 15 packs')
            ->assertSee('15 packs')
            ->assertSee('Base +15 packs')
            ->assertSee('Balance 15 packs');
    }

    public function test_product_profile_shows_base_stock_after_carton_purchase_and_piece_sale(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Profile Crisps');

        $this->postPurchase($carton, 1, 24000)->assertRedirect()->assertSessionHasNoErrors();
        $this->postSale($piece, 3, 1500)->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/products/'.$product->id)
            ->assertOk()
            ->assertSee('Profile Crisps')
            ->assertSee('Current Base Stock')
            ->assertSee('21 pieces')
            ->assertSee('Friendly Breakdown')
            ->assertSee('Breakdown: 21 pieces')
            ->assertSee('Base Unit')
            ->assertSee('Piece')
            ->assertSee('Configured Units')
            ->assertSee('Carton 24, Half Carton 12, Piece 1')
            ->assertSee(route('stock.product-history', $product, false), false)
            ->assertDontSee('System Count')
            ->assertDontSee('Units In')
            ->assertDontSee('Units Out');
    }

    /**
     * @return array{0: Product, 1: ProductUnit, 2: ProductUnit, 3: ProductUnit}
     */
    private function pieceCartonProduct(string $name, int $reorderLevel = 0): array
    {
        $product = Product::create(['name' => $name, 'reorder_level' => $reorderLevel, 'is_active' => true]);
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
    private function kgSackProduct(string $name): array
    {
        $product = Product::create(['name' => $name, 'is_active' => true]);
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
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $kg->id, 'base_unit_label' => 'Kg']);

        return [$product, $kg, $sack];
    }

    private function postPurchase(ProductUnit $unit, float $quantity, float $unitCost)
    {
        return $this->post('/purchases', [
            'purchase_date' => '2026-06-01',
            'store_id' => $this->store->id,
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

    private function postSale(ProductUnit $unit, float $quantity, float $unitPrice)
    {
        return $this->post('/sales', [
            'sale_date' => '2026-06-02',
            'store_id' => $this->store->id,
            'sale_type' => 'cash',
            'customer_id' => $this->customer->id,
            'payment_mode_id' => $this->cash->id,
            'amount_paid' => $quantity * $unitPrice,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
            ],
        ]);
    }

    private function postBaseStock(Product $product, ProductUnit $unit, float $baseQuantity): void
    {
        InventoryTransaction::create([
            'transaction_date' => '2026-06-01',
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'opening',
            'reference_id' => $product->id,
            'reference_no' => 'OPEN-'.$product->id,
            'movement_type' => 'opening_stock',
            'quantity_in' => $baseQuantity,
            'quantity_out' => 0,
            'base_quantity_in' => $baseQuantity,
            'base_quantity_out' => 0,
            'conversion_factor_snapshot' => 1,
            'unit_cost' => $unit->cost_price,
        ]);
    }
}
