<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleBrowserUxTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private Customer $customer;
    private PaymentMode $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsRole('admin');

        $this->store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $this->cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
    }

    public function test_purchase_create_can_quick_add_supplier(): void
    {
        $response = $this
            ->postJson('/suppliers/quick-store', [
                'name' => 'New Counter Supplier',
                'phone' => '0777000000',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('supplier.name', 'New Counter Supplier')
            ->assertJsonPath('supplier.phone', '0777000000');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'New Counter Supplier',
            'phone' => '0777000000',
            'is_active' => true,
        ]);
    }

    public function test_purchase_create_prefills_selected_product_but_keeps_other_products_available(): void
    {
        [, $teaBox] = $this->teaBagsProduct();
        [, $crispsPiece] = $this->baseProductWithUnit('GONJA CRISPS', 'Piece');
        Supplier::create(['name' => 'Stock Supplier', 'is_active' => true]);

        $response = $this->get('/purchases/create?product_id='.$teaBox->product_id);

        $response
            ->assertOk()
            ->assertSee('Find Items')
            ->assertSee('Supplier & Invoice', false)
            ->assertSee('Quick Add Supplier')
            ->assertSee('Incoming Items')
            ->assertSee('Purchase Summary')
            ->assertSee('Receive')
            ->assertSee('Product / Pack')
            ->assertSee('Qty Received')
            ->assertSee('Buying Cost')
            ->assertSee('Line Total')
            ->assertSee('Remove')
            ->assertSee('<input type="hidden" name="store_id"', false)
            ->assertDontSee('<span>Store</span>', false)
            ->assertSee('class="result-list"', false)
            ->assertSee('grid-template-rows:auto auto auto minmax(0,1fr)', false)
            ->assertSee('height:100%', false)
            ->assertSeeInOrder([
                'Find Items',
                'Search product, barcode, code, or part number',
                'id="purchase-search"',
                'id="purchase-search-results"',
            ], false)
            ->assertSee('data-add-unit', false)
            ->assertSee('data-minus', false)
            ->assertSee('data-qty', false)
            ->assertSee('data-plus', false)
            ->assertSee('data-price', false)
            ->assertSee('data-remove', false)
            ->assertSee('cart.unshift', false)
            ->assertSee('cart.splice(existingIndex, 1)', false)
            ->assertSee('Tea Bags - Tea Bag Box 6 Packets')
            ->assertSee('GONJA CRISPS - Piece')
            ->assertSee('"id":'.$teaBox->id, false)
            ->assertSee('"id":'.$crispsPiece->id, false);
    }

    public function test_stock_balance_row_actions_target_the_exact_product(): void
    {
        [$tea, $teaBox] = $this->teaBagsProduct();
        [$crisps, $crispsPiece] = $this->baseProductWithUnit('GONJA CRISPS', 'Piece');
        $this->seedBaseStock($tea, $teaBox, 1, 150);
        $this->seedBaseStock($crisps, $crispsPiece, 10, 10);

        $response = $this->get('/stock/balances?store_id='.$this->store->id);

        $response
            ->assertOk()
            ->assertSee('/stock/products/'.$tea->id.'/history', false)
            ->assertSee('/stock/products/'.$crisps->id.'/history', false)
            ->assertSee('/purchases/create?product_id='.$tea->id, false)
            ->assertSee('/stock/transfers/create?product_id='.$tea->id, false)
            ->assertSee('/stock/counts/create?store_id='.$this->store->id.'&amp;product_id='.$tea->id, false);
    }

    public function test_stock_transfer_create_can_prefill_from_product_action_link(): void
    {
        [$tea, $teaBox] = $this->teaBagsProduct();

        $response = $this->get('/stock/transfers/create?product_id='.$tea->id);

        $response
            ->assertOk()
            ->assertSee('Tea Bags - Tea Bag Box 6 Packets')
            ->assertSee('"id":'.$teaBox->id, false)
            ->assertSee('"quantity":1', false);
    }

    public function test_sale_receipts_show_product_with_compact_pack_name(): void
    {
        [, $box, $bundle] = $this->teaBagsProduct();
        $this->seedBaseStock($box->product, $box, 8, 1200);

        $sale = $this->postTeaBagSale([
            ['product_unit_id' => $box->id, 'quantity' => 1, 'unit_price' => 35000],
            ['product_unit_id' => $bundle->id, 'quantity' => 1, 'unit_price' => 58000],
        ]);

        $this->get('/sales/'.$sale->id.'/print')
            ->assertOk()
            ->assertSee('Tea Bags - Box 6 Packets')
            ->assertSee('Tea Bags - Bundle 10 Packets')
            ->assertDontSee('Tea Bags - Tea Bag Box 6 Packets');

        $this->get('/sales/'.$sale->id.'/print?theme=full')
            ->assertOk()
            ->assertSee('Tea Bags - Box 6 Packets')
            ->assertSee('Tea Bags - Bundle 10 Packets');
    }

    public function test_selling_two_tea_bag_boxes_writes_expected_base_stock_out(): void
    {
        [$tea, $box] = $this->teaBagsProduct();
        $this->seedBaseStock($tea, $box, 8, 1200);

        $sale = $this->postTeaBagSale([
            ['product_unit_id' => $box->id, 'quantity' => 2, 'unit_price' => 35000],
        ]);

        $transaction = InventoryTransaction::query()
            ->where('reference_type', 'sale')
            ->where('reference_no', $sale->sale_no)
            ->firstOrFail();

        $this->assertSame($tea->id, (int) $transaction->product_id);
        $this->assertSame($box->id, (int) $transaction->product_unit_id);
        $this->assertEquals(2.0, (float) $transaction->quantity_out);
        $this->assertEquals(300.0, (float) $transaction->base_quantity_out);
        $this->assertEquals(150.0, (float) $transaction->conversion_factor_snapshot);

        $this->get('/stock/balances?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('900 tea bag pieces');

        $this->get('/stock/products/'.$tea->id.'/history?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('Base -300 tea bag pieces');

        $this->get('/sales/'.$sale->id)
            ->assertOk()
            ->assertSee('Base stock out: 300 tea bag pieces');
    }

    private function teaBagsProduct(): array
    {
        $product = Product::create(['name' => 'Tea Bags', 'is_active' => true, 'base_unit_label' => 'Tea Bag Piece']);
        $piece = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Tea Bag Piece',
            'conversion_factor' => 1,
            'selling_price' => 250,
            'cost_price' => 160,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $box = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Tea Bag Box 6 Packets',
            'conversion_factor' => 150,
            'selling_price' => 35000,
            'cost_price' => 24000,
            'is_active' => true,
        ]);
        $bundle = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Tea Bag Bundle 10 Packets',
            'conversion_factor' => 250,
            'selling_price' => 58000,
            'cost_price' => 40000,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $piece->id]);

        return [$product, $box, $bundle, $piece];
    }

    private function baseProductWithUnit(string $productName, string $unitName): array
    {
        $product = Product::create(['name' => $productName, 'is_active' => true, 'base_unit_label' => $unitName]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => $unitName,
            'conversion_factor' => 1,
            'selling_price' => 1000,
            'cost_price' => 700,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $unit->id]);

        return [$product, $unit];
    }

    private function seedBaseStock(Product $product, ProductUnit $unit, float $enteredQuantity, float $baseQuantity): void
    {
        InventoryTransaction::create([
            'transaction_date' => '2026-06-01',
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'test_seed',
            'reference_id' => abs(crc32($product->id.'-'.$unit->id)),
            'reference_no' => 'SEED-'.$product->id,
            'movement_type' => 'purchase',
            'quantity_in' => $enteredQuantity,
            'quantity_out' => 0,
            'base_quantity_in' => $baseQuantity,
            'base_quantity_out' => 0,
            'conversion_factor_snapshot' => $unit->conversion_factor,
            'unit_cost' => $unit->cost_price,
            'unit_price' => $unit->selling_price,
        ]);
    }

    private function postTeaBagSale(array $items): Sale
    {
        $response = $this->post('/sales', [
            'sale_date' => '2026-06-02',
            'store_id' => $this->store->id,
            'sale_type' => 'cash',
            'customer_id' => $this->customer->id,
            'payment_mode_id' => $this->cash->id,
            'items' => $items,
        ]);

        $sale = Sale::query()->latest('id')->firstOrFail();
        $response->assertRedirect('/sales/'.$sale->id)->assertSessionHasNoErrors();

        return $sale;
    }
}
