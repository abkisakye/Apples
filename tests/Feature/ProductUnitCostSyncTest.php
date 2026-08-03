<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseFundingSource;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUnitCostSyncTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private Supplier $supplier;
    private PaymentMode $paymentMode;
    private PurchaseFundingSource $businessCash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsRole('admin');

        $this->store = Store::create(['name' => 'Main Shop', 'is_active' => true]);
        $this->supplier = Supplier::create(['name' => 'Cost Supplier', 'is_active' => true]);
        $this->paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $this->businessCash = PurchaseFundingSource::query()->firstOrCreate([
            'name' => 'Business Cash / Shop Cash',
        ], [
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_posted_cash_purchase_updates_product_unit_cost_without_changing_selling_price(): void
    {
        $unit = $this->productUnit('AZAM 2KG', 'Cartons', 0, 82000);

        $this->postPurchase($unit, 2, 64000, 'cash', 128000)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertEquals(64000.0, (float) $unit->cost_price);
        $this->assertEquals(82000.0, (float) $unit->selling_price);
    }

    public function test_posted_credit_purchase_syncs_cost_when_stock_is_bought(): void
    {
        $unit = $this->productUnit('CREDIT RICE', 'Sacks', 0, 150000);

        $this->postPurchase($unit, 1, 100000, 'credit', 0)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(100000.0, (float) $unit->fresh()->cost_price);
    }

    public function test_zero_purchase_cost_does_not_overwrite_existing_product_unit_cost(): void
    {
        $unit = $this->productUnit('ZERO COST ITEM', 'Pieces', 2500, 3500);

        $this->postPurchase($unit, 1, 0, 'credit', 0)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertEquals(2500.0, (float) $unit->cost_price);
        $this->assertEquals(3500.0, (float) $unit->selling_price);
    }

    public function test_backfill_command_dry_run_does_not_update_product_units(): void
    {
        $unit = $this->productUnit('DRY RUN ITEM', 'Boxes', 0, 12000);
        $this->historicalPurchaseItem($unit, 9000, '2026-07-28');

        $this->artisan('product-units:sync-costs-from-purchases', ['--dry-run' => true])
            ->expectsOutput('Dry-run only. No product unit costs were changed.')
            ->assertSuccessful();

        $this->assertEquals(0.0, (float) $unit->fresh()->cost_price);
    }

    public function test_backfill_command_commit_updates_only_missing_costs_by_default(): void
    {
        $missingCostUnit = $this->productUnit('MISSING BACKFILL ITEM', 'Cartons', 0, 36000);
        $existingCostUnit = $this->productUnit('EXISTING BACKFILL ITEM', 'Pieces', 1200, 1800);
        $this->historicalPurchaseItem($missingCostUnit, 24000, '2026-07-28');
        $this->historicalPurchaseItem($existingCostUnit, 1500, '2026-07-28');

        $this->artisan('product-units:sync-costs-from-purchases', ['--commit' => true])
            ->expectsOutput('Product unit cost sync committed.')
            ->assertSuccessful();

        $this->assertEquals(24000.0, (float) $missingCostUnit->fresh()->cost_price);
        $this->assertEquals(1200.0, (float) $existingCostUnit->fresh()->cost_price);
    }

    public function test_backfill_command_update_all_uses_latest_purchase_cost(): void
    {
        $unit = $this->productUnit('UPDATE ALL ITEM', 'Pieces', 1000, 1800);
        $this->historicalPurchaseItem($unit, 1200, '2026-07-27');
        $this->historicalPurchaseItem($unit, 1400, '2026-07-28');

        $this->artisan('product-units:sync-costs-from-purchases', ['--commit' => true, '--update-all' => true])
            ->expectsOutput('Product unit cost sync committed.')
            ->assertSuccessful();

        $this->assertEquals(1400.0, (float) $unit->fresh()->cost_price);
    }

    private function productUnit(string $productName, string $unitName, float $costPrice, float $sellingPrice): ProductUnit
    {
        $category = Category::query()->firstOrCreate(['name' => 'TEST CATEGORY']);
        $product = Product::create([
            'name' => $productName,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        return ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => $unitName,
            'conversion_factor' => 1,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'is_active' => true,
        ]);
    }

    private function postPurchase(ProductUnit $unit, float $quantity, float $unitCost, string $purchaseType, float $amountPaid)
    {
        return $this->post('/purchases', [
            'purchase_date' => '2026-07-28',
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_type' => $purchaseType,
            'payment_mode_id' => $this->paymentMode->id,
            'purchase_funding_source_id' => $amountPaid > 0 ? $this->businessCash->id : null,
            'amount_paid' => $amountPaid,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => $quantity, 'unit_cost' => $unitCost],
            ],
        ]);
    }

    private function historicalPurchaseItem(ProductUnit $unit, float $unitCost, string $purchaseDate): void
    {
        $purchase = Purchase::create([
            'purchase_no' => 'PUR-HIST-'.str_pad((string) (Purchase::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'purchase_date' => $purchaseDate,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $this->paymentMode->id,
            'purchase_funding_source_id' => $this->businessCash->id,
            'subtotal' => $unitCost,
            'total_amount' => $unitCost,
            'amount_paid' => $unitCost,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $unit->product_id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'base_quantity' => 1,
            'conversion_factor_snapshot' => 1,
            'unit_cost' => $unitCost,
            'line_total' => $unitCost,
        ]);
    }
}
