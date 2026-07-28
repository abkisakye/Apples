<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOpeningStockConversionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_stock_purchase_conversion_dry_run_makes_no_database_changes(): void
    {
        $purchase = $this->creditPurchase('PUR-20260728-0001', 'OTHERS', '2026-07-28', 125000);
        $this->seedPurchaseInventory($purchase);

        $this->artisan('purchases:convert-opening-stock-to-paid', [
            '--supplier' => 'OTHERS',
            '--date' => '2026-07-28',
            '--dry-run' => true,
        ])
            ->expectsOutput('Dry run mode')
            ->expectsOutput('Matched purchases: 1')
            ->assertExitCode(0);

        $purchase->refresh();
        $this->assertSame('credit', $purchase->purchase_type);
        $this->assertEquals(0.0, (float) $purchase->amount_paid);
        $this->assertEquals(125000.0, (float) $purchase->balance_due);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase',
            'reference_no' => 'PUR-20260728-0001',
            'quantity_in' => 5,
        ]);
    }

    public function test_opening_stock_purchase_conversion_commit_marks_only_matching_purchases_paid_without_touching_stock(): void
    {
        $cashMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $matching = $this->creditPurchase('PUR-20260728-0001', 'OTHERS', '2026-07-28', 125000);
        $unrelatedSupplier = $this->creditPurchase('PUR-20260728-0002', 'SOAP SUPPLIER', '2026-07-28', 80000);
        $unrelatedDate = $this->creditPurchase('PUR-20260727-0001', 'OTHERS', '2026-07-27', 60000);
        $this->seedPurchaseInventory($matching);
        $this->seedPurchaseInventory($unrelatedSupplier);
        $this->seedPurchaseInventory($unrelatedDate);

        $this->artisan('purchases:convert-opening-stock-to-paid', [
            '--supplier' => 'OTHERS',
            '--date' => '2026-07-28',
            '--payment-mode' => 'Cash',
            '--note' => 'Opening stock already paid before system start',
            '--commit' => true,
        ])
            ->expectsOutput('Commit mode')
            ->expectsOutput('Matched purchases: 1')
            ->expectsOutput('Converted purchases: 1')
            ->assertExitCode(0);

        $matching->refresh();
        $unrelatedSupplier->refresh();
        $unrelatedDate->refresh();

        $this->assertSame('cash', $matching->purchase_type);
        $this->assertSame($cashMode->id, $matching->payment_mode_id);
        $this->assertEquals(125000.0, (float) $matching->amount_paid);
        $this->assertEquals(0.0, (float) $matching->balance_due);
        $this->assertNull($matching->credit_due_date);
        $this->assertStringContainsString('[opening-stock-paid-conversion] Opening stock already paid before system start', (string) $matching->remarks);

        $this->assertSame('credit', $unrelatedSupplier->purchase_type);
        $this->assertEquals(80000.0, (float) $unrelatedSupplier->balance_due);
        $this->assertSame('credit', $unrelatedDate->purchase_type);
        $this->assertEquals(60000.0, (float) $unrelatedDate->balance_due);

        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase',
            'reference_no' => 'PUR-20260728-0001',
            'quantity_in' => 5,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'purchase.opening_stock_marked_paid',
            'subject_type' => Purchase::class,
            'subject_id' => $matching->id,
        ]);
    }

    public function test_opening_stock_purchase_conversion_is_idempotent(): void
    {
        $purchase = $this->creditPurchase('PUR-20260728-0001', 'OTHERS', '2026-07-28', 125000);

        foreach (range(1, 2) as $run) {
            $this->artisan('purchases:convert-opening-stock-to-paid', [
                '--purchase-no' => $purchase->purchase_no,
                '--commit' => true,
            ])->assertExitCode(0);
        }

        $purchase->refresh();
        $this->assertSame('cash', $purchase->purchase_type);
        $this->assertEquals(125000.0, (float) $purchase->amount_paid);
        $this->assertEquals(0.0, (float) $purchase->balance_due);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_opening_stock_purchase_conversion_commit_requires_a_selector(): void
    {
        $this->creditPurchase('PUR-20260728-0001', 'OTHERS', '2026-07-28', 125000);

        $this->artisan('purchases:convert-opening-stock-to-paid', [
            '--commit' => true,
        ])
            ->expectsOutput('For safety, --commit requires at least one selector: --supplier, --date, --from-date, --to-date, or --purchase-no.')
            ->assertExitCode(1);

        $this->assertDatabaseHas('purchases', [
            'purchase_no' => 'PUR-20260728-0001',
            'purchase_type' => 'credit',
            'balance_due' => 125000,
        ]);
    }

    private function creditPurchase(string $purchaseNo, string $supplierName, string $date, float $total): Purchase
    {
        $store = Store::query()->firstOrCreate(['name' => 'Apples Of Gold'], ['is_active' => true]);
        $supplier = Supplier::query()->firstOrCreate(['name' => $supplierName], ['is_active' => true]);
        $creditMode = PaymentMode::query()->firstOrCreate(['name' => 'Credit'], ['is_active' => true]);

        return Purchase::create([
            'purchase_no' => $purchaseNo,
            'purchase_date' => $date,
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $creditMode->id,
            'subtotal' => $total,
            'total_amount' => $total,
            'amount_paid' => 0,
            'balance_due' => $total,
            'credit_period_days' => 30,
            'credit_due_date' => '2026-08-27',
            'status' => 'posted',
            'remarks' => 'Imported current shop stock',
        ]);
    }

    private function seedPurchaseInventory(Purchase $purchase): void
    {
        $product = Product::create([
            'name' => 'Opening Product '.$purchase->purchase_no,
            'is_active' => true,
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Carton',
            'conversion_factor' => 24,
            'cost_price' => 25000,
            'selling_price' => 30000,
            'is_active' => true,
        ]);

        InventoryTransaction::create([
            'transaction_date' => $purchase->purchase_date,
            'store_id' => $purchase->store_id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
            'reference_no' => $purchase->purchase_no,
            'movement_type' => 'purchase',
            'quantity_in' => 5,
            'quantity_out' => 0,
            'base_quantity_in' => 120,
            'base_quantity_out' => 0,
            'conversion_factor_snapshot' => 24,
            'unit_cost' => 25000,
        ]);
    }
}
