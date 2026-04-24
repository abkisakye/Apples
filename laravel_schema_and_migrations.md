# Laravel Schema And Migrations

## Scope

This schema is for the Laravel replacement of the Access system, excluding banking.

Included:

- sales
- stock
- customers
- suppliers
- purchases
- customer payments
- supplier payments
- store-level inventory
- reports
- capital input tracking

Excluded from this schema:

- bank deposits
- bank withdrawals
- bank reconciliation
- bank loan workflows

## Technical conventions

Recommended conventions for Laravel and MySQL:

- primary keys: `id`
- foreign keys: `unsignedBigInteger`
- money fields: `decimal(18,2)`
- quantity fields: `decimal(18,3)`
- rates and percentages: `decimal(8,2)`
- codes and reference numbers: `string`
- free notes: `text()->nullable()`
- status flags: `string` or `enum` depending on team preference
- every transaction table should have `created_by`, `updated_by`, `created_at`, `updated_at`

Recommended status values:

- `draft`
- `posted`
- `cancelled`

## Migration order

### Phase 1. Foundation

1. `create_roles_table`
2. `create_stores_table`
3. `add_role_and_store_fields_to_users_table`
4. `create_payment_modes_table`
5. `create_customers_table`
6. `create_suppliers_table`
7. `create_categories_table`
8. `create_products_table`
9. `create_product_units_table`

### Phase 2. Transactions

10. `create_sales_table`
11. `create_sale_items_table`
12. `create_customer_payments_table`
13. `create_purchases_table`
14. `create_purchase_items_table`
15. `create_supplier_payments_table`
16. `create_inventory_transactions_table`

### Phase 3. Capital and reports support

17. `create_capital_sources_table`
18. `create_capital_entries_table`

### Optional later

19. `create_stock_takes_table`
20. `create_stock_take_items_table`
21. `create_customer_refunds_table`
22. `create_supplier_returns_table`

## Core tables

### 1. `roles`

Purpose:

- user access grouping

Columns:

- `id`
- `name`
- `description` nullable
- timestamps

Unique:

- `name`

Suggested seed data:

- `admin`
- `manager`
- `cashier`
- `stock_clerk`

### 2. `stores`

Maps from Access:

- `TblStores`

Columns:

- `id`
- `name`
- `code` nullable
- `location` nullable
- `in_charge_name` nullable
- `is_active` boolean default true
- timestamps

Indexes:

- unique `name`
- index `is_active`

### 3. `users`

Use Laravel users table and extend it.

Extra columns:

- `role_id` nullable
- `default_store_id` nullable
- `is_active` boolean default true

Foreign keys:

- `role_id` -> `roles.id`
- `default_store_id` -> `stores.id`

### 4. `payment_modes`

Maps from Access:

- `TblPayModes`

Columns:

- `id`
- `name`
- `account_no` nullable
- `is_active` boolean default true
- timestamps

Unique:

- `name`

Examples:

- cash
- mobile money
- cheque
- credit

### 5. `customers`

Maps from Access:

- `TblCustomers`

Columns:

- `id`
- `legacy_customer_id` nullable
- `name`
- `phone` nullable
- `email` nullable
- `fax` nullable
- `address` nullable
- `location` nullable
- `opening_balance` decimal(18,2) default 0
- `credit_limit` decimal(18,2) default 0
- `customer_type` nullable
- `is_walk_in` boolean default false
- `is_system` boolean default false
- `is_active` boolean default true
- `notes` nullable
- timestamps

Indexes:

- unique `name`
- index `is_walk_in`
- index `is_system`
- index `is_active`

Special seed/system records:

- `CASH SALE`
- `Unknown Customer`

### 6. `suppliers`

Maps from Access:

- `TblSuppliers`

Columns:

- `id`
- `legacy_supplier_id` nullable
- `name`
- `phone` nullable
- `email` nullable
- `tin` nullable
- `address` nullable
- `postal_code` nullable
- `country` nullable
- `payment_terms_days` integer nullable
- `opening_balance` decimal(18,2) default 0
- `supplier_type` nullable
- `is_system` boolean default false
- `is_active` boolean default true
- `notes` nullable
- timestamps

