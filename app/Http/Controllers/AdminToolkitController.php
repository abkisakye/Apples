<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Operations\ProductionReadinessService;
use Illuminate\View\View;

class AdminToolkitController extends Controller
{
    public function uatCenter(): View
    {
        $tracks = [
            [
                'title' => 'Cashier UAT',
                'summary' => 'Use this flow to confirm that the sales desk is fast, clear, and safe for daily checkout work on desktop, tablet, and phone.',
                'owner' => 'Cashier / Supervisor',
                'success' => 'Receipts print correctly, totals stay accurate, and the cashier can finish sales and payments without confusion.',
                'steps' => [
                    [
                        'title' => 'Open shift and prepare the till',
                        'summary' => 'Start with the cash shift flow so the day begins with a controlled opening balance.',
                        'route' => 'cash-shifts.create',
                        'label' => 'Open Cash Shift',
                    ],
                    [
                        'title' => 'Post a cash sale',
                        'summary' => 'Search products, add quantities quickly, choose the customer, and confirm the printed receipt is clear.',
                        'route' => 'sales.create',
                        'label' => 'Open Sales Desk',
                    ],
                    [
                        'title' => 'Post a credit sale and part payment',
                        'summary' => 'Confirm the balance due, customer selection, and invoice output all reflect the transaction correctly.',
                        'route' => 'sales.index',
                        'route_params' => ['type' => 'credit'],
                        'label' => 'Review Credit Sales',
                    ],
                    [
                        'title' => 'Post customer payment',
                        'summary' => 'Record a follow-up payment and confirm the payment receipt and customer balance update immediately.',
                        'route' => 'customer-payments.create',
                        'label' => 'Post Customer Payment',
                    ],
                    [
                        'title' => 'Run a return or exchange',
                        'summary' => 'Use the guided return flow and confirm stock, refund/credit outcome, and follow-up actions stay clear.',
                        'route' => 'sales.index',
                        'label' => 'Open Sales List',
                    ],
                ],
            ],
            [
                'title' => 'Stock UAT',
                'summary' => 'Use this flow to prove that stock coming in, moving between stores, and physical corrections all stay traceable.',
                'owner' => 'Store Keeper / Stock Clerk',
                'success' => 'Stock balances, history, and print documents agree after purchases, transfers, adjustments, and counts.',
                'steps' => [
                    [
                        'title' => 'Receive a purchase',
                        'summary' => 'Post a supplier delivery, confirm quantities, and print the purchase document.',
                        'route' => 'purchases.create',
                        'label' => 'New Purchase',
                    ],
                    [
                        'title' => 'Transfer stock',
                        'summary' => 'Move items between stores and confirm both transfer directions appear in stock history.',
                        'route' => 'stock.transfers.create',
                        'label' => 'New Transfer',
                    ],
                    [
                        'title' => 'Adjust damaged or missing stock',
                        'summary' => 'Use a decrease adjustment and confirm the reason appears in stock movement history and printouts.',
                        'route' => 'stock.adjustments.create',
                        'label' => 'New Adjustment',
                    ],
                    [
                        'title' => 'Run a physical stock count',
                        'summary' => 'Save progress, resume later, and post only after the counted lines and variances look correct.',
                        'route' => 'stock.counts.create',
                        'label' => 'Start Stock Count',
                    ],
                    [
                        'title' => 'Trace movement history',
                        'summary' => 'Confirm that purchases, sales, returns, transfers, adjustments, and counts all explain the current balance.',
                        'route' => 'stock.balances',
                        'label' => 'Review Stock Balances',
                    ],
                ],
            ],
            [
                'title' => 'Accounts And Management UAT',
                'summary' => 'Use this flow to check statements, aging, reporting, and accountability before client sign-off.',
                'owner' => 'Manager / Admin / Accountant',
                'success' => 'Statements, aging, follow-ups, and management reports all match the operational transactions already posted.',
                'steps' => [
                    [
                        'title' => 'Review customer accounts',
                        'summary' => 'Open a customer statement and confirm sales, payments, returns, and the closing balance agree.',
                        'route' => 'customers.index',
                        'label' => 'Open Customers',
                    ],
                    [
                        'title' => 'Review supplier accounts',
                        'summary' => 'Check supplier statements and confirm purchases, supplier payments, and supplier returns stay aligned.',
                        'route' => 'suppliers.index',
                        'label' => 'Open Suppliers',
                    ],
                    [
                        'title' => 'Check aging and follow-ups',
                        'summary' => 'Confirm overdue credit, pending reminders, and follow-up statuses show what the manager expects.',
                        'route' => 'reports.customer-aging',
                        'label' => 'Open Aging Reports',
                    ],
                    [
                        'title' => 'Check financial and cash reports',
                        'summary' => 'Use the date filters to confirm sales, collections, expenses, and profit summaries read correctly.',
                        'route' => 'reports.financial-summary',
                        'label' => 'Open Financial Summary',
                    ],
                    [
                        'title' => 'Check permissions and audit trail',
                        'summary' => 'Confirm staff only see what they should, and that sensitive actions remain visible in the activity log.',
                        'route' => 'roles.matrix',
                        'label' => 'Open Permissions Matrix',
                    ],
                ],
            ],
        ];

        $signOff = [
            'Cashier confirms the sales desk is fast enough on phone, tablet, and desktop.',
            'Stock clerk confirms quantities, movements, and stock counts are understandable and traceable.',
            'Manager confirms statements, aging, follow-ups, and reports match the posted transactions.',
            'Admin confirms users, permissions, backups, and readiness checks are acceptable.',
            'Client confirms printouts and business wording are acceptable for daily use.',
        ];

        $commands = [
            ['label' => 'Readiness Check', 'command' => 'php artisan ops:go-live-check'],
            ['label' => 'Database Backup', 'command' => 'php artisan ops:backup-database'],
            ['label' => 'Refresh Cached Views', 'command' => 'php artisan view:cache'],
        ];

        return view('admin.uat_center', [
            'tracks' => $tracks,
            'signOff' => $signOff,
            'commands' => $commands,
        ]);
    }

