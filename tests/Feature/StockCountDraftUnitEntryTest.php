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
            ->assertSee('count-sheet-panel', false)
            ->assertSee('count-sheet-scroller', false)
            ->assertSee('count-side-panel', false)
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

    public function test_posting_cartons_and_pieces_creates_positive_base_count_in(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Positive Crisps');
        $this->postBaseStock($product, $piece, 48);

        $this->postProductLevelCount('post', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 48)->assertRedirect('/stock/counts/CNT-20260607-0001')->assertSessionHasNoErrors();

        $count = StockCount::query()->firstOrFail();
        $item = $count->items()->firstOrFail();
        $this->assertSame('48.000', $item->system_base_qty);
        $this->assertSame('54.000', $item->physical_base_qty);
        $this->assertSame('6.000', $item->variance_base_qty);
        $this->assertSame('6.000', $count->total_variance_base_qty);

        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_count',
            'reference_no' => 'CNT-20260607-0001',
            'movement_type' => 'count_in',
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'quantity_in' => '6.000',
            'base_quantity_in' => '6.000',
            'quantity_out' => '0.000',
            'base_quantity_out' => '0.000',
            'conversion_factor_snapshot' => '1.000000',
        ]);
        $this->assertSame(2, StockCountUnitEntry::query()->count());
    }

    public function test_negative_base_variance_creates_base_count_out(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Negative Crisps');
        $this->postBaseStock($product, $piece, 60);

        $this->postProductLevelCount('post', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 60)->assertRedirect('/stock/counts/CNT-20260607-0001')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_count_items', [
            'product_id' => $product->id,
            'system_base_qty' => '60.000',
            'physical_base_qty' => '54.000',
            'variance_base_qty' => '-6.000',
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_count',
            'movement_type' => 'count_out',
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'quantity_in' => '0.000',
            'base_quantity_in' => '0.000',
            'quantity_out' => '6.000',
            'base_quantity_out' => '6.000',
            'conversion_factor_snapshot' => '1.000000',
        ]);
    }

    public function test_zero_base_variance_creates_no_inventory_movement(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Matched Crisps');
        $this->postBaseStock($product, $piece, 54);

        $this->postProductLevelCount('post', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 54)->assertRedirect('/stock/counts/CNT-20260607-0001')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_count_items', [
            'product_id' => $product->id,
            'variance_base_qty' => '0.000',
        ]);
        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_type' => 'stock_count',
            'reference_no' => 'CNT-20260607-0001',
        ]);
    }

    public function test_stale_draft_recalculates_current_system_base_stock_when_posted(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Stale Crisps');
        $this->postBaseStock($product, $piece, 48);

        $this->postProductLevelCount('draft', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 48)->assertRedirect();

        $this->postBaseStock($product, $piece, 2, 'EXTRA-'.$product->id);

        $this->postProductLevelCount('post', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 48, 1)->assertRedirect('/stock/counts/CNT-20260607-0001')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_count_items', [
            'product_id' => $product->id,
            'system_base_qty' => '50.000',
            'physical_base_qty' => '54.000',
            'variance_base_qty' => '4.000',
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_count',
            'reference_no' => 'CNT-20260607-0001',
            'movement_type' => 'count_in',
            'base_quantity_in' => '4.000',
        ]);
    }

    public function test_same_product_level_stock_count_cannot_be_posted_twice(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Duplicate Crisps');
        $this->postBaseStock($product, $piece, 48);

        $this->postProductLevelCount('draft', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 48)->assertRedirect();

        $this->postProductLevelCount('post', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 48, 1)->assertRedirect();

        $this->postProductLevelCount('post', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 48, 1)->assertSessionHasErrors();

        $this->assertSame(1, InventoryTransaction::query()
            ->where('reference_type', 'stock_count')
            ->where('reference_no', 'CNT-20260607-0001')
            ->count());
    }

    public function test_legacy_single_unit_count_posting_remains_compatible(): void
    {
        $product = Product::create(['name' => 'Legacy Soap', 'is_active' => true]);
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
            'action' => 'post',
            'count_date' => '2026-06-07',
            'store_id' => $this->store->id,
            'items' => [
                ['product_unit_id' => $bar->id, 'physical_count' => 10, 'is_counted' => 1],
            ],
        ])->assertRedirect('/stock/counts/CNT-20260607-0001')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_count',
            'reference_no' => 'CNT-20260607-0001',
            'movement_type' => 'count_out',
            'product_unit_id' => $bar->id,
            'quantity_out' => '2.000',
        ]);
    }

    public function test_stock_balance_reflects_base_variance_after_posted_count(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Balance Crisps');
        $this->postBaseStock($product, $piece, 48);

        $this->postProductLevelCount('post', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 48)->assertRedirect();

        $this->get('/stock/balances?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('Balance Crisps')
            ->assertSee('Base Stock 54 pieces');
    }

    public function test_count_show_and_print_display_positive_base_count_details(): void
    {
        [$product, $piece, $carton] = $this->pieceCartonProduct('Show Positive Crisps');
        $this->postBaseStock($product, $piece, 48);

        $this->postProductLevelCount('post', $product, [
            [$carton, 2],
            [$piece, 6],
        ], 48)->assertRedirect();

        $this->get('/stock/counts/CNT-20260607-0001')
            ->assertOk()
            ->assertSee('System Base Stock')
            ->assertSee('Physical Base Count')
            ->assertSee('Show Positive Crisps')
            ->assertSee('48 pieces')
            ->assertSee('54 pieces')
            ->assertSee('2 cartons + 6 pieces')
            ->assertSee('+6 pieces')
            ->assertSee('count_in');

        $this->get('/stock/counts/CNT-20260607-0001/print')
            ->assertOk()
            ->assertSee('SYSTEM STOCK')
            ->assertSee('COUNTED')
            ->assertSee('ENTERED UNITS')
            ->assertSee('Show Positive Crisps')
            ->assertSee('2 cartons + 6 pieces')
            ->assertSee('+6 pieces')
            ->assertSee('count_in');
    }

    public function test_count_show_and_print_display_negative_and_zero_base_variances(): void
    {
        [$negativeProduct, $negativePiece, $negativeCarton] = $this->pieceCartonProduct('Show Negative Crisps');
        $this->postBaseStock($negativeProduct, $negativePiece, 60);

        $this->postProductLevelCount('post', $negativeProduct, [
            [$negativeCarton, 2],
            [$negativePiece, 6],
        ], 60)->assertRedirect();

        $this->get('/stock/counts/CNT-20260607-0001')
            ->assertOk()
            ->assertSee('Show Negative Crisps')
            ->assertSee('-6 pieces')
            ->assertSee('count_out');
        $this->get('/stock/counts/CNT-20260607-0001/print')
            ->assertOk()
            ->assertSee('-6 pieces')
            ->assertSee('count_out');

        [$zeroProduct, $zeroPiece, $zeroCarton] = $this->pieceCartonProduct('Show Zero Crisps');
        $this->postBaseStock($zeroProduct, $zeroPiece, 54);

        $this->postProductLevelCount('post', $zeroProduct, [
            [$zeroCarton, 2],
            [$zeroPiece, 6],
        ], 54)->assertRedirect();

        $this->get('/stock/counts/CNT-20260607-0002')
            ->assertOk()
            ->assertSee('Show Zero Crisps')
            ->assertSee('0 pieces')
            ->assertSee('none');
        $this->get('/stock/counts/CNT-20260607-0002/print')
            ->assertOk()
            ->assertSee('0 pieces')
            ->assertSee('none');
    }

    public function test_count_show_and_print_display_single_unit_product_cleanly(): void
    {
        $product = Product::create(['name' => 'Show Single Soap', 'is_active' => true]);
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
            'action' => 'post',
            'count_date' => '2026-06-07',
            'store_id' => $this->store->id,
            'items' => [
                ['product_unit_id' => $bar->id, 'physical_count' => 10, 'is_counted' => 1],
            ],
        ])->assertRedirect();

        $this->get('/stock/counts/CNT-20260607-0001')
            ->assertOk()
            ->assertSee('Show Single Soap')
            ->assertSee('12 bars')
            ->assertSee('10 bars')
            ->assertSee('-2 bars')
            ->assertSee('count_out');
        $this->get('/stock/counts/CNT-20260607-0001/print')
            ->assertOk()
            ->assertSee('Show Single Soap')
            ->assertSee('10 bars')
            ->assertSee('-2 bars');
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

    private function postProductLevelCount(string $action, Product $product, array $entries, float $systemBaseQty, ?int $stockCountId = null)
    {
        return $this->post('/stock/counts', [
            'action' => $action,
            'stock_count_id' => $stockCountId,
            'count_date' => '2026-06-07',
            'store_id' => $this->store->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'system_base_qty' => $systemBaseQty,
                    'is_counted' => 1,
                    'unit_entries' => collect($entries)
                        ->map(fn (array $entry) => [
                            'product_unit_id' => $entry[0]->id,
                            'entered_quantity' => $entry[1],
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }

    private function postBaseStock(Product $product, ProductUnit $unit, float $baseQuantity, ?string $referenceNo = null): void
    {
        InventoryTransaction::create([
            'transaction_date' => '2026-06-01',
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'opening',
            'reference_id' => abs(crc32(($referenceNo ?: 'OPEN-'.$product->id).'-'.$baseQuantity)),
            'reference_no' => $referenceNo ?: 'OPEN-'.$product->id,
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