Indexes:

- unique `name`
- index `tin`
- index `is_active`

Special system records:

- `OTHERS`
- `OUT PURCHASE`

### 7. `categories`

Maps from Access:

- `TblStockItemCategories`

Columns:

- `id`
- `legacy_category_key` nullable
- `name`
- `description` nullable
- `is_active` boolean default true
- timestamps

Unique:

- `name`

### 8. `products`

Maps from Access:

- `TblStockItems`

Columns:

- `id`
- `legacy_product_id` nullable
- `name`
- `code` nullable
- `category_id` nullable
- `supplier_id` nullable
- `item_group` nullable
- `base_cost_price` decimal(18,2) default 0
- `reorder_level` decimal(18,3) default 0
- `is_vat_applicable` boolean default false
- `is_active` boolean default true
- `notes` nullable
- timestamps

Foreign keys:

- `category_id` -> `categories.id`
- `supplier_id` -> `suppliers.id`

Indexes:

- unique `code`
- unique `name`
- index `category_id`
- index `supplier_id`
- index `is_active`

### 9. `product_units`

Maps from Access:

- `TblStockItemsDetails`

Purpose:

- store units, prices, pack definitions, and barcode-level selling options

Columns:

- `id`
- `legacy_item_id` nullable
- `product_id`
- `unit_name`
- `conversion_factor` decimal(18,3) default 1
- `selling_price` decimal(18,2) default 0
- `cost_price` decimal(18,2) default 0
- `opening_stock_qty` decimal(18,3) default 0
- `barcode` nullable
- `part_number` nullable
- `is_pos_unit` boolean default true
- `is_active` boolean default true
- timestamps

Foreign keys:

- `product_id` -> `products.id`

Indexes:

- index `product_id`
- index `barcode`
- unique `product_id + unit_name`

## Sales tables

### 10. `sales`

Maps from Access:

- `TblCashSales`
- `TblCreditSales`

Design note:

- both Access tables become one table with `sale_type`

Columns:

- `id`
- `legacy_source_table` nullable
- `legacy_source_id` nullable
- `sale_no`
- `sale_date`
- `sale_time` nullable
- `store_id`
- `customer_id` nullable
- `sale_type`
- `payment_mode_id` nullable
- `vat_percent` decimal(8,2) default 0
- `subtotal` decimal(18,2) default 0
- `discount_amount` decimal(18,2) default 0
- `vat_amount` decimal(18,2) default 0
- `total_amount` decimal(18,2) default 0
- `amount_paid` decimal(18,2) default 0
- `balance_due` decimal(18,2) default 0
- `credit_period_days` integer nullable
- `credit_due_date` nullable
- `cash_tendered` decimal(18,2) nullable
- `change_given` decimal(18,2) default 0
- `status` default `posted`
- `remarks` nullable
- `created_by` nullable
- `updated_by` nullable
- timestamps

Suggested `sale_type` values:

- `cash`
- `credit`

Foreign keys:

- `store_id` -> `stores.id`
- `customer_id` -> `customers.id`
- `payment_mode_id` -> `payment_modes.id`
- `created_by` -> `users.id`
- `updated_by` -> `users.id`

Indexes:

- unique `sale_no`
- index `sale_date`
- index `store_id`
- index `customer_id`
- index `sale_type`
- index `status`
- index `legacy_source_table + legacy_source_id`

### 11. `sale_items`

Maps from Access:

- `TblCashSalesDetails`
- `TblCreditSalesDetails`

Columns:

- `id`
- `legacy_source_table` nullable
- `legacy_source_id` nullable
- `sale_id`
- `product_id`
- `product_unit_id`
- `quantity` decimal(18,3)
- `unit_price` decimal(18,2)
- `selling_price_snapshot` decimal(18,2) nullable
- `cost_price_snapshot` decimal(18,2) default 0
- `discount_amount` decimal(18,2) default 0
- `vat_amount` decimal(18,2) default 0
- `line_total` decimal(18,2)
- `remarks` nullable
- timestamps

