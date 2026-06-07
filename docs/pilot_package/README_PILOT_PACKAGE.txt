APPLES OF GOLD SUPERMARKET/POS SYSTEM
README - PILOT DOCUMENTATION PACKAGE

Branch: version-2.0.0-dev

This folder contains plain text documentation for installing, setting up, testing, and supporting the Apples Of Gold supermarket/POS/business management system.

FILES

1. INSTALLATION_GUIDE.txt

Use this for normal installation from Git or from an offline ZIP. Includes WAMP, PHP, MySQL, Composer, Node.js, database setup, .env setup, Laravel commands, LAN access, firewall notes, backups, and common errors.

2. OFFLINE_INSTALLATION_GUIDE.txt

Use this when installing on a computer without internet. Explains what must be inside the offline ZIP and what to do if composer/npm are unavailable.

3. BUSINESS_SETUP_GUIDE.txt

Use this after installation to configure business settings, stores, users, roles, payment modes, product categories, expense categories, suppliers, customers, products, opening stock, balances, cash shifts, and approval PIN.

4. USER_MANUAL_ADMIN_OWNER.txt

Daily guide for the owner/admin: dashboard, business settings, users, setup, products, stock, reports, purchases, supplier/customer balances, expenses, backups, and go-live checks.

5. USER_MANUAL_CASHIER.txt

Cashier guide: login, change password, open shift, create sale, add products, choose customer, handle payment, print receipt, customer payments, close shift, and common mistakes.

6. USER_MANUAL_STOCK_PURCHASES.txt

Stock and purchase guide: suppliers, products, units, purchases, cash vs credit purchase, supplier payments, outstanding purchases, stock balances, adjustments, transfers, counts, and reorder.

7. TESTING_CHECKLIST.txt

First-test and UAT checklist covering login, product setup, purchases, supplier payments, sales/POS, credit rules, customer payments, expenses, dashboard, reports, stock, and print.

8. TROUBLESHOOTING_GUIDE.txt

Fixes for login, admin user, 500 errors, migrations, expense categories, MySQL key length, secure cookies, APP_URL, mysqldump, firewall, ngrok/browser warnings, Vite build issues, and cache issues.

9. DEPLOYMENT_CHECKLIST.txt

Pre-handover checklist for git status, tests, go-live check, migrations, cache, backups, users, stores, payment modes, products, opening stock, printing, and dashboard totals.

10. SYSTEM_OVERVIEW_FOR_SUPPORT.txt

Technical summary for developers/support staff: stack, modules, routes, controllers, models, database tables, permissions, flows, reports, printing, commands, and limitations.

HOW TO USE THIS PACKAGE

For installing a new pilot computer:

1. Read INSTALLATION_GUIDE.txt.
2. If offline, read OFFLINE_INSTALLATION_GUIDE.txt.
3. Complete BUSINESS_SETUP_GUIDE.txt.
4. Run TESTING_CHECKLIST.txt.
5. Give the cashier USER_MANUAL_CASHIER.txt.
6. Give stock/purchase staff USER_MANUAL_STOCK_PURCHASES.txt.
7. Keep TROUBLESHOOTING_GUIDE.txt and SYSTEM_OVERVIEW_FOR_SUPPORT.txt for support.

SECURITY NOTE

Do not share real .env passwords. Change default admin credentials immediately after installation.

