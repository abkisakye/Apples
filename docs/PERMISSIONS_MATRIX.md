# Permissions Matrix

This matrix is taken from the live access rules in [AccessService.php](/C:/wamp64/www/Apples/app/Support/AccessService.php) and the protected routes in [web.php](/C:/wamp64/www/Apples/routes/web.php).

## Roles

- `admin`
- `manager`
- `cashier`
- `stock_clerk`
- `guest`

## Permissions

| Permission | Admin | Manager | Cashier | Stock Clerk | Guest |
|---|---|---|---|---|---|
| `dashboard.view` | Yes | Yes | Yes | Yes | No |
| `customers.view` | Yes | Yes | Yes | No | No |
| `customers.statement` | Yes | Yes | Yes | No | No |
| `suppliers.view` | Yes | Yes | No | Yes | No |
| `suppliers.statement` | Yes | Yes | No | Yes | No |
| `products.view` | Yes | Yes | Yes | Yes | No |
| `users.manage` | Yes | No | No | No | No |
| `follow_ups.manage` | Yes | Yes | Yes | No | No |
| `activity_logs.view` | Yes | Yes | No | No | No |
| `business.manage` | Yes | No | No | No | No |
| `reports.view` | Yes | Yes | No | No | No |
| `sales.view` | Yes | Yes | Yes | No | No |
| `sales.manage` | Yes | Yes | Yes | No | No |
| `purchases.view` | Yes | Yes | No | Yes | No |
| `purchases.manage` | Yes | Yes | No | Yes | No |
| `capital.view` | Yes | Yes | No | No | No |
| `capital.manage` | Yes | Yes | No | No | No |
| `stock.view` | Yes | Yes | No | Yes | No |
| `stock.manage` | Yes | No | No | Yes | No |
| `customer_payments.manage` | Yes | Yes | Yes | No | No |
| `supplier_payments.manage` | Yes | Yes | No | Yes | No |

## Notes

- `admin` has full access through the wildcard rule `*`.
- `business.manage` is the permission that controls the Business Settings screen.
- `guest` has no protected app permissions.
- This is still a practical default workflow split. If the client wants tighter approval control, the `manager` and `cashier` roles are the first ones to review.
