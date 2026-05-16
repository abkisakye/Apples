# User Acceptance Testing Checklist

Use the in-app UAT Center at `/admin/uat-center` as the main test cockpit.

This document is the written version of that same flow so the team can print it, share it, or mark notes during rehearsal.

## Before You Start

- prepare realistic products, customers, suppliers, and balances
- confirm at least one cashier user and one admin user can log in
- test on desktop, tablet, and phone where possible
- make sure printer/browser print works for receipts and statements
- take a backup before major rehearsal changes with `php artisan ops:backup-database`

## Cashier Track

### Core flow

- log in successfully
- open a cash shift
- create a cash sale
- create a credit sale with part payment
- post a customer payment
- post a sale return or exchange
- close the shift

### Accept if

- the sales screen is understandable without extra explanation
- totals, balances, and change remain correct
- receipts and invoices print clearly
- the experience works comfortably on tablet and mobile widths

## Stock Track

### Core flow

- record a purchase
- print the purchase document
- post a stock transfer
- post a stock adjustment
- start a physical stock count
- save progress and resume the count
- post the final stock count
- review stock movement history

### Accept if

- stock increases and decreases correctly after each document
- transfer, adjustment, and count reasons remain visible in history
- stock balances can be explained from the movement trail
- print documents are clear enough for daily operations

## Management / Accounts Track

### Core flow

- open a customer statement
- open a supplier statement
- review customer aging
- review supplier aging
- review overdue follow-ups
- review financial summary and payment method reports
- confirm dashboard alerts and shortcuts make sense

### Accept if

- statements match posted sales, payments, and returns
- aging totals agree with outstanding balances
- follow-up screens clearly show what still needs action
- reports answer the manager’s basic daily and weekly questions

## Admin Track

### Core flow

- create or edit a user
- update role permissions
- test role access with a cashier account
- review activity log
- review readiness page
- run `php artisan ops:go-live-check`
- run `php artisan ops:backup-database`

### Accept if

- staff only see the actions they are supposed to use
- sensitive actions remain visible in the audit trail
- readiness warnings are understood and documented
- backup and restore ownership is clear before deployment

## Final Sign-Off

- cashier confirms the sales desk is fast enough for daily work
- stock staff confirm quantities and stock controls are understandable
- manager confirms statements, aging, and reports are acceptable
- admin confirms users, permissions, backup routine, and readiness status are acceptable
- client confirms printouts and wording are acceptable
- client approves moving away from Access when ready
