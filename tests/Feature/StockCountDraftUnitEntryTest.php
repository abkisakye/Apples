<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockCount;
use App\Models\StockCountUnitEntry;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCountDraftUnitEntryTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsRole('admin');
        $this->store = Store::create(['name' => 'Main Store', 'is_active' => true]);
    }

    public function test_stock_count_create_page_shows_product_level_rows_with_unit_inputs(): void
    {
        [$product, $piece, $carton, $halfCarton] = $this->pieceCartonProduct('GONJA CRISPS');
        $this->postBaseStock($product, $piece, 48);

        $this->get('/stock/counts/create?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('GONJA CRISPS')
            ->assertSee('System 48 pieces')
            ->assertSee('Base unit: Piece')
            ->assertSee('Carton')
            ->assertSee('Half Carton')
            ->assertSee('Piece')
            ->assertSee('name="items[0][product_id]"', false)
            ->assertSee('name="items[0][unit_entries][0][entered_quantity]"', false)
            ->assertDontSee('System Count');
    }

    public function test_draft_saves_cartons_and_pieces_as_base_quantity_unit_entries(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('GONJA CRISPS');
        $this->postBaseStock($product, $piece, 48);

        $response = $this->post('/stock/counts', [
            'action' => 'draft',
            'count_date' => '2026-06-07',
            'store_id' => $this->store->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'system_base_qty' => 48,
                    'is_counted' => 1,
                    'unit_entries' => [
                        ['product_unit_id' => $carton->id, 'entered_quantity' => 2],
                        ['product_unit_id' => $piece->id, 'entered_quantity' => 6],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect('/stock/counts/create?draft_id=1&store_id='.$this->store->id.'&q=&count_focus=all&show_status=pending');
        $this->assertDatabaseHas('stock_counts', [
            'id' => 1,
            'status' => 'draft',
            'line_count' => 1,
        ]);

        $item = StockCount::query()->firstOrFail()->items()->firstOrFail();
        $this->assertSame('48.000', $item->system_base_qty);
        $this->assertSame('54.000', $item->physical_base_qty);
        $this->assertSame('6.000', $item->variance_base_qty);
        $this->assertSame('6.000', $item->stockCount->total_variance_base_qty);
        $this->assertSame(2, StockCountUnitEntry::query()->count());
        $this->assertDatabaseHas('stock_count_unit_entries', [
            'product_id' => $product->id,
            'product_unit_id' => $carton->id,
            'entered_quantity' => '2.000',
            'conversion_factor_snapshot' => '24.000000',
            'base_quantity' => '48.000',
        ]);
        $this->assertDatabaseHas('stock_count_unit_entries', [
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'entered_quantity' => '6.000',
            'conversion_factor_snapshot' => '1.000000',
            'base_quantity' => '6.000',
        ]);
        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_type' => 'stock_count',
        ]);
    }

    public function test_draft_resumes_previously_entered_unit_quantities(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Resume Crisps');
        $this->postBaseStock($product, $piece, 48);

        $this->post('/stock/counts', [
            'action' => 'draft',
            'count_date' => '2026-06-07',
            'store_id' => $this->store->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'system_base_qty' => 48,
                    'is_counted' => 1,
                    'unit_entries' => [
                        ['product_unit_id' => $carton->id, 'entered_quantity' => 2],
                        ['product_unit_id' => $piece->id, 'entered_quantity' => 6],
                    ],
                ],
            ],
        ])->assertRedirect();

        $this->get('/stock/counts/create?draft_id=1&show_status=counted')
            ->assertOk()
            ->assertSee('Resume Crisps')
            ->assertSee('value="2.000"', false)
            ->assertSee('value="6.000"', false)
            ->assertSee('54', false);
    }

    public function test_kg_fractional_count_works_when_allowed(): void
    {
        [$product, $kg, $sack] = $this->kgSackProduct('RICE');
        $this->postBaseStock($product, $kg, 50);

        $this->post('/stock/counts', [
            'action' => 'draft',
            'count_date' => '2026-06-07',
            'store_id' => $this->store->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'system_base_qty' => 50,
                    'is_counted' => 1,
                    'unit_entries' => [
                        ['product_unit_id' => $sack->id, 'entered_quantity' => 1],
                        ['product_unit_id' => $kg->id, 'entered_quantity' => 12.5],
                    ],
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = StockCount::query()->firstOrFail()->items()->firstOrFail();
        $this->assertSame('62.500', $item->physical_base_qty);
        $this->assertDatabaseHas('stock_count_unit_entries', [
            'product_unit_id' => $kg->id,
            'entered_quantity' => '12.500',
            'base_quantity' => '12.500',
        ]);
    }

    public function test_decimal_count_is_rejected_for_non_fractional_units(): void
    {
        [$product, $piece] = $this->pieceCartonProduct('Decimal Crisps');
        $this->postBaseStock($product, $piece, 10);

        $this->post('/stock/counts', [
            'action' => 'draft',
            'count_date' => '2026-06-07',
            'store_id' => $this->store->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'system_base_qty' => 10,
                    'is_counted' => 1,
                    'unit_entries' => [
                        ['product_unit_id' => $piece->id, 'entered_quantity' => 1.5],
                    ],
                ],
            ],
        ])->assertSessionHasErrors();
    }

    public function test_old_single_unit_count_workflow_still_saves_legacy_draft(): void
    {
        $product = Product::create(['name' => 'Single Soap', 'is_active' => true]);
        $bar = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bar',
            'conversion_factor' => 1,
            'cost_price' => 1000,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $bar->id, 'base_unit_label' => 'Bar']);
        $this->postBaseStock($product, $bar, 12);

        $this->post('/stock/counts', [
            'action' => 'draft',
            'count_date' => '2026-06-07',
            'store_id' => $this->store->id,
            'items' => [
                ['product_unit_id' => $bar->id, 'physical_count' => 12, 'is_counted' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_counts', [
            'status' => 'draft',
            'line_count' => 1,
        ]);
        $this->assertDatabaseHas('stock_count_items', [
            'product_id' => $product->id,
            'product_unit_id' => $bar->id,
            'physical_qty' => 12,
            'variance_qty' => 0,
        ]);
    }

    /**
     * @return array{0: Product, 1: ProductUnit, 2: ProductUnit, 3: ProductUnit}
     */
    private function pieceCartonProduct(string $name): array
    {
        $product = Product::create(['name' => $name, 'base_unit_label' => 'Piece', 'is_active' => true]);
        $piece = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Piece',
            'conversion_factor' => 1,
            'cost_price' => 1000,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $carton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Carton',
            'conversion_factor' => 24,
            'cost_price' => 24000,
            'is_active' => true,
        ]);
        $halfCarton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Half Carton',
            'conversion_factor' => 12,
            'cost_price' => 12000,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $piece->id]);

        return [$product, $piece, $carton, $halfCarton];
    }

    /**
     * @return array{0: Product, 1: ProductUnit, 2: ProductUnit}
     */
    private function kgSackProduct(string $name): array
    {
        $product = Product::create(['name' => $name, 'base_unit_label' => 'Kg', 'is_active' => true]);
        $kg = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Kg',
            'conversion_factor' => 1,
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
            'cost_price' => 100000,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $kg->id]);

        return [$product, $kg, $sack];
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