Foreign keys:

- `sale_id` -> `sales.id`
- `product_id` -> `products.id`
- `product_unit_id` -> `product_units.id`

Indexes:

- index `sale_id`
- index `product_id`
- index `product_unit_id`

## Customer payments

### 12. `customer_payments`

Maps from Access:

- `TblCustomerbalancepayment`

Columns:

- `id`
- `legacy_pay_id` nullable
- `payment_no`
- `payment_date`
- `customer_id`
- `sale_id` nullable
- `store_id` nullable
- `payment_mode_id` nullable
- `amount` decimal(18,2)
- `reference_no` nullable
- `cheque_number` nullable
- `remarks` nullable
- `status` default `posted`
- `created_by` nullable
- `updated_by` nullable
- timestamps

Foreign keys:

- `customer_id` -> `customers.id`
- `sale_id` -> `sales.id`
- `store_id` -> `stores.id`
- `payment_mode_id` -> `payment_modes.id`
- `created_by` -> `users.id`
- `updated_by` -> `users.id`

Indexes:

- unique `payment_no`
- index `payment_date`
- index `customer_id`
- index `sale_id`
- index `store_id`

## Purchase tables

### 13. `purchases`

Maps from Access:

- `TblCashPurchases`
- `TblCreditPurchase`

Columns:

- `id`
- `legacy_source_table` nullable
- `legacy_source_id` nullable
- `purchase_no`
- `purchase_date`
- `supplier_id`
- `store_id`
- `purchase_type`
- `payment_mode_id` nullable
- `supplier_invoice_no` nullable
- `vat_percent` decimal(8,2) default 0
- `subtotal` decimal(18,2) default 0
- `discount_amount` decimal(18,2) default 0
- `vat_amount` decimal(18,2) default 0
- `total_amount` decimal(18,2) default 0
- `amount_paid` decimal(18,2) default 0
- `balance_due` decimal(18,2) default 0
- `credit_period_days` integer nullable
- `credit_due_date` nullable
- `status` default `posted`
- `remarks` nullable
- `created_by` nullable
- `updated_by` nullable
- timestamps

Suggested `purchase_type` values:

- `cash`
- `credit`

Foreign keys:

- `supplier_id` -> `suppliers.id`
- `store_id` -> `stores.id`
- `payment_mode_id` -> `payment_modes.id`
- `created_by` -> `users.id`
- `updated_by` -> `users.id`

Indexes:

- unique `purchase_no`
- index `purchase_date`
- index `supplier_id`
- index `store_id`
- index `purchase_type`
- index `legacy_source_table + legacy_source_id`

### 14. `purchase_items`

Maps from Access:

- `TblCashPurchasesDetails`
- `TblCreditPurchasesdetails`

Columns:

- `id`
- `legacy_source_table` nullable
- `legacy_source_id` nullable
- `purchase_id`
- `product_id`
- `product_unit_id`
- `description` nullable
- `quantity` decimal(18,3)
- `unit_cost` decimal(18,2)
- `vat_amount` decimal(18,2) default 0
- `discount_amount` decimal(18,2) default 0
- `line_total` decimal(18,2)
- timestamps

Foreign keys:

- `purchase_id` -> `purchases.id`
- `product_id` -> `products.id`
- `product_unit_id` -> `product_units.id`

Indexes:

- index `purchase_id`
- index `product_id`
- index `product_unit_id`

## Supplier payments

### 15. `supplier_payments`

Maps from Access:

- `TblSupplierPayments`

Columns:

- `id`
- `legacy_supplier_payment_id` nullable
- `payment_no`
- `payment_date`
- `supplier_id`
- `purchase_id` nullable
- `store_id` nullable
- `payment_mode_id` nullable
- `amount` decimal(18,2)
- `supplier_invoice_no` nullable
- `reference_no` nullable
- `cheque_number` nullable
- `remarks` nullable
- `status` default `posted`
- `created_by` nullable
- `updated_by` nullable
- timestamps