    public function demoCenter(): View
    {
        $demoUsers = User::query()
            ->with('role')
            ->whereIn('username', ['admin', 'manager.demo', 'cashier.demo', 'stock.demo'])
            ->orderByRaw("case username when 'admin' then 0 when 'manager.demo' then 1 when 'cashier.demo' then 2 when 'stock.demo' then 3 else 4 end")
            ->get();

        $demoFlow = [
            [
                'title' => 'Open the dashboard',
                'summary' => 'Start with the manager snapshot, overdue credit, low stock, and pending follow-ups.',
                'route' => 'dashboard',
                'label' => 'Open Dashboard',
            ],
            [
                'title' => 'Show access control',
                'summary' => 'Open the users list, permissions matrix, and role pages to show how staff access is controlled.',
                'route' => 'users.index',
                'label' => 'Open Users',
            ],
            [
                'title' => 'Post a live sale',
                'summary' => 'Use the cashier flow to create a cash sale and show how the receipt prints immediately.',
                'route' => 'sales.create',
                'label' => 'Open Sales Entry',
            ],
            [
                'title' => 'Show stock movement',
                'summary' => 'Move into purchases, stock balance, reorder, and movement history to show stock control.',
                'route' => 'stock.balances',
                'label' => 'Open Stock',
            ],
            [
                'title' => 'Close with reporting',
                'summary' => 'Use customer statements, supplier statements, and aging reports to show management value.',
                'route' => 'reports.customer-aging',
                'label' => 'Open Aging Reports',
            ],
        ];

        return view('admin.demo_center', [
            'demoUsers' => $demoUsers,
            'demoFlow' => $demoFlow,
        ]);
    }

    public function readiness(ProductionReadinessService $readinessService): View
    {
        $checks = $readinessService->checks();
        $groupedChecks = $readinessService->groupedChecks();
        $goal = collect($checks)->every(fn (array $check) => $check['ready'])
            ? 'Production Ready'
            : (config('app.env') === 'production' ? 'Final Hardening' : 'Internal Build');

        return view('admin.readiness', [
            'checks' => $checks,
            'groupedChecks' => $groupedChecks,
            'goal' => $goal,
        ]);
    }
}
