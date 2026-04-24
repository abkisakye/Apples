# Owner Testing SOP

## Purpose

This SOP helps a supermarket owner or lead staff member test the new system in a simple, structured way.

The goal is not to test every screen. The goal is to confirm that the system is understandable, practical, and useful for daily mini supermarket work.

## Before Starting

1. Open the system and log in with one of the test accounts in [PRIVATE_USER_ACCESS_SHEET.md](C:/wamp64/www/Apples/docs/PRIVATE_USER_ACCESS_SHEET.md).
2. Start with the `admin` account if you want to see the full system first.
3. Use the `cashier`, `manager`, and `stock_clerk` accounts if you want to test the system the way staff will actually use it.
4. Use simple sample data that feels real to your business.

Examples:

- Sugar 1kg
- Bread
- Milk
- Soda
- Soap
- Cooking oil

## Suggested Order Of Testing

### 1. Business Setup

Use the admin account.

Go to:

- Business Settings
- Master Data
- Users
- Permissions Matrix

What to do:

- check business name, contacts, and print footer text
- confirm stores exist
- confirm payment modes exist
- confirm users and roles make sense

What to ask:

- does the system look like our business?
- do the screens make sense?
- are the menu names understandable?

### 2. Add Basic Records

Use the admin or stock clerk account.

Go to:

- Suppliers
- Customers
- Products

What to do:

- add at least 2 suppliers
- add at least 3 customers
- add at least 10 products

For each product, confirm:

- name
- selling price
- buying price
- reorder level

What to ask:

- is product entry simple enough?
- are the labels understandable?
- is anything missing for daily use?

### 3. Test Purchase And Stock Entry

Use the stock clerk account.

Go to:

- Purchases
- Stock Balances
- Stock History

What to do:

- create a purchase from a supplier
- add several products with quantities
- save the purchase
- print the purchase document
- open stock balances and confirm stock increased

What to ask:

- is receiving stock easy enough?
- are quantities and totals clear?
- is the printout acceptable?

### 4. Test Sales Desk

Use the cashier account.

Go to:

- Cash Shifts
- Sales
- Customer Payments

What to do:

- open a shift
- create one cash sale
- create one credit sale
- print the receipt/invoice
- receive a customer payment

What to ask:

- is the sales page fast enough?
- can a cashier understand it without too much training?
- are totals and balances very clear?
- is the printout acceptable for real customer use?

### 5. Test Return Flow

Use the cashier or manager account.

Go to:

- Sales
- Sale Returns

What to do:

- open a posted sale
- create a return for one item
- test a credit note or exchange flow

What to ask:

- is the return process understandable?
- is it clear what happens to stock?
- is it clear what happens to customer balance or refund?

### 6. Test Stock Control

Use the stock clerk account.

Go to:

- Stock Transfer
- Stock Adjustment
- Stock Counts

What to do:

- transfer stock between stores
- adjust one damaged item
- start a physical stock count
- save progress
- continue and post the count

What to ask:

- can we understand where stock moved?
- does the count process make sense?
- is anything too slow or too crowded?

### 7. Test Reports And Statements

Use the manager or admin account.

Go to:

- Customer Statement
- Supplier Statement
- Customer Aging
- Supplier Aging
- Financial Summary
- Dashboard

What to do:

- open a customer statement
- open a supplier statement
- check aging reports
- check dashboard cards
- check whether the figures match the transactions already entered

What to ask:

- can the owner quickly see what is going on?
- are the reports useful for decision making?
- is anything missing for daily supervision?

## How To Record Feedback

For every page tested, note:

- what was easy
- what was confusing
- what looked too big or too crowded
- what was missing
- what should be renamed or simplified

Keep notes in this simple style:

- Page:
- What we tried:
- What worked:
- What was confusing:
- Suggested improvement:

## Important Note On Data Separation

At the moment, this testing setup uses one shared database and one shared app link.

That means:

- one tester is given access at a time
- the tester uses the accounts assigned for their store
- test data is still stored in one shared system database

So this is **not yet true business-by-business separation**.

If later you want each owner to have fully separate data, the best options are:

1. a fully separate deployment and database for each supermarket
2. a proper multi-tenant setup inside one codebase

For this testing phase, the shared database is acceptable because access is given to one tester at a time, but the sample records still stay in the same environment.

## Recommended Test Accounts

- `admin` for internal full access
- the assigned `manager` account for owner/manager review
- the assigned `cashier` account for sales desk testing
- the assigned `stock_clerk` account for purchase and stock testing

## End Of Test Sign-Off

At the end, each tester should answer:

1. Is the system easy enough to understand?
2. Is it better than the old Access workflow?
3. Would staff be able to use it after short training?
4. Are the printouts acceptable?
5. What must still change before real use?