Foreign keys:

- `supplier_id` -> `suppliers.id`
- `purchase_id` -> `purchases.id`
- `store_id` -> `stores.id`
- `payment_mode_id` -> `payment_modes.id`
- `created_by` -> `users.id`
- `updated_by` -> `users.id`

Indexes:

- unique `payment_no`
- index `payment_date`
- index `supplier_id`
- index `purchase_id`

## Inventory ledger

### 16. `inventory_transactions`

Maps from Access:

- `TblStoresOpeningStock`
- `TblCashSales` and detail lines
- `TblCreditSales` and detail lines
- `TblCashPurchases` and detail lines
- `TblCreditPurchase` and detail lines
- `TblStockDisposal`
- `TblStockDisposalDetails`
- optional later stock returns and stock issues

Design note:

- this is the stock movement ledger
- current stock should be calculated from this table, not typed manually

Columns:

- `id`
- `transaction_date`
- `store_id`
- `product_id`
- `product_unit_id`
- `reference_type`
- `reference_id` nullable
- `reference_no` nullable
- `movement_type`
- `quantity_in` decimal(18,3) default 0
- `quantity_out` decimal(18,3) default 0
- `unit_cost` decimal(18,2) default 0
- `unit_price` decimal(18,2) nullable
- `remarks` nullable
- `created_by` nullable
- timestamps

Suggested `reference_type` values:

- `opening_stock`
- `purchase`
- `sale`
- `customer_return`
- `supplier_return`
- `adjustment`
- `disposal`
- `transfer`

Suggested `movement_type` values:

- `opening_stock`
- `purchase`
- `sale`
- `adjustment_plus`
- `adjustment_minus`
- `disposal`
- `transfer_in`
- `transfer_out`
- `customer_return`
- `supplier_return`

Foreign keys:

- `store_id` -> `stores.id`
- `product_id` -> `products.id`
- `product_unit_id` -> `product_units.id`
- `created_by` -> `users.id`

Indexes:

- index `transaction_date`
- index `store_id`
- index `product_id`
- index `product_unit_id`
- index `reference_type + reference_id`
- index `movement_type`

## Capital tracking

### 17. `capital_sources`

Purpose:

- define where money introduced into the business comes from

Columns:

- `id`
- `name`
- `source_type`
- `description` nullable
- `is_active` boolean default true
- timestamps

Suggested `source_type` values:

- `business_generated`
- `owner_injection`
- `external_investor`
- `loan`
- `other`

Examples:

- retained earnings
- owner top-up
- partner contribution
- borrowed cash

Indexes:

- unique `name`
- index `source_type`

### 18. `capital_entries`

Purpose:

- record money introduced into the business and where it came from

Columns:

- `id`
- `entry_no`
- `entry_date`
- `store_id` nullable
- `capital_source_id`
- `payment_mode_id` nullable
- `amount` decimal(18,2)
- `reference_no` nullable
- `source_origin`
- `notes` nullable
- `status` default `posted`
- `created_by` nullable
- `updated_by` nullable
- timestamps

Suggested `source_origin` values:

- `inside_business`
- `outside_business`

Foreign keys:

- `store_id` -> `stores.id`
- `capital_source_id` -> `capital_sources.id`
- `payment_mode_id` -> `payment_modes.id`
- `created_by` -> `users.id`
- `updated_by` -> `users.id`

Indexes:

- unique `entry_no`
- index `entry_date`
- index `capital_source_id`
- index `source_origin`
- index `store_id`

## Optional stock-taking module

### 19. `stock_takes`

Columns:

- `id`
- `stock_take_no`
- `stock_take_date`
- `store_id`
- `status`
- `remarks` nullable
- `created_by` nullable
- `posted_by` nullable
- timestamps

### 20. `stock_take_items`

Columns:

- `id`
- `stock_take_id`
- `product_id`
- `product_unit_id`
- `system_qty` decimal(18,3) default 0
- `counted_qty` decimal(18,3) default 0
- `variance_qty` decimal(18,3) default 0
- `remarks` nullable
- timestamps

