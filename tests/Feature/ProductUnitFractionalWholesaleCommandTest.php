<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUnitFractionalWholesaleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_update_product_units(): void
    {
        $box = $this->productUnit('DRY RUN SOAP', 'Boxes', 24, 90000, 114000);

        $this->artisan('product-units:enable-fractional-wholesale', ['--dry-run' => true])
            ->expectsOutput('Dry-run only. No product units were changed.')
            ->assertSuccessful();

        $box->refresh();
        $this->assertFalse((bool) $box->allow_fractional_quantity);
        $this->assertSame(0, (int) $box->quantity_precision);
        $this->assertNull($box->minimum_wholesale_quantity);
    }

    public function test_commit_updates_boxes_cartons_and_dozens_only_by_default_without_touching_prices_conversion_or_stock(): void
    {
        $store = Store::create(['name' => 'Main Shop', 'is_active' => true]);
        $box = $this->productUnit('BOX SOAP', 'Box', 24, 90000, 114000);
        $longBox = $this->productUnit('SOFT CARE WIPES', 'Soft Care wipes Box', 12, 8000, 12000);
        $carton = $this->productUnit('CARTON SOAP', 'Cartons', 24, 240000, 298000);
        $dozen = $this->productUnit('DOZEN SOAP', 'Dozens', 12, 30000, 40000);
        $bag = $this->productUnit('BAG FLOUR', 'Bags', 1, 50000, 64000);
        $packet = $this->productUnit('PACKET WIPES', 'Packets', 1, 2000, 3500);
        $tin = $this->productUnit('TIN BISCUITS', 'Tins', 1, 18000, 25000);
        $jerrican = $this->productUnit('JERRICAN OIL', 'Jerricans', 1, 80000, 120000);
        $sack = $this->productUnit('SACK RICE', 'Sacks', 50, 80000, 110000);
        $piece = $this->productUnit('RETAIL SOAP', 'Pieces', 1, 2500, 3500);
        $pc = $this->productUnit('RETAIL BATTERY', 'Pcs', 1, 1000, 1500);
        $customMinimum = $this->productUnit('CUSTOM MIN CARTON', 'Carton', 24, 100000, 130000, [
            'allow_fractional_quantity' => true,
            'quantity_precision' => 1,
            'minimum_wholesale_quantity' => 0.75,
        ]);
        $manualSack = $this->productUnit('MANUAL SACK', 'Sacks', 50, 80000, 110000, [
            'allow_fractional_quantity' => true,
            'quantity_precision' => 1,
            'minimum_wholesale_quantity' => 0.1,
        ]);

        $inventory = InventoryTransaction::create([
            'transaction_date' => '2026-08-04',
            'store_id' => $store->id,
            'product_id' => $carton->product_id,
            'product_unit_id' => $carton->id,
            'reference_type' => 'test',
            'reference_id' => 1,
            'reference_no' => 'TEST-001',
            'movement_type' => 'opening_stock',
            'quantity_in' => 2,
            'quantity_out' => 0,
            'base_quantity_in' => 48,
            'base_quantity_out' => 0,
            'conversion_factor_snapshot' => 24,
            'unit_cost' => 240000,
        ]);

        $before = ProductUnit::query()
            ->whereKey([
                $box->id,
                $longBox->id,
                $carton->id,
                $dozen->id,
                $bag->id,
                $packet->id,
                $tin->id,
                $jerrican->id,
                $sack->id,
                $piece->id,
                $pc->id,
                $customMinimum->id,
                $manualSack->id,
            ])
            ->get()
            ->keyBy('id');
        $inventoryBefore = $inventory->fresh()->getAttributes();

        $this->artisan('product-units:enable-fractional-wholesale', ['--commit' => true])
            ->expectsOutput('Fractional wholesale settings committed.')
            ->assertSuccessful();

        foreach ([$box, $longBox, $carton, $dozen] as $unit) {
            $unit->refresh();
            $original = $before->get($unit->id);

            $this->assertTrue((bool) $unit->allow_fractional_quantity, $unit->unit_name.' should allow fractions.');
            $this->assertSame(2, (int) $unit->quantity_precision);
            $this->assertEquals(0.25, (float) $unit->minimum_wholesale_quantity);
            $this->assertEquals((float) $original->conversion_factor, (float) $unit->conversion_factor);
            $this->assertEquals((float) $original->selling_price, (float) $unit->selling_price);
            $this->assertEquals((float) $original->cost_price, (float) $unit->cost_price);
        }

        foreach ([$bag, $packet, $tin, $jerrican, $sack, $piece, $pc, $manualSack] as $retailUnit) {
            $retailUnit->refresh();
            $original = $before->get($retailUnit->id);

            $this->assertSame((bool) $original->allow_fractional_quantity, (bool) $retailUnit->allow_fractional_quantity);
            $this->assertSame((int) $original->quantity_precision, (int) $retailUnit->quantity_precision);
            $this->assertEquals($original->minimum_wholesale_quantity, $retailUnit->minimum_wholesale_quantity);
            $this->assertEquals((float) $original->conversion_factor, (float) $retailUnit->conversion_factor);
            $this->assertEquals((float) $original->selling_price, (float) $retailUnit->selling_price);
            $this->assertEquals((float) $original->cost_price, (float) $retailUnit->cost_price);
        }

        $customMinimum->refresh();
        $this->assertTrue((bool) $customMinimum->allow_fractional_quantity);
        $this->assertSame(2, (int) $customMinimum->quantity_precision);
        $this->assertEquals(0.75, (float) $customMinimum->minimum_wholesale_quantity);

        $this->assertSame($inventoryBefore, $inventory->fresh()->getAttributes());
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_optional_include_and_exclude_unit_names_are_respected(): void
    {
        $tray = $this->productUnit('TRAY EGGS', 'Tray', 30, 12000, 15000);
        $bag = $this->productUnit('BAG FLOUR', 'Bags', 1, 50000, 64000);
        $bundle = $this->productUnit('BUNDLE ITEM', 'Bundle', 10, 5000, 8000);

        $this->artisan('product-units:enable-fractional-wholesale', [
            '--commit' => true,
            '--include' => 'Tray,Bags',
            '--exclude' => 'Bundle',
            '--min' => 0.5,
            '--precision' => 3,
        ])->assertSuccessful();

        $tray->refresh();
        $this->assertTrue((bool) $tray->allow_fractional_quantity);
        $this->assertSame(3, (int) $tray->quantity_precision);
        $this->assertEquals(0.5, (float) $tray->minimum_wholesale_quantity);

        $bag->refresh();
        $this->assertTrue((bool) $bag->allow_fractional_quantity);
        $this->assertSame(3, (int) $bag->quantity_precision);
        $this->assertEquals(0.5, (float) $bag->minimum_wholesale_quantity);

        $bundle->refresh();
        $this->assertFalse((bool) $bundle->allow_fractional_quantity);
        $this->assertSame(0, (int) $bundle->quantity_precision);
        $this->assertNull($bundle->minimum_wholesale_quantity);
    }

    public function test_rollback_excluded_reverts_only_accidental_excluded_unit_settings(): void
    {
        $bag = $this->productUnit('BAG FLOUR', 'Bags', 1, 50000, 64000, [
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.25,
        ]);
        $tin = $this->productUnit('TIN BISCUITS', 'Tins', 1, 18000, 25000, [
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.25,
        ]);
        $jerrican = $this->productUnit('JERRICAN OIL', 'Jerricans', 1, 80000, 120000, [
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.25,
        ]);
        $sack = $this->productUnit('SACK RICE', 'Sacks', 50, 80000, 110000, [
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.25,
        ]);
        $manualBag = $this->productUnit('MANUAL BAG', 'Bags', 1, 50000, 64000, [
            'allow_fractional_quantity' => true,
            'quantity_precision' => 3,
            'minimum_wholesale_quantity' => 0.5,
        ]);
        $box = $this->productUnit('BOX SOAP', 'Boxes', 24, 90000, 114000, [
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.25,
        ]);

        $before = ProductUnit::query()
            ->whereKey([$bag->id, $tin->id, $jerrican->id, $sack->id, $manualBag->id, $box->id])
            ->get()
            ->keyBy('id');

        $this->artisan('product-units:enable-fractional-wholesale', [
            '--rollback-excluded' => true,
            '--commit' => true,
        ])
            ->expectsOutput('Excluded unit fractional settings rolled back.')
            ->assertSuccessful();

        foreach ([$bag, $tin, $jerrican, $sack] as $unit) {
            $unit->refresh();
            $original = $before->get($unit->id);

            $this->assertFalse((bool) $unit->allow_fractional_quantity);
            $this->assertSame(0, (int) $unit->quantity_precision);
            $this->assertNull($unit->minimum_wholesale_quantity);
            $this->assertEquals((float) $original->conversion_factor, (float) $unit->conversion_factor);
            $this->assertEquals((float) $original->selling_price, (float) $unit->selling_price);
            $this->assertEquals((float) $original->cost_price, (float) $unit->cost_price);
        }

        foreach ([$manualBag, $box] as $unit) {
            $unit->refresh();
            $original = $before->get($unit->id);

            $this->assertSame((bool) $original->allow_fractional_quantity, (bool) $unit->allow_fractional_quantity);
            $this->assertSame((int) $original->quantity_precision, (int) $unit->quantity_precision);
            $this->assertEquals((float) $original->minimum_wholesale_quantity, (float) $unit->minimum_wholesale_quantity);
            $this->assertEquals((float) $original->conversion_factor, (float) $unit->conversion_factor);
            $this->assertEquals((float) $original->selling_price, (float) $unit->selling_price);
            $this->assertEquals((float) $original->cost_price, (float) $unit->cost_price);
        }
    }

    private function productUnit(
        string $productName,
        string $unitName,
        float $conversionFactor,
        float $costPrice,
        float $sellingPrice,
        array $overrides = []
    ): ProductUnit {
        $product = Product::create([
            'name' => $productName,
            'is_active' => true,
        ]);

        return ProductUnit::create(array_merge([
            'product_id' => $product->id,
            'unit_name' => $unitName,
            'conversion_factor' => $conversionFactor,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'allow_fractional_quantity' => false,
            'quantity_precision' => 0,
            'minimum_wholesale_quantity' => null,
            'is_active' => true,
        ], $overrides));
    }
}
