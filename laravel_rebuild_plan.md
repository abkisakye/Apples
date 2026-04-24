# Laravel Rebuild Plan

## Source database

Source file: `VENUS BUSINESS MANAGER-PREMIER-POSB-FEB21.mdb`

Confirmed core volumes:

- Customers: 55
- Suppliers: 66
- Stores: 6
- Stock items: 614
- Stock item detail/unit records: 1,384
- Cash sales: 13,056
- Cash sale lines: 77,195
- Credit sales: 1,956
- Credit sale lines: 4,539
- Cash purchases: 2,375
- Cash purchase lines: 5,650
- Credit purchases: 24
- Credit purchase lines: 39
- Customer balance payments: 223
- Supplier payments: 33
- Opening stock rows: 338

## Rebuild goal

Build a modern Laravel system that replaces the Access workflow for:

- sales
- stock/inventory
- customers
- suppliers
- purchases
- debt collection and bill payments
- business reports
- capital input tracking

Excluded from phase 1:

- banking module
- bank deposits/withdrawals
- bank reconciliation
- loan/bank statement workflows

## Main business modules

### 1. Setup

- Users and roles
- Stores
- Customers
- Suppliers
- Product categories
- Products
- Product units/pricing
- Payment modes

### 2. Sales

- Create sale
- Add sale items
- Cash sale or credit sale
- Discounts
- Receipt/invoice printing
- Sale cancellation rules

### 3. Customer accounts

- Customer balances
- Customer payment entry
- Customer statements
- Customer credit notes/refunds later if needed

### 4. Purchases

- Record supplier purchase
- Cash or credit purchase
- Purchase line items
- Purchase reference/invoice number

### 5. Supplier accounts

- Supplier balances
- Supplier payments
- Supplier statements

### 6. Inventory

- Opening stock
- Purchase stock in
- Sales stock out
- Returns
- Disposals
- Adjustments
- Store-level stock balances
- Reorder reporting

### 7. Capital inputs

- Record money introduced into the business
- Capture where the money came from
- Distinguish business-generated funds from outside funds
- Keep a simple audit trail with date, amount, source, reference, and notes

### 8. Reports

- Sales summary
- Detailed sales
- Stock balance
- Reorder list
- Customer balances
- Supplier balances
- Purchases summary
- Capital input summary

## Recommended Laravel domain model

Do not copy the Access table split exactly.

Use a simpler normalized model:

- `users`
- `roles`
- `stores`
- `customers`
- `suppliers`
- `categories`
- `products`
- `product_units`
- `sales`
- `sale_items`
- `customer_payments`
- `purchases`
- `purchase_items`
- `supplier_payments`
- `inventory_transactions`
- `capital_sources`
- `capital_entries`

Optional later:

- `stock_takes`
- `stock_take_items`
- `returns`
- `credit_notes`
- `attachments`
- `audit_logs`

## Important simplification

### Sales

Access uses:

- `TblCashSales`
- `TblCreditSales`
- separate detail tables

Laravel should use:

- one `sales` table
- one `sale_items` table

Suggested fields on `sales`:

- `id`
- `sale_no`
- `sale_date`
- `store_id`
- `customer_id` nullable
- `sale_type` enum: `cash`, `credit`
- `subtotal`
- `discount_amount`
- `vat_amount`
- `total_amount`
- `amount_paid`
- `balance_due`
- `payment_mode_id` nullable
- `credit_due_date` nullable
- `status`
- `created_by`
- timestamps

Suggested fields on `sale_items`:

- `id`
- `sale_id`
- `product_id`
- `product_unit_id`
- `quantity`
- `unit_price`
- `cost_price_snapshot`
- `line_total`
- `discount_amount`
- `vat_amount`

### Purchases

Access uses:

- `TblCashPurchases`
- `TblCreditPurchase`
- separate detail tables

Laravel should use:

- one `purchases` table
- one `purchase_items` table

Suggested fields on `purchases`:

- `id`
- `purchase_no`
- `purchase_date`
- `supplier_id`
- `store_id`
- `purchase_type` enum: `cash`, `credit`
- `supplier_invoice_no` nullable
- `subtotal`
- `discount_amount`
- `vat_amount`
- `total_amount`
- `amount_paid`
- `balance_due`
- `credit_due_date` nullable
- `status`
- `created_by`
- timestamps

Suggested fields on `purchase_items`:

- `id`
- `purchase_id`
- `product_id`
- `product_unit_id`
- `quantity`
- `unit_cost`
- `line_total`
- `vat_amount`

### Inventory

Instead of storing current stock only, use a movement ledger.

Suggested `inventory_transactions` fields:

- `id`
- `transaction_date`
- `store_id`
- `product_id`
- `product_unit_id`
- `reference_type`
- `reference_id`
- `movement_type`
- `quantity_in`
- `quantity_out`
- `unit_cost`
- `unit_price` nullable
- `remarks`
- `created_by`
- timestamps

Recommended movement types:

- `opening_stock`
- `purchase`
- `sale`
- `customer_return`
- `supplier_return`
- `adjustment_plus`
- `adjustment_minus`
- `disposal`
- `transfer_in`
- `transfer_out`

This is the cleanest way to produce stock balance reports and trace stock history per store.

## Capital input design

The new requirement is to monitor capital inputs and identify where the money comes from.

Recommended tables:

### `capital_sources`

- `id`
- `name`
- `source_type`
- `description` nullable
- timestamps

Recommended `source_type` values:

- `business_generated`
- `owner_injection`
- `external_investor`
- `loan`
- `other`

Examples:

- retained business cash
- owner top-up
- partner contribution
- borrowed funds

### `capital_entries`

- `id`
- `entry_date`
- `store_id` nullable
- `capital_source_id`
- `amount`
- `reference_no` nullable
- `received_via` nullable
- `notes` nullable
- `recorded_by`
- timestamps

This module should answer:

- how much capital was added
- when it was added
- where it came from
- whether it came from the business itself or outside

## Access to Laravel table mapping

### Migrate directly or map into new tables

- `TblCustomers` -> `customers`
- `TblSuppliers` -> `suppliers`
- `TblStores` -> `stores`
- `TblStockItemCategories` -> `categories`
- `TblStockItems` -> `products`
- `TblStockItemsDetails` -> `product_units`
- `TblCashSales` + `TblCreditSales` -> `sales`
- `TblCashSalesDetails` + `TblCreditSalesDetails` -> `sale_items`
- `TblCustomerbalancepayment` -> `customer_payments`
- `TblCashPurchases` + `TblCreditPurchase` -> `purchases`
- `TblCashPurchasesDetails` + `TblCreditPurchasesdetails` -> `purchase_items`
- `TblSupplierPayments` -> `supplier_payments`
- `TblStoresOpeningStock` -> `inventory_transactions` as `opening_stock`
- `TblStockDisposal` + `TblStockDisposalDetails` -> `inventory_transactions` as `disposal`

### Ignore in phase 1

- `TblBankdeposits`
- `TblBankDepositsDetails`
- `TblBankLoans-In`
- `TblBankLoans-Out`
- bank forms/reports
- payroll/HR tables
- asset tables
- non-core Access admin/password tables

## Migration issues already identified

### 1. Transactions often store names instead of IDs

Examples seen in sales and purchases:

- customer names are saved as text in sales tables
- supplier names are saved as text in purchase tables

This means migration must include a name-matching step.

### 2. Blank customer values exist

Observed:

- `TblCreditSales` contains blank customer names in some rows
- `TblCashSales` has at least one blank customer row

These need fallback rules such as:

- map blank credit customer to an `Unknown Customer` record
- map `CASH SALE` to a dedicated walk-in customer record

### 3. Special placeholder master records exist

Examples already seen:

- customer `CASH SALE`
- supplier `OTHERS`
- supplier `OUT PURCHASE`

These should be preserved but treated as system records in the new app.

### 4. Access accounting fields should not drive phase 1 design

Many tables contain fields such as:

- `Account-Cr`
- `Account-Dr`
- `Account-Cr-VAT`

These are useful as historical references, but phase 1 Laravel should not be built as a full accounting package unless that is explicitly added later.

## Recommended migration sequence

### Phase A. Master data

1. stores
2. customers
3. suppliers
4. categories
5. products
6. product units
7. payment modes

### Phase B. Opening and stock base

1. opening stock
2. stock adjustments if needed
3. disposals if needed

### Phase C. Transactions

1. sales
2. sale items
3. customer payments
4. purchases
5. purchase items
6. supplier payments

### Phase D. Reporting validation

1. compare old and new sales totals
2. compare customer balances
3. compare supplier balances
4. compare stock balances for sampled products/stores

## Suggested implementation phases

### Phase 1

- authentication and roles
- stores
- customers
- suppliers
- products and units
- opening stock

### Phase 2

- sales
- sale items
- receipt/invoice print
- customer balances
- customer payments

### Phase 3

- purchases
- supplier balances
- supplier payments
- stock movement ledger

### Phase 4

- capital input tracking
- reports
- migration scripts
- user testing and fixes

## Recommendation on scope

Build the first production version around these five anchors:

- `sales`
- `purchases`
- `inventory_transactions`
- `customer_payments`
- `supplier_payments`

Then add:

- `capital_sources`
- `capital_entries`

This gives the business a clean replacement for the Access system without carrying over unnecessary complexity from the old design.