## Access import mapping summary

### Master data imports

- `TblStores` -> `stores`
- `TblCustomers` -> `customers`
- `TblSuppliers` -> `suppliers`
- `TblStockItemCategories` -> `categories`
- `TblStockItems` -> `products`
- `TblStockItemsDetails` -> `product_units`
- `TblPayModes` -> `payment_modes`

### Transaction imports

- `TblCashSales` -> `sales` with `sale_type = cash`
- `TblCreditSales` -> `sales` with `sale_type = credit`
- `TblCashSalesDetails` -> `sale_items`
- `TblCreditSalesDetails` -> `sale_items`
- `TblCustomerbalancepayment` -> `customer_payments`
- `TblCashPurchases` -> `purchases` with `purchase_type = cash`
- `TblCreditPurchase` -> `purchases` with `purchase_type = credit`
- `TblCashPurchasesDetails` -> `purchase_items`
- `TblCreditPurchasesdetails` -> `purchase_items`
- `TblSupplierPayments` -> `supplier_payments`
- `TblStoresOpeningStock` -> `inventory_transactions`
- `TblStockDisposalDetails` plus header -> `inventory_transactions`

## Import rules and cleanup

### Customer mapping

Rules:

- map `CASH SALE` to the dedicated walk-in customer
- map blank credit customers to `Unknown Customer`
- match customer names case-insensitively
- keep `legacy_customer_id` for traceability

### Supplier mapping

Rules:

- preserve `OTHERS` and `OUT PURCHASE` as system suppliers
- match supplier names case-insensitively
- keep `legacy_supplier_id` for traceability

### Product mapping

Rules:

- import `TblStockItems` before `TblStockItemsDetails`
- use `legacy_product_id` and `legacy_item_id` during import
- do not trust Access accounting columns as domain keys

### Sale numbering

Rules:

- generate fresh Laravel `sale_no` values
- store original Access IDs in `legacy_source_id`
- do not rely on Access cash and credit IDs being globally unique together without the source table

### Money and stock validation

After import, compare:

- total sales by month
- total purchases by month
- customer balances
- supplier balances
- stock balances for sample stores and products

## Recommended Eloquent model list

- `Role`
- `Store`
- `PaymentMode`
- `Customer`
- `Supplier`
- `Category`
- `Product`
- `ProductUnit`
- `Sale`
- `SaleItem`
- `CustomerPayment`
- `Purchase`
- `PurchaseItem`
- `SupplierPayment`
- `InventoryTransaction`
- `CapitalSource`
- `CapitalEntry`

## Suggested relationships

### `Customer`

- hasMany `sales`
- hasMany `customerPayments`

### `Supplier`

- hasMany `purchases`
- hasMany `supplierPayments`

### `Store`

- hasMany `sales`
- hasMany `purchases`
- hasMany `inventoryTransactions`
- hasMany `capitalEntries`

### `Product`

- belongsTo `category`
- belongsTo `supplier`
- hasMany `productUnits`
- hasMany `saleItems`
- hasMany `purchaseItems`
- hasMany `inventoryTransactions`

### `Sale`

- belongsTo `customer`
- belongsTo `store`
- belongsTo `paymentMode`
- hasMany `saleItems`
- hasMany `customerPayments`

### `Purchase`

- belongsTo `supplier`
- belongsTo `store`
- belongsTo `paymentMode`
- hasMany `purchaseItems`
- hasMany `supplierPayments`

## Final recommendation

If we start implementation now, the first schema to actually build in Laravel should be:

1. `stores`
2. `customers`
3. `suppliers`
4. `categories`
5. `products`
6. `product_units`
7. `payment_modes`
8. `sales`
9. `sale_items`
10. `customer_payments`
11. `purchases`
12. `purchase_items`
13. `supplier_payments`
14. `inventory_transactions`
15. `capital_sources`
16. `capital_entries`

That gives you a clean business system that replaces the old Access workflow without dragging the banking complexity into phase 1.

