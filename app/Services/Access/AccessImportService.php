<?php

namespace App\Services\Access;

use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessImportService
{
    public function __construct(
        private readonly AccessMdbReader $reader
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function import(string $databasePath, bool $includeTransactions = true): array
    {
        $summary = [];

        $summary['stores'] = $this->importStores($databasePath);
        $summary['payment_modes'] = $this->importPaymentModes($databasePath);
        $summary['categories'] = $this->importCategories($databasePath);
        $summary['customers'] = $this->importCustomers($databasePath);
        $summary['suppliers'] = $this->importSuppliers($databasePath);
        $summary['products'] = $this->importProducts($databasePath);
        $summary['product_units'] = $this->importProductUnits($databasePath);
        $summary['users'] = $this->importUsers($databasePath);

        if ($includeTransactions) {
            $salesSummary = $this->importSales($databasePath);
            $purchaseSummary = $this->importPurchases($databasePath);
            $summary['sales'] = $salesSummary['sales'];
            $summary['sale_items'] = $salesSummary['sale_items'];
            $summary['customer_payments'] = $salesSummary['customer_payments'];
            $summary['purchases'] = $purchaseSummary['purchases'];
            $summary['purchase_items'] = $purchaseSummary['purchase_items'];
            $summary['supplier_payments'] = $purchaseSummary['supplier_payments'];
            $summary['inventory_transactions'] = $this->importInventoryTransactions($databasePath);
        }

        return $summary;
    }

    public function clearData(bool $includeTransactions = true): void
    {
        $tables = $includeTransactions
            ? ['capital_entries', 'inventory_transactions', 'supplier_payments', 'purchase_items', 'purchases', 'customer_payments', 'sale_items', 'sales', 'product_units', 'products', 'categories', 'payment_modes', 'suppliers', 'customers', 'stores', 'users']
            : ['product_units', 'products', 'categories', 'payment_modes', 'suppliers', 'customers', 'stores', 'users'];

        DB::transaction(function () use ($tables): void {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            }

            foreach ($tables as $table) {
                DB::table($table)->delete();
            }

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        });
    }

    private function importStores(string $databasePath): int
    {
        $count = 0;

        foreach ($this->reader->table($databasePath, 'TblStores') as $row) {
            Store::updateOrCreate(
                ['legacy_store_id' => $this->intValue($row['StoreID'] ?? null)],
                [
                    'name' => $this->stringOrFallback($row['StoreName'] ?? null, 'Store '.($row['StoreID'] ?? 'Unknown')),
                    'location' => $this->nullableString($row['StoreLocation'] ?? null),
                    'in_charge_name' => $this->nullableString($row['StoreInCharge'] ?? null),
                    'is_active' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function importUsers(string $databasePath): int
    {
        $count = 0;
        $departmentRows = collect($this->reader->table($databasePath, 'TblUserLevels'));
        $departments = $departmentRows->mapWithKeys(function (array $row): array {
            return [
                (int) ($row['DepartmentID'] ?? 0) => [
                    'department' => $this->nullableString($row['Department'] ?? null),
                    'description' => $this->nullableString($row['Description'] ?? null),
                ],
            ];
        })->all();
        $rolesByName = Role::query()->pluck('id', 'name')->all();

        foreach ($this->reader->table($databasePath, 'TblUsers') as $row) {
            $legacyLoginId = $this->intValue($row['LoginID'] ?? null);
            $username = $this->stringOrFallback($row['UserName'] ?? null, 'legacy_user_'.$legacyLoginId);
            $legacyDepartmentId = $this->intValue($row['DepartmentID'] ?? null);
            $departmentName = $departments[$legacyDepartmentId]['department'] ?? null;
            $roleId = $this->resolveRoleIdForLegacyUser($departmentName, $row['Kind'] ?? null, $row, $rolesByName);
            $password = $this->nullableString($row['UserPWD'] ?? null) ?? Str::password(16);

            User::updateOrCreate(
                ['legacy_login_id' => $legacyLoginId],
                [
                    'role_id' => $roleId,
                    'default_store_id' => null,
                    'legacy_user_id' => $this->intValue($row['UserID'] ?? null),
                    'legacy_department_id' => $legacyDepartmentId,
                    'legacy_owner_user_id' => $this->intValue($row['User'] ?? null),
                    'legacy_kind' => $this->nullableString($row['Kind'] ?? null),
                    'name' => $username,
                    'username' => $username,
                    'email' => $this->makeLegacyUserEmail($username, $legacyLoginId),
                    'password' => $password,
                    'is_active' => true,
                    'can_open' => $this->boolValue($row['Open'] ?? false),
                    'can_add' => $this->boolValue($row['Add'] ?? false),
                    'can_edit' => $this->boolValue($row['Edit'] ?? false),
                    'can_delete' => $this->boolValue($row['Delete'] ?? false),
                    'is_legacy_user' => true,
                    'email_verified_at' => now(),
                ]
            );

            $count++;
        }

        return $count;
    }

    private function importPaymentModes(string $databasePath): int
    {
        $count = 0;

        foreach ($this->reader->table($databasePath, 'TblPayModes') as $row) {
            $legacyId = $this->intValue($row['TypeID'] ?? null);
            $name = $this->stringOrFallback($row['PType'] ?? null, 'Mode '.($row['TypeID'] ?? 'Unknown'));

            $mode = PaymentMode::query()
                ->when($legacyId, fn ($query) => $query->where('legacy_type_id', $legacyId))
                ->orWhere('name', $name)
                ->first() ?? new PaymentMode();

            $mode->fill([
                'legacy_type_id' => $legacyId,
                'name' => $name,
                'account_no' => $this->nullableString($row['AcNo'] ?? null),
                'is_active' => true,
            ])->save();

            $count++;
        }

        return $count;
    }

    private function importCategories(string $databasePath): int
    {
        $count = 0;

        foreach ($this->reader->table($databasePath, 'TblStockItemCategories') as $row) {
            Category::updateOrCreate(
                ['legacy_category_id' => $this->intValue($row['CategoryID'] ?? null)],
                [
                    'name' => $this->stringOrFallback($row['CategoryName'] ?? null, 'Category '.($row['CategoryID'] ?? 'Unknown')),
                    'is_active' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function importCustomers(string $databasePath): int
    {
        $count = 0;

        foreach ($this->reader->table($databasePath, 'TblCustomers') as $row) {
            $rawName = $this->stringOrFallback($row['CustomerName'] ?? null, 'Customer '.($row['CustomerID'] ?? 'Unknown'));
            $rawNormalized = $this->normalize($rawName);
            $name = $rawNormalized === 'CASH SALE' ? 'Walk-in Customer' : $rawName;
            $isWalkIn = $rawNormalized === 'CASH SALE' || $this->normalize($name) === 'WALK-IN CUSTOMER';
            $legacyId = $this->intValue($row['CustomerID'] ?? null);

            $customer = Customer::query()
                ->when($legacyId, fn ($query) => $query->where('legacy_customer_id', $legacyId))
                ->orWhere('name', $name)
                ->first() ?? new Customer();

            $customer->fill([
                'legacy_customer_id' => $legacyId,
                'name' => $name,
                'phone' => $this->extractPhoneLikeValue($row['PhoneNumber'] ?? null),
                'email' => $this->normalizeEmail($row['EmailAddress'] ?? null),
                'fax' => $this->nullableString($row['FaxNumber'] ?? null),
                'address' => $this->nullableString($row['Address'] ?? null),
                'location' => $this->nullableString($row['Location'] ?? null),
                'opening_balance' => $this->money($row['OpeningBalance'] ?? 0),
                'credit_limit' => $this->money($row['CreditLimit'] ?? 0),
                'customer_type' => $this->nullableString($row['Kind'] ?? null),
                'is_walk_in' => $isWalkIn,
                'is_system' => $isWalkIn,
                'is_active' => ! $this->boolValue($row['Inactive'] ?? false),
            ])->save();

            $count++;
        }

        Customer::updateOrCreate(['name' => 'Unknown Customer'], ['is_system' => true, 'is_active' => true]);

        return $count;
    }

    private function importSuppliers(string $databasePath): int
    {
        $count = 0;

        foreach ($this->reader->table($databasePath, 'TblSuppliers') as $row) {
            $name = $this->stringOrFallback($row['SupplierName'] ?? null, 'Supplier '.($row['SupplierID'] ?? 'Unknown'));
            $normalized = $this->normalize($name);
            $legacyId = $this->intValue($row['SupplierID'] ?? null);

            $supplier = Supplier::query()
                ->when($legacyId, fn ($query) => $query->where('legacy_supplier_id', $legacyId))
                ->orWhere('name', $name)
                ->first() ?? new Supplier();

            $supplier->fill([
                'legacy_supplier_id' => $legacyId,
                'name' => $name,
                'email' => $this->normalizeEmail($row['EmailAddress'] ?? null),
                'phone' => $this->extractPhoneLikeValue($row['PhoneNumber'] ?? ($row['PostalCode'] ?? null)),
                'tin' => $this->nullableString($row['TIN'] ?? null),
                'address' => $this->nullableString($row['Address'] ?? null),
                'postal_code' => $this->extractPhoneLikeValue($row['PostalCode'] ?? null) ? null : $this->nullableString($row['PostalCode'] ?? null),
                'country' => $this->nullableString($row['Country'] ?? null),
                'payment_terms_days' => $this->intValue($row['PaymentTerms'] ?? null),
                'opening_balance' => $this->money($row['SupplierBalances'] ?? 0),
                'supplier_type' => $this->nullableString($row['Kind'] ?? null),
                'is_system' => in_array($normalized, ['OTHERS', 'OUT PURCHASE'], true),
                'is_active' => true,
                'notes' => $this->nullableString($row['Notes'] ?? null),
            ])->save();

            $count++;
        }

        foreach (['OTHERS', 'OUT PURCHASE'] as $name) {
            Supplier::updateOrCreate(['name' => $name], ['is_system' => true, 'is_active' => true]);
        }

        return $count;
    }

    private function importProducts(string $databasePath): int
    {
        $categoriesByLegacy = Category::pluck('id', 'legacy_category_id')->all();
        $categoriesByName = Category::get(['id', 'name'])->mapWithKeys(fn (Category $category) => [$this->normalize($category->name) => $category->id])->all();
        $suppliersByName = Supplier::get(['id', 'name'])->mapWithKeys(fn (Supplier $supplier) => [$this->normalize($supplier->name) => $supplier->id])->all();
        $count = 0;

        foreach ($this->reader->table($databasePath, 'TblStockItems') as $row) {
            Product::updateOrCreate(
                ['legacy_product_id' => $this->intValue($row['ProductID'] ?? null)],
                [
                    'name' => $this->stringOrFallback($row['Item'] ?? null, 'Product '.($row['ProductID'] ?? 'Unknown')),
                    'code' => $this->nullableString($row['Code'] ?? null),
                    'category_id' => $this->resolveCategoryId($row['CategoryID'] ?? null, $categoriesByLegacy, $categoriesByName),
                    'supplier_id' => $this->resolveSupplierId($row['Supplier'] ?? null, $suppliersByName),
                    'item_group' => $this->nullableString($row['ItemGroup'] ?? ($row['Group'] ?? null)),
                    'base_cost_price' => $this->money($row['PCostPrice'] ?? 0),
                    'reorder_level' => $this->decimal($row['ReorderLevel'] ?? 0, 3),
                    'is_vat_applicable' => $this->boolValue($row['VATC'] ?? false),
                    'is_active' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function importProductUnits(string $databasePath): int
    {
        $productsByLegacy = Product::pluck('id', 'legacy_product_id')->all();
        $count = 0;

        foreach ($this->reader->table($databasePath, 'TblStockItemsDetails') as $row) {
            $productId = $productsByLegacy[$this->intValue($row['ProductID'] ?? null)] ?? null;

            if (! $productId) {
                continue;
            }

            ProductUnit::updateOrCreate(
                ['legacy_item_id' => $this->intValue($row['ItemID'] ?? null)],
                [
                    'product_id' => $productId,
                    'unit_name' => $this->stringOrFallback($row['Unit'] ?? null, 'Unit'),
                    'conversion_factor' => $this->decimal($row['CUnit'] ?? 1, 3),
                    'selling_price' => $this->money($row['SellingPrice'] ?? 0),
                    'cost_price' => $this->money($row['CostPrice'] ?? 0),
                    'opening_stock_qty' => $this->decimal($row['OpeningStock'] ?? 0, 3),
                    'barcode' => $this->nullableString($row['BCode'] ?? null),
                    'part_number' => $this->nullableString($row['PNumber'] ?? null),
                    'is_pos_unit' => $this->boolValue($row['POS'] ?? true),
                    'is_active' => $this->boolValue($row['Active'] ?? true),
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * @return array{sales:int,sale_items:int,customer_payments:int}
     */
    private function importSales(string $databasePath): array
    {
        $storeIds = Store::pluck('id', 'legacy_store_id')->all();
        $paymentModeIds = PaymentMode::get(['id', 'name'])->mapWithKeys(fn (PaymentMode $mode) => [$this->normalize($mode->name) => $mode->id])->all();

        $cashDetailPrepared = $this->prepareSaleItems($this->reader->table($databasePath, 'TblCashSalesDetails'), 'TblCashSalesDetails', 'CashSalesDetailID', 'CashSalesID');
        $creditDetailPrepared = $this->prepareSaleItems($this->reader->table($databasePath, 'TblCreditSalesDetails'), 'TblCreditSalesDetails', 'SalesDetailID', 'SalesID');

        $payload = [];

        foreach ($this->reader->table($databasePath, 'TblCashSales') as $row) {
            $legacyId = $this->intValue($row['CashSalesID'] ?? null);
            $discount = $this->money($row['Discount'] ?? 0);
            $subtotal = $cashDetailPrepared['totals'][$legacyId] ?? 0;
            $total = max($subtotal - $discount, 0);
            $cashTendered = $this->money($row['CashTendered'] ?? 0);

            $payload[] = [
                'legacy_source_table' => 'TblCashSales',
                'legacy_source_id' => $legacyId,
                'sale_no' => 'CS-'.$legacyId,
                'sale_date' => $this->date($row['Date'] ?? null),
                'sale_time' => $this->time($row['Time'] ?? null),
                'store_id' => $storeIds[$this->intValue($row['Store'] ?? null)] ?? null,
                'customer_id' => $this->resolveCustomerId($row['Customer'] ?? 'CASH SALE'),
                'sale_type' => 'cash',
                'payment_mode_id' => $this->resolvePaymentModeId($row['Paymode'] ?? 'Cash', $paymentModeIds),
                'vat_percent' => $this->money($row['VAT%'] ?? 0),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'vat_amount' => 0,
                'total_amount' => $total,
                'amount_paid' => $total,
                'balance_due' => 0,
                'credit_period_days' => null,
                'credit_due_date' => null,
                'cash_tendered' => $cashTendered > 0 ? $cashTendered : $total,
                'change_given' => max(($cashTendered > 0 ? $cashTendered : $total) - $total, 0),
                'status' => 'posted',
                'remarks' => null,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($this->reader->table($databasePath, 'TblCreditSales') as $row) {
            $legacyId = $this->intValue($row['SalesID'] ?? null);
            $discount = $this->money($row['Discount'] ?? 0);
            $subtotal = $creditDetailPrepared['totals'][$legacyId] ?? 0;
            $total = max($subtotal - $discount, 0);
            $creditPeriod = $this->intValue($row['CreditPeriod'] ?? null);
            $saleDate = $this->date($row['Date'] ?? null);

            $payload[] = [
                'legacy_source_table' => 'TblCreditSales',
                'legacy_source_id' => $legacyId,
                'sale_no' => 'CR-'.$legacyId,
                'sale_date' => $saleDate,
                'sale_time' => $this->time($row['Time'] ?? null),
                'store_id' => $storeIds[$this->intValue($row['Store'] ?? null)] ?? null,
                'customer_id' => $this->resolveCustomerId($row['Customer'] ?? null, true),
                'sale_type' => 'credit',
                'payment_mode_id' => $this->resolvePaymentModeId('Credit', $paymentModeIds),
                'vat_percent' => $this->money($row['VAT%'] ?? 0),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'vat_amount' => 0,
                'total_amount' => $total,
                'amount_paid' => 0,
                'balance_due' => $total,
                'credit_period_days' => $creditPeriod,
                'credit_due_date' => $creditPeriod && $saleDate ? Carbon::parse($saleDate)->addDays($creditPeriod)->toDateString() : null,
                'cash_tendered' => null,
                'change_given' => 0,
                'status' => 'posted',
                'remarks' => null,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($payload, 25) as $chunk) {
            DB::table('sales')->upsert($chunk, ['sale_no'], ['store_id', 'customer_id', 'sale_type', 'payment_mode_id', 'vat_percent', 'subtotal', 'discount_amount', 'vat_amount', 'total_amount', 'amount_paid', 'balance_due', 'credit_period_days', 'credit_due_date', 'cash_tendered', 'change_given', 'status', 'updated_at']);
        }

        $saleIds = DB::table('sales')->pluck('id', 'sale_no')->all();
        $saleItems = $this->upsertSaleItems($cashDetailPrepared['items'], $saleIds, 'CS-') + $this->upsertSaleItems($creditDetailPrepared['items'], $saleIds, 'CR-');
        $customerPayments = $this->importCustomerPayments($databasePath, $storeIds, $paymentModeIds, $saleIds);

        return ['sales' => count($payload), 'sale_items' => $saleItems, 'customer_payments' => $customerPayments];
    }

    /**
     * @return array{purchases:int,purchase_items:int,supplier_payments:int}
     */
    private function importPurchases(string $databasePath): array
    {
        $storeIds = Store::pluck('id', 'legacy_store_id')->all();
        $paymentModeIds = PaymentMode::get(['id', 'name'])->mapWithKeys(fn (PaymentMode $mode) => [$this->normalize($mode->name) => $mode->id])->all();

        $cashDetailPrepared = $this->preparePurchaseItems($this->reader->table($databasePath, 'TblCashPurchasesDetails'), 'TblCashPurchasesDetails', 'CashPurchasedetailsID', 'CashPurchaseID');
        $creditDetailPrepared = $this->preparePurchaseItems($this->reader->table($databasePath, 'TblCreditPurchasesdetails'), 'TblCreditPurchasesdetails', 'PurchaseDetailsID', 'PurchaseID');

        $payload = [];

        foreach ($this->reader->table($databasePath, 'TblCashPurchases') as $row) {
            $legacyId = $this->intValue($row['CashPurchaseID'] ?? null);
            $discount = $this->money($row['Discount'] ?? 0);
            $subtotal = $cashDetailPrepared['totals'][$legacyId] ?? 0;
            $total = max($subtotal - $discount, 0);

            $payload[] = [
                'legacy_source_table' => 'TblCashPurchases',
                'legacy_source_id' => $legacyId,
                'purchase_no' => 'CP-'.$legacyId,
                'purchase_date' => $this->date($row['Date'] ?? null),
                'supplier_id' => $this->resolveSupplierIdByName($row['Supplier'] ?? null),
                'store_id' => $storeIds[$this->intValue($row['Store'] ?? null)] ?? null,
                'purchase_type' => 'cash',
                'payment_mode_id' => $this->resolvePaymentModeId('Cash', $paymentModeIds),
                'supplier_invoice_no' => $this->nullableString($row['Reference'] ?? null),
                'vat_percent' => $this->money($row['VAT%'] ?? 0),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'vat_amount' => 0,
                'total_amount' => $total,
                'amount_paid' => $total,
                'balance_due' => 0,
                'credit_period_days' => null,
                'credit_due_date' => null,
                'status' => 'posted',
                'remarks' => null,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($this->reader->table($databasePath, 'TblCreditPurchase') as $row) {
            $legacyId = $this->intValue($row['PurchaseID'] ?? null);
            $discount = $this->money($row['Discount'] ?? 0);
            $subtotal = $creditDetailPrepared['totals'][$legacyId] ?? 0;
            $total = max($subtotal - $discount, 0);
            $creditPeriod = $this->intValue($row['CreditPeriod'] ?? null);
            $purchaseDate = $this->date($row['Date'] ?? null);

            $payload[] = [
                'legacy_source_table' => 'TblCreditPurchase',
                'legacy_source_id' => $legacyId,
                'purchase_no' => 'CRP-'.$legacyId,
                'purchase_date' => $purchaseDate,
                'supplier_id' => $this->resolveSupplierIdByName($row['Supplier'] ?? null),
                'store_id' => $storeIds[$this->intValue($row['Store'] ?? null)] ?? null,
                'purchase_type' => 'credit',
                'payment_mode_id' => $this->resolvePaymentModeId('Credit', $paymentModeIds),
                'supplier_invoice_no' => $this->nullableString($row['Reference'] ?? null),
                'vat_percent' => $this->money($row['VAT%'] ?? 0),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'vat_amount' => 0,
                'total_amount' => $total,
                'amount_paid' => 0,
                'balance_due' => $total,
                'credit_period_days' => $creditPeriod,
                'credit_due_date' => $creditPeriod && $purchaseDate ? Carbon::parse($purchaseDate)->addDays($creditPeriod)->toDateString() : null,
                'status' => 'posted',
                'remarks' => null,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($payload, 25) as $chunk) {
            DB::table('purchases')->upsert($chunk, ['purchase_no'], ['supplier_id', 'store_id', 'purchase_type', 'payment_mode_id', 'supplier_invoice_no', 'vat_percent', 'subtotal', 'discount_amount', 'vat_amount', 'total_amount', 'amount_paid', 'balance_due', 'credit_period_days', 'credit_due_date', 'status', 'updated_at']);
        }

        $purchaseIds = DB::table('purchases')->pluck('id', 'purchase_no')->all();
        $purchaseItems = $this->upsertPurchaseItems($cashDetailPrepared['items'], $purchaseIds, 'CP-') + $this->upsertPurchaseItems($creditDetailPrepared['items'], $purchaseIds, 'CRP-');
        $supplierPayments = $this->importSupplierPayments($databasePath, $storeIds, $paymentModeIds);

        return ['purchases' => count($payload), 'purchase_items' => $purchaseItems, 'supplier_payments' => $supplierPayments];
    }

    private function importInventoryTransactions(string $databasePath): int
    {
        $storeIds = Store::pluck('id', 'legacy_store_id')->all();
        $units = ProductUnit::get(['id', 'legacy_item_id', 'product_id'])->keyBy('legacy_item_id');
        $sales = DB::table('sales')->get(['id', 'sale_no', 'sale_date', 'store_id'])->keyBy('id');
        $purchases = DB::table('purchases')->get(['id', 'purchase_no', 'purchase_date', 'store_id'])->keyBy('id');
        $payload = [];

        foreach ($this->reader->table($databasePath, 'TblStoresOpeningStock') as $row) {
            $unit = $units->get($this->intValue($row['ItemName'] ?? null));
            if (! $unit) {
                continue;
            }

            $payload[] = [
                'transaction_date' => $this->date($row['Date'] ?? null),
                'store_id' => $storeIds[$this->intValue($row['Store'] ?? null)] ?? null,
                'product_id' => $unit->product_id,
                'product_unit_id' => $unit->id,
                'reference_type' => 'opening_stock',
                'reference_id' => $this->intValue($row['StockID'] ?? null),
                'reference_no' => 'OS-'.$this->intValue($row['StockID'] ?? null),
                'movement_type' => 'opening_stock',
                'quantity_in' => $this->decimal($row['OpeningQty'] ?? 0, 3),
                'quantity_out' => 0,
                'unit_cost' => $this->money($row['Rate'] ?? 0),
                'unit_price' => $this->money($row['Price'] ?? 0),
                'remarks' => $this->nullableString($row['Remark'] ?? null),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (DB::table('sale_items')->get() as $item) {
            $sale = $sales->get($item->sale_id);
            if (! $sale) {
                continue;
            }

            $payload[] = [
                'transaction_date' => $sale->sale_date,
                'store_id' => $sale->store_id,
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'reference_type' => 'sale',
                'reference_id' => $item->id,
                'reference_no' => $sale->sale_no,
                'movement_type' => 'sale',
                'quantity_in' => 0,
                'quantity_out' => $item->quantity,
                'unit_cost' => $item->cost_price_snapshot,
                'unit_price' => $item->unit_price,
                'remarks' => null,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (DB::table('purchase_items')->get() as $item) {
            $purchase = $purchases->get($item->purchase_id);
            if (! $purchase) {
                continue;
            }

            $payload[] = [
                'transaction_date' => $purchase->purchase_date,
                'store_id' => $purchase->store_id,
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'reference_type' => 'purchase',
                'reference_id' => $item->id,
                'reference_no' => $purchase->purchase_no,
                'movement_type' => 'purchase',
                'quantity_in' => $item->quantity,
                'quantity_out' => 0,
                'unit_cost' => $item->unit_cost,
                'unit_price' => null,
                'remarks' => null,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $disposals = collect($this->reader->table($databasePath, 'TblStockDisposal'))->keyBy(fn (array $row) => $this->intValue($row['DisposalID'] ?? null));

        foreach ($this->reader->table($databasePath, 'TblStockDisposalDetails') as $row) {
            $header = $disposals->get($this->intValue($row['DisposalID'] ?? null));
            $unit = $units->get($this->intValue($row['StockItemID'] ?? null));
            if (! $header || ! $unit) {
                continue;
            }

            $payload[] = [
                'transaction_date' => $this->date($header['Date'] ?? null),
                'store_id' => $storeIds[$this->intValue($header['Store'] ?? null)] ?? null,
                'product_id' => $unit->product_id,
                'product_unit_id' => $unit->id,
                'reference_type' => 'disposal',
                'reference_id' => $this->intValue($row['StockDisposalDetailsID'] ?? null),
                'reference_no' => 'DSP-'.$this->intValue($header['DisposalID'] ?? null),
                'movement_type' => 'disposal',
                'quantity_in' => 0,
                'quantity_out' => $this->decimal($row['Quantity'] ?? 0, 3),
                'unit_cost' => $this->money($row['Price'] ?? 0),
                'unit_price' => null,
                'remarks' => $this->nullableString($row['reason'] ?? ($header['Remark'] ?? null)),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($payload, 50) as $chunk) {
            DB::table('inventory_transactions')->upsert($chunk, ['reference_type', 'reference_id', 'product_unit_id', 'movement_type', 'store_id'], ['transaction_date', 'quantity_in', 'quantity_out', 'unit_cost', 'unit_price', 'remarks', 'updated_at']);
        }

        return count($payload);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{items: array<int, array<string, mixed>>, totals: array<int, float>}
     */
    private function prepareSaleItems(array $rows, string $legacySourceTable, string $legacyIdField, string $headerIdField): array
    {
        $units = ProductUnit::get(['id', 'legacy_item_id', 'product_id'])->keyBy('legacy_item_id');
        $items = [];
        $totals = [];

        foreach ($rows as $row) {
            $unit = $units->get($this->intValue($row['ItemID'] ?? null));
            if (! $unit) {
                continue;
            }

            $quantity = $this->decimal($row['Quantity'] ?? 0, 3);
            $headerId = $this->intValue($row[$headerIdField] ?? null);
            $lineTotal = $this->money(($row['Price'] ?? 0) * $quantity);
            $totals[$headerId] = ($totals[$headerId] ?? 0) + $lineTotal;

            $items[] = [
                'legacy_source_table' => $legacySourceTable,
                'legacy_source_id' => $this->intValue($row[$legacyIdField] ?? null),
                'legacy_parent_id' => $headerId,
                'product_id' => $unit->product_id,
                'product_unit_id' => $unit->id,
                'quantity' => $quantity,
                'unit_price' => $this->money($row['Price'] ?? 0),
                'selling_price_snapshot' => $this->money($row['SPrice'] ?? ($row['Price'] ?? 0)),
                'cost_price_snapshot' => $this->money($row['COG'] ?? 0),
                'discount_amount' => 0,
                'vat_amount' => 0,
                'line_total' => $lineTotal,
                'remarks' => null,
            ];
        }

        return ['items' => $items, 'totals' => $totals];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{items: array<int, array<string, mixed>>, totals: array<int, float>}
     */
    private function preparePurchaseItems(array $rows, string $legacySourceTable, string $legacyIdField, string $headerIdField): array
    {
        $units = ProductUnit::get(['id', 'legacy_item_id', 'product_id'])->keyBy('legacy_item_id');
        $items = [];
        $totals = [];

        foreach ($rows as $row) {
            $unit = $units->get($this->intValue($row['ItemID'] ?? null));
            if (! $unit) {
                continue;
            }

            $quantity = $this->decimal($row['Quantity'] ?? 0, 3);
            $headerId = $this->intValue($row[$headerIdField] ?? null);
            $lineTotal = $this->money($row['TotalAmount'] ?? (($row['Price'] ?? 0) * $quantity));
            $totals[$headerId] = ($totals[$headerId] ?? 0) + $lineTotal;

            $items[] = [
                'legacy_source_table' => $legacySourceTable,
                'legacy_source_id' => $this->intValue($row[$legacyIdField] ?? null),
                'legacy_parent_id' => $headerId,
                'product_id' => $unit->product_id,
                'product_unit_id' => $unit->id,
                'description' => $this->nullableString($row['Description'] ?? null),
                'quantity' => $quantity,
                'unit_cost' => $this->money($row['Price'] ?? 0),
                'vat_amount' => $this->money($row['VATAmount'] ?? 0),
                'discount_amount' => $this->money($row['SchemeDisc'] ?? 0),
                'line_total' => $lineTotal,
            ];
        }

        return ['items' => $items, 'totals' => $totals];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, int>  $saleIds
     */
    private function upsertSaleItems(array $items, array $saleIds, string $prefix): int
    {
        $payload = [];

        foreach ($items as $item) {
            $saleId = $saleIds[$prefix.$item['legacy_parent_id']] ?? null;
            if (! $saleId) {
                continue;
            }

            $payload[] = [
                'legacy_source_table' => $item['legacy_source_table'],
                'legacy_source_id' => $item['legacy_source_id'],
                'sale_id' => $saleId,
                'product_id' => $item['product_id'],
                'product_unit_id' => $item['product_unit_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'selling_price_snapshot' => $item['selling_price_snapshot'],
                'cost_price_snapshot' => $item['cost_price_snapshot'],
                'discount_amount' => $item['discount_amount'],
                'vat_amount' => $item['vat_amount'],
                'line_total' => $item['line_total'],
                'remarks' => $item['remarks'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($payload, 50) as $chunk) {
            DB::table('sale_items')->upsert($chunk, ['legacy_source_table', 'legacy_source_id'], ['sale_id', 'product_id', 'product_unit_id', 'quantity', 'unit_price', 'selling_price_snapshot', 'cost_price_snapshot', 'discount_amount', 'vat_amount', 'line_total', 'remarks', 'updated_at']);
        }

        return count($payload);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, int>  $purchaseIds
     */
    private function upsertPurchaseItems(array $items, array $purchaseIds, string $prefix): int
    {
        $payload = [];

        foreach ($items as $item) {
            $purchaseId = $purchaseIds[$prefix.$item['legacy_parent_id']] ?? null;
            if (! $purchaseId) {
                continue;
            }

            $payload[] = [
                'legacy_source_table' => $item['legacy_source_table'],
                'legacy_source_id' => $item['legacy_source_id'],
                'purchase_id' => $purchaseId,
                'product_id' => $item['product_id'],
                'product_unit_id' => $item['product_unit_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'vat_amount' => $item['vat_amount'],
                'discount_amount' => $item['discount_amount'],
                'line_total' => $item['line_total'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($payload, 50) as $chunk) {
            DB::table('purchase_items')->upsert($chunk, ['legacy_source_table', 'legacy_source_id'], ['purchase_id', 'product_id', 'product_unit_id', 'description', 'quantity', 'unit_cost', 'vat_amount', 'discount_amount', 'line_total', 'updated_at']);
        }

        return count($payload);
    }

    /**
     * @param  array<int, int>  $storeIds
     * @param  array<string, int>  $paymentModeIds
     * @param  array<string, int>  $saleIds
     */
    private function importCustomerPayments(string $databasePath, array $storeIds, array $paymentModeIds, array $saleIds): int
    {
        $payload = [];

        foreach ($this->reader->table($databasePath, 'TblCustomerbalancepayment') as $row) {
            $legacySalesId = $this->intValue($row['SalesID'] ?? null);
            $saleId = $legacySalesId ? ($saleIds['CR-'.$legacySalesId] ?? null) : null;

            $payload[] = [
                'legacy_pay_id' => $this->intValue($row['PayID'] ?? null),
                'payment_no' => 'CPY-'.$this->intValue($row['PayID'] ?? null),
                'payment_date' => $this->date($row['Date'] ?? null),
                'customer_id' => $this->resolveCustomerId($row['Customer'] ?? null, true),
                'sale_id' => $saleId,
                'store_id' => $storeIds[$this->intValue($row['Store'] ?? null)] ?? null,
                'payment_mode_id' => $this->resolvePaymentModeId($row['Mode'] ?? 'Cash', $paymentModeIds),
                'amount' => $this->money($row['Amount'] ?? 0),
                'reference_no' => $this->nullableString($row['Reference'] ?? null),
                'cheque_number' => $this->nullableString($row['ChequeNumber'] ?? null),
                'remarks' => $this->nullableString($row['Remark'] ?? null),
                'status' => 'posted',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($payload, 50) as $chunk) {
            DB::table('customer_payments')->upsert($chunk, ['payment_no'], ['customer_id', 'sale_id', 'store_id', 'payment_mode_id', 'amount', 'reference_no', 'cheque_number', 'remarks', 'updated_at']);
        }

        return count($payload);
    }

    /**
     * @param  array<int, int>  $storeIds
     * @param  array<string, int>  $paymentModeIds
     */
    private function importSupplierPayments(string $databasePath, array $storeIds, array $paymentModeIds): int
    {
        $payload = [];

        foreach ($this->reader->table($databasePath, 'TblSupplierPayments') as $row) {
            $payload[] = [
                'legacy_supplier_payment_id' => $this->intValue($row['SupplierPaymentID'] ?? null),
                'payment_no' => 'SPY-'.$this->intValue($row['SupplierPaymentID'] ?? null),
                'payment_date' => $this->date($row['Date'] ?? null),
                'supplier_id' => $this->resolveSupplierIdByName($row['Supplier'] ?? null),
                'purchase_id' => null,
                'store_id' => $storeIds[$this->intValue($row['Store'] ?? null)] ?? null,
                'payment_mode_id' => $this->resolvePaymentModeId($row['Mode'] ?? 'Cash', $paymentModeIds),
                'amount' => $this->money($row['Amount'] ?? 0),
                'supplier_invoice_no' => $this->nullableString($row['InvoiceNumber'] ?? null),
                'reference_no' => $this->nullableString($row['Reference'] ?? null),
                'cheque_number' => $this->nullableString($row['ChequeNumber'] ?? null),
                'remarks' => $this->nullableString($row['Remarks'] ?? null),
                'status' => 'posted',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($payload, 50) as $chunk) {
            DB::table('supplier_payments')->upsert($chunk, ['payment_no'], ['supplier_id', 'store_id', 'payment_mode_id', 'amount', 'supplier_invoice_no', 'reference_no', 'cheque_number', 'remarks', 'updated_at']);
        }

        return count($payload);
    }

    /**
     * @param  array<int|string, int>  $categoriesByLegacy
     * @param  array<string, int>  $categoriesByName
     */
    private function resolveCategoryId(mixed $rawCategory, array &$categoriesByLegacy, array &$categoriesByName): ?int
    {
        $legacyId = $this->intValue($rawCategory);
        if ($legacyId && isset($categoriesByLegacy[$legacyId])) {
            return $categoriesByLegacy[$legacyId];
        }

        $name = $this->nullableString($rawCategory);
        if (! $name) {
            return null;
        }

        $normalized = $this->normalize($name);
        if (isset($categoriesByName[$normalized])) {
            return $categoriesByName[$normalized];
        }

        $category = Category::create(['name' => $name, 'is_active' => true]);
        $categoriesByName[$normalized] = $category->id;

        return $category->id;
    }

    /**
     * @param  array<string, int>  $suppliersByName
     */
    private function resolveSupplierId(mixed $rawSupplier, array &$suppliersByName): ?int
    {
        $name = $this->nullableString($rawSupplier);
        if (! $name) {
            return null;
        }

        $normalized = $this->normalize($name);
        if (isset($suppliersByName[$normalized])) {
            return $suppliersByName[$normalized];
        }

        $supplier = Supplier::create(['name' => $name, 'is_active' => true]);
        $suppliersByName[$normalized] = $supplier->id;

        return $supplier->id;
    }

    private function resolveSupplierIdByName(mixed $rawSupplier): ?int
    {
        $name = $this->nullableString($rawSupplier);

        if (! $name) {
            return null;
        }

        return Supplier::firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }

    private function resolveCustomerId(mixed $rawCustomer, bool $useUnknownWhenBlank = false): ?int
    {
        $name = $this->nullableString($rawCustomer);

        if (! $name) {
            $fallback = $useUnknownWhenBlank ? 'Unknown Customer' : 'Walk-in Customer';

            return Customer::firstOrCreate(
                ['name' => $fallback],
                ['is_system' => true, 'is_walk_in' => $fallback === 'Walk-in Customer', 'is_active' => true]
            )->id;
        }

        if ($this->normalize($name) === 'CASH SALE') {
            $name = 'Walk-in Customer';
        }

        return Customer::firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }

    /**
     * @param  array<string, int>  $paymentModeIds
     */
    private function resolvePaymentModeId(mixed $rawMode, array $paymentModeIds): ?int
    {
        $name = $this->nullableString($rawMode);
        if (! $name) {
            return null;
        }

        $normalized = $this->normalize($name);
        if (isset($paymentModeIds[$normalized])) {
            return $paymentModeIds[$normalized];
        }

        return PaymentMode::firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }

    /**
     * @param  array<string, int>  $rolesByName
     * @param  array<string, mixed>  $row
     */
    private function resolveRoleIdForLegacyUser(?string $departmentName, mixed $kind, array $row, array $rolesByName): ?int
    {
        $department = $this->normalize((string) ($departmentName ?? ''));
        $kind = $this->normalize((string) ($kind ?? ''));
        $hasHighAccess = $this->boolValue($row['Add'] ?? false)
            && $this->boolValue($row['Edit'] ?? false)
            && $this->boolValue($row['Delete'] ?? false);

        $roleName = match (true) {
            $department === 'ADMIN', $kind === 'SYSTEM', $hasHighAccess => 'admin',
            $department === 'POS' => 'cashier',
            $department === 'PURCHASES' => 'stock_clerk',
            $department === 'ACCOUNTS' => 'manager',
            default => 'manager',
        };

        return $rolesByName[$roleName] ?? null;
    }

    private function makeLegacyUserEmail(string $username, ?int $legacyLoginId): string
    {
        $slug = Str::lower((string) preg_replace('/[^A-Za-z0-9]+/', '.', $username));
        $slug = trim(preg_replace('/\.{2,}/', '.', $slug) ?: 'legacy.user', '.');

        return sprintf('%s.%s@legacy.apples.local', $slug !== '' ? $slug : 'legacy.user', $legacyLoginId ?? Str::random(6));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = $this->nullableString($value);

        return $email ? Str::lower($email) : null;
    }

    private function extractPhoneLikeValue(mixed $value): ?string
    {
        $raw = $this->nullableString($value);

        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        return strlen($digits) >= 9 ? $raw : null;
    }

    private function stringOrFallback(mixed $value, string $fallback): string
    {
        return $this->nullableString($value) ?? $fallback;
    }

    private function normalize(string $value): string
    {
        return Str::upper(preg_replace('/\s+/', ' ', trim($value)) ?: '');
    }

    private function intValue(mixed $value): ?int
    {
        $value = $this->nullableString($value);

        return $value === null || ! is_numeric($value) ? null : (int) $value;
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower((string) $value), ['1', 'true', 'yes', 'on', '-1'], true);
    }

    private function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    private function decimal(mixed $value, int $precision = 3): float
    {
        return round((float) ($value ?? 0), $precision);
    }

    private function date(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    private function time(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value ? Carbon::parse($value)->toTimeString() : null;
    }
}
