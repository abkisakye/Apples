<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessSetting;
use App\Models\CashShift;
use App\Models\CapitalSource;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FollowUpAction;
use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\Sale;
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
        $response->assertSessionHas('auto_print_document', true);

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
        $response->assertSessionHas('auto_print_receipt', true);

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
        $customer = Customer::create(['name' => 'Credit Buyer', 'is_active' => true]);
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
        $response->assertSessionHas('auto_print_document', true);

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
        $response->assertSessionHas('auto_print_document', true);

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
        ])->assertSessionHasErrors('customer_id');
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

        $this->get('/sales/'.$sale->id)->assertOk()->assertSee($sale->sale_no);
        $this->get('/sales/'.$sale->id.'/print')->assertOk()->assertSee($sale->sale_no);
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
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 3, 'unit_cost' => 100000],
            ],
        ]);

        $purchase = Purchase::query()->firstOrFail();
        $response->assertRedirect('/purchases/'.$purchase->id);
        $response->assertSessionHas('auto_print_document', true);

        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_items', 1);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference_type' => 'purchase',
            'movement_type' => 'purchase',
            'product_unit_id' => $unit->id,
            'quantity_in' => 3,
        ]);
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
        $response->assertSessionHas('auto_print_receipt', true);

        $purchase->refresh();

        $this->assertEquals(500.0, (float) $purchase->amount_paid);
        $this->assertEquals(300.0, (float) $purchase->balance_due);
        $this->assertDatabaseCount('supplier_payments', 1);
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
            ->assertSee('Selling Units And Stock Position')
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
            'is_active' => 1,
        ])->assertRedirect('/customers/'.$customer->id);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Browser Customer Updated',
            'credit_limit' => 1500,
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
                    'selling_price' => 1200,
                    'cost_price' => 800,
                    'is_active' => 1,
                ],
                [
                    'unit_name' => 'Box',
                    'conversion_factor' => 12,
                    'selling_price' => 13500,
                    'cost_price' => 9200,
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
                    'selling_price' => 1500,
                    'cost_price' => 900,
                    'is_active' => 1,
                ],
                [
                    'unit_name' => 'Carton',
                    'conversion_factor' => 24,
                    'selling_price' => 28000,
                    'cost_price' => 18000,
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
        ]);
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

        $this->assertDatabaseHas('stores', ['name' => 'Downtown Branch Updated']);
        $this->assertDatabaseHas('payment_modes', ['name' => 'Mobile Money']);
        $this->assertDatabaseHas('capital_sources', ['name' => 'Business Savings']);
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
        $response->assertSessionHas('auto_print_document', true);

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
            ->assertSee('Scan barcode or code');

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
        $response->assertSessionHas('auto_print_document', true);

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
        $transferResponse->assertSessionHas('auto_print_document', true);

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
            'items' => [
                ['product_unit_id' => $unit->id, 'quantity' => 2],
            ],
        ]);

        $adjustmentResponse->assertRedirect('/stock/adjustments/ADJ-20260325-0001');
        $adjustmentResponse->assertSessionHas('auto_print_document', true);

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
        $response->assertSessionHas('auto_print_document', true);

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
            ->assertSee('Beans')
            ->assertDontSee('Rice');

        $this->get('/stock/counts/create?draft_id=1&show_status=pending')
            ->assertOk()
            ->assertSee('Beans')
            ->assertDontSee('Rice');

        $this->get('/stock/counts/create?draft_id=1&show_status=counted')
            ->assertOk()
            ->assertSee('Rice')
            ->assertDontSee('Beans');

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
            ->assertSee('Window Sales')
            ->assertSee('Gross Profit')
            ->assertSee('Trading Window Trend')
            ->assertSee('Top Selling In Window')
            ->assertSee('Payment Breakdown In Window')
            ->assertSee('Sugar')
            ->assertSee('5,600');
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

        $response = $this->post('/expenses', [
            'expense_date' => '2026-04-17',
            'store_id' => $store->id,
            'payment_mode_id' => $paymentMode->id,
            'category' => 'Transport',
            'amount' => 12000,
            'reference_no' => 'EXP-REF-1',
            'notes' => 'Morning delivery transport',
        ]);

        $expense = Expense::query()->firstOrFail();

        $response->assertRedirect('/expenses/'.$expense->id);
        $this->get('/expenses')->assertOk()->assertSee($expense->expense_no);
        $this->get('/expenses/'.$expense->id)->assertOk()->assertSee('Transport');
        $this->get('/expenses/'.$expense->id.'/print')->assertOk()->assertSee($expense->expense_no);
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
            ->assertSee('Client Demo Stores');

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

        $this->get('/sales/'.$sale->id.'/print')
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
        $customer = Customer::create(['name' => 'Daily Customer', 'phone' => '0700111222', 'location' => 'Kampala', 'is_active' => true]);
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
        $purchaseResponse->assertSessionHas('auto_print_document', true);
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
        $supplierPaymentResponse->assertSessionHas('auto_print_receipt', true);

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
        $saleResponse->assertSessionHas('auto_print_document', true);
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
        $customerPaymentResponse->assertSessionHas('auto_print_receipt', true);

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
        $saleReturnResponse->assertSessionHas('auto_print_document', true);

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
        $transferResponse->assertSessionHas('auto_print_document', true);

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
        $adjustmentResponse->assertSessionHas('auto_print_document', true);

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
        $countResponse->assertSessionHas('auto_print_document', true);

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
        $this->get('/sales/'.$sale->id.'/print')->assertOk()->assertSee($sale->sale_no)->assertSee('Sales Invoice');
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
}
