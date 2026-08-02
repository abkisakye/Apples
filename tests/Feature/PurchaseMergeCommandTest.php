<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PurchaseMergeCommandTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private Supplier $others;
    private Supplier $bookside;
    private PaymentMode $cashMode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::query()->firstOrCreate(['name' => 'Apples Of Gold'], ['is_active' => true]);
        $this->others = Supplier::query()->firstOrCreate(['name' => 'OTHERS'], ['is_active' => true]);
        $this->bookside = Supplier::query()->firstOrCreate(['name' => 'BOOKSIDE LIMITED'], ['is_active' => true]);
        $this->cashMode = PaymentMode::query()->firstOrCreate(['name' => 'Cash'], ['is_active' => true]);

        File::deleteDirectory(storage_path('app/purchase-merges'));
    }

    public function test_purchase_merge_dry_run_changes_nothing(): void
    {
        [$target, $source, $zeroTotal] = $this->createMergeFixture();
        $sourceItem = $source->items()->firstOrFail();

        $this->artisan('purchases:merge', $this->mergeOptions(['--dry-run' => true]))
            ->expectsOutput('Target: PUR-20260714-0001')
            ->expectsOutput('Purchase count: 3')
            ->expectsOutput('Source count: 2')
            ->expectsOutput('Zero-total purchases: PUR-20260716-0001')
            ->expectsOutput('Confirmation: no data changed.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('purchases', [
            'purchase_no' => $target->purchase_no,
            'total_amount' => 10000000,
        ]);
        $this->assertDatabaseHas('purchases', ['purchase_no' => $source->purchase_no]);
        $this->assertDatabaseHas('purchases', ['purchase_no' => $zeroTotal->purchase_no]);
        $this->assertDatabaseHas('purchase_items', [
            'id' => $sourceItem->id,
            'purchase_id' => $source->id,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase',
            'reference_id' => $sourceItem->id,
            'reference_no' => $source->purchase_no,
        ]);
        $this->assertFalse(File::exists(storage_path('app/purchase-merges')));
    }

    public function test_purchase_merge_commit_moves_items_and_keeps_inventory_quantities_unchanged(): void
    {
        [$target, $source, $zeroTotal, $bookside] = $this->createMergeFixture();
        $sourceItem = $source->items()->firstOrFail();
        $zeroItem = $zeroTotal->items()->firstOrFail();
        $beforeTransactions = InventoryTransaction::query()
            ->orderBy('id')
            ->get(['id', 'reference_id', 'quantity_in', 'quantity_out', 'base_quantity_in', 'base_quantity_out'])
            ->keyBy('id');

        $this->artisan('purchases:merge', $this->mergeOptions(['--commit' => true]))
            ->expectsOutput('Purchase merge completed successfully into PUR-20260714-0001.')
            ->assertExitCode(0);

        $target->refresh();
        $bookside->refresh();

        $this->assertEquals(25966999.52, (float) $target->total_amount);
        $this->assertEquals(25966999.52, (float) $target->amount_paid);
        $this->assertEquals(0.0, (float) $target->balance_due);
        $this->assertSame('cash', $target->purchase_type);
        $this->assertSame('posted', $target->status);
        $this->assertNull($target->credit_due_date);
        $this->assertNull($target->credit_period_days);

        $this->assertDatabaseMissing('purchases', ['purchase_no' => $source->purchase_no]);
        $this->assertDatabaseMissing('purchases', ['purchase_no' => $zeroTotal->purchase_no]);
        $this->assertDatabaseHas('purchase_items', [
            'id' => $sourceItem->id,
            'purchase_id' => $target->id,
        ]);
        $this->assertDatabaseHas('purchase_items', [
            'id' => $zeroItem->id,
            'purchase_id' => $target->id,
            'line_total' => 0,
        ]);
        $this->assertSame(3, $target->items()->count());

        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase',
            'reference_id' => $sourceItem->id,
            'reference_no' => $target->purchase_no,
        ]);

        InventoryTransaction::query()
            ->orderBy('id')
            ->get(['id', 'reference_id', 'quantity_in', 'quantity_out', 'base_quantity_in', 'base_quantity_out'])
            ->each(function (InventoryTransaction $transaction) use ($beforeTransactions): void {
                $before = $beforeTransactions->get($transaction->id);

                $this->assertNotNull($before);
                $this->assertSame((int) $before->reference_id, (int) $transaction->reference_id);
                $this->assertEquals((float) $before->quantity_in, (float) $transaction->quantity_in);
                $this->assertEquals((float) $before->quantity_out, (float) $transaction->quantity_out);
                $this->assertEquals((float) $before->base_quantity_in, (float) $transaction->base_quantity_in);
                $this->assertEquals((float) $before->base_quantity_out, (float) $transaction->base_quantity_out);
            });

        $this->assertDatabaseHas('purchases', [
            'purchase_no' => 'PUR-20260801-0005',
            'supplier_id' => $this->bookside->id,
            'total_amount' => 90000,
            'amount_paid' => 0,
            'balance_due' => 90000,
            'status' => 'posted',
        ]);
        $this->assertSame(2, $bookside->items()->count());
        $this->assertNotEmpty(File::glob(storage_path('app/purchase-merges/*.json')));
    }

    public function test_purchase_merge_rolls_back_on_validation_failure(): void
    {
        [$target, $source] = $this->createMergeFixture();
        $sourceItem = $source->items()->firstOrFail();

        DB::table('follow_up_actions')->insert([
            'purchase_id' => $source->id,
            'supplier_id' => $this->others->id,
            'reminder_date' => '2026-08-02',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('purchases:merge', $this->mergeOptions(['--commit' => true]))
            ->expectsOutputToContain('Unexpected purchase_id references found')
            ->assertExitCode(1);

        $target->refresh();
        $this->assertEquals(10000000.0, (float) $target->total_amount);
        $this->assertDatabaseHas('purchases', ['purchase_no' => $source->purchase_no]);
        $this->assertDatabaseHas('purchase_items', [
            'id' => $sourceItem->id,
            'purchase_id' => $source->id,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_id' => $sourceItem->id,
            'reference_no' => $source->purchase_no,
        ]);
        $this->assertFalse(File::exists(storage_path('app/purchase-merges')));
    }

    public function test_document_number_service_uses_maximum_suffix_plus_one_when_numbers_have_gaps(): void
    {
        $this->createPurchase('PUR-20260801-0005', $this->others, '2026-08-01', [5000], 5000, 0);

        $number = app(DocumentNumberService::class)->make('purchase', '2026-08-01');

        $this->assertSame('PUR-20260801-0006', $number);
    }

    private function createMergeFixture(): array
    {
        $target = $this->createPurchase('PUR-20260714-0001', $this->others, '2026-07-14', [10000000.00], 10000000.00, 0);
        $source = $this->createPurchase('PUR-20260715-0001', $this->others, '2026-07-15', [15966999.52], 15966999.52, 0);
        $zeroTotal = $this->createPurchase('PUR-20260716-0001', $this->others, '2026-07-16', [0.00], 0, 0);
        $bookside = $this->createPurchase('PUR-20260801-0005', $this->bookside, '2026-08-01', [40000.00, 50000.00], 0, 90000);

        return [$target, $source, $zeroTotal, $bookside];
    }

    private function createPurchase(string $purchaseNo, Supplier $supplier, string $date, array $lineTotals, float $amountPaid, float $balanceDue): Purchase
    {
        $total = round(array_sum($lineTotals), 2);
        $purchase = Purchase::create([
            'purchase_no' => $purchaseNo,
            'purchase_date' => $date,
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
            'purchase_type' => $balanceDue > 0 ? 'credit' : 'cash',
            'payment_mode_id' => $this->cashMode->id,
            'subtotal' => $total,
            'discount_amount' => 0,
            'vat_amount' => 0,
            'total_amount' => $total,
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'credit_period_days' => $balanceDue > 0 ? 30 : null,
            'credit_due_date' => $balanceDue > 0 ? '2026-08-31' : null,
            'status' => 'posted',
            'remarks' => 'Opening stock import',
        ]);

        foreach ($lineTotals as $index => $lineTotal) {
            $product = Product::create([
                'name' => "Merge Product {$purchaseNo} {$index}",
                'is_active' => true,
            ]);
            $unit = ProductUnit::create([
                'product_id' => $product->id,
                'unit_name' => 'Piece',
                'conversion_factor' => 1,
                'cost_price' => $lineTotal,
                'selling_price' => $lineTotal,
                'is_active' => true,
            ]);
            $item = $purchase->items()->create([
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'quantity' => 1,
                'base_quantity' => 1,
                'conversion_factor_snapshot' => 1,
                'unit_cost' => $lineTotal,
                'vat_amount' => 0,
                'discount_amount' => 0,
                'line_total' => $lineTotal,
            ]);

            InventoryTransaction::create([
                'transaction_date' => $date,
                'store_id' => $this->store->id,
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'reference_type' => 'purchase',
                'reference_id' => $item->id,
                'reference_no' => $purchaseNo,
                'movement_type' => 'purchase',
                'quantity_in' => 1,
                'quantity_out' => 0,
                'base_quantity_in' => 1,
                'base_quantity_out' => 0,
                'conversion_factor_snapshot' => 1,
                'unit_cost' => $lineTotal,
            ]);
        }

        return $purchase;
    }

    private function mergeOptions(array $mode): array
    {
        return array_merge([
            '--target' => 'PUR-20260714-0001',
            '--supplier' => ' OTHERS ',
            '--from' => '2026-07-14',
            '--to' => '2026-08-01',
            '--exclude' => ['PUR-20260801-0005'],
        ], $mode);
    }
}
