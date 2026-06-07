<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockCountUnitEntry;
use App\Models\Store;
use App\Support\ProductUnitConversionService;
use App\Support\StockCountCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockCountCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockCountCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StockCountCalculationService(new ProductUnitConversionService());
    }

    public function test_it_calculates_cartons_and_pieces_as_base_pieces(): void
    {
        $product = Product::create(['name' => 'GONJA CRISPS', 'base_unit_label' => 'piece', 'is_active' => true]);
        $piece = $this->unit($product, 'piece', 1, false, 0, true);
        $carton = $this->unit($product, 'carton', 24);
        $product->update(['base_product_unit_id' => $piece->id]);

        $total = $this->service->physicalBaseQuantity([
            ['quantity' => 2, 'unit' => $carton],
            ['quantity' => 6, 'unit' => $piece],
        ]);

        $this->assertSame(54.0, $total);
    }

    public function test_it_calculates_sacks_and_fractional_kg_as_base_kg(): void
    {
        $product = Product::create(['name' => 'RICE', 'base_unit_label' => 'kg', 'is_active' => true]);
        $kg = $this->unit($product, 'kg', 1, true, 2, true);
        $sack = $this->unit($product, 'sack', 50);
        $product->update(['base_product_unit_id' => $kg->id]);

        $total = $this->service->physicalBaseQuantity([
            ['quantity' => 1, 'unit' => $sack],
            ['quantity' => 12.5, 'unit' => $kg],
        ]);

        $this->assertSame(62.5, $total);
    }

    public function test_it_validates_fractional_quantity_rules_and_precision(): void
    {
        $product = Product::create(['name' => 'Rice', 'is_active' => true]);
        $piece = $this->unit($product, 'piece', 1, false, 0);
        $kg = $this->unit($product, 'kg', 1, true, 2);

        $this->service->validateQuantity(1.5, $kg);
        $this->assertTrue(true);

        $this->expectException(ValidationException::class);
        $this->service->validateQuantity(1.5, $piece);
    }

    public function test_it_rejects_quantities_beyond_the_unit_precision(): void
    {
        $product = Product::create(['name' => 'Rice', 'is_active' => true]);
        $kg = $this->unit($product, 'kg', 1, true, 2);

        $this->expectException(ValidationException::class);
        $this->service->validateQuantity(12.555, $kg);
    }

    public function test_single_unit_product_count_calculation_stays_simple(): void
    {
        $product = Product::create(['name' => 'Soap', 'base_unit_label' => 'bar', 'is_active' => true]);
        $bar = $this->unit($product, 'bar', 1, false, 0, true);
        $product->update(['base_product_unit_id' => $bar->id]);

        $totals = $this->service->countTotals([
            ['quantity' => 15, 'unit' => $bar],
        ], 12);

        $this->assertSame(15.0, $totals['physical_base_qty']);
        $this->assertSame(12.0, $totals['system_base_qty']);
        $this->assertSame(3.0, $totals['variance_base_qty']);
    }

    public function test_unit_entry_rows_can_be_created_and_related_to_a_count_item(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $product = Product::create(['name' => 'GONJA CRISPS', 'is_active' => true]);
        $piece = $this->unit($product, 'piece', 1, false, 0, true);
        $carton = $this->unit($product, 'carton', 24);
        $product->update(['base_product_unit_id' => $piece->id, 'base_unit_label' => 'piece']);

        $count = StockCount::create([
            'count_no' => 'CNT-20260607-0001',
            'count_date' => '2026-06-07',
            'store_id' => $store->id,
            'line_count' => 1,
            'total_variance_qty' => 6,
            'total_variance_base_qty' => 6,
            'status' => 'draft',
        ]);
        $item = StockCountItem::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'system_qty' => 48,
            'physical_qty' => 54,
            'variance_qty' => 6,
            'quantity_adjusted' => 6,
            'system_base_qty' => 48,
            'physical_base_qty' => 54,
            'variance_base_qty' => 6,
        ]);

        $attributes = $this->service->unitEntryAttributes(2, $carton);
        StockCountUnitEntry::create($attributes + [
            'stock_count_id' => $count->id,
            'stock_count_item_id' => $item->id,
        ]);
        StockCountUnitEntry::create($this->service->unitEntryAttributes(6, $piece) + [
            'stock_count_id' => $count->id,
            'stock_count_item_id' => $item->id,
        ]);

        $this->assertCount(2, $count->fresh()->unitEntries);
        $this->assertCount(2, $item->fresh()->unitEntries);
        $this->assertSame(54.0, (float) $item->fresh()->unitEntries()->sum('base_quantity'));
        $this->assertSame('6.000', $count->fresh()->total_variance_base_qty);
    }

    public function test_total_base_variance_is_calculated_at_service_level(): void
    {
        $product = Product::create(['name' => 'GONJA CRISPS', 'is_active' => true]);
        $piece = $this->unit($product, 'piece', 1, false, 0, true);
        $carton = $this->unit($product, 'carton', 24);

        $totals = $this->service->countTotals([
            ['quantity' => 2, 'unit' => $carton],
            ['quantity' => 6, 'unit' => $piece],
        ], 50);

        $this->assertSame(54.0, $totals['physical_base_qty']);
        $this->assertSame(50.0, $totals['system_base_qty']);
        $this->assertSame(4.0, $totals['variance_base_qty']);
    }

    private function unit(
        Product $product,
        string $name,
        float $factor,
        bool $allowFractional = false,
        int $precision = 0,
        bool $isBaseUnit = false
    ): ProductUnit {
        return ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => $name,
            'conversion_factor' => $factor,
            'allow_fractional_quantity' => $allowFractional,
            'quantity_precision' => $precision,
            'is_base_unit' => $isBaseUnit,
            'is_active' => true,
        ]);
    }
}
