<?php

namespace Tests\Feature;

use Carbon\Carbon;
use App\Models\ActivityLog;
use App\Models\BusinessSetting;
use App\Models\CashShift;
use App\Models\CapitalSource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FollowUpAction;
use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseFundingSource;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsRole('admin');
    }

    private function seedStock(Store $store, ProductUnit $unit, int $quantity, string $date = '2026-03-24', ?string $referenceNo = null): void
    {
        $referenceNo = $referenceNo ?? strtoupper(uniqid('SEED-', true));

        InventoryTransaction::create([
            'transaction_date' => $date,
            'store_id' => $store->id,
            'product_id' => $unit->product_id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'test_seed',
            'reference_id' => crc32($referenceNo.$unit->id.$store->id.$date.$quantity),
            'reference_no' => $referenceNo,
            'movement_type' => 'purchase',
            'quantity_in' => $quantity,
            'quantity_out' => 0,
            'unit_cost' => $unit->cost_price ?? 0,
            'unit_price' => $unit->selling_price ?? 0,
        ]);
    }

    private function businessCashFundingSource(): PurchaseFundingSource
    {
        return PurchaseFundingSource::query()->firstOrCreate([
            'name' => 'Business Cash / Shop Cash',
        ], [
            'description' => 'Cash available in the shop or business till.',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    private function supplierCreditFundingSource(): PurchaseFundingSource
    {
        return PurchaseFundingSource::query()->firstOrCreate([
            'name' => 'Supplier Credit / Not Paid Yet',
        ], [
            'description' => 'Unpaid supplier credit balance.',
            'is_active' => true,
            'sort_order' => 80,
        ]);
    }

    public function test_cash_sale_posting_creates_sale_items_and_inventory_transaction(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $walkInCustomer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Sugar', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'selling_price' => 500,
            'cost_price' => 350,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 10);

        $response = $this->post('/sales', [
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 500],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $response->assertRedirect('/sales/'.$sale->id);
        $response->assertSessionMissing('auto_print_document');

        $this->assertSame($walkInCustomer->id, $sale->customer_id);
        $this->assertSame('cash', $sale->sale_type);
        $this->assertStringStartsWith('RCPT-20260325-', $sale->sale_no);
        $this->assertEquals(1000.0, (float) $sale->total_amount);
        $this->assertEquals(0.0, (float) $sale->balance_due);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'sale',
            'reference_no' => $sale->sale_no,
            'movement_type' => 'sale',
            'product_unit_id' => $unit->id,
        ]);

        $this->get('/sales')->assertOk()->assertSee($sale->sale_no);
        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_insufficient_stock_sale_attempt_saves_nothing(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $walkInCustomer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'AZAM 2KG', 'is_active' => true]);
        $carton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Cartons',
            'conversion_factor' => 24,
            'selling_price' => 82000,
            'cost_price' => 70000,
            'is_active' => true,
        ]);

        $response = $this->from('/sales/create')->post('/sales', [
            'sale_date' => '2026-07-11',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 82000,
            'items' => [
                ['product_unit_id' => $carton->id, 'quantity' => 1, 'unit_price' => 82000],
            ],
        ]);

        $response
            ->assertRedirect('/sales/create')
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->get('/sales/create')
            ->assertOk()
            ->assertSee('AZAM 2KG - Cartons');
        $this->get('/sales')->assertOk()->assertDontSee('UGX 82,000');
    }

    public function test_mixed_cart_with_one_insufficient_item_rolls_back_whole_sale(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $walkInCustomer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $sugar = Product::create(['name' => 'Sugar', 'is_active' => true]);
        $sugarUnit = ProductUnit::create([
            'product_id' => $sugar->id,
            'unit_name' => 'Pack',
            'conversion_factor' => 1,
            'selling_price' => 500,
            'cost_price' => 350,
            'is_active' => true,
        ]);
        $this->seedStock($store, $sugarUnit, 5, '2026-07-10', 'SEED-SUGAR');

        $flour = Product::create(['name' => 'Flour', 'is_active' => true]);
        $flourUnit = ProductUnit::create([
            'product_id' => $flour->id,
            'unit_name' => 'Bag',
            'conversion_factor' => 1,
            'selling_price' => 1000,
            'cost_price' => 800,
            'is_active' => true,
        ]);
        $this->seedStock($store, $flourUnit, 1, '2026-07-10', 'SEED-FLOUR');

        $inventoryCountBefore = InventoryTransaction::query()->count();

        $response = $this->from('/sales/create')->post('/sales', [
            'sale_date' => '2026-07-11',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 3000,
            'items' => [
                ['product_unit_id' => $sugarUnit->id, 'quantity' => 2, 'unit_price' => 500],
                ['product_unit_id' => $flourUnit->id, 'quantity' => 2, 'unit_price' => 1000],
            ],
        ]);

        $response
            ->assertRedirect('/sales/create')
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertSame($inventoryCountBefore, InventoryTransaction::query()->count());
        $this->get('/sales')->assertOk()->assertDontSee('UGX 3,000');
    }

    public function test_multi_unit_sale_fails_atomically_when_requested_base_stock_is_short(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $walkInCustomer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'GONJA CRISPS', 'base_unit_label' => 'piece', 'is_active' => true]);
        $piece = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Piece',
            'conversion_factor' => 1,
            'selling_price' => 1000,
            'cost_price' => 700,
            'is_active' => true,
            'is_base_unit' => true,
        ]);
        $carton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Carton',
            'conversion_factor' => 24,
            'selling_price' => 24000,
            'cost_price' => 16800,
            'is_active' => true,
        ]);

        InventoryTransaction::create([
            'transaction_date' => '2026-07-10',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'reference_type' => 'test_seed',
            'reference_id' => 1001,
            'reference_no' => 'SEED-PIECES',
            'movement_type' => 'purchase',
            'quantity_in' => 10,
            'quantity_out' => 0,
            'base_quantity_in' => 10,
            'base_quantity_out' => 0,
            'conversion_factor_snapshot' => 1,
            'unit_cost' => $piece->cost_price,
            'unit_price' => $piece->selling_price,
        ]);

        $inventoryCountBefore = InventoryTransaction::query()->count();

        $response = $this->from('/sales/create')->post('/sales', [
            'sale_date' => '2026-07-11',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 24000,
            'items' => [
                ['product_unit_id' => $carton->id, 'quantity' => 1, 'unit_price' => 24000],
            ],
        ]);

        $response
            ->assertRedirect('/sales/create')
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertSame($inventoryCountBefore, InventoryTransaction::query()->count());
        $this->get('/sales')->assertOk()->assertDontSee('UGX 24,000');
    }

    public function test_cash_sales_index_shows_sales_not_purchase_documents(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Counter Customer', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Stock Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $sale = Sale::create([
            'sale_no' => 'RCPT-TEST-CASH',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'amount_paid' => 1000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        Purchase::create([
            'purchase_no' => 'STOCK-PURCHASE-TEST',
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 2500,
            'total_amount' => 2500,
            'amount_paid' => 2500,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $this->get('/sales?type=cash')
            ->assertOk()
            ->assertSee('Cash Sales')
            ->assertSee($sale->sale_no)
            ->assertDontSee('STOCK-PURCHASE-TEST');
    }

    public function test_listing_pages_have_server_backed_live_search_and_keep_server_side_filtering(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $matchCustomer = Customer::create(['name' => 'Live Search Customer', 'phone' => '0700000001', 'is_active' => true]);
        $otherCustomer = Customer::create(['name' => 'Quiet Customer', 'phone' => '0700000002', 'is_active' => true]);
        $matchSupplier = Supplier::create(['name' => 'Live Search Supplier', 'phone' => '0710000001', 'is_active' => true]);
        $otherSupplier = Supplier::create(['name' => 'Quiet Supplier', 'phone' => '0710000002', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $matchPurchase = Purchase::create([
            'purchase_no' => 'PO-LIVE-SEARCH',
            'purchase_date' => '2026-07-10',
            'store_id' => $store->id,
            'supplier_id' => $matchSupplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'amount_paid' => 1000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);
        Purchase::create([
            'purchase_no' => 'PO-QUIET',
            'purchase_date' => '2026-07-10',
            'store_id' => $store->id,
            'supplier_id' => $otherSupplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 2000,
            'total_amount' => 2000,
            'amount_paid' => 2000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $matchSale = Sale::create([
            'sale_no' => 'RCPT-LIVE-SEARCH',
            'sale_date' => '2026-07-11',
            'store_id' => $store->id,
            'customer_id' => $matchCustomer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1500,
            'total_amount' => 1500,
            'amount_paid' => 1500,
            'balance_due' => 0,
            'status' => 'posted',
        ]);
        Sale::create([
            'sale_no' => 'RCPT-QUIET',
            'sale_date' => '2026-07-11',
            'store_id' => $store->id,
            'customer_id' => $otherCustomer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 2500,
            'total_amount' => 2500,
            'amount_paid' => 2500,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        \App\Models\CustomerPayment::create([
            'payment_no' => 'CP-LIVE-SEARCH',
            'payment_date' => '2026-07-11',
            'customer_id' => $matchCustomer->id,
            'sale_id' => $matchSale->id,
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 500,
            'status' => 'posted',
        ]);
        \App\Models\CustomerPayment::create([
            'payment_no' => 'CP-QUIET',
            'payment_date' => '2026-07-11',
            'customer_id' => $otherCustomer->id,
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 600,
            'status' => 'posted',
        ]);

        $stockProduct = Product::create(['name' => 'Live Stock Rice', 'code' => 'LIVE-RICE', 'is_active' => true]);
        $stockUnit = ProductUnit::create([
            'product_id' => $stockProduct->id,
            'unit_name' => 'Kg',
            'conversion_factor' => 1,
            'selling_price' => 4000,
            'cost_price' => 3000,
            'is_active' => true,
        ]);
        $otherStockProduct = Product::create(['name' => 'Quiet Stock Beans', 'code' => 'QUIET-BEANS', 'is_active' => true]);
        $otherStockUnit = ProductUnit::create([
            'product_id' => $otherStockProduct->id,
            'unit_name' => 'Kg',
            'conversion_factor' => 1,
            'selling_price' => 5000,
            'cost_price' => 3500,
            'is_active' => true,
        ]);
        $this->seedStock($store, $stockUnit, 10, '2026-07-10', 'SEED-LIVE-RICE');
        $this->seedStock($store, $otherStockUnit, 10, '2026-07-10', 'SEED-QUIET-BEANS');

        $this->get('/purchases?q=LIVE')
            ->assertOk()
            ->assertSee('data-server-live-search-form', false)
            ->assertSee('data-server-live-search-input', false)
            ->assertSee('data-server-live-search-results="#purchases-results"', false)
            ->assertSee('Searches all purchases, not only visible rows.')
            ->assertSee($matchPurchase->purchase_no)
            ->assertDontSee('PO-QUIET');

        $this->get('/customers?q=Live')
            ->assertOk()
            ->assertSee('data-server-live-search-form', false)
            ->assertSee('data-server-live-search-input', false)
            ->assertSee('data-server-live-search-results="#customers-results"', false)
            ->assertSee('Searches all customers, not only visible rows.')
            ->assertSee($matchCustomer->name)
            ->assertDontSee($otherCustomer->name);

        $this->get('/customer-payments?q=CP-LIVE')
            ->assertOk()
            ->assertSee('data-server-live-search-form', false)
            ->assertSee('data-server-live-search-input', false)
            ->assertSee('data-server-live-search-results="#customer-payments-results"', false)
            ->assertSee('Searches all customer payments, not only visible rows.')
            ->assertSee('CP-LIVE-SEARCH')
            ->assertDontSee('CP-QUIET');

        $this->get('/suppliers?q=Live')
            ->assertOk()
            ->assertSee('data-server-live-search-form', false)
            ->assertSee('data-server-live-search-input', false)
            ->assertSee('data-server-live-search-results="#suppliers-results"', false)
            ->assertSee('Searches all suppliers, not only visible rows.')
            ->assertSee($matchSupplier->name)
            ->assertDontSee($otherSupplier->name);

        $this->get('/stock/balances?q=LIVE-RICE')
            ->assertOk()
            ->assertSee('data-server-live-search-form', false)
            ->assertSee('data-server-live-search-input', false)
            ->assertSee('data-server-live-search-results="#stock-balances-results"', false)
            ->assertSee('Searches all stock rows, not only visible rows.')
            ->assertSee('Live Stock Rice')
            ->assertDontSee('Quiet Stock Beans');

        $this->get('/sales?q=RCPT-LIVE')
            ->assertOk()
            ->assertSee('data-server-live-search-form', false)
            ->assertSee('data-server-live-search-input', false)
            ->assertSee('data-server-live-search-results="#sales-results"', false)
            ->assertSee('Searches all sales records, not only visible rows.')
            ->assertSee('RCPT-LIVE-SEARCH')
            ->assertDontSee('RCPT-QUIET');

        $this->get('/sales')
            ->assertOk()
            ->assertDontSee('Filter visible rows');

        $ajaxCases = [
            ['/purchases?q=LIVE', $matchPurchase->purchase_no, 'PO-QUIET'],
            ['/customers?q=Live', $matchCustomer->name, $otherCustomer->name],
            ['/customer-payments?q=CP-LIVE', 'CP-LIVE-SEARCH', 'CP-QUIET'],
            ['/suppliers?q=Live', $matchSupplier->name, $otherSupplier->name],
            ['/stock/balances?q=LIVE-RICE', 'Live Stock Rice', 'Quiet Stock Beans'],
            ['/sales?q=RCPT-LIVE', 'RCPT-LIVE-SEARCH', 'RCPT-QUIET'],
        ];

        foreach ($ajaxCases as [$url, $visible, $hidden]) {
            $this->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->get($url)
                ->assertOk()
                ->assertSee($visible)
                ->assertDontSee($hidden)
                ->assertDontSee('data-server-live-search-form', false);
        }
    }

    public function test_customer_payment_reduces_credit_sale_balance(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Test Customer', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $sale = Sale::create([
            'sale_no' => 'CR-TEST-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 300,
            'total_amount' => 300,
            'amount_paid' => 0,
            'balance_due' => 300,
            'status' => 'posted',
        ]);

        $response = $this->post('/customer-payments', [
            'payment_date' => '2026-03-25',
            'customer_id' => $customer->id,
            'account_reference_type' => 'sale',
            'sale_id' => $sale->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 200,
        ]);

        $payment = \App\Models\CustomerPayment::query()->firstOrFail();
        $response->assertRedirect('/customer-payments/'.$payment->id);
        $response->assertSessionMissing('auto_print_receipt');

        $sale->refresh();

        $this->assertEquals(200.0, (float) $sale->amount_paid);
        $this->assertEquals(100.0, (float) $sale->balance_due);
        $this->assertDatabaseCount('customer_payments', 1);
    }

    public function test_customer_payment_can_reduce_opening_balance_without_fake_sale(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'Legacy Credit Customer',
            'opening_balance' => 500,
            'opening_balance_date' => '2026-03-01',
            'is_active' => true,
        ]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $actingUser = $this->app['auth']->user();
        $actingUser->update(['default_store_id' => $store->id]);

        $response = $this->post('/customer-payments', [
            'payment_date' => '2026-03-25',
            'customer_id' => $customer->id,
            'account_reference_type' => 'opening_balance',
            'payment_mode_id' => $paymentMode->id,
            'amount' => 200,
        ]);

        $payment = \App\Models\CustomerPayment::query()->firstOrFail();
        $response->assertRedirect('/customer-payments/'.$payment->id);

        $this->assertNull($payment->sale_id);
        $this->assertSame('opening_balance', $payment->account_reference_type);
        $this->assertEquals(300.0, (float) $customer->fresh()->openingBalanceOutstanding());
    }

    public function test_partial_amount_received_turns_sale_into_credit_with_balance(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Credit Buyer', 'allow_credit_sales' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Soap', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bar',
            'selling_price' => 2000,
            'cost_price' => 1200,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 10);

        $response = $this->post('/sales', [
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'amount_paid' => 1500,
            'credit_period_days' => 14,
            'payment_mode_id' => $paymentMode->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 2000],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $response->assertRedirect('/sales/'.$sale->id);
        $response->assertSessionMissing('auto_print_document');

        $this->assertSame('credit', $sale->sale_type);
        $this->assertEquals(4000.0, (float) $sale->total_amount);
        $this->assertEquals(1500.0, (float) $sale->amount_paid);
        $this->assertEquals(2500.0, (float) $sale->balance_due);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertNotNull($sale->credit_due_date);
    }

    public function test_overpayment_is_saved_as_cash_with_change_given(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $walkInCustomer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Bread', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Loaf',
            'selling_price' => 3000,
            'cost_price' => 1800,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 10);

        $response = $this->post('/sales', [
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 7000,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 3000],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $response->assertRedirect('/sales/'.$sale->id);
        $response->assertSessionMissing('auto_print_document');

        $this->assertSame($walkInCustomer->id, $sale->customer_id);
        $this->assertSame('cash', $sale->sale_type);
        $this->assertEquals(6000.0, (float) $sale->total_amount);
        $this->assertEquals(6000.0, (float) $sale->amount_paid);
        $this->assertEquals(7000.0, (float) $sale->cash_tendered);
        $this->assertEquals(1000.0, (float) $sale->change_given);
    }

    public function test_walk_in_customer_cannot_receive_credit(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $walkInCustomer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Milk', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'selling_price' => 3000,
            'cost_price' => 2000,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 5);

        $this->post('/sales', [
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 1000,
            'credit_period_days' => 7,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 3000],
            ],
        ])->assertSessionHasErrors([
            'customer_id' => 'This customer is not approved for credit sales. Please choose Cash/Mobile Money/Card or ask admin to approve credit for this customer.',
        ]);
    }

    public function test_unapproved_customer_cannot_post_credit_sale(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Unapproved Credit Buyer', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Credit Soap', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bar',
            'selling_price' => 3000,
            'cost_price' => 2000,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 5);

        $this->from('/sales/create')->post('/sales', [
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 1000,
            'credit_period_days' => 7,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 3000],
            ],
        ])->assertRedirect('/sales/create')
            ->assertSessionHasErrors([
                'customer_id' => 'This customer is not approved for credit sales. Please choose Cash/Mobile Money/Card or ask admin to approve credit for this customer.',
            ]);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_approved_customer_can_post_credit_sale(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Approved Credit Buyer', 'allow_credit_sales' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Credit Milk', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'selling_price' => 3000,
            'cost_price' => 2000,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 5);

        $response = $this->post('/sales', [
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 1000,
            'credit_period_days' => 7,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 3000],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $response->assertRedirect('/sales/'.$sale->id);
        $this->assertSame('credit', $sale->sale_type);
        $this->assertEquals(2000.0, (float) $sale->balance_due);
    }

    public function test_cash_sale_still_works_for_unapproved_customer(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Cash Only Buyer', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Cash Bread', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Loaf',
            'selling_price' => 3000,
            'cost_price' => 2000,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 5);

        $response = $this->post('/sales', [
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 3000,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 3000],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $response->assertRedirect('/sales/'.$sale->id);
        $this->assertSame('cash', $sale->sale_type);
        $this->assertEquals(0.0, (float) $sale->balance_due);
    }

    public function test_cashier_cannot_override_sale_price_without_approval(): void
    {
        $store = Store::create(['name' => 'Counter Store', 'is_active' => true]);
        $cashMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Counter Customer', 'is_active' => true]);
        $product = Product::create(['name' => 'Tea', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'selling_price' => 4000,
            'cost_price' => 2600,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 6);

        $cashierRole = \App\Models\Role::query()->updateOrCreate(
            ['name' => 'cashier'],
            ['description' => 'Cashier', 'permissions' => ['dashboard.view', 'sales.view', 'sales.manage']]
        );
        $cashier = User::factory()->create([
            'role_id' => $cashierRole->id,
            'default_store_id' => $store->id,
            'is_active' => true,
        ]);
        $cashier->roles()->sync([$cashierRole->id]);
        $this->actingAs($cashier);

        $this->post('/sales', [
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_mode_id' => $cashMode->id,
            'amount_paid' => 1000,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 1000],
            ],
        ])->assertSessionHasErrors('items.0.unit_price');
    }

    public function test_customer_can_be_quick_added_during_sale_entry(): void
    {
        $response = $this->postJson('/customers/quick-store', [
            'name' => 'New Counter Customer',
            'phone' => '0700123456',
            'location' => 'Kampala',
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'New Counter Customer',
                'phone' => '0700123456',
                'location' => 'Kampala',
            ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'New Counter Customer',
            'phone' => '0700123456',
            'location' => 'Kampala',
            'is_system' => false,
        ]);
    }

    public function test_common_sale_customer_and_purchase_supplier_are_ordered_first(): void
    {
        Customer::create(['name' => 'Zulu Customer', 'is_active' => true]);
        Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        Customer::create(['name' => 'Alpha Customer', 'is_active' => true]);

        $saleContent = $this->get('/sales/create')->assertOk()->getContent();
        $this->assertLessThan(
            strpos($saleContent, 'Alpha Customer'),
            strpos($saleContent, 'Walk-in Customer')
        );

        Supplier::create(['name' => 'Zulu Supplier', 'is_active' => true]);
        Supplier::create(['name' => 'OTHERS', 'is_active' => true]);
        Supplier::create(['name' => 'Alpha Supplier', 'is_active' => true]);

        $purchaseContent = $this->get('/purchases/create')->assertOk()->getContent();
        $this->assertLessThan(
            strpos($purchaseContent, 'Alpha Supplier'),
            strpos($purchaseContent, 'OTHERS')
        );
    }

    public function test_sale_detail_and_print_pages_load(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Test Customer', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $sale = Sale::create([
            'sale_no' => 'SL-DETAIL-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 100,
            'total_amount' => 100,
            'amount_paid' => 100,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $this->get('/sales/'.$sale->id)
            ->assertOk()
            ->assertSee($sale->sale_no)
            ->assertSee('Shortcut: F2 to print receipt')
            ->assertSee("event.key !== 'F2'", false)
            ->assertSee("localStorage.removeItem('apples.pos.sale.draft')", false);
        $this->get('/sales/'.$sale->id.'/print')
            ->assertOk()
            ->assertSee($sale->sale_no)
            ->assertSee('CASH SALE RECEIPT')
            ->assertSee('Apples Of Gold System')
            ->assertSee('Rolanz Software Solutions');
        $this->get('/sales/'.$sale->id.'/print?theme=full')->assertOk()->assertSee($sale->sale_no)->assertSee('Sales Receipt');
        $this->get('/sales/'.$sale->id.'/print?theme=thermal')->assertOk()->assertSee($sale->sale_no)->assertSee('CASH SALE RECEIPT');
    }

    public function test_pos_thermal_cash_receipt_uses_simple_80mm_layout(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Thermal Customer', 'phone' => '0700000000', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);
        $product = Product::create(['name' => 'Thermal Soap Long Name', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Boxes',
            'selling_price' => 184000,
            'cost_price' => 150000,
            'is_active' => true,
        ]);
        $sale = Sale::create([
            'sale_no' => 'RCPT-THERMAL-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 184000,
            'total_amount' => 184000,
            'amount_paid' => 184000,
            'balance_due' => 0,
            'status' => 'posted',
            'created_by' => auth()->id(),
        ]);
        \App\Models\SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'unit_price' => 184000,
            'line_total' => 184000,
        ]);
        \App\Models\CustomerPayment::create([
            'payment_no' => 'CP-THERMAL-1',
            'payment_date' => '2026-03-25',
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 184000,
            'status' => 'posted',
        ]);

        $response = $this->get('/sales/'.$sale->id.'/print')->assertOk();

        $response
            ->assertSee('size: 80mm', false)
            ->assertSee('width: 80mm', false)
            ->assertSee('width: 72mm', false)
            ->assertSee('class="receipt-screen-wrap"', false)
            ->assertSee('class="receipt-paper"', false)
            ->assertSee('class="no-print"', false)
            ->assertSee('display: none !important', false)
            ->assertSee('Scale 100')
            ->assertSee('Headers and footers Off')
            ->assertSee('Do not use Fit to page width')
            ->assertDontSee('Your business address')
            ->assertDontSee('+256700000000')
            ->assertSee('APPLES OF GOLD')
            ->assertSee('WHOLESALE')
            ->assertSee('Mutungo Biina - Kiduuka Stage')
            ->assertSee('CASH SALE RECEIPT')
            ->assertSee('Receipt No:')
            ->assertDontSee('Invoice No:')
            ->assertSee('RCPT-THERMAL-1')
            ->assertSee('Served By:')
            ->assertDontSee('Salesperson:')
            ->assertDontSee('Cashier:')
            ->assertDontSee('Time:')
            ->assertSee('Thermal Customer')
            ->assertSee('Thermal Soap Long Name')
            ->assertSee('Boxes')
            ->assertSee('1 x 184000')
            ->assertSee('184000')
            ->assertSee('Total')
            ->assertSee('Paid')
            ->assertSee('Change')
            ->assertDontSee('Due Date')
            ->assertDontSee('Subtotal')
            ->assertDontSee('PAYMENT')
            ->assertDontSee('Payment</div>', false)
            ->assertDontSee('Please settle outstanding balances')
            ->assertSee('Thank you for shopping with us.')
            ->assertSee('Apples Of Gold System')
            ->assertSee('Designed &amp; Developed by', false)
            ->assertSee('Rolanz Software Solutions')
            ->assertSee('Tel: +256 703/773-086 770')
            ->assertDontSee('Choose Format')
            ->assertDontSee('Full A4 Document')
            ->assertDontSee('Professional')
            ->assertDontSee('Simple')
            ->assertDontSee('<aside', false)
            ->assertDontSee('<nav', false)
            ->assertDontSee('size: A4', false)
            ->assertDontSee('3276', false)
            ->assertDontSee('transform: scale', false)
            ->assertDontSee('zoom:', false);

        $this->get('/sales/'.$sale->id.'/print?theme=full')
            ->assertOk()
            ->assertSee('size: A4', false)
            ->assertSee('Sales Receipt');
    }

    public function test_pos_thermal_credit_invoice_uses_invoice_labels_without_due_date(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Credit Thermal Customer', 'phone' => '0700000001', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Credit', 'is_active' => true]);
        $product = Product::create(['name' => 'Always Extra Long', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pieces',
            'selling_price' => 3000,
            'cost_price' => 2500,
            'is_active' => true,
        ]);
        $sale = Sale::create([
            'sale_no' => 'INV-THERMAL-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 12000,
            'total_amount' => 12000,
            'amount_paid' => 0,
            'balance_due' => 12000,
            'credit_due_date' => '2026-04-27',
            'status' => 'posted',
            'created_by' => auth()->id(),
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 4,
            'unit_price' => 3000,
            'line_total' => 12000,
        ]);

        $this->get('/sales/'.$sale->id.'/print')
            ->assertOk()
            ->assertSee('CREDIT SALE INVOICE')
            ->assertSee('Invoice No:')
            ->assertDontSee('Receipt No:')
            ->assertSee('INV-THERMAL-1')
            ->assertSee('APPLES OF GOLD')
            ->assertSee('WHOLESALE')
            ->assertSee('Mutungo Biina - Kiduuka Stage')
            ->assertSee('Served By:')
            ->assertDontSee('Salesperson:')
            ->assertDontSee('Cashier:')
            ->assertDontSee('Time:')
            ->assertSee('Customer:')
            ->assertSee('Credit Thermal Customer')
            ->assertSee('Always Extra Long')
            ->assertSee('Pieces')
            ->assertSee('4 x 3000')
            ->assertSee('12000')
            ->assertSee('Total')
            ->assertSee('Paid')
            ->assertSee('Balance')
            ->assertDontSee('Due Date')
            ->assertDontSee('27 Apr 2026')
            ->assertDontSee('Your business address')
            ->assertDontSee('+256700000000')
            ->assertDontSee('Subtotal')
            ->assertDontSee('PAYMENT')
            ->assertDontSee('Payment</div>', false)
            ->assertDontSee('Please settle outstanding balances')
            ->assertSee('Thank you for your business.');
    }

    public function test_pos_thermal_receipt_hides_routine_customer_names(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        foreach (['Walk-in Customer', 'Unknown Customer'] as $index => $customerName) {
            $customer = Customer::create([
                'name' => $customerName,
                'is_walk_in' => $customerName === 'Walk-in Customer',
                'is_system' => true,
                'is_active' => true,
            ]);
            $sale = Sale::create([
                'sale_no' => 'RCPT-ROUTINE-'.$index,
                'sale_date' => '2026-03-25',
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'sale_type' => 'cash',
                'payment_mode_id' => $paymentMode->id,
                'subtotal' => 1000,
                'total_amount' => 1000,
                'amount_paid' => 1000,
                'balance_due' => 0,
                'status' => 'posted',
            ]);

            $this->get('/sales/'.$sale->id.'/print')
                ->assertOk()
                ->assertSee('CASH SALE RECEIPT')
                ->assertDontSee('Customer:')
                ->assertDontSee($customerName);
        }
    }

    public function test_purchase_posting_creates_purchase_items_and_inventory_transaction(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Acme Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Rice', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bag',
            'selling_price' => 120000,
            'cost_price' => 100000,
            'is_active' => true,
        ]);

        $response = $this->post('/purchases', [
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 3, 'unit_cost' => '100,000'],
            ],
        ]);

        $purchase = Purchase::query()->firstOrFail();
        $response->assertRedirect('/purchases/'.$purchase->id);
        $response->assertSessionMissing('auto_print_document');

        $this->assertSame('Business Cash / Shop Cash', $purchase->fundingSource?->name);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_items', 1);
        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'product_unit_id' => $unit->id,
            'unit_cost' => 100000,
            'line_total' => 300000,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase',
            'movement_type' => 'purchase',
            'product_unit_id' => $unit->id,
            'quantity_in' => 3,
        ]);

        $this->get('/purchases/'.$purchase->id)
            ->assertOk()
            ->assertSee('Money Source')
            ->assertSee('Business Cash / Shop Cash');

        $this->get('/purchases?q=Business+Cash')
            ->assertOk()
            ->assertSee($purchase->purchase_no)
            ->assertSee('Business Cash / Shop Cash');
    }

    public function test_purchase_unit_cost_rejects_invalid_money_text(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Invalid Money Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Invalid Money Rice', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bag',
            'selling_price' => 120000,
            'cost_price' => 100000,
            'is_active' => true,
        ]);

        $this->from('/purchases/create')->post('/purchases', [
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 3, 'unit_cost' => '1,2,3'],
            ],
        ])
            ->assertRedirect('/purchases/create')
            ->assertSessionHasErrors(['items.0.unit_cost']);

        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('purchase_items', 0);
    }

    public function test_paid_purchase_requires_money_source_and_preserves_selected_items(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Money Source Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Money Source Rice', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bag',
            'selling_price' => 120000,
            'cost_price' => 100000,
            'is_active' => true,
        ]);

        $this->from('/purchases/create')->post('/purchases', [
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 100000,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => 100000],
            ],
        ])
            ->assertRedirect('/purchases/create')
            ->assertSessionHasErrors([
                'purchase_funding_source_id' => 'Please select where the purchase money came from.',
            ]);

        $this->assertDatabaseCount('purchases', 0);

        $this->get('/purchases/create')
            ->assertOk()
            ->assertSee('Money Source')
            ->assertSee('data-money-input', false)
            ->assertSee('purchase-funding-required', false)
            ->assertSee('For unpaid credit purchases, money source will be Supplier Credit / Not Paid Yet.')
            ->assertSee('Select the cash, mobile money, bank, owner, loan, or other real source used to pay.')
            ->assertSee('Money Source Rice - Bag');
    }

    public function test_unpaid_credit_purchase_defaults_to_supplier_credit_source(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Credit Source Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Credit Source Rice', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bag',
            'selling_price' => 120000,
            'cost_price' => 100000,
            'is_active' => true,
        ]);

        $response = $this->post('/purchases', [
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 0,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => 100000],
            ],
        ]);

        $purchase = Purchase::query()->firstOrFail();
        $response->assertRedirect('/purchases/'.$purchase->id);
        $this->assertSame('Supplier Credit / Not Paid Yet', $purchase->fundingSource?->name);
    }

    public function test_credit_purchase_with_partial_payment_requires_actual_money_source(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Partial Credit Source Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Partial Credit Rice', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bag',
            'selling_price' => 120000,
            'cost_price' => 100000,
            'is_active' => true,
        ]);

        $this->from('/purchases/create')->post('/purchases', [
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 25000,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => 100000],
            ],
        ])
            ->assertRedirect('/purchases/create')
            ->assertSessionHasErrors([
                'purchase_funding_source_id' => 'Please select where the purchase money came from.',
            ]);

        $this->assertDatabaseCount('purchases', 0);

        $response = $this->post('/purchases', [
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'amount_paid' => 25000,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => 100000],
            ],
        ]);

        $purchase = Purchase::query()->firstOrFail();
        $response->assertRedirect('/purchases/'.$purchase->id);
        $this->assertSame('credit', $purchase->purchase_type);
        $this->assertEquals(25000.0, (float) $purchase->amount_paid);
        $this->assertEquals(75000.0, (float) $purchase->balance_due);
        $this->assertSame('Business Cash / Shop Cash', $purchase->fundingSource?->name);
    }

    public function test_paid_purchase_cannot_use_supplier_credit_as_money_source(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Wrong Source Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Wrong Source Rice', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bag',
            'selling_price' => 120000,
            'cost_price' => 100000,
            'is_active' => true,
        ]);

        $this->from('/purchases/create')->post('/purchases', [
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->supplierCreditFundingSource()->id,
            'amount_paid' => 100000,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => 100000],
            ],
        ])
            ->assertRedirect('/purchases/create')
            ->assertSessionHasErrors([
                'purchase_funding_source_id' => 'Paid purchases must use an actual money source, not Supplier Credit / Not Paid Yet.',
            ]);

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_supplier_payment_reduces_credit_purchase_balance(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Acme Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $purchase = \App\Models\Purchase::create([
            'purchase_no' => 'CP-TEST-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 800,
            'total_amount' => 800,
            'amount_paid' => 0,
            'balance_due' => 800,
            'status' => 'posted',
        ]);

        $response = $this->post('/supplier-payments', [
            'payment_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 500,
        ]);

        $payment = \App\Models\SupplierPayment::query()->firstOrFail();
        $response->assertRedirect('/supplier-payments/'.$payment->id);
        $response->assertSessionMissing('auto_print_receipt');

        $purchase->refresh();

        $this->assertEquals(500.0, (float) $purchase->amount_paid);
        $this->assertEquals(300.0, (float) $purchase->balance_due);
        $this->assertDatabaseCount('supplier_payments', 1);
    }

    public function test_supplier_payments_explain_cash_purchases_and_link_to_outstanding_purchases(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Statement Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        Purchase::create([
            'purchase_no' => 'PUR-CASH-CLEARED',
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 5000,
            'total_amount' => 5000,
            'amount_paid' => 5000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        Purchase::create([
            'purchase_no' => 'PUR-CREDIT-OPEN',
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 8000,
            'total_amount' => 8000,
            'amount_paid' => 2000,
            'balance_due' => 6000,
            'status' => 'posted',
        ]);

        Purchase::create([
            'purchase_no' => 'PUR-CREDIT-CLEARED',
            'purchase_date' => '2026-03-25',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 4000,
            'total_amount' => 4000,
            'amount_paid' => 4000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $this->get('/supplier-payments')
            ->assertOk()
            ->assertSee('This page shows supplier payments recorded after credit purchases. Cash purchases paid during purchase entry are shown under Purchases.')
            ->assertSee('Outstanding Purchases')
            ->assertSee('/purchases?balance=outstanding', false);

        $this->get('/purchases')
            ->assertOk()
            ->assertSee('PUR-CASH-CLEARED')
            ->assertSee('PUR-CREDIT-OPEN')
            ->assertSee('PUR-CREDIT-CLEARED');

        $this->get('/purchases?balance=outstanding')
            ->assertOk()
            ->assertSee('PUR-CREDIT-OPEN')
            ->assertDontSee('PUR-CASH-CLEARED')
            ->assertDontSee('PUR-CREDIT-CLEARED');
    }

    public function test_supplier_payment_create_can_preselect_purchase_from_purchase_row(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Acme Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $purchase = Purchase::create([
            'purchase_no' => 'CP-PRESELECT-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1200,
            'total_amount' => 1200,
            'amount_paid' => 200,
            'balance_due' => 1000,
            'status' => 'posted',
        ]);

        $this->get('/purchases')
            ->assertOk()
            ->assertSee('purchase_id='.$purchase->id, false);

        $this->get('/supplier-payments/create?supplier_id='.$supplier->id.'&purchase_id='.$purchase->id)
            ->assertOk()
            ->assertSee('Record Supplier Payment')
            ->assertSee('Acme Supplier')
            ->assertSee('CP-PRESELECT-1')
            ->assertSee('Outstanding')
            ->assertSee('1,000');
    }

    public function test_full_supplier_payment_clears_purchase_balance(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Full Pay Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $purchase = Purchase::create([
            'purchase_no' => 'CP-FULL-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 900,
            'total_amount' => 900,
            'amount_paid' => 0,
            'balance_due' => 900,
            'status' => 'posted',
        ]);

        $this->post('/supplier-payments', [
            'payment_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 900,
        ])->assertRedirect();

        $purchase->refresh();

        $this->assertEquals(900.0, (float) $purchase->amount_paid);
        $this->assertEquals(0.0, (float) $purchase->balance_due);
    }

    public function test_supplier_payment_rejects_overpayment_and_requires_payment_mode(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Validation Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $purchase = Purchase::create([
            'purchase_no' => 'CP-VALIDATE-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 700,
            'total_amount' => 700,
            'amount_paid' => 0,
            'balance_due' => 700,
            'status' => 'posted',
        ]);

        $this->post('/supplier-payments', [
            'payment_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 701,
        ])->assertSessionHasErrors('amount');

        $this->post('/supplier-payments', [
            'payment_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'amount' => 100,
        ])->assertSessionHasErrors('payment_mode_id');

        $purchase->refresh();

        $this->assertEquals(700.0, (float) $purchase->balance_due);
        $this->assertDatabaseCount('supplier_payments', 0);
    }

    public function test_purchase_detail_and_statement_pages_load(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Statement Customer', 'opening_balance' => 50, 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Statement Supplier', 'opening_balance' => 80, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $sale = Sale::create([
            'sale_no' => 'CR-STMT-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 150,
            'total_amount' => 150,
            'amount_paid' => 0,
            'balance_due' => 150,
            'status' => 'posted',
        ]);

        \App\Models\Purchase::create([
            'purchase_no' => 'CP-STMT-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 200,
            'total_amount' => 200,
            'amount_paid' => 0,
            'balance_due' => 200,
            'status' => 'posted',
        ]);

        $purchase = \App\Models\Purchase::firstOrFail();

        $this->get('/purchases/'.$purchase->id)->assertOk()->assertSee($purchase->purchase_no);
        $this->get('/purchases/'.$purchase->id.'/print')->assertOk()->assertSee($purchase->purchase_no);
        $this->get('/customers/'.$customer->id.'/statement')->assertOk()->assertSee($customer->name)->assertSee($sale->sale_no);
        $this->get('/suppliers/'.$supplier->id.'/statement')->assertOk()->assertSee($supplier->name)->assertSee($purchase->purchase_no);
        $this->get('/customers/'.$customer->id.'/statement/print')->assertOk()->assertSee('Customer Statement');
        $this->get('/suppliers/'.$supplier->id.'/statement/print')->assertOk()->assertSee('Supplier Statement');
    }

    public function test_customer_and_product_profile_pages_load(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'Profile Customer',
            'phone' => '0700001234',
            'location' => 'Kampala',
            'opening_balance' => 100,
            'credit_limit' => 500,
            'is_active' => true,
        ]);
        $supplier = Supplier::create(['name' => 'Profile Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Profile Product',
            'code' => 'PRD-100',
            'supplier_id' => $supplier->id,
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Each',
            'selling_price' => 2000,
            'cost_price' => 1200,
            'is_pos_unit' => true,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_no' => 'INV-PROFILE-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 300,
            'total_amount' => 300,
            'amount_paid' => 100,
            'balance_due' => 200,
            'status' => 'posted',
        ]);

        \App\Models\CustomerPayment::create([
            'payment_no' => 'CPY-PROFILE-1',
            'payment_date' => '2026-03-25',
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 100,
            'status' => 'posted',
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'reference_no' => 'PUR-PROFILE-1',
            'movement_type' => 'purchase',
            'quantity_in' => 10,
            'quantity_out' => 0,
            'unit_cost' => 1200,
        ]);

        $this->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('Current Balance')
            ->assertSee('Latest Statement Entries');

        $this->get('/products/'.$product->id)
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Selling Unit Configuration')
            ->assertSee($unit->unit_name);
    }

    public function test_customer_and_product_can_be_maintained_from_browser(): void
    {
        $supplier = Supplier::create(['name' => 'Maintenance Supplier', 'is_active' => true]);
        $category = \App\Models\Category::create(['name' => 'Groceries', 'is_active' => true]);

        $this->post('/customers', [
            'name' => 'Browser Customer',
            'phone' => '0700111222',
            'email' => 'browser.customer@example.test',
            'location' => 'Kampala',
            'address' => 'Market Street',
            'customer_type' => 'Retail',
            'opening_balance' => 50,
            'credit_limit' => 1000,
            'allow_credit_sales' => 0,
            'is_active' => 1,
        ])->assertRedirect();

        $customer = Customer::query()->where('name', 'Browser Customer')->firstOrFail();

        $this->put('/customers/'.$customer->id, [
            'name' => 'Browser Customer Updated',
            'phone' => '0700111222',
            'email' => 'browser.customer@example.test',
            'location' => 'Kampala',
            'address' => 'Market Street',
            'customer_type' => 'Retail',
            'opening_balance' => 50,
            'credit_limit' => 1500,
            'allow_credit_sales' => 1,
            'is_active' => 1,
        ])->assertRedirect('/customers/'.$customer->id);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Browser Customer Updated',
            'credit_limit' => 1500,
            'allow_credit_sales' => true,
        ]);

        $this->post('/products', [
            'name' => 'Browser Product',
            'code' => 'BPR-001',
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'item_group' => 'Essentials',
            'base_cost_price' => 800,
            'reorder_level' => 4,
            'is_vat_applicable' => 0,
            'is_active' => 1,
            'default_unit_index' => 0,
            'units' => [
                [
                    'unit_name' => 'Each',
                    'conversion_factor' => 1,
                    'selling_price' => '1,200',
                    'cost_price' => '800',
                    'is_active' => 1,
                ],
                [
                    'unit_name' => 'Box',
                    'conversion_factor' => 12,
                    'selling_price' => '13,500',
                    'cost_price' => '9,200',
                    'is_active' => 1,
                ],
            ],
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Browser Product')->firstOrFail();
        $defaultUnit = ProductUnit::query()->where('product_id', $product->id)->where('unit_name', 'Each')->firstOrFail();

        $this->put('/products/'.$product->id, [
            'name' => 'Browser Product Updated',
            'code' => 'BPR-001',
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'item_group' => 'Essentials',
            'base_cost_price' => 900,
            'reorder_level' => 6,
            'is_vat_applicable' => 1,
            'is_active' => 1,
            'default_unit_index' => 1,
            'units' => [
                [
                    'id' => $defaultUnit->id,
                    'unit_name' => 'Each',
                    'conversion_factor' => 1,
                    'selling_price' => '1,500',
                    'cost_price' => '900',
                    'is_active' => 1,
                ],
                [
                    'unit_name' => 'Carton',
                    'conversion_factor' => 24,
                    'selling_price' => '28,000',
                    'cost_price' => '18,000',
                    'is_active' => 1,
                ],
            ],
        ])->assertRedirect('/products/'.$product->id);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Browser Product Updated',
            'reorder_level' => 6,
        ]);
        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_name' => 'Each',
            'selling_price' => 1500,
        ]);
        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_name' => 'Carton',
            'is_pos_unit' => true,
            'cost_price' => 18000,
            'selling_price' => 28000,
        ]);

        $this->get('/products/'.$product->id.'/edit?focus=units')
            ->assertOk()
            ->assertSee('value="28,000"', false)
            ->assertSee('value="18,000"', false)
            ->assertSee('data-money-input', false)
            ->assertSee('name="units[0][conversion_factor]" value="24"', false);
    }

    public function test_products_index_uses_server_backed_search_across_pagination(): void
    {
        $africaCategory = Category::create(['name' => 'Africa Foods', 'is_active' => true]);
        $generalCategory = Category::create(['name' => 'General Foods', 'is_active' => true]);
        $africaSupplier = Supplier::create(['name' => 'Africa Imports', 'is_active' => true]);
        $generalSupplier = Supplier::create(['name' => 'General Supplier', 'is_active' => true]);

        for ($index = 1; $index <= 25; $index++) {
            Product::create([
                'name' => sprintf('AAA Filler Product %02d', $index),
                'code' => sprintf('FILL-%02d', $index),
                'category_id' => $generalCategory->id,
                'supplier_id' => $generalSupplier->id,
                'is_active' => true,
            ]);
        }

        $nameMatch = Product::create([
            'name' => 'ZZZ Africa Maize Flour',
            'code' => 'MAIZE-ZZZ',
            'category_id' => $generalCategory->id,
            'supplier_id' => $generalSupplier->id,
            'is_active' => true,
        ]);
        $codeMatch = Product::create([
            'name' => 'Hidden Code Product',
            'code' => 'AFR-CODE-001',
            'category_id' => $generalCategory->id,
            'supplier_id' => $generalSupplier->id,
            'is_active' => true,
        ]);
        $categoryMatch = Product::create([
            'name' => 'AAB Hidden Millet Flour',
            'code' => 'HIDDEN-MILLET',
            'category_id' => $africaCategory->id,
            'supplier_id' => $generalSupplier->id,
            'is_active' => true,
        ]);
        $supplierMatch = Product::create([
            'name' => 'AAC Source Matooke Flour',
            'code' => 'SOURCE-MAT',
            'category_id' => $generalCategory->id,
            'supplier_id' => $africaSupplier->id,
            'is_active' => true,
        ]);
        Product::create([
            'name' => 'Africa Dormant Item',
            'code' => 'AFR-DORMANT',
            'category_id' => $africaCategory->id,
            'supplier_id' => $africaSupplier->id,
            'is_active' => false,
        ]);

        for ($index = 1; $index <= 22; $index++) {
            Product::create([
                'name' => sprintf('Africa Bulk Item %02d', $index),
                'code' => sprintf('AFR-BULK-%02d', $index),
                'category_id' => $africaCategory->id,
                'supplier_id' => $africaSupplier->id,
                'is_active' => true,
            ]);
        }

        $this->get('/products')
            ->assertOk()
            ->assertSee('data-server-live-search-form', false)
            ->assertSee('data-server-live-search-input', false)
            ->assertSee('data-server-live-search-results="#products-results"', false)
            ->assertSee('Searches all products, not only visible rows.')
            ->assertSee('AAA Filler Product 01')
            ->assertDontSee($nameMatch->name);

        $this->get('/products?q=AFRICA')
            ->assertOk()
            ->assertSee($categoryMatch->name)
            ->assertSee($supplierMatch->name)
            ->assertDontSee('AAA Filler Product 01')
            ->assertSee('q=AFRICA', false)
            ->assertSee('page=2', false);

        $this->get('/products?q=AFRICA&page=2')
            ->assertOk()
            ->assertSee($nameMatch->name);

        $this->get('/products?q=AFR-CODE-001')
            ->assertOk()
            ->assertSee($codeMatch->name)
            ->assertDontSee($nameMatch->name);

        $this->get('/products?q=AFRICA&category='.$africaCategory->id)
            ->assertOk()
            ->assertSee($categoryMatch->name)
            ->assertDontSee($nameMatch->name);

        $this->get('/products?q=AFRICA&supplier_id='.$africaSupplier->id)
            ->assertOk()
            ->assertSee($supplierMatch->name)
            ->assertDontSee($nameMatch->name);

        $this->get('/products?q=AFRICA&status=inactive')
            ->assertOk()
            ->assertSee('Africa Dormant Item')
            ->assertDontSee($nameMatch->name);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/products?q=AFRICA')
            ->assertOk()
            ->assertSee($categoryMatch->name)
            ->assertDontSee('data-server-live-search-form', false);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/products?q=AFRICA&page=2')
            ->assertOk()
            ->assertSee($nameMatch->name)
            ->assertDontSee('data-server-live-search-form', false);
    }

    public function test_customer_and_supplier_payment_detail_pages_load(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Detail Customer', 'phone' => '0700000001', 'location' => 'Kampala', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Detail Supplier', 'phone' => '0700000002', 'country' => 'Uganda', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $sale = Sale::create([
            'sale_no' => 'INV-PAY-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 500,
            'total_amount' => 500,
            'amount_paid' => 200,
            'balance_due' => 300,
            'status' => 'posted',
        ]);

        $purchase = Purchase::create([
            'purchase_no' => 'PUR-PAY-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 900,
            'total_amount' => 900,
            'amount_paid' => 400,
            'balance_due' => 500,
            'status' => 'posted',
        ]);

        $customerPayment = \App\Models\CustomerPayment::create([
            'payment_no' => 'CPY-20260325-0100',
            'payment_date' => '2026-03-25',
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 200,
            'status' => 'posted',
        ]);

        $supplierPayment = \App\Models\SupplierPayment::create([
            'payment_no' => 'SPY-20260325-0100',
            'payment_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 400,
            'status' => 'posted',
        ]);

        $this->get('/customer-payments/'.$customerPayment->id)->assertOk()->assertSee($customerPayment->payment_no);
        $this->get('/customer-payments/'.$customerPayment->id.'/print')->assertOk()->assertSee('Customer Payment Receipt');
        $this->get('/customer-payments/'.$customerPayment->id.'/print?theme=thermal')->assertOk()->assertSee($customerPayment->payment_no);
        $this->get('/supplier-payments/'.$supplierPayment->id)->assertOk()->assertSee($supplierPayment->payment_no);
        $this->get('/supplier-payments/'.$supplierPayment->id.'/print')->assertOk()->assertSee('Supplier Payment Voucher');
        $this->get('/supplier-payments/'.$supplierPayment->id.'/print?theme=thermal')->assertOk()->assertSee($supplierPayment->payment_no);
    }

    public function test_supplier_and_master_data_can_be_maintained_from_browser(): void
    {
        $this->post('/suppliers', [
            'name' => 'Browser Supplier',
            'phone' => '0700999888',
            'email' => 'supplier@example.test',
            'country' => 'Uganda',
            'address' => 'Industrial Area',
            'supplier_type' => 'Distributor',
            'payment_terms_days' => 21,
            'opening_balance' => 100,
            'is_active' => 1,
        ])->assertRedirect();

        $supplier = Supplier::query()->where('name', 'Browser Supplier')->firstOrFail();

        $this->put('/suppliers/'.$supplier->id, [
            'name' => 'Browser Supplier Updated',
            'phone' => '0700999888',
            'email' => 'supplier@example.test',
            'country' => 'Uganda',
            'address' => 'Industrial Area',
            'supplier_type' => 'Distributor',
            'payment_terms_days' => 30,
            'opening_balance' => 150,
            'is_active' => 1,
        ])->assertRedirect('/suppliers/'.$supplier->id);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Browser Supplier Updated',
            'payment_terms_days' => 30,
        ]);

        $this->post('/setup/stores', [
            'name' => 'Downtown Branch',
            'code' => 'DT-1',
            'location' => 'Kampala Road',
            'in_charge_name' => 'Jane',
            'is_active' => 1,
        ])->assertRedirect('/setup/stores');

        $store = Store::query()->where('name', 'Downtown Branch')->firstOrFail();

        $this->put('/setup/stores/'.$store->id, [
            'name' => 'Downtown Branch Updated',
            'code' => 'DT-1',
            'location' => 'Kampala Road',
            'in_charge_name' => 'Jane',
            'is_active' => 1,
        ])->assertRedirect('/setup/stores');

        $this->post('/setup/payment-modes', [
            'name' => 'Mobile Money',
            'account_no' => 'MM-001',
            'is_active' => 1,
        ])->assertRedirect('/setup/payment-modes');

        $this->post('/setup/capital-sources', [
            'name' => 'Business Savings',
            'source_type' => 'business',
            'description' => 'Saved trading cash',
            'is_active' => 1,
        ])->assertRedirect('/setup/capital-sources');

        $this->post('/setup/expense-categories', [
            'name' => 'Transport',
            'description' => 'Fuel, boda, and delivery movement costs',
            'is_active' => 1,
        ])->assertRedirect('/setup/expense-categories');

        $this->assertDatabaseHas('stores', ['name' => 'Downtown Branch Updated']);
        $this->assertDatabaseHas('payment_modes', ['name' => 'Mobile Money']);
        $this->assertDatabaseHas('capital_sources', ['name' => 'Business Savings']);
        $this->assertDatabaseHas('expense_categories', ['name' => 'Transport']);
    }

    public function test_setup_pages_are_available_to_admin_and_blocked_for_cashier(): void
    {
        foreach (['stores', 'categories', 'payment-modes', 'capital-sources', 'expense-categories'] as $resource) {
            $this->get('/setup/'.$resource)->assertOk();
        }

        $this->get('/setup/categories')->assertOk()->assertSee('Categories');

        $this->signInAsRole('cashier');

        foreach (['stores', 'categories', 'payment-modes', 'capital-sources', 'expense-categories'] as $resource) {
            $this->get('/setup/'.$resource)->assertForbidden();
        }
    }

    public function test_admin_can_quick_add_product_category_and_use_it_on_product(): void
    {
        $response = $this->postJson('/products/categories/quick-store', [
            'name' => 'Household',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('category.name', 'Household');

        $categoryId = $response->json('category.id');

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'name' => 'Household',
            'is_active' => true,
        ]);

        $this->postJson('/products/categories/quick-store', [
            'name' => 'Household',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->post('/products', [
            'name' => 'Laundry Soap',
            'code' => 'SOAP-1',
            'category_id' => $categoryId,
            'supplier_id' => null,
            'item_group' => 'Cleaning',
            'base_cost_price' => 1200,
            'reorder_level' => 5,
            'is_vat_applicable' => 0,
            'is_active' => 1,
            'units' => [
                [
                    'unit_name' => 'Bar',
                    'conversion_factor' => 1,
                    'selling_price' => 1800,
                    'cost_price' => 1200,
                    'is_active' => 1,
                ],
            ],
            'default_unit_index' => 0,
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Laundry Soap')->firstOrFail();

        $this->assertSame($categoryId, $product->category_id);
    }

    public function test_admin_can_quick_add_expense_category_and_use_it_on_expense(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $response = $this->postJson('/expenses/categories/quick-store', [
            'name' => 'Utilities',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('category.name', 'Utilities');

        $categoryId = $response->json('category.id');

        $this->assertDatabaseHas('expense_categories', [
            'id' => $categoryId,
            'name' => 'Utilities',
            'is_active' => true,
        ]);

        $this->postJson('/expenses/categories/quick-store', [
            'name' => 'Utilities',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->post('/expenses', [
            'expense_date' => '2026-04-18',
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'expense_category_id' => $categoryId,
            'amount' => 25000,
            'reference_no' => 'UMEME-1',
            'notes' => 'Power bill',
        ])->assertRedirect();

        $expense = Expense::query()->where('reference_no', 'UMEME-1')->firstOrFail();

        $this->assertSame($categoryId, $expense->expense_category_id);
        $this->assertSame('Utilities', $expense->category);
    }

    public function test_cashier_cannot_quick_add_categories(): void
    {
        $this->signInAsRole('cashier');

        $this->postJson('/products/categories/quick-store', [
            'name' => 'Blocked Product Category',
        ])->assertForbidden();

        $this->postJson('/expenses/categories/quick-store', [
            'name' => 'Blocked Expense Category',
        ])->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Blocked Product Category']);
        $this->assertDatabaseMissing('expense_categories', ['name' => 'Blocked Expense Category']);
    }

    public function test_quick_add_category_buttons_are_hidden_without_setup_permission(): void
    {
        $this->get('/products/create')->assertOk()->assertSee('+ New');
        $this->get('/expenses/create')->assertOk()->assertSee('+ Add Category');

        $this->signInAsRole('manager');

        $this->get('/products/create')->assertOk()->assertDontSee('+ New');
        $this->get('/expenses/create')->assertOk()->assertDontSee('+ Add Category');
    }

    public function test_sales_purchases_and_accounts_can_be_archived_or_voided_safely(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Archive Customer', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Archive Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Archive Product', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Each',
            'selling_price' => 1000,
            'cost_price' => 700,
            'is_active' => true,
            'is_pos_unit' => true,
        ]);

        $sale = Sale::create([
            'sale_no' => 'INV-VOID-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 2000,
            'total_amount' => 2000,
            'amount_paid' => 2000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'selling_price_snapshot' => 1000,
            'cost_price_snapshot' => 700,
            'line_total' => 2000,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'sale',
            'reference_id' => $saleItem->id,
            'reference_no' => $sale->sale_no,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 2,
            'unit_cost' => 700,
            'unit_price' => 1000,
        ]);

        $purchase = Purchase::create([
            'purchase_no' => 'PUR-VOID-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 3000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $purchaseItem = $purchase->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 3,
            'unit_cost' => 700,
            'line_total' => 2100,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseItem->id,
            'reference_no' => $purchase->purchase_no,
            'movement_type' => 'purchase',
            'quantity_in' => 3,
            'quantity_out' => 0,
            'unit_cost' => 700,
        ]);

        $this->post('/customers/'.$customer->id.'/status', ['is_active' => 0])->assertRedirect('/customers');
        $this->post('/suppliers/'.$supplier->id.'/status', ['is_active' => 0])->assertRedirect('/suppliers');
        $this->post('/products/'.$product->id.'/status', ['is_active' => 0])->assertRedirect('/products');

        $this->post('/sales/'.$sale->id.'/void', ['void_reason' => 'Entered by mistake'])->assertRedirect('/sales/'.$sale->id);
        $this->post('/purchases/'.$purchase->id.'/void', ['void_reason' => 'Entered by mistake'])->assertRedirect('/purchases/'.$purchase->id);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => false]);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_active' => false]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'void']);
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'status' => 'void']);
        $this->assertDatabaseHas('inventory_transactions', ['reference_type' => 'sale_void', 'reference_no' => $sale->sale_no]);
        $this->assertDatabaseHas('inventory_transactions', ['reference_type' => 'purchase_void', 'reference_no' => $purchase->purchase_no]);
    }

    public function test_sale_returns_reduce_customer_balance_and_restore_stock(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Return Customer', 'opening_balance' => 50, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Milk', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Packet',
            'selling_price' => 2500,
            'cost_price' => 1800,
            'is_active' => true,
            'is_pos_unit' => true,
        ]);

        $sale = Sale::create([
            'sale_no' => 'INV-RETURN-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 5000,
            'total_amount' => 5000,
            'amount_paid' => 0,
            'balance_due' => 5000,
            'status' => 'posted',
        ]);

        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 2,
            'unit_price' => 2500,
            'selling_price_snapshot' => 2500,
            'cost_price_snapshot' => 1800,
            'line_total' => 5000,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'sale',
            'reference_id' => $saleItem->id,
            'reference_no' => $sale->sale_no,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 2,
            'unit_cost' => 1800,
            'unit_price' => 2500,
        ]);

        $response = $this->post('/sales/'.$sale->id.'/returns', [
            'return_date' => '2026-03-26',
            'return_type' => 'credit_note',
            'payment_mode_id' => $paymentMode->id,
            'remarks' => 'Customer brought one packet back',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 1],
            ],
        ]);

        $saleReturn = \App\Models\SaleReturn::query()->firstOrFail();

        $response->assertRedirect('/sale-returns/'.$saleReturn->id);
        $response->assertSessionMissing('auto_print_document');

        $sale->refresh();

        $this->assertEquals(2500.0, (float) $sale->balance_due);
        $this->assertEquals(2500.0, (float) $saleReturn->returned_total);
        $this->assertEquals(0.0, (float) $saleReturn->refund_amount);
        $this->assertEquals(0.0, (float) $saleReturn->store_credit_amount);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'sale_return',
            'reference_no' => $saleReturn->return_no,
            'movement_type' => 'sale_return',
            'product_unit_id' => $unit->id,
            'quantity_in' => 1,
        ]);

        $this->get('/customers/'.$customer->id.'/statement')
            ->assertOk()
            ->assertSee('Sale Return')
            ->assertSee($saleReturn->return_no)
            ->assertSee('2,500');
    }

    public function test_sale_return_create_page_shows_guided_settlement_preview(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Guided Return Customer', 'is_active' => true]);
        PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Butter', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Tub',
            'selling_price' => 6500,
            'cost_price' => 5000,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_no' => 'INV-GUIDED-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'subtotal' => 6500,
            'total_amount' => 6500,
            'amount_paid' => 0,
            'balance_due' => 6500,
            'status' => 'posted',
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'unit_price' => 6500,
            'selling_price_snapshot' => 6500,
            'cost_price_snapshot' => 5000,
            'line_total' => 6500,
        ]);

        $this->get('/sales/'.$sale->id.'/returns/create')
            ->assertOk()
            ->assertSee('Settlement Preview')
            ->assertSee('Refund')
            ->assertSee('Credit Note')
            ->assertSee('Exchange')
            ->assertSee('Outstanding Reduced')
            ->assertSee('Store Credit / Exchange Value');
    }

    public function test_cashier_refund_return_requires_admin_approval_pin_when_money_goes_out(): void
    {
        config(['business.admin_approval_pin' => '2468']);

        $this->signInAsRole('cashier');

        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Refund Customer', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Yoghurt', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Cup',
            'selling_price' => 3000,
            'cost_price' => 1800,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_no' => 'INV-REFUND-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 3000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'unit_price' => 3000,
            'selling_price_snapshot' => 3000,
            'cost_price_snapshot' => 1800,
            'line_total' => 3000,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'sale',
            'reference_id' => $saleItem->id,
            'reference_no' => $sale->sale_no,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 1,
            'unit_cost' => 1800,
            'unit_price' => 3000,
        ]);

        $this->from('/sales/'.$sale->id.'/returns/create')
            ->post('/sales/'.$sale->id.'/returns', [
                'return_date' => '2026-03-26',
                'return_type' => 'refund',
                'payment_mode_id' => $paymentMode->id,
                'remarks' => 'Customer requested cash back',
                'items' => [
                    ['sale_item_id' => $saleItem->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect('/sales/'.$sale->id.'/returns/create')
            ->assertSessionHasErrors('approval_pin');

        $this->assertDatabaseCount('sale_returns', 0);

        $response = $this->post('/sales/'.$sale->id.'/returns', [
            'return_date' => '2026-03-26',
            'return_type' => 'refund',
            'payment_mode_id' => $paymentMode->id,
            'approval_pin' => '2468',
            'remarks' => 'Customer requested cash back',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 1],
            ],
        ]);

        $saleReturn = \App\Models\SaleReturn::query()->firstOrFail();

        $response->assertRedirect('/sale-returns/'.$saleReturn->id);
        $this->assertDatabaseHas('sale_returns', [
            'id' => $saleReturn->id,
            'refund_amount' => 3000,
            'payment_mode_id' => $paymentMode->id,
        ]);
    }

    public function test_refund_return_requires_payment_mode_when_cash_is_paid_out(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Refund Mode Customer', 'is_active' => true]);
        PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Sugar', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'selling_price' => 5000,
            'cost_price' => 3500,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_no' => 'INV-REFUND-2',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'subtotal' => 5000,
            'total_amount' => 5000,
            'amount_paid' => 5000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'selling_price_snapshot' => 5000,
            'cost_price_snapshot' => 3500,
            'line_total' => 5000,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'sale',
            'reference_id' => $saleItem->id,
            'reference_no' => $sale->sale_no,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 1,
            'unit_cost' => 3500,
            'unit_price' => 5000,
        ]);

        $this->post('/sales/'.$sale->id.'/returns', [
            'return_date' => '2026-03-26',
            'return_type' => 'refund',
            'remarks' => 'Payment mode missing',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('payment_mode_id');
    }

    public function test_exchange_returns_can_open_a_prefilled_replacement_sale_and_link_back(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Exchange Customer', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Juice', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bottle',
            'selling_price' => 3500,
            'cost_price' => 2200,
            'barcode' => '12345001',
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 4);

        $sale = Sale::create([
            'sale_no' => 'INV-EX-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 3500,
            'total_amount' => 3500,
            'amount_paid' => 3500,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'unit_price' => 3500,
            'selling_price_snapshot' => 3500,
            'cost_price_snapshot' => 2200,
            'line_total' => 3500,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'sale',
            'reference_id' => $saleItem->id,
            'reference_no' => $sale->sale_no,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 1,
            'unit_cost' => 2200,
            'unit_price' => 3500,
        ]);

        $returnResponse = $this->post('/sales/'.$sale->id.'/returns', [
            'return_date' => '2026-03-26',
            'return_type' => 'exchange',
            'remarks' => 'Exchange for damaged bottle',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 1],
            ],
        ]);

        $saleReturn = \App\Models\SaleReturn::query()->firstOrFail();
        $returnResponse->assertRedirect('/sale-returns/'.$saleReturn->id);

        $this->get('/sale-returns/'.$saleReturn->id)
            ->assertOk()
            ->assertSee('Replacement Sale Still Needed')
            ->assertSee('Start Replacement Sale');

        $this->get('/sales/create?exchange_return_id='.$saleReturn->id)
            ->assertOk()
            ->assertSee('Replacement for '.$saleReturn->return_no)
            ->assertSee('Exchange return')
            ->assertSee('Juice - Bottle')
            ->assertSee('Scan / barcode / code');

        $replacementResponse = $this->post('/sales', [
            'sale_date' => '2026-03-26',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 3500,
            'exchange_return_id' => $saleReturn->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 3500],
            ],
        ]);

        $replacementSale = Sale::query()->where('id', '!=', $sale->id)->latest('id')->firstOrFail();

        $replacementResponse->assertRedirect('/sales/'.$replacementSale->id);
        $this->assertDatabaseHas('sale_returns', [
            'id' => $saleReturn->id,
            'replacement_sale_id' => $replacementSale->id,
        ]);

        $this->get('/sale-returns/'.$saleReturn->id)
            ->assertOk()
            ->assertSee('Open Replacement Sale')
            ->assertSee($replacementSale->sale_no);
    }

    public function test_purchase_returns_reduce_supplier_balance_and_reverse_stock_out(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Return Supplier', 'opening_balance' => 30, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Soap', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bar',
            'selling_price' => 2000,
            'cost_price' => 1200,
            'is_active' => true,
        ]);

        $purchase = Purchase::create([
            'purchase_no' => 'PUR-RETURN-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 3600,
            'total_amount' => 3600,
            'amount_paid' => 0,
            'balance_due' => 3600,
            'status' => 'posted',
        ]);

        $purchaseItem = $purchase->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 3,
            'unit_cost' => 1200,
            'line_total' => 3600,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseItem->id,
            'reference_no' => $purchase->purchase_no,
            'movement_type' => 'purchase',
            'quantity_in' => 3,
            'quantity_out' => 0,
            'unit_cost' => 1200,
        ]);

        $response = $this->post('/purchases/'.$purchase->id.'/returns', [
            'return_date' => '2026-03-26',
            'return_type' => 'supplier_credit',
            'payment_mode_id' => $paymentMode->id,
            'remarks' => 'Damaged bars returned',
            'items' => [
                ['purchase_item_id' => $purchaseItem->id, 'quantity' => 1],
            ],
        ]);

        $purchaseReturn = \App\Models\PurchaseReturn::query()->firstOrFail();

        $response->assertRedirect('/purchase-returns/'.$purchaseReturn->id);
        $response->assertSessionMissing('auto_print_document');

        $purchase->refresh();

        $this->assertEquals(2400.0, (float) $purchase->balance_due);
        $this->assertEquals(1200.0, (float) $purchaseReturn->returned_total);
        $this->assertEquals(0.0, (float) $purchaseReturn->refund_amount);
        $this->assertEquals(0.0, (float) $purchaseReturn->supplier_credit_amount);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase_return',
            'reference_no' => $purchaseReturn->return_no,
            'movement_type' => 'purchase_return',
            'product_unit_id' => $unit->id,
            'quantity_out' => 1,
        ]);

        $this->get('/suppliers/'.$supplier->id.'/statement')
            ->assertOk()
            ->assertSee('Supplier Return')
            ->assertSee($purchaseReturn->return_no)
            ->assertSee('1,200');
    }

    public function test_sales_and_purchases_can_be_corrected_by_reposting_with_linked_audit_trail(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Correction Customer', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Correction Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Correction Product', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Each',
            'selling_price' => 1000,
            'cost_price' => 650,
            'is_active' => true,
            'is_pos_unit' => true,
        ]);
        $this->seedStock($store, $unit, 2);

        $sale = Sale::create([
            'sale_no' => 'INV-CORR-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'amount_paid' => 1000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'selling_price_snapshot' => 1000,
            'cost_price_snapshot' => 650,
            'line_total' => 1000,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'sale',
            'reference_id' => $saleItem->id,
            'reference_no' => $sale->sale_no,
            'movement_type' => 'sale',
            'quantity_in' => 0,
            'quantity_out' => 1,
            'unit_cost' => 650,
            'unit_price' => 1000,
        ]);

        $purchase = Purchase::create([
            'purchase_no' => 'PUR-CORR-1',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1200,
            'total_amount' => 1200,
            'amount_paid' => 1200,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $purchaseItem = $purchase->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 1,
            'unit_cost' => 1200,
            'line_total' => 1200,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseItem->id,
            'reference_no' => $purchase->purchase_no,
            'movement_type' => 'purchase',
            'quantity_in' => 1,
            'quantity_out' => 0,
            'unit_cost' => 1200,
        ]);

        $saleResponse = $this->post('/sales', [
            'sale_date' => '2026-03-26',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 2000,
            'corrected_from_sale_id' => $sale->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 1000],
            ],
        ]);

        $correctedSale = Sale::query()
            ->whereNotNull('corrected_from_sale_id')
            ->firstOrFail();

        $saleResponse->assertRedirect('/sales/'.$correctedSale->id);

        $sale->refresh();
        $correctedSale->refresh();

        $this->assertSame('void', $sale->status);
        $this->assertSame($correctedSale->id, $sale->replaced_by_sale_id);
        $this->assertSame($sale->id, $correctedSale->corrected_from_sale_id);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'sale_void',
            'reference_no' => $sale->sale_no,
        ]);

        $purchaseResponse = $this->post('/purchases', [
            'purchase_date' => '2026-03-26',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'amount_paid' => 1500,
            'corrected_from_purchase_id' => $purchase->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => 1500],
            ],
        ]);

        $correctedPurchase = Purchase::query()
            ->whereNotNull('corrected_from_purchase_id')
            ->firstOrFail();

        $purchaseResponse->assertRedirect('/purchases/'.$correctedPurchase->id);

        $purchase->refresh();
        $correctedPurchase->refresh();

        $this->assertSame('void', $purchase->status);
        $this->assertSame($correctedPurchase->id, $purchase->replaced_by_purchase_id);
        $this->assertSame($purchase->id, $correctedPurchase->corrected_from_purchase_id);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase_void',
            'reference_no' => $purchase->purchase_no,
        ]);
    }

    public function test_admin_can_open_purchase_correction_form_prefilled_with_original_items(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Early Save Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'BREAD IMPROVER', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Cartons',
            'conversion_factor' => 24,
            'selling_price' => 82000,
            'cost_price' => 64000,
            'is_active' => true,
        ]);

        $this->post('/purchases', [
            'purchase_date' => '2026-07-28',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'amount_paid' => 128000,
            'supplier_invoice_no' => 'INV-OLD-1',
            'remarks' => 'Saved before all items were entered',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2, 'unit_cost' => 64000],
            ],
        ])->assertRedirect();

        $purchase = Purchase::query()->firstOrFail();

        $this->get('/purchases/'.$purchase->id)
            ->assertOk()
            ->assertSee('Correct / Edit Purchase')
            ->assertSee('Saved too early or entered wrong? Use Correct / Edit Purchase to add, remove, or change items safely.');

        $this->followingRedirects()
            ->get('/purchases/'.$purchase->id.'/correct')
            ->assertOk()
            ->assertSee('Purchase correction mode')
            ->assertSee("You are correcting purchase {$purchase->purchase_no}. Posting will replace the old purchase with a corrected one.")
            ->assertSee('BREAD IMPROVER - Cartons')
            ->assertSee('INV-OLD-1')
            ->assertSee('Post Corrected Purchase');
    }

    public function test_purchase_correction_can_add_remove_and_edit_items_without_double_counting_stock_or_supplier_balance(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Correction Supplier Two', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $crisps = Product::create(['name' => 'GONJA CRISPS', 'base_unit_label' => 'pieces', 'is_active' => true]);
        $crispsCarton = ProductUnit::create([
            'product_id' => $crisps->id,
            'unit_name' => 'Carton',
            'conversion_factor' => 24,
            'selling_price' => 24000,
            'cost_price' => 100,
            'is_active' => true,
        ]);
        $soap = Product::create(['name' => 'WRONG SOAP', 'base_unit_label' => 'pieces', 'is_active' => true]);
        $soapPiece = ProductUnit::create([
            'product_id' => $soap->id,
            'unit_name' => 'Piece',
            'conversion_factor' => 1,
            'selling_price' => 1000,
            'cost_price' => 50,
            'is_active' => true,
        ]);
        $rice = Product::create(['name' => 'RICE', 'base_unit_label' => 'kg', 'is_active' => true]);
        $riceKg = ProductUnit::create([
            'product_id' => $rice->id,
            'unit_name' => 'Kg',
            'conversion_factor' => 1,
            'selling_price' => 4000,
            'cost_price' => 25,
            'is_active' => true,
        ]);

        $this->post('/purchases', [
            'purchase_date' => '2026-07-28',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 0,
            'items' => [
                ['product_unit_id' => $crispsCarton->id, 'quantity' => 1, 'unit_cost' => 100],
                ['product_unit_id' => $soapPiece->id, 'quantity' => 3, 'unit_cost' => 50],
            ],
        ])->assertRedirect();

        $originalPurchase = Purchase::query()->firstOrFail();

        $this->post('/purchases', [
            'purchase_date' => '2026-07-29',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'amount_paid' => 100,
            'corrected_from_purchase_id' => $originalPurchase->id,
            'items' => [
                ['product_unit_id' => $crispsCarton->id, 'quantity' => 2, 'unit_cost' => 120],
                ['product_unit_id' => $riceKg->id, 'quantity' => 4, 'unit_cost' => 25],
            ],
        ])->assertRedirect();

        $replacement = Purchase::query()->whereNotNull('corrected_from_purchase_id')->firstOrFail();
        $originalPurchase->refresh();
        $replacement->refresh();

        $this->assertSame('void', $originalPurchase->status);
        $this->assertSame(0.0, (float) $originalPurchase->balance_due);
        $this->assertSame($replacement->id, $originalPurchase->replaced_by_purchase_id);
        $this->assertSame($originalPurchase->id, $replacement->corrected_from_purchase_id);
        $this->assertEquals(340.0, (float) $replacement->total_amount);
        $this->assertEquals(240.0, (float) $replacement->balance_due);
        $this->assertDatabaseMissing('purchase_items', [
            'purchase_id' => $replacement->id,
            'product_id' => $soap->id,
        ]);
        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $replacement->id,
            'product_unit_id' => $crispsCarton->id,
            'quantity' => 2,
            'unit_cost' => 120,
        ]);
        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $replacement->id,
            'product_unit_id' => $riceKg->id,
            'quantity' => 4,
            'unit_cost' => 25,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase_void',
            'reference_no' => $originalPurchase->purchase_no,
            'product_id' => $crisps->id,
            'base_quantity_out' => 24,
        ]);

        $stockFor = fn (Product $product): float => (float) InventoryTransaction::query()
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->selectRaw('COALESCE(SUM(base_quantity_in), 0) - COALESCE(SUM(base_quantity_out), 0) as balance')
            ->value('balance');

        $this->assertEquals(48.0, $stockFor($crisps));
        $this->assertEquals(0.0, $stockFor($soap));
        $this->assertEquals(4.0, $stockFor($rice));
        $this->assertEquals(240.0, (float) Purchase::query()->posted()->where('supplier_id', $supplier->id)->sum('balance_due'));
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'purchase.corrected',
        ]);
    }

    public function test_non_admin_purchase_user_cannot_correct_posted_purchase(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Blocked Correction Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Blocked Product', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Box',
            'conversion_factor' => 1,
            'selling_price' => 1000,
            'cost_price' => 500,
            'is_active' => true,
        ]);

        $this->post('/purchases', [
            'purchase_date' => '2026-07-28',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'amount_paid' => 500,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => 500],
            ],
        ])->assertRedirect();

        $purchase = Purchase::query()->firstOrFail();

        $this->signInAsRole('stock_clerk');

        $this->get('/purchases/'.$purchase->id.'/correct')->assertForbidden();
        $this->post('/purchases', [
            'purchase_date' => '2026-07-29',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'amount_paid' => 500,
            'corrected_from_purchase_id' => $purchase->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => 500],
            ],
        ])->assertForbidden();

        $this->assertDatabaseCount('purchases', 1);
        $this->assertNull($purchase->fresh()->replaced_by_purchase_id);
    }

    public function test_stock_transfer_and_adjustment_post_inventory_transactions(): void
    {
        $storeA = Store::create(['name' => 'Store A', 'is_active' => true]);
        $storeB = Store::create(['name' => 'Store B', 'is_active' => true]);
        $product = Product::create(['name' => 'Soap', 'reorder_level' => 5, 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bar',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);
        $this->seedStock($storeA, $unit, 6);

        $transferResponse = $this->post('/stock/transfers', [
            'transfer_date' => '2026-03-25',
            'from_store_id' => $storeA->id,
            'to_store_id' => $storeB->id,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 4],
            ],
        ]);

        $transferResponse->assertRedirect('/stock/transfers/TRF-20260325-0001');
        $transferResponse->assertSessionMissing('auto_print_document');

        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_transfer',
            'reference_no' => 'TRF-20260325-0001',
            'store_id' => $storeA->id,
            'movement_type' => 'transfer_out',
        ]);
        $this->get('/stock/transfers/TRF-20260325-0001')->assertOk()->assertSee('Stock Transfer');
        $this->get('/stock/transfers/TRF-20260325-0001/print')->assertOk()->assertSee('Stock Transfer');

        $adjustmentResponse = $this->post('/stock/adjustments', [
            'adjustment_date' => '2026-03-25',
            'store_id' => $storeA->id,
            'adjustment_type' => 'decrease',
            'remarks' => 'Damaged stock removed after supervisor check',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2],
            ],
        ]);

        $adjustmentResponse->assertRedirect('/stock/adjustments/ADJ-20260325-0001');
        $adjustmentResponse->assertSessionMissing('auto_print_document');

        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_adjustment',
            'reference_no' => 'ADJ-20260325-0001',
            'store_id' => $storeA->id,
            'movement_type' => 'adjustment_out',
        ]);
        $this->get('/stock/adjustments/ADJ-20260325-0001')->assertOk()->assertSee('Stock Adjustment');
        $this->get('/stock/adjustments/ADJ-20260325-0001/print')->assertOk()->assertSee('Stock Adjustment');
        $this->get('/stock/items/'.$unit->id.'/history')->assertOk()->assertSee('Track how this stock item moved');
    }

    public function test_physical_stock_count_posts_only_variances_and_creates_count_document(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $productA = Product::create(['name' => 'Bath Soap', 'reorder_level' => 5, 'is_active' => true]);
        $productB = Product::create(['name' => 'Toothpaste', 'reorder_level' => 4, 'is_active' => true]);
        $unitA = ProductUnit::create([
            'product_id' => $productA->id,
            'unit_name' => 'Bar',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);
        $unitB = ProductUnit::create([
            'product_id' => $productB->id,
            'unit_name' => 'Tube',
            'cost_price' => 2500,
            'selling_price' => 3500,
            'is_active' => true,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-24',
            'store_id' => $store->id,
            'product_id' => $productA->id,
            'product_unit_id' => $unitA->id,
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'reference_no' => 'PUR-20260324-0001',
            'movement_type' => 'purchase',
            'quantity_in' => 10,
            'quantity_out' => 0,
            'unit_cost' => 1000,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-24',
            'store_id' => $store->id,
            'product_id' => $productB->id,
            'product_unit_id' => $unitB->id,
            'reference_type' => 'purchase',
            'reference_id' => 2,
            'reference_no' => 'PUR-20260324-0002',
            'movement_type' => 'purchase',
            'quantity_in' => 6,
            'quantity_out' => 0,
            'unit_cost' => 2500,
        ]);

        $this->get('/stock/counts/create?store_id='.$store->id)
            ->assertOk()
            ->assertSee('Physical Stock Count')
            ->assertSee('Bath Soap');

        $response = $this->post('/stock/counts', [
            'action' => 'post',
            'count_date' => '2026-03-25',
            'store_id' => $store->id,
            'remarks' => 'Morning shelf count',
            'items' => [
                ['product_unit_id' => $unitA->id, 'physical_count' => 7, 'is_counted' => 1],
                ['product_unit_id' => $unitB->id, 'physical_count' => 6, 'is_counted' => 1],
            ],
        ]);

        $response->assertRedirect('/stock/counts/CNT-20260325-0001');
        $response->assertSessionMissing('auto_print_document');

        $this->assertDatabaseHas('stock_counts', [
            'count_no' => 'CNT-20260325-0001',
            'store_id' => $store->id,
            'line_count' => 2,
            'total_variance_qty' => 3,
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('stock_count_items', [
            'system_qty' => 10,
            'physical_qty' => 7,
            'variance_qty' => -3,
            'quantity_adjusted' => 3,
            'product_unit_id' => $unitA->id,
        ]);
        $this->assertDatabaseHas('stock_count_items', [
            'system_qty' => 6,
            'physical_qty' => 6,
            'variance_qty' => 0,
            'quantity_adjusted' => 0,
            'product_unit_id' => $unitB->id,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_count',
            'reference_no' => 'CNT-20260325-0001',
            'movement_type' => 'count_out',
            'product_unit_id' => $unitA->id,
            'quantity_out' => 3,
        ]);

        $this->get('/stock/counts/CNT-20260325-0001')->assertOk()->assertSee('Physical Stock Count')->assertSee('Morning shelf count');
        $this->get('/stock/counts/CNT-20260325-0001/print')->assertOk()->assertSee('Physical Stock Count');
        $this->get('/stock/counts')->assertOk()->assertSee('CNT-20260325-0001');
        $this->get('/stock/items/'.$unitA->id.'/history')->assertOk()->assertSee('Physical Count');
    }

    public function test_physical_stock_count_can_save_draft_progress_and_resume_later(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $productA = Product::create(['name' => 'Rice', 'is_active' => true]);
        $productB = Product::create(['name' => 'Beans', 'is_active' => true]);
        $unitA = ProductUnit::create([
            'product_id' => $productA->id,
            'unit_name' => 'Bag',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);
        $unitB = ProductUnit::create([
            'product_id' => $productB->id,
            'unit_name' => 'Bag',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-24',
            'store_id' => $store->id,
            'product_id' => $productA->id,
            'product_unit_id' => $unitA->id,
            'reference_type' => 'purchase',
            'reference_id' => 10,
            'reference_no' => 'PUR-20260324-0010',
            'movement_type' => 'purchase',
            'quantity_in' => 12,
            'quantity_out' => 0,
            'unit_cost' => 1000,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-24',
            'store_id' => $store->id,
            'product_id' => $productB->id,
            'product_unit_id' => $unitB->id,
            'reference_type' => 'purchase',
            'reference_id' => 11,
            'reference_no' => 'PUR-20260324-0011',
            'movement_type' => 'purchase',
            'quantity_in' => 8,
            'quantity_out' => 0,
            'unit_cost' => 1000,
        ]);

        $draftResponse = $this->post('/stock/counts', [
            'action' => 'draft',
            'count_date' => '2026-03-25',
            'store_id' => $store->id,
            'remarks' => 'Aisle one count',
            'items' => [
                ['product_unit_id' => $unitA->id, 'physical_count' => 12, 'is_counted' => 1],
                ['product_unit_id' => $unitB->id, 'physical_count' => 8],
            ],
        ]);

        $draftResponse->assertRedirect('/stock/counts/create?draft_id=1&store_id='.$store->id.'&q=&count_focus=all&show_status=pending');
        $draftResponse->assertSessionHas('status', 'Draft CNT-20260325-0001 saved. 1 line(s) are marked as counted so far.');

        $this->assertDatabaseHas('stock_counts', [
            'id' => 1,
            'count_no' => 'CNT-20260325-0001',
            'status' => 'draft',
            'line_count' => 1,
        ]);
        $this->assertDatabaseHas('stock_count_items', [
            'stock_count_id' => 1,
            'product_unit_id' => $unitA->id,
            'variance_qty' => 0,
        ]);
        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_type' => 'stock_count',
            'reference_no' => 'CNT-20260325-0001',
        ]);

        $this->get('/stock/counts/create?draft_id=1')
            ->assertOk()
            ->assertSee('Draft CNT-20260325-0001')
            ->assertSee('already counted')
            ->assertSee('Pending only')
            ->assertSee('<strong>Beans</strong>', false)
            ->assertDontSee('<strong>Rice</strong>', false);

        $this->get('/stock/counts/create?draft_id=1&show_status=pending')
            ->assertOk()
            ->assertSee('<strong>Beans</strong>', false)
            ->assertDontSee('<strong>Rice</strong>', false);

        $this->get('/stock/counts/create?draft_id=1&show_status=counted')
            ->assertOk()
            ->assertSee('<strong>Rice</strong>', false)
            ->assertDontSee('<strong>Beans</strong>', false);

        $this->get('/stock/counts/create?draft_id=1&show_status=all&per_page=1&page=1')
            ->assertOk()
            ->assertSee('Showing 1-1 of 2 matching lines.')
            ->assertSee('Beans')
            ->assertDontSee('Rice');

        $this->get('/stock/counts/create?draft_id=1&show_status=all&per_page=1&page=2')
            ->assertOk()
            ->assertSee('Showing 2-2 of 2 matching lines.')
            ->assertSee('Rice')
            ->assertDontSee('Beans');

        $postResponse = $this->post('/stock/counts', [
            'action' => 'post',
            'stock_count_id' => 1,
            'count_date' => '2026-03-25',
            'store_id' => $store->id,
            'remarks' => 'Completed floor count',
            'show_status' => 'pending',
            'items' => [
                ['product_unit_id' => $unitA->id, 'physical_count' => 12, 'is_counted' => 1],
                ['product_unit_id' => $unitB->id, 'physical_count' => 6, 'is_counted' => 1],
            ],
        ]);

        $postResponse->assertRedirect('/stock/counts/CNT-20260325-0001');
        $this->assertDatabaseHas('stock_counts', [
            'id' => 1,
            'status' => 'posted',
            'line_count' => 2,
            'total_variance_qty' => 2,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_count',
            'reference_no' => 'CNT-20260325-0001',
            'movement_type' => 'count_out',
            'product_unit_id' => $unitB->id,
            'quantity_out' => 2,
        ]);
    }

    public function test_stock_count_assignment_fields_are_saved_and_displayed(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $staff = User::factory()->create(['name' => 'Shelf Counter', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Count Supplier', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Soda',
            'supplier_id' => $supplier->id,
            'is_active' => true,
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bottle',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'reference_no' => 'PUR-20260325-0001',
            'movement_type' => 'purchase',
            'quantity_in' => 9,
            'quantity_out' => 0,
            'unit_cost' => 1000,
        ]);

        $this->get('/stock/counts/create?store_id='.$store->id)
            ->assertOk()
            ->assertSee('Assigned Staff')
            ->assertSee('Aisle / Section');

        $response = $this->post('/stock/counts', [
            'action' => 'post',
            'count_date' => '2026-03-25',
            'store_id' => $store->id,
            'assigned_user_id' => $staff->id,
            'section_name' => 'Front Shelf',
            'remarks' => 'Front shelf recount',
            'items' => [
                ['product_unit_id' => $unit->id, 'physical_count' => 8, 'is_counted' => 1],
            ],
        ]);

        $response->assertRedirect('/stock/counts/CNT-20260325-0001');
        $this->assertDatabaseHas('stock_counts', [
            'count_no' => 'CNT-20260325-0001',
            'assigned_user_id' => $staff->id,
            'section_name' => 'Front Shelf',
        ]);

        $this->get('/stock/counts')
            ->assertOk()
            ->assertSee('Shelf Counter')
            ->assertSee('Front Shelf');

        $this->get('/stock/counts/CNT-20260325-0001')
            ->assertOk()
            ->assertSee('Assigned Staff')
            ->assertSee('Shelf Counter')
            ->assertSee('Front Shelf');

        $this->get('/stock/counts/CNT-20260325-0001/print')
            ->assertOk()
            ->assertSee('Assigned:')
            ->assertSee('Shelf Counter')
            ->assertSee('Section: Front Shelf');
    }

    public function test_stock_count_priority_filter_can_focus_low_or_zero_stock_lines(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $productLow = Product::create(['name' => 'Tea Leaves', 'reorder_level' => 5, 'is_active' => true]);
        $productZero = Product::create(['name' => 'Bathing Soap', 'reorder_level' => 2, 'is_active' => true]);
        $productHealthy = Product::create(['name' => 'Cooking Oil', 'reorder_level' => 3, 'is_active' => true]);
        $unitLow = ProductUnit::create([
            'product_id' => $productLow->id,
            'unit_name' => 'Pack',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);
        $unitZero = ProductUnit::create([
            'product_id' => $productZero->id,
            'unit_name' => 'Bar',
            'cost_price' => 800,
            'selling_price' => 1200,
            'is_active' => true,
        ]);
        $unitHealthy = ProductUnit::create([
            'product_id' => $productHealthy->id,
            'unit_name' => 'Bottle',
            'cost_price' => 4000,
            'selling_price' => 5000,
            'is_active' => true,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $productLow->id,
            'product_unit_id' => $unitLow->id,
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'reference_no' => 'PUR-LOW-1',
            'movement_type' => 'purchase',
            'quantity_in' => 4,
            'quantity_out' => 0,
            'unit_cost' => 1000,
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $productHealthy->id,
            'product_unit_id' => $unitHealthy->id,
            'reference_type' => 'purchase',
            'reference_id' => 2,
            'reference_no' => 'PUR-OK-1',
            'movement_type' => 'purchase',
            'quantity_in' => 9,
            'quantity_out' => 0,
            'unit_cost' => 4000,
        ]);

        $this->get('/stock/counts/create?store_id='.$store->id.'&count_focus=low_stock')
            ->assertOk()
            ->assertSee('Low stock first')
            ->assertSee('Tea Leaves')
            ->assertDontSee('Cooking Oil');

        $this->get('/stock/counts/create?store_id='.$store->id.'&count_focus=zero_or_negative')
            ->assertOk()
            ->assertSee('Zero / negative first')
            ->assertSee('Bathing Soap')
            ->assertDontSee('Cooking Oil')
            ->assertDontSee('Tea Leaves');
    }

    public function test_product_stock_actions_prefill_selected_item_and_adjustment_can_return_back(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Soap Supplier', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Laundry Soap',
            'supplier_id' => $supplier->id,
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bar',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_pos_unit' => true,
            'is_active' => true,
        ]);

        $productUrl = 'http://localhost/products/'.$product->id;

        $this->get('/stock/adjustments/create?product_unit_id='.$unit->id.'&return_to='.urlencode($productUrl))
            ->assertOk()
            ->assertSee('Laundry Soap - Bar');

        $this->get('/purchases/create?product_unit_id='.$unit->id.'&return_to='.urlencode($productUrl))
            ->assertOk()
            ->assertSee('Laundry Soap - Bar');

        $response = $this->post('/stock/adjustments', [
            'adjustment_date' => '2026-03-25',
            'store_id' => $store->id,
            'adjustment_type' => 'increase',
            'return_to' => $productUrl,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 3],
            ],
        ]);

        $response->assertRedirect($productUrl);
        $response->assertSessionHas('status', 'Stock adjustment ADJ-20260325-0001 posted successfully.');
    }

    public function test_dashboard_shows_overdue_credit_and_low_stock_alerts(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Overdue Customer', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Salt', 'reorder_level' => 10, 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Packet',
            'cost_price' => 500,
            'selling_price' => 700,
            'is_active' => true,
        ]);

        Sale::create([
            'sale_no' => 'INV-20260301-0001',
            'sale_date' => '2026-03-01',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 600,
            'total_amount' => 600,
            'amount_paid' => 0,
            'balance_due' => 600,
            'credit_due_date' => '2026-03-10',
            'status' => 'posted',
        ]);

        \App\Models\InventoryTransaction::create([
            'transaction_date' => '2026-03-25',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'stock_adjustment',
            'reference_id' => 1,
            'reference_no' => 'ADJ-20260325-0001',
            'movement_type' => 'adjustment_out',
            'quantity_in' => 0,
            'quantity_out' => 3,
            'unit_cost' => 500,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Overdue Credit')
            ->assertSee('Low Stock Alert')
            ->assertSee('Overdue Customer')
            ->assertSee('Salt');
    }

    public function test_dashboard_management_window_filters_range_summary_and_top_selling_items(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Window Customer', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $productA = Product::create(['name' => 'Biscuits', 'is_active' => true]);
        $productB = Product::create(['name' => 'Sugar', 'is_active' => true]);
        $unitA = ProductUnit::create([
            'product_id' => $productA->id,
            'unit_name' => 'Pack',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);
        $unitB = ProductUnit::create([
            'product_id' => $productB->id,
            'unit_name' => 'Bag',
            'cost_price' => 2000,
            'selling_price' => 2800,
            'is_active' => true,
        ]);

        $oldSale = Sale::create([
            'sale_no' => 'INV-OLD-1',
            'sale_date' => '2026-03-10',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1500,
            'total_amount' => 1500,
            'amount_paid' => 1500,
            'balance_due' => 0,
            'status' => 'posted',
            'created_by' => $this->app['auth']->id(),
        ]);
        $oldSale->items()->create([
            'product_id' => $productA->id,
            'product_unit_id' => $unitA->id,
            'quantity' => 1,
            'unit_price' => 1500,
            'selling_price_snapshot' => 1500,
            'cost_price_snapshot' => 1000,
            'line_total' => 1500,
        ]);

        $windowSale = Sale::create([
            'sale_no' => 'INV-WIN-1',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 5600,
            'total_amount' => 5600,
            'amount_paid' => 5600,
            'balance_due' => 0,
            'status' => 'posted',
            'created_by' => $this->app['auth']->id(),
        ]);
        $windowSale->items()->create([
            'product_id' => $productB->id,
            'product_unit_id' => $unitB->id,
            'quantity' => 2,
            'unit_price' => 2800,
            'selling_price_snapshot' => 2800,
            'cost_price_snapshot' => 2000,
            'line_total' => 5600,
        ]);

        Expense::create([
            'expense_date' => '2026-03-25',
            'amount' => 600,
            'expense_no' => 'EXP-20260325-0001',
            'store_id' => $store->id,
            'category' => 'Transport',
            'payment_mode_id' => $paymentMode->id,
            'status' => 'posted',
            'created_by' => $this->app['auth']->id(),
        ]);

        $this->get('/?from=2026-03-25&to=2026-03-25')
            ->assertOk()
            ->assertSee('Sales')
            ->assertSee('Gross Profit')
            ->assertSee('Trading Trend')
            ->assertSee('Top Selling Products')
            ->assertSee('Payment Breakdown')
            ->assertSee('Sugar')
            ->assertSee('5,600');
    }

    public function test_dashboard_summary_cards_use_posted_business_dates_and_link_to_drilldowns(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Dashboard Customer', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Dashboard Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Dashboard Soap', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bar',
            'cost_price' => 300,
            'selling_price' => 500,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_no' => 'INV-DASH-1',
            'sale_date' => '2026-05-01',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1500,
            'total_amount' => 1500,
            'amount_paid' => 900,
            'balance_due' => 600,
            'status' => 'posted',
            'created_by' => $this->app['auth']->id(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => 3,
            'unit_price' => 500,
            'selling_price_snapshot' => 500,
            'cost_price_snapshot' => 300,
            'line_total' => 1500,
        ]);

        Sale::create([
            'sale_no' => 'INV-DASH-VOID',
            'sale_date' => '2026-05-01',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 99999,
            'total_amount' => 99999,
            'amount_paid' => 99999,
            'balance_due' => 0,
            'status' => 'voided',
        ]);

        \App\Models\CustomerPayment::create([
            'payment_no' => 'PAY-DASH-1',
            'payment_date' => '2026-05-01',
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'account_reference_type' => 'sale',
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 400,
            'status' => 'posted',
            'created_by' => $this->app['auth']->id(),
        ]);

        Purchase::create([
            'purchase_no' => 'PUR-DASH-1',
            'purchase_date' => '2026-05-01',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 700,
            'total_amount' => 700,
            'amount_paid' => 700,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        Expense::create([
            'expense_no' => 'EXP-DASH-1',
            'expense_date' => '2026-05-01',
            'store_id' => $store->id,
            'category' => 'Transport',
            'payment_mode_id' => $paymentMode->id,
            'amount' => 100,
            'status' => 'posted',
            'created_by' => $this->app['auth']->id(),
        ]);

        \App\Models\SaleReturn::create([
            'return_no' => 'SRET-DASH-1',
            'return_date' => '2026-05-01',
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'return_type' => 'refund',
            'returned_total' => 200,
            'refund_amount' => 200,
            'status' => 'posted',
        ]);

        $this->get('/?date_from=2026-05-01&date_to=2026-05-01')
            ->assertOk()
            ->assertSee('UGX 1,500')
            ->assertSee('UGX 700')
            ->assertSee('UGX 100')
            ->assertSee('UGX 900')
            ->assertSee('UGX 600')
            ->assertSee('UGX 500')
            ->assertSee('UGX 200')
            ->assertDontSee('99,999')
            ->assertSee('/sales?date_from=2026-05-01&amp;date_to=2026-05-01', false)
            ->assertSee('/purchases?date_from=2026-05-01&amp;date_to=2026-05-01', false)
            ->assertSee('/expenses?date_from=2026-05-01&amp;date_to=2026-05-01', false)
            ->assertSee('/reports/payment-methods?date_from=2026-05-01&amp;date_to=2026-05-01', false)
            ->assertSee('/reports/financial-summary?date_from=2026-05-01&amp;date_to=2026-05-01', false);
    }

    public function test_aging_reports_and_role_preview_access_rules_work(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Aging Customer', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Aging Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        Sale::create([
            'sale_no' => 'INV-20260325-0001',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 400,
            'total_amount' => 400,
            'amount_paid' => 0,
            'balance_due' => 400,
            'credit_due_date' => '2026-03-15',
            'status' => 'posted',
        ]);

        \App\Models\Purchase::create([
            'purchase_no' => 'PUR-20260325-0001',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 600,
            'total_amount' => 600,
            'amount_paid' => 0,
            'balance_due' => 600,
            'credit_due_date' => '2026-03-05',
            'status' => 'posted',
        ]);

        $this->get('/reports/customer-aging')->assertOk()->assertSee('Aging Customer');
        $this->get('/reports/supplier-aging')->assertOk()->assertSee('Aging Supplier');

        $this->withSession(['preview_role' => 'cashier'])
            ->get('/capital-inputs')
            ->assertForbidden();

        $this->withSession(['preview_role' => 'stock_clerk'])
            ->get('/sales')
            ->assertForbidden();
    }

    public function test_management_report_pages_load(): void
    {
        $this->get('/reports/financial-summary')->assertOk()->assertSee('Financial Summary');
        $this->get('/reports/payment-methods')->assertOk()->assertSee('Payment Method Breakdown');
        $this->get('/reports/cashier-performance')->assertOk()->assertSee('Cashier Performance');
        $this->get('/reports/daily-closing')->assertOk()->assertSee('Daily Closing Report');
        $this->get('/reports/daily-sales-summary')->assertOk()->assertSee('Summary Cash Sales/Income by Shop Report');
    }

    public function test_daily_sales_summary_groups_items_and_totals(): void
    {
        $store = Store::create(['name' => 'Main Shop', 'is_active' => true]);
        $otherStore = Store::create(['name' => 'Branch Shop', 'is_active' => true]);
        $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $mobile = PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);
        $product = Product::create(['name' => 'DETTOL FRESH SOAP 90G', 'is_active' => true]);
        $box = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Boxes',
            'selling_price' => 298000,
            'cost_price' => 250000,
            'is_active' => true,
        ]);
        $piece = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pieces',
            'selling_price' => 3500,
            'cost_price' => 2500,
            'is_active' => true,
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
        ]);

        $sale = Sale::create([
            'sale_no' => 'RCPT-SUMMARY-1',
            'sale_date' => '2026-05-01',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $cash->id,
            'subtotal' => 894875,
            'total_amount' => 894875,
            'amount_paid' => 894875,
            'balance_due' => 0,
            'status' => 'posted',
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $box->id,
            'quantity' => 2,
            'unit_price' => 298000,
            'line_total' => 596000,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $box->id,
            'quantity' => 1,
            'unit_price' => 298000,
            'line_total' => 298000,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'quantity' => 0.25,
            'unit_price' => 3500,
            'line_total' => 875,
        ]);

        $outsideDateSale = Sale::create([
            'sale_no' => 'RCPT-SUMMARY-OLD',
            'sale_date' => '2026-04-30',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $cash->id,
            'subtotal' => 111,
            'total_amount' => 111,
            'amount_paid' => 111,
            'balance_due' => 0,
            'status' => 'posted',
        ]);
        SaleItem::create([
            'sale_id' => $outsideDateSale->id,
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'quantity' => 1,
            'unit_price' => 111,
            'line_total' => 111,
        ]);

        $voidSale = Sale::create([
            'sale_no' => 'RCPT-SUMMARY-VOID',
            'sale_date' => '2026-05-01',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $cash->id,
            'subtotal' => 999,
            'total_amount' => 999,
            'amount_paid' => 999,
            'balance_due' => 0,
            'status' => 'void',
        ]);
        SaleItem::create([
            'sale_id' => $voidSale->id,
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'quantity' => 1,
            'unit_price' => 999,
            'line_total' => 999,
        ]);

        $branchSale = Sale::create([
            'sale_no' => 'RCPT-SUMMARY-BRANCH',
            'sale_date' => '2026-05-01',
            'store_id' => $otherStore->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $mobile->id,
            'subtotal' => 1234,
            'total_amount' => 1234,
            'amount_paid' => 1234,
            'balance_due' => 0,
            'status' => 'posted',
        ]);
        SaleItem::create([
            'sale_id' => $branchSale->id,
            'product_id' => $product->id,
            'product_unit_id' => $piece->id,
            'quantity' => 1,
            'unit_price' => 1234,
            'line_total' => 1234,
        ]);

        $this->get('/reports/daily-sales-summary?date_from=2026-05-01&date_to=2026-05-01&store_id='.$store->id)
            ->assertOk()
            ->assertSee('APPLES OF GOLD WHOLESALERS')
            ->assertSee('Summary Cash Sales/Income by Shop Report')
            ->assertSee('SHOP: Main Shop')
            ->assertSee('Cash Sale')
            ->assertSee('Total Cash Sale')
            ->assertSee('DETTOL FRESH SOAP 90G - Boxes')
            ->assertSee('DETTOL FRESH SOAP 90G - Pieces')
            ->assertSee('3')
            ->assertSee('0.25')
            ->assertSee('UGX 298,000')
            ->assertSee('UGX 894,000')
            ->assertSee('UGX 3,500')
            ->assertSee('UGX 875')
            ->assertSee('Grand Total')
            ->assertSee('UGX 894,875')
            ->assertDontSee('UGX 111')
            ->assertDontSee('UGX 999')
            ->assertDontSee('SHOP: Branch Shop')
            ->assertDontSee('UGX 1,234');

        $excelMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $this->get('/reports/daily-sales-summary/export?date_from=2026-05-01&date_to=2026-05-01')
            ->assertOk()
            ->assertHeader('content-type', $excelMime);
    }

    public function test_management_centre_loads_with_grouped_existing_shortcuts(): void
    {
        config(['business.developer_phone' => '']);

        $this->get('/management-centre')
            ->assertOk()
            ->assertSee('Management Centre')
            ->assertSee('&copy; 2026 Apples Of Gold System', false)
            ->assertSee('Designed & Developed by Kisakye Allan | Rolans Software Solutions')
            ->assertDontSee('Tel:')
            ->assertSee('Stock Sales')
            ->assertSee('Stock Purchases')
            ->assertSee('Stock Control')
            ->assertSee('Accounts')
            ->assertSee('Reports')
            ->assertSee('Setup')
            ->assertSee('@media (max-width: 720px)', false)
            ->assertSee('.management-quick-actions .button-link', false)
            ->assertSee(route('sales.create', [], false), false)
            ->assertSee(route('purchases.create', [], false), false)
            ->assertSee(route('stock.balances', [], false), false)
            ->assertSee(route('reports.daily-sales-summary', [], false), false)
            ->assertSee(route('reports.financial-summary', [], false), false)
            ->assertSee(route('products.create', [], false), false);
    }

    public function test_developer_credit_shows_phone_only_when_configured(): void
    {
        config(['business.developer_phone' => '+256 703/773-086 770']);

        $this->get('/management-centre')
            ->assertOk()
            ->assertSee('Designed & Developed by Kisakye Allan | Rolans Software Solutions')
            ->assertSee('Tel: +256 703/773-086 770');
    }

    public function test_management_centre_requires_dashboard_access_and_hides_unauthorized_setup_links(): void
    {
        $this->signInAsRole('guest');
        $this->get('/management-centre')->assertForbidden();

        $this->signInAsRole('cashier');
        $this->get('/management-centre')
            ->assertOk()
            ->assertSee('Stock Sales')
            ->assertSee('New Sale')
            ->assertDontSee('Business Settings')
            ->assertDontSee('Product Categories');
    }

    public function test_product_forms_show_wholesale_unit_setup_guidance(): void
    {
        $product = Product::create(['name' => 'Guidance Product', 'is_active' => true]);
        ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Piece',
            'conversion_factor' => 1,
            'selling_price' => 1000,
            'cost_price' => 700,
            'is_active' => true,
            'is_pos_unit' => true,
        ]);

        $guidance = 'Create one product, then add all selling packs/units such as piece, dozen, box, carton, sack, bundle, bottle, tin, half carton where applicable.';
        $duplicateWarning = 'Do not create separate products for carton and piece versions of the same item';
        $fractionalGuidance = 'Allow fractional quantities for wholesale packs such as 0.25, 0.5, or 0.75 carton.';

        $this->get('/products/create')
            ->assertOk()
            ->assertSee($guidance)
            ->assertSee($duplicateWarning)
            ->assertSee($fractionalGuidance)
            ->assertSee('Minimum Wholesale Qty')
            ->assertSee('Example: 0.25')
            ->assertSee('php artisan product-units:enable-fractional-wholesale --dry-run');

        $this->get('/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertSee($guidance)
            ->assertSee($duplicateWarning)
            ->assertSee($fractionalGuidance)
            ->assertSee('Minimum Wholesale Qty')
            ->assertSee('Example: 0.25')
            ->assertSee('php artisan product-units:enable-fractional-wholesale --dry-run');
    }

    public function test_product_unit_base_conversion_metadata_can_be_saved_without_changing_stock_behavior(): void
    {
        $this->get('/products/create')
            ->assertOk()
            ->assertSee('Allow Fractional Qty')
            ->assertSee('Quantity Precision')
            ->assertSee('Base Unit');

        $this->post('/products', [
            'name' => 'Wholesale Setup Product',
            'code' => 'WSP-001',
            'reorder_level' => 10,
            'is_vat_applicable' => 0,
            'is_active' => 1,
            'default_unit_index' => 0,
            'units' => [
                [
                    'unit_name' => 'Kg',
                    'conversion_factor' => 1,
                    'selling_price' => 3000,
                    'cost_price' => 2200,
                    'allow_fractional_quantity' => 1,
                    'quantity_precision' => 3,
                    'is_base_unit' => 1,
                    'is_active' => 1,
                ],
                [
                    'unit_name' => 'Sack',
                    'conversion_factor' => 50,
                    'selling_price' => 145000,
                    'cost_price' => 110000,
                    'allow_fractional_quantity' => 1,
                    'quantity_precision' => 2,
                    'is_base_unit' => 0,
                    'is_active' => 1,
                ],
            ],
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Wholesale Setup Product')->firstOrFail();
        $kg = ProductUnit::query()->where('product_id', $product->id)->where('unit_name', 'Kg')->firstOrFail();

        $this->assertDatabaseHas('product_units', [
            'id' => $kg->id,
            'allow_fractional_quantity' => true,
            'quantity_precision' => 3,
            'is_base_unit' => true,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'base_product_unit_id' => $kg->id,
            'base_unit_label' => 'Kg',
        ]);

        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_name' => 'Sack',
            'conversion_factor' => 50,
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.25,
            'is_base_unit' => false,
        ]);
    }

    public function test_follow_up_and_csv_export_routes_work(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Follow Up Customer', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Follow Up Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $sale = Sale::create([
            'sale_no' => 'INV-20260325-0001',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 400,
            'total_amount' => 400,
            'amount_paid' => 0,
            'balance_due' => 400,
            'credit_due_date' => '2026-03-15',
            'status' => 'posted',
        ]);

        $purchase = \App\Models\Purchase::create([
            'purchase_no' => 'PUR-20260325-0001',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 600,
            'total_amount' => 600,
            'amount_paid' => 0,
            'balance_due' => 600,
            'credit_due_date' => '2026-03-05',
            'status' => 'posted',
        ]);

        $this->post('/follow-ups', [
            'sale_id' => $sale->id,
            'reminder_date' => '2026-03-25',
            'channel' => 'Phone Call',
        ])->assertRedirect('/follow-ups');

        $followUp = \App\Models\FollowUpAction::firstOrFail();

        $this->post('/follow-ups/'.$followUp->id.'/complete')->assertRedirect();
        $this->assertDatabaseHas('follow_up_actions', [
            'id' => $followUp->id,
            'status' => 'completed',
        ]);

        $excelMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $this->get('/customers/'.$customer->id.'/statement/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/suppliers/'.$supplier->id.'/statement/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/customers/'.$customer->id.'/statement/export')->assertOk()->assertHeader('content-type', $excelMime);
        $this->get('/suppliers/'.$supplier->id.'/statement/export')->assertOk()->assertHeader('content-type', $excelMime);
        $this->get('/reports/customer-aging/export')->assertOk()->assertHeader('content-type', $excelMime);
        $this->get('/reports/supplier-aging/export')->assertOk()->assertHeader('content-type', $excelMime);
        $this->get('/stock/balances/export')->assertOk()->assertHeader('content-type', $excelMime);
        $this->get('/stock/reorder/export')->assertOk()->assertHeader('content-type', $excelMime);
        $this->get('/stock/items/'.ProductUnit::create([
            'product_id' => Product::create(['name' => 'Export Product', 'is_active' => true])->id,
            'unit_name' => 'Each',
            'cost_price' => 10,
            'selling_price' => 15,
            'is_active' => true,
        ])->id.'/history/export')->assertOk()->assertHeader('content-type', $excelMime);
    }

    public function test_capital_input_can_be_recorded(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $capitalSource = CapitalSource::create(['name' => 'Owner Injection', 'source_type' => 'owner_injection', 'is_active' => true]);

        $response = $this->post('/capital-inputs', [
            'entry_date' => '2026-03-25',
            'store_id' => $store->id,
            'capital_source_id' => $capitalSource->id,
            'payment_mode_id' => $paymentMode->id,
            'amount' => 5000,
            'source_origin' => 'external',
            'reference_no' => 'CAP-REF-1',
        ]);

        $response->assertRedirect('/capital-inputs');

        $this->assertDatabaseHas('capital_entries', [
            'store_id' => $store->id,
            'capital_source_id' => $capitalSource->id,
            'amount' => 5000,
            'source_origin' => 'external',
            'reference_no' => 'CAP-REF-1',
        ]);
        $this->assertDatabaseHas('capital_entries', [
            'entry_no' => 'CAP-20260325-0001',
        ]);
    }

    public function test_expense_can_be_recorded_and_printed(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $expenseCategory = ExpenseCategory::create(['name' => 'Transport', 'is_active' => true]);

        $response = $this->post('/expenses', [
            'expense_date' => '2026-04-17',
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'expense_category_id' => $expenseCategory->id,
            'amount' => 12000,
            'reference_no' => 'EXP-REF-1',
            'notes' => 'Morning delivery transport',
        ]);

        $expense = Expense::query()->firstOrFail();

        $response->assertRedirect('/expenses/'.$expense->id);
        $this->get('/expenses')->assertOk()->assertSee($expense->expense_no);
        $this->get('/expenses/'.$expense->id)->assertOk()->assertSee('Transport');
        $this->get('/expenses/'.$expense->id.'/print')->assertOk()->assertSee($expense->expense_no);
        $this->assertSame($expenseCategory->id, $expense->expense_category_id);
        $this->assertEquals(12000.0, (float) $expense->amount);
    }

    public function test_cashier_shift_can_be_opened_used_and_closed(): void
    {
        $cashier = $this->signInAsRole('cashier');
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $cashier->update(['default_store_id' => $store->id]);

        $this->post('/cash-shifts', [
            'store_id' => $store->id,
            'opened_at' => '2026-04-17 08:00:00',
            'opening_balance' => 50000,
        ])->assertRedirect();

        $shift = CashShift::query()->firstOrFail();

        $walkInCustomer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'Soda', 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Bottle',
            'selling_price' => 2500,
            'cost_price' => 1500,
            'is_active' => true,
        ]);
        $this->seedStock($store, $unit, 4);

        $this->post('/sales', [
            'sale_date' => '2026-04-17',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 5000,
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 2500],
            ],
        ])->assertRedirect();

        $this->post('/cash-shifts/'.$shift->id.'/close', [
            'counted_cash' => 55000,
            'closing_notes' => 'Drawer matched expected cash.',
        ])->assertRedirect('/cash-shifts/'.$shift->id);

        $shift->refresh();

        $this->assertSame('closed', $shift->status);
        $this->assertEquals(5000.0, (float) $shift->cash_sales_total);
        $this->assertEquals(55000.0, (float) $shift->expected_cash);
        $this->assertEquals(0.0, (float) $shift->shortage_overage);
    }

    public function test_password_flows_and_activity_log_are_available(): void
    {
        /** @var User $user */
        $user = $this->app['auth']->user();

        $this->post('/change-password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertSessionHas('status', 'Password changed successfully.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-123', $user->password));

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', [
            'login' => $user->username,
            'password' => 'new-secret-123',
        ])->assertRedirect('/');

        $this->get('/activity-logs')
            ->assertOk()
            ->assertSee('Activity Log')
            ->assertSee('Password Changed')
            ->assertSee('Auth Login');

        $this->post('/logout')->assertRedirect('/login');
        $this->get('/forgot-password')->assertOk()->assertSee('Forgot Password');
        $this->post('/forgot-password', [
            'login' => $user->username,
        ])->assertSessionHas('status');

        $token = Password::broker()->createToken($user);

        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertOk()
            ->assertSee('Reset Password');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'reset-secret-123',
            'password_confirmation' => 'reset-secret-123',
        ])->assertRedirect('/login');

        $user->refresh();
        $this->assertTrue(Hash::check('reset-secret-123', $user->password));
        $this->assertDatabaseHas('activity_logs', ['event' => 'password.reset.completed']);
    }

    public function test_email_and_sms_reminders_update_follow_up_status_and_audit_log(): void
    {
        Mail::fake();
        Log::spy();

        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'Reminder Customer',
            'email' => 'customer@example.com',
            'phone' => '256700000001',
            'is_active' => true,
        ]);
        $supplier = Supplier::create([
            'name' => 'Reminder Supplier',
            'phone' => '256700000002',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'sale_no' => 'INV-20260325-1001',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 500,
            'total_amount' => 500,
            'amount_paid' => 0,
            'balance_due' => 500,
            'credit_due_date' => '2026-03-20',
            'status' => 'posted',
        ]);

        $purchase = Purchase::create([
            'purchase_no' => 'PUR-20260325-1001',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 700,
            'total_amount' => 700,
            'amount_paid' => 0,
            'balance_due' => 700,
            'credit_due_date' => '2026-03-18',
            'status' => 'posted',
        ]);

        $emailFollowUp = FollowUpAction::create([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $this->app['auth']->id(),
            'reminder_date' => '2026-03-25',
            'status' => 'pending',
        ]);

        $smsFollowUp = FollowUpAction::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'assigned_user_id' => $this->app['auth']->id(),
            'reminder_date' => '2026-03-25',
            'status' => 'pending',
        ]);

        $this->post('/follow-ups/'.$emailFollowUp->id.'/send', ['channel' => 'email'])
            ->assertSessionHas('status', 'Reminder sent successfully.');

        $emailFollowUp->refresh();
        $this->assertSame('sent', $emailFollowUp->status);
        $this->assertSame('EMAIL', $emailFollowUp->channel);
        $this->assertNotNull($emailFollowUp->last_sent_at);

        Mail::assertNothingOutgoing();

        $this->post('/follow-ups/'.$smsFollowUp->id.'/send', ['channel' => 'sms'])
            ->assertSessionHas('status', 'Reminder sent successfully.');

        $smsFollowUp->refresh();
        $this->assertSame('sent', $smsFollowUp->status);
        $this->assertSame('SMS', $smsFollowUp->channel);
        $this->assertNotNull($smsFollowUp->last_sent_at);

        Log::shouldHaveReceived('info')->once();
        $this->assertDatabaseHas('activity_logs', ['event' => 'follow_up.sent']);
    }

    public function test_dashboard_shows_pending_follow_ups_and_aging_bucket_totals(): void
    {
        Carbon::setTestNow('2026-03-25 12:00:00');
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());

        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Bucket Customer', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Bucket Supplier', 'is_active' => true]);

        Sale::create([
            'sale_no' => 'INV-20260325-2001',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 300,
            'total_amount' => 300,
            'amount_paid' => 0,
            'balance_due' => 300,
            'credit_due_date' => '2026-03-24',
            'status' => 'posted',
        ]);

        $sale31 = Sale::create([
            'sale_no' => 'INV-20260325-2002',
            'sale_date' => '2026-03-25',
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'sale_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 900,
            'total_amount' => 900,
            'amount_paid' => 0,
            'balance_due' => 900,
            'credit_due_date' => '2026-02-15',
            'status' => 'posted',
        ]);

        Purchase::create([
            'purchase_no' => 'PUR-20260325-2001',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 450,
            'total_amount' => 450,
            'amount_paid' => 0,
            'balance_due' => 450,
            'credit_due_date' => '2026-03-10',
            'status' => 'posted',
        ]);

        Purchase::create([
            'purchase_no' => 'PUR-20260325-2002',
            'purchase_date' => '2026-03-25',
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'purchase_type' => 'credit',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 850,
            'total_amount' => 850,
            'amount_paid' => 0,
            'balance_due' => 850,
            'credit_due_date' => '2025-12-20',
            'status' => 'posted',
        ]);

        FollowUpAction::create([
            'sale_id' => $sale31->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $this->app['auth']->id(),
            'reminder_date' => '2026-03-26',
            'status' => 'pending',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Pending Follow-ups')
            ->assertSee('Customer Aging Totals')
            ->assertSee('Supplier Aging Totals')
            ->assertSee('Bucket Customer')
            ->assertSee('300')
            ->assertSee('900')
            ->assertSee('450')
            ->assertSee('850');
    }

    public function test_business_settings_update_branding_values(): void
    {
        Storage::fake('public');

        $response = $this->post('/settings/business', [
            'name' => 'Client Demo Stores',
            'tagline' => 'Retail control made simple',
            'address' => 'Plot 10 Demo Road',
            'phone' => '0700000000',
            'email' => 'hello@example.test',
            'tin' => 'TIN-123',
            'currency' => 'UGX',
            'receipt_footer' => 'Receipt footer text',
            'invoice_footer' => 'Invoice footer text',
            'statement_footer' => 'Statement footer text',
        ]);

        $response
            ->assertRedirect('/settings/business')
            ->assertSessionHas('status', 'Business settings updated successfully.');

        $this->assertDatabaseHas('business_settings', [
            'key' => 'name',
            'value' => 'Client Demo Stores',
        ]);

        $this->assertSame('Retail control made simple', BusinessSetting::query()->where('key', 'tagline')->value('value'));

        $this->get('/settings/business')
            ->assertOk()
            ->assertSee('Client Demo Stores')
            ->assertSee('Retail control made simple');

        $this->get('/sales/create')
            ->assertOk()
            ->assertSee('Client Demo Stores')
            ->assertSee('class="brand-logo"', false)
            ->assertSee('brand/apples-icon.png', false)
            ->assertSee('class="brand-copy"', false);

        $store = Store::create(['name' => 'Demo Store', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);

        $sale = Sale::create([
            'sale_no' => 'RCPT-TEST-1',
            'sale_date' => '2026-03-26',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'amount_paid' => 1000,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        $this->get('/sales/'.$sale->id.'/print?theme=full')
            ->assertOk()
            ->assertSee('Client Demo Stores')
            ->assertSee('Receipt footer text');
    }

    public function test_role_permissions_and_user_role_assignment_can_be_managed(): void
    {
        $managerRole = \App\Models\Role::query()->create([
            'name' => 'manager',
            'description' => 'Manager',
            'permissions' => ['dashboard.view', 'sales.view'],
        ]);
        $cashierRole = \App\Models\Role::query()->create([
            'name' => 'cashier',
            'description' => 'Cashier',
            'permissions' => ['dashboard.view'],
        ]);

        $user = User::factory()->create([
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ]);

        $this->get('/roles')
            ->assertOk()
            ->assertSee('Manage Permissions')
            ->assertSee('Manager');

        $this->get('/roles/'.$managerRole->id.'/edit')
            ->assertOk()
            ->assertSee('dashboard.view');

        $this->put('/roles/'.$managerRole->id, [
            'description' => 'Operations Manager',
            'permissions' => ['dashboard.view', 'sales.view', 'reports.view'],
        ])->assertRedirect('/roles');

        $managerRole->refresh();
        $this->assertSame('Operations Manager', $managerRole->description);
        $this->assertSame(['dashboard.view', 'sales.view', 'reports.view'], $managerRole->permissionList());

        $this->get('/users/'.$user->id.'/role')
            ->assertOk()
            ->assertSee('Assign Roles');

        $this->put('/users/'.$user->id.'/role', [
            'role_ids' => [$managerRole->id],
        ])->assertRedirect('/users');

        $user->refresh();
        $this->assertSame($managerRole->id, $user->role_id);
    }

    public function test_user_permissions_combine_across_multiple_roles(): void
    {
        \App\Models\Role::query()->create([
            'name' => 'sales_officer',
            'description' => 'Sales Officer',
            'permissions' => ['dashboard.view', 'sales.manage'],
        ]);
        \App\Models\Role::query()->create([
            'name' => 'stock_helper',
            'description' => 'Stock Helper',
            'permissions' => ['stock.manage'],
        ]);

        $this->signInWithRoles(['sales_officer', 'stock_helper']);

        $this->get('/')->assertOk();
        $this->get('/sales/create')->assertOk();
        $this->get('/stock/adjustments/create')->assertOk();
    }

    public function test_sale_create_shows_modern_pos_shortcuts(): void
    {
        PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);
        PaymentMode::create(['name' => 'Card', 'is_active' => true]);
        PaymentMode::create(['name' => 'Credit', 'is_active' => true]);
        $category = Category::create(['name' => 'BATHING SOAP']);
        $product = Product::create(['name' => 'GONJA CRISPS EXTRA LONG WHOLESALE PACK', 'code' => 'GONJA-001', 'category_id' => $category->id, 'is_active' => true]);
        ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'box',
            'conversion_factor' => 24,
            'selling_price' => 114000,
            'cost_price' => 90000,
            'barcode' => '6001234567890',
            'allow_fractional_quantity' => true,
            'quantity_precision' => 2,
            'minimum_wholesale_quantity' => 0.25,
            'is_active' => true,
        ]);

        $this->get('/sales/create')
            ->assertOk()
            ->assertSee('TOTAL')
            ->assertSee('CASH')
            ->assertSee('MOBILE MONEY')
            ->assertSee('CARD')
            ->assertSee('CREDIT')
            ->assertSee('Scan / barcode / code')
            ->assertSee('Cart')
            ->assertSee('Clear All')
            ->assertSee('Search products')
            ->assertDontSee('<h2>Sales Desk</h2>', false)
            ->assertDontSee('class="sale-hero panel"', false)
            ->assertSee('id="product-search-results"', false)
            ->assertSee('class="sale-floating-results"', false)
            ->assertSee('role="listbox"', false)
            ->assertSee('sales\/product-search', false)
            ->assertSee('z-index: 12050', false)
            ->assertSee('z-index: 12040', false)
            ->assertSee('z-index: 12045', false)
            ->assertSee('min-height: 38px', false)
            ->assertSee('padding: 4px 6px', false)
            ->assertSee('min-width: min(650px, calc(100vw - 32px))', false)
            ->assertSee('width: min(900px, calc(100vw - 330px))', false)
            ->assertSee('max-width: 100%', false)
            ->assertSee('overflow-x: clip', false)
            ->assertSee('Quick Pick')
            ->assertSee('id="quick-pick-results"', false)
            ->assertSee('GONJA CRISPS EXTRA LONG WHOLESALE PACK - box')
            ->assertSee('Product / Pack')
            ->assertSee('Category')
            ->assertSee('Code / Barcode')
            ->assertSee('Unit')
            ->assertSee('Price')
            ->assertSee('GONJA-001')
            ->assertSee('6001234567890')
            ->assertSee('BATHING SOAP')
            ->assertSee('"category_name":"BATHING SOAP"', false)
            ->assertSee('"unit_name":"box"', false)
            ->assertSee('"price":114000', false)
            ->assertSee('"allow_fractional_quantity":true', false)
            ->assertSee('"quantity_precision":2', false)
            ->assertSee('"minimum_wholesale_quantity":0.25', false)
            ->assertSee('sale-search-results-table', false)
            ->assertSee('sale-search-results-head', false)
            ->assertSee('sale-search-result-name', false)
            ->assertSee('sale-result-cell', false)
            ->assertSee('product-unit-chip', false)
            ->assertSee('sale-search-result-side', false)
            ->assertSee("event.key === 'ArrowDown'", false)
            ->assertSee("event.key === 'ArrowUp'", false)
            ->assertSee("event.key === 'Enter'", false)
            ->assertSee("event.key === 'Escape'", false)
            ->assertSee("document.addEventListener('click'", false)
            ->assertSee('data-add-unit', false)
            ->assertSee('quickPickResults?.addEventListener', false)
            ->assertSee('fetchProductResults', false)
            ->assertSee('Searching products...', false)
            ->assertSee('data-pos-area="checkout-keypad"', false)
            ->assertSeeInOrder([
                '<section class="panel sale-checkout-panel">',
                'class="sale-payment-grid"',
                'data-pos-area="checkout-keypad"',
                'id="fill-total"',
                'Checkout',
            ], false)
            ->assertSee('bill-table-head', false)
            ->assertSee('bill-table', false)
            ->assertSee('--cart-grid-columns: minmax(280px, 1fr) 170px 140px 140px 88px', false)
            ->assertSee('grid-template-columns: var(--cart-grid-columns)', false)
            ->assertSee('role="table"', false)
            ->assertSee('aria-label="Cart items"', false)
            ->assertSee('grid-template-rows: auto minmax(0, 1fr) auto', false)
            ->assertSee('max-height: calc(100vh - 300px)', false)
            ->assertSee('overscroll-behavior: contain', false)
            ->assertSee('@media (max-width: 1320px) and (min-width: 1181px)', false)
            ->assertSee('@media (max-width: 1180px) and (min-width: 901px)', false)
            ->assertSee('@media (max-width: 900px)', false)
            ->assertSee('order: 1', false)
            ->assertSee('order: 2', false)
            ->assertSee('top: 118px', false)
            ->assertSee('Product / Pack')
            ->assertSee('Qty')
            ->assertSee('Price')
            ->assertSee('Total')
            ->assertSee('Remove')
            ->assertSee('No products added yet.')
            ->assertSee('bill-cell', false)
            ->assertSee('data-qty-minus', false)
            ->assertSee('data-qty-input', false)
            ->assertSee('data-qty-step', false)
            ->assertSee('data-qty-plus', false)
            ->assertSee('data-price-input', false)
            ->assertSee('function quantityIncrement(item)', false)
            ->assertSee('minimumWholesaleQuantity > 0 ? minimumWholesaleQuantity', false)
            ->assertSee('data-keypad-input="${item.allow_fractional_quantity ? \'decimal\' : \'integer\'}"', false)
            ->assertSee('bill-line-total', false)
            ->assertSee('data-line-total', false)
            ->assertSee('function syncCartSummary()', false)
            ->assertSee('function updateCartLineTotal(index)', false)
            ->assertSee('const saleDraftKey = \'apples.pos.sale.draft\'', false)
            ->assertSee('Your previous items were restored.', false)
            ->assertSee("cartList.addEventListener('focusout'", false)
            ->assertSee('data-remove-index', false)
            ->assertSee('title="Remove item"', false)
            ->assertSee('>Remove</button>', false)
            ->assertSee('cart.unshift', false)
            ->assertSee('cart.splice(existingIndex, 1)', false)
            ->assertSee('cartList.scrollTop = 0', false)
            ->assertSee('<strong>${item.label}</strong>', false)
            ->assertDontSee('<span class="readiness-chip">${escapeHtml(item.part_number || \'Ready\')}</span>', false);
    }

    public function test_sale_create_limits_initial_pos_product_payload_and_uses_server_search(): void
    {
        $category = Category::create(['name' => 'FAST POS CATEGORY']);

        foreach (range(1, 30) as $index) {
            $product = Product::create([
                'name' => sprintf('POS SPEED PRODUCT %02d', $index),
                'code' => sprintf('POS-SPEED-%02d', $index),
                'category_id' => $category->id,
                'is_active' => true,
                'base_unit_label' => 'Pieces',
            ]);

            ProductUnit::create([
                'product_id' => $product->id,
                'unit_name' => 'Pieces',
                'conversion_factor' => 1,
                'selling_price' => 1000 + $index,
                'cost_price' => 700,
                'is_base_unit' => true,
                'is_active' => true,
            ]);
        }

        $this->get('/sales/create')
            ->assertOk()
            ->assertSee('POS SPEED PRODUCT 01')
            ->assertDontSee('POS SPEED PRODUCT 30')
            ->assertSee('sales\/product-search', false);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/sales/product-search?q=POS-SPEED-30')
            ->assertOk()
            ->assertJsonPath('results.0.product_name', 'POS SPEED PRODUCT 30')
            ->assertJsonPath('results.0.unit_name', 'Pieces')
            ->assertJsonPath('results.0.base_stock_label', '0 Pieces');
    }

    public function test_pos_search_shows_base_stock_and_sells_pieces_after_carton_purchase(): void
    {
        $store = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $this->app['auth']->user()->update(['default_store_id' => $store->id]);
        $walkInCustomer = Customer::create(['name' => 'Walk-in Customer', 'is_walk_in' => true, 'is_system' => true, 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Soap Supplier', 'is_active' => true]);
        $paymentMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $product = Product::create(['name' => 'DETTOL ACTIVE SOAP 90G', 'code' => 'IMP000013', 'is_active' => true]);
        $carton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Cartons',
            'conversion_factor' => 24,
            'selling_price' => 298000,
            'cost_price' => 240000,
            'is_active' => true,
        ]);
        $piece = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pieces',
            'conversion_factor' => 1,
            'selling_price' => 3500,
            'cost_price' => 2500,
            'is_active' => true,
            'is_base_unit' => true,
        ]);

        $purchaseResponse = $this->post('/purchases', [
            'purchase_date' => '2026-07-11',
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'purchase_type' => 'cash',
            'payment_mode_id' => $paymentMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'amount_paid' => 2400000,
            'items' => [
                ['product_unit_id' => $carton->id, 'quantity' => 10, 'unit_cost' => 240000],
            ],
        ]);

        $purchase = Purchase::query()->firstOrFail();
        $purchaseResponse->assertRedirect('/purchases/'.$purchase->id);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase',
            'product_unit_id' => $carton->id,
            'quantity_in' => 10,
            'base_quantity_in' => 240,
            'conversion_factor_snapshot' => 24,
        ]);

        $this->get('/sales/create')
            ->assertOk()
            ->assertSee('DETTOL ACTIVE SOAP 90G - Pieces')
            ->assertSee('"unit_name":"Pieces"', false)
            ->assertSee('"base_stock_label":"240 Pieces"', false)
            ->assertSee('"units_available_label":"Cartons, Pieces"', false)
            ->assertSee('Available:', false)
            ->assertSee('Units:', false)
            ->assertSee('data-add-unit', false);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/sales/product-search?store_id='.$store->id.'&q=DETTOL')
            ->assertOk()
            ->assertJsonFragment([
                'product_name' => 'DETTOL ACTIVE SOAP 90G',
                'unit_name' => 'Pieces',
                'base_stock_label' => '240 Pieces',
                'units_available_label' => 'Cartons, Pieces',
            ]);

        $saleResponse = $this->post('/sales', [
            'sale_date' => '2026-07-11',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 17500,
            'items' => [
                ['product_unit_id' => $piece->id, 'quantity' => 5, 'unit_price' => 3500],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $saleResponse->assertRedirect('/sales/'.$sale->id);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_unit_id' => $piece->id,
            'quantity' => 5,
            'base_quantity' => 5,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'sale',
            'product_unit_id' => $piece->id,
            'quantity_out' => 5,
            'base_quantity_out' => 5,
        ]);

        $failedSaleResponse = $this->from('/sales/create')->post('/sales', [
            'sale_date' => '2026-07-11',
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'customer_id' => $walkInCustomer->id,
            'payment_mode_id' => $paymentMode->id,
            'amount_paid' => 1050000,
            'items' => [
                ['product_unit_id' => $piece->id, 'quantity' => 300, 'unit_price' => 3500],
            ],
        ]);

        $failedSaleResponse
            ->assertRedirect('/sales/create')
            ->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_permissions_matrix_can_be_updated_from_one_page(): void
    {
        $managerRole = \App\Models\Role::query()->create([
            'name' => 'manager',
            'description' => 'Manager',
            'permissions' => ['dashboard.view'],
        ]);
        $cashierRole = \App\Models\Role::query()->create([
            'name' => 'cashier',
            'description' => 'Cashier',
            'permissions' => ['dashboard.view', 'sales.view'],
        ]);
        \App\Models\Role::query()->firstOrCreate([
            'name' => 'admin',
        ], [
            'description' => 'Admin',
            'permissions' => ['*'],
        ]);

        $this->get('/permissions')
            ->assertOk()
            ->assertSee('Permissions Matrix')
            ->assertSee('dashboard.view')
            ->assertSee('Manager')
            ->assertSee('Cashier');

        $this->post('/permissions', [
            'matrix' => [
                $managerRole->id => [
                    'dashboard.view' => 1,
                    'reports.view' => 1,
                    'sales.view' => 1,
                ],
                $cashierRole->id => [
                    'dashboard.view' => 1,
                    'customer_payments.manage' => 1,
                ],
            ],
        ])->assertRedirect('/permissions');

        $managerRole->refresh();
        $cashierRole->refresh();

        $this->assertEqualsCanonicalizing(['dashboard.view', 'sales.view', 'reports.view'], $managerRole->permissionList());
        $this->assertEqualsCanonicalizing(['dashboard.view', 'customer_payments.manage'], $cashierRole->permissionList());
    }

    public function test_end_to_end_supermarket_workflow_keeps_stock_documents_and_reports_in_sync(): void
    {
        $storeMain = Store::create(['name' => 'Main Store', 'is_active' => true]);
        $storeBranch = Store::create(['name' => 'Branch Store', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Daily Customer', 'phone' => '0700111222', 'location' => 'Kampala', 'allow_credit_sales' => true, 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Daily Supplier', 'phone' => '0700222333', 'country' => 'Uganda', 'is_active' => true]);
        $cashMode = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $mobileMode = PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Sugar',
            'supplier_id' => $supplier->id,
            'reorder_level' => 3,
            'is_active' => true,
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pack',
            'cost_price' => 3000,
            'selling_price' => 5000,
            'barcode' => '1234567890123',
            'is_active' => true,
            'is_pos_unit' => true,
        ]);

        $purchaseResponse = $this->post('/purchases', [
            'purchase_date' => '2026-04-01',
            'store_id' => $storeMain->id,
            'supplier_id' => $supplier->id,
            'payment_mode_id' => $cashMode->id,
            'purchase_funding_source_id' => $this->businessCashFundingSource()->id,
            'amount_paid' => 16000,
            'credit_period_days' => 7,
            'supplier_invoice_no' => 'SUP-001',
            'remarks' => 'Opening stock delivery',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 12, 'unit_cost' => 3000],
            ],
        ]);

        $purchase = Purchase::query()->firstOrFail();
        $purchaseResponse->assertRedirect('/purchases/'.$purchase->id);
        $purchaseResponse->assertSessionMissing('auto_print_document');
        $this->assertSame('credit', $purchase->purchase_type);
        $this->assertEquals(36000.0, (float) $purchase->total_amount);
        $this->assertEquals(20000.0, (float) $purchase->balance_due);

        $supplierPaymentResponse = $this->post('/supplier-payments', [
            'payment_date' => '2026-04-02',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'payment_mode_id' => $cashMode->id,
            'amount' => 11000,
            'reference_no' => 'SUP-PAY-001',
            'remarks' => 'Part payment to supplier',
        ]);

        $supplierPayment = \App\Models\SupplierPayment::query()->firstOrFail();
        $supplierPaymentResponse->assertRedirect('/supplier-payments/'.$supplierPayment->id);
        $supplierPaymentResponse->assertSessionMissing('auto_print_receipt');

        $purchase->refresh();
        $this->assertEquals(9000.0, (float) $purchase->balance_due);

        $saleResponse = $this->post('/sales', [
            'sale_date' => '2026-04-02',
            'store_id' => $storeMain->id,
            'customer_id' => $customer->id,
            'payment_mode_id' => $mobileMode->id,
            'amount_paid' => 8000,
            'credit_period_days' => 7,
            'remarks' => 'Customer picked mixed groceries',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 4, 'unit_price' => 5000],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $saleResponse->assertRedirect('/sales/'.$sale->id);
        $saleResponse->assertSessionMissing('auto_print_document');
        $this->assertSame('credit', $sale->sale_type);
        $this->assertEquals(20000.0, (float) $sale->total_amount);
        $this->assertEquals(12000.0, (float) $sale->balance_due);

        $customerPaymentResponse = $this->post('/customer-payments', [
            'payment_date' => '2026-04-03',
            'customer_id' => $customer->id,
            'account_reference_type' => 'sale',
            'sale_id' => $sale->id,
            'payment_mode_id' => $cashMode->id,
            'amount' => 5000,
            'reference_no' => 'CUST-PAY-001',
            'remarks' => 'Customer paid part of balance',
        ]);

        $customerPayment = \App\Models\CustomerPayment::query()->firstOrFail();
        $customerPaymentResponse->assertRedirect('/customer-payments/'.$customerPayment->id);
        $customerPaymentResponse->assertSessionMissing('auto_print_receipt');

        $sale->refresh();
        $this->assertEquals(7000.0, (float) $sale->balance_due);

        $saleItem = $sale->items()->firstOrFail();
        $saleReturnResponse = $this->post('/sales/'.$sale->id.'/returns', [
            'return_date' => '2026-04-04',
            'return_type' => 'credit_note',
            'payment_mode_id' => $cashMode->id,
            'remarks' => 'One pack returned in good condition',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 1],
            ],
        ]);

        $saleReturn = \App\Models\SaleReturn::query()->firstOrFail();
        $saleReturnResponse->assertRedirect('/sale-returns/'.$saleReturn->id);
        $saleReturnResponse->assertSessionMissing('auto_print_document');

        $sale->refresh();
        $this->assertEquals(2000.0, (float) $sale->balance_due);

        $transferResponse = $this->post('/stock/transfers', [
            'transfer_date' => '2026-04-04',
            'from_store_id' => $storeMain->id,
            'to_store_id' => $storeBranch->id,
            'remarks' => 'Move packs to branch',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2],
            ],
        ]);

        $transferResponse->assertRedirect('/stock/transfers/TRF-20260404-0001');
        $transferResponse->assertSessionMissing('auto_print_document');

        $adjustmentResponse = $this->post('/stock/adjustments', [
            'adjustment_date' => '2026-04-04',
            'store_id' => $storeMain->id,
            'adjustment_type' => 'decrease',
            'remarks' => 'Damaged pack removed',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1],
            ],
        ]);

        $adjustmentResponse->assertRedirect('/stock/adjustments/ADJ-20260404-0001');
        $adjustmentResponse->assertSessionMissing('auto_print_document');

        $countResponse = $this->post('/stock/counts', [
            'action' => 'post',
            'count_date' => '2026-04-05',
            'store_id' => $storeMain->id,
            'assigned_user_id' => $this->app['auth']->id(),
            'section_name' => 'Front Rack',
            'remarks' => 'Evening recount',
            'items' => [
                ['product_unit_id' => $unit->id, 'physical_count' => 5, 'is_counted' => 1],
            ],
        ]);

        $countResponse->assertRedirect('/stock/counts/CNT-20260405-0001');
        $countResponse->assertSessionMissing('auto_print_document');

        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase',
            'reference_no' => $purchase->purchase_no,
            'movement_type' => 'purchase',
            'product_unit_id' => $unit->id,
            'quantity_in' => 12,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'sale',
            'reference_no' => $sale->sale_no,
            'movement_type' => 'sale',
            'product_unit_id' => $unit->id,
            'quantity_out' => 4,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'sale_return',
            'reference_no' => $saleReturn->return_no,
            'movement_type' => 'sale_return',
            'product_unit_id' => $unit->id,
            'quantity_in' => 1,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_transfer',
            'reference_no' => 'TRF-20260404-0001',
            'movement_type' => 'transfer_out',
            'product_unit_id' => $unit->id,
            'quantity_out' => 2,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_adjustment',
            'reference_no' => 'ADJ-20260404-0001',
            'movement_type' => 'adjustment_out',
            'product_unit_id' => $unit->id,
            'quantity_out' => 1,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_count',
            'reference_no' => 'CNT-20260405-0001',
            'movement_type' => 'count_out',
            'product_unit_id' => $unit->id,
            'quantity_out' => 1,
        ]);

        $this->get('/sales/'.$sale->id)->assertOk()->assertSee($sale->sale_no)->assertSee('Daily Customer');
        $this->get('/sales/'.$sale->id.'/print')->assertOk()->assertSee($sale->sale_no)->assertSee('CREDIT SALE INVOICE');
        $this->get('/sales/'.$sale->id.'/print?theme=full')->assertOk()->assertSee($sale->sale_no)->assertSee('Sales Invoice');
        $this->get('/purchases/'.$purchase->id)->assertOk()->assertSee($purchase->purchase_no)->assertSee('Daily Supplier');
        $this->get('/purchases/'.$purchase->id.'/print')->assertOk()->assertSee($purchase->purchase_no)->assertSee('Purchase Invoice');
        $this->get('/customer-payments/'.$customerPayment->id.'/print')->assertOk()->assertSee($customerPayment->payment_no)->assertSee('Customer Payment Receipt');
        $this->get('/supplier-payments/'.$supplierPayment->id.'/print')->assertOk()->assertSee($supplierPayment->payment_no)->assertSee('Supplier Payment Voucher');
        $this->get('/sale-returns/'.$saleReturn->id.'/print')->assertOk()->assertSee($saleReturn->return_no)->assertSee('Sale Return Note');
        $this->get('/stock/transfers/TRF-20260404-0001/print')->assertOk()->assertSee('TRF-20260404-0001')->assertSee('Branch Store');
        $this->get('/stock/adjustments/ADJ-20260404-0001/print')->assertOk()->assertSee('ADJ-20260404-0001')->assertSee('Damaged pack removed');
        $this->get('/stock/counts/CNT-20260405-0001/print')->assertOk()->assertSee('Assigned:')->assertSee('Front Rack');

        $this->get('/customers/'.$customer->id.'/statement')
            ->assertOk()
            ->assertSee($sale->sale_no)
            ->assertSee($customerPayment->payment_no)
            ->assertSee($saleReturn->return_no)
            ->assertSee('2,000');

        $this->get('/suppliers/'.$supplier->id.'/statement')
            ->assertOk()
            ->assertSee($purchase->purchase_no)
            ->assertSee($supplierPayment->payment_no)
            ->assertSee('9,000');

        $this->get('/stock/items/'.$unit->id.'/history')
            ->assertOk()
            ->assertSee('Purchase')
            ->assertSee('Sale Return')
            ->assertSee('Physical Count');

        $this->get('/stock/balances?q=Sugar')
            ->assertOk()
            ->assertSee('Sugar');

        $this->get('/reports/customer-aging')
            ->assertOk()
            ->assertSee('Daily Customer')
            ->assertSee('2,000');

        $this->get('/reports/supplier-aging')
            ->assertOk()
            ->assertSee('Daily Supplier')
            ->assertSee('9,000');

        $this->get('/reports/financial-summary?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertSee('Financial Summary')
            ->assertSee('36,000')
            ->assertSee('20,000');
    }

    public function test_stock_adjustment_create_defaults_to_increase_and_prefills_product(): void
    {
        $product = Product::create(['name' => 'Opening Stock Soap', 'is_active' => true]);
        ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Carton',
            'cost_price' => 12000,
            'selling_price' => 15000,
            'is_active' => true,
        ]);

        $this->get('/stock/adjustments/create?product_id='.$product->id)
            ->assertOk()
            ->assertSee('Opening Stock Soap - Carton')
            ->assertSee('value="increase" selected', false);
    }

    public function test_decrease_stock_adjustment_requires_reason(): void
    {
        [$store, $unit] = $this->stockAdjustmentFixture();
        $this->seedStock($store, $unit, 5);

        $this->from('/stock/adjustments/create')->post('/stock/adjustments', [
            'adjustment_date' => '2026-07-28',
            'store_id' => $store->id,
            'adjustment_type' => 'decrease',
            'remarks' => '',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1],
            ],
        ])
            ->assertRedirect('/stock/adjustments/create')
            ->assertSessionHasErrors('remarks');

        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_type' => 'stock_adjustment',
            'movement_type' => 'adjustment_out',
        ]);

        $this->get('/stock/adjustments/create')
            ->assertOk()
            ->assertSee($unit->product->name.' - '.$unit->unit_name)
            ->assertSee('function syncCartState()', false)
            ->assertSee("cartList.addEventListener('focusout'", false)
            ->assertSee('data-qty', false);
    }

    public function test_non_admin_cannot_post_decrease_stock_adjustment(): void
    {
        [$store, $unit] = $this->stockAdjustmentFixture();
        $this->seedStock($store, $unit, 5);
        $stockClerk = $this->signInAsRole('stock_clerk');
        $stockClerk->update(['default_store_id' => $store->id]);

        $this->from('/stock/adjustments/create')->post('/stock/adjustments', [
            'adjustment_date' => '2026-07-28',
            'store_id' => $store->id,
            'adjustment_type' => 'decrease',
            'remarks' => 'Damaged stock removed',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1],
            ],
        ])
            ->assertRedirect('/stock/adjustments/create')
            ->assertSessionHasErrors('adjustment_type');

        $this->assertDatabaseMissing('inventory_transactions', [
            'reference_type' => 'stock_adjustment',
            'movement_type' => 'adjustment_out',
        ]);
    }

    public function test_admin_can_post_decrease_stock_adjustment_with_reason(): void
    {
        [$store, $unit] = $this->stockAdjustmentFixture();
        $this->seedStock($store, $unit, 5);

        $this->post('/stock/adjustments', [
            'adjustment_date' => '2026-07-28',
            'store_id' => $store->id,
            'adjustment_type' => 'decrease',
            'remarks' => 'Expired units removed after count',
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 1],
            ],
        ])
            ->assertRedirect('/stock/adjustments/ADJ-20260728-0001');

        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_adjustment',
            'reference_no' => 'ADJ-20260728-0001',
            'movement_type' => 'adjustment_out',
            'remarks' => 'Expired units removed after count',
        ]);
    }

    public function test_opening_stock_entry_page_loads_and_is_linked_from_stock_balance(): void
    {
        [$store, $unit] = $this->stockAdjustmentFixture();

        $this->get('/stock/balances?store_id='.$store->id)
            ->assertOk()
            ->assertSee('Add Existing Stock')
            ->assertSee(route('stock.opening-stock.create', [], false), false);

        $this->get('/management-centre')
            ->assertOk()
            ->assertSee('Add Existing Stock')
            ->assertSee('Enter old shop stock without creating supplier debt.');

        $this->get('/stock/opening-stock/create?product_id='.$unit->product_id)
            ->assertOk()
            ->assertSee('Opening Stock Entry')
            ->assertSee('EXISTING / OLD STOCK MODE')
            ->assertSee('You are entering stock that was already physically in the shop before this system started. This is not a new purchase or delivery.')
            ->assertSee('Use this when stock already existed in the shop before the system started. It increases stock without creating supplier debt.')
            ->assertSee('Old / Existing Stock Being Added')
            ->assertSee('Add as Old Stock')
            ->assertSee('OLD STOCK')
            ->assertSee('Old / Existing Stock In')
            ->assertSee('Post Old / Existing Stock')
            ->assertSee('You are about to add these quantities as old/existing stock.', false)
            ->assertSee('No purchase, supplier payment, or supplier balance will be created.', false)
            ->assertSee('Existing stock before system start')
            ->assertSee('Adjustment Safety Product')
            ->assertDontSee('<option value="decrease"', false);
    }

    public function test_opening_stock_entry_posts_stock_in_without_purchase_or_supplier_debt(): void
    {
        $store = Store::query()->firstOrCreate(['name' => 'Apples Of Gold'], ['is_active' => true]);
        $product = Product::create(['name' => 'Opening Carton Product', 'base_unit_label' => 'Pieces', 'is_active' => true]);
        $carton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Carton',
            'conversion_factor' => 24,
            'cost_price' => 24000,
            'selling_price' => 30000,
            'is_active' => true,
        ]);

        $this->post('/stock/opening-stock', [
            'adjustment_date' => '2026-07-28',
            'store_id' => $store->id,
            'remarks' => 'Existing stock before system start',
            'opening_reference' => 'Shelf sheet 1',
            'items' => [
                ['product_unit_id' => $carton->id, 'quantity' => 2],
            ],
        ])
            ->assertRedirect('/stock/adjustments/ADJ-20260728-0001')
            ->assertSessionMissing('auto_print_document');

        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'stock_adjustment',
            'reference_no' => 'ADJ-20260728-0001',
            'movement_type' => 'opening_stock',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_unit_id' => $carton->id,
            'quantity_in' => 2,
            'quantity_out' => 0,
            'base_quantity_in' => 48,
            'base_quantity_out' => 0,
            'conversion_factor_snapshot' => 24,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'stock_opening.posted',
        ]);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('purchase_items', 0);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertSame(0.0, (float) Purchase::query()->sum('balance_due'));
        $this->assertSame(0.0, (float) Supplier::query()->sum('opening_balance'));

        $this->get('/stock/balances?store_id='.$store->id.'&q=Opening Carton Product')
            ->assertOk()
            ->assertSee('Base Stock 48 pieces')
            ->assertSee('Breakdown: 2 cartons');
    }

    private function stockAdjustmentFixture(): array
    {
        $store = Store::query()->firstOrCreate(['name' => 'Apples Of Gold'], ['is_active' => true]);
        $product = Product::create(['name' => 'Adjustment Safety Product '.uniqid(), 'is_active' => true]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Piece',
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);

        return [$store, $unit];
    }
}
