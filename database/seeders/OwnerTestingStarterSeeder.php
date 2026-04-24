<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerTestingStarterSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::query()->where('username', 'admin')->first();
        $cashMode = PaymentMode::query()->where('name', 'Cash')->first();

        $stores = [
            'Testing Main Store' => Store::query()->firstOrCreate(['name' => 'Testing Main Store'], ['is_active' => true]),
            'Owner One Test Store' => Store::query()->firstOrCreate(['name' => 'Owner One Test Store'], ['is_active' => true]),
            'Owner Two Test Store' => Store::query()->firstOrCreate(['name' => 'Owner Two Test Store'], ['is_active' => true]),
        ];

        $categories = [
            'Groceries' => Category::query()->firstOrCreate(['name' => 'Groceries'], ['is_active' => true]),
            'Beverages' => Category::query()->firstOrCreate(['name' => 'Beverages'], ['is_active' => true]),
            'Bakery' => Category::query()->firstOrCreate(['name' => 'Bakery'], ['is_active' => true]),
            'Toiletries' => Category::query()->firstOrCreate(['name' => 'Toiletries'], ['is_active' => true]),
            'Household' => Category::query()->firstOrCreate(['name' => 'Household'], ['is_active' => true]),
        ];

        $suppliers = [
            'Freshline Wholesalers' => Supplier::query()->firstOrCreate(
                ['name' => 'Freshline Wholesalers'],
                ['phone' => '0701000001', 'address' => 'Kampala', 'country' => 'Uganda', 'is_active' => true]
            ),
            'City Beverage Depot' => Supplier::query()->firstOrCreate(
                ['name' => 'City Beverage Depot'],
                ['phone' => '0701000002', 'address' => 'Kampala', 'country' => 'Uganda', 'is_active' => true]
            ),
            'Home Choice Supplies' => Supplier::query()->firstOrCreate(
                ['name' => 'Home Choice Supplies'],
                ['phone' => '0701000003', 'address' => 'Kampala', 'country' => 'Uganda', 'is_active' => true]
            ),
        ];

        foreach ([
            ['name' => 'Sarah Nanyonga', 'phone' => '0702000001', 'location' => 'Kisaasi'],
            ['name' => 'Peter Mugisha', 'phone' => '0702000002', 'location' => 'Ntinda'],
            ['name' => 'Bright Office Supplies', 'phone' => '0702000003', 'location' => 'Kololo'],
            ['name' => 'Janet Namusoke', 'phone' => '0702000004', 'location' => 'Naalya'],
            ['name' => 'Hope Salon', 'phone' => '0702000005', 'location' => 'Bukoto'],
        ] as $customer) {
            Customer::query()->firstOrCreate(
                ['name' => $customer['name']],
                [
                    'phone' => $customer['phone'],
                    'location' => $customer['location'],
                    'is_active' => true,
                    'is_walk_in' => false,
                    'is_system' => false,
                ]
            );
        }

        $catalog = [
            [
                'name' => 'Sugar 1kg',
                'code' => 'TEST-001',
                'category' => 'Groceries',
                'supplier' => 'Freshline Wholesalers',
                'cost' => 3200,
                'price' => 4500,
                'reorder' => 8,
                'barcode' => '990000000001',
            ],
            [
                'name' => 'Rice 1kg',
                'code' => 'TEST-002',
                'category' => 'Groceries',
                'supplier' => 'Freshline Wholesalers',
                'cost' => 3800,
                'price' => 5200,
                'reorder' => 8,
                'barcode' => '990000000002',
            ],
            [
                'name' => 'Cooking Oil 1L',
                'code' => 'TEST-003',
                'category' => 'Groceries',
                'supplier' => 'Freshline Wholesalers',
                'cost' => 7200,
                'price' => 9000,
                'reorder' => 6,
                'barcode' => '990000000003',
            ],
            [
                'name' => 'Milk 500ml',
                'code' => 'TEST-004',
                'category' => 'Beverages',
                'supplier' => 'City Beverage Depot',
                'cost' => 1800,
                'price' => 2500,
                'reorder' => 10,
                'barcode' => '990000000004',
            ],
            [
                'name' => 'Soda 500ml',
                'code' => 'TEST-005',
                'category' => 'Beverages',
                'supplier' => 'City Beverage Depot',
                'cost' => 1200,
                'price' => 2000,
                'reorder' => 12,
                'barcode' => '990000000005',
            ],
            [
                'name' => 'Mineral Water 1L',
                'code' => 'TEST-006',
                'category' => 'Beverages',
                'supplier' => 'City Beverage Depot',
                'cost' => 900,
                'price' => 1500,
                'reorder' => 12,
                'barcode' => '990000000006',
            ],
            [
                'name' => 'Bread Large',
                'code' => 'TEST-007',
                'category' => 'Bakery',
                'supplier' => 'Freshline Wholesalers',
                'cost' => 2300,
                'price' => 3200,
                'reorder' => 10,
                'barcode' => '990000000007',
            ],
            [
                'name' => 'Biscuits Pack',
                'code' => 'TEST-008',
                'category' => 'Bakery',
                'supplier' => 'Freshline Wholesalers',
                'cost' => 1400,
                'price' => 2200,
                'reorder' => 10,
                'barcode' => '990000000008',
            ],
            [
                'name' => 'Laundry Soap Bar',
                'code' => 'TEST-009',
                'category' => 'Toiletries',
                'supplier' => 'Home Choice Supplies',
                'cost' => 1700,
                'price' => 2500,
                'reorder' => 8,
                'barcode' => '990000000009',
            ],
            [
                'name' => 'Toilet Tissue Roll',
                'code' => 'TEST-010',
                'category' => 'Household',
                'supplier' => 'Home Choice Supplies',
                'cost' => 2200,
                'price' => 3200,
                'reorder' => 8,
                'barcode' => '990000000010',
            ],
            [
                'name' => 'Salt 500g',
                'code' => 'TEST-011',
                'category' => 'Groceries',
                'supplier' => 'Freshline Wholesalers',
                'cost' => 900,
                'price' => 1500,
                'reorder' => 10,
                'barcode' => '990000000011',
            ],
            [
                'name' => 'Bathing Soap',
                'code' => 'TEST-012',
                'category' => 'Toiletries',
                'supplier' => 'Home Choice Supplies',
                'cost' => 1500,
                'price' => 2300,
                'reorder' => 8,
                'barcode' => '990000000012',
            ],
        ];

        $units = [];

        foreach ($catalog as $item) {
            $product = Product::query()->updateOrCreate(
                ['name' => $item['name']],
                [
                    'code' => $item['code'],
                    'category_id' => $categories[$item['category']]->id,
                    'supplier_id' => $suppliers[$item['supplier']]->id,
                    'base_cost_price' => $item['cost'],
                    'reorder_level' => $item['reorder'],
                    'is_active' => true,
                ]
            );

            $units[$item['name']] = ProductUnit::query()->updateOrCreate(
                ['product_id' => $product->id, 'unit_name' => 'Each'],
                [
                    'selling_price' => $item['price'],
                    'cost_price' => $item['cost'],
                    'barcode' => $item['barcode'],
                    'part_number' => $item['code'].'-EA',
                    'is_pos_unit' => true,
                    'is_active' => true,
                ]
            );
        }

        $purchasePlans = [
            [
                'purchase_no' => 'PUR-TEST-0001',
                'supplier' => 'Freshline Wholesalers',
                'store' => 'Testing Main Store',
                'invoice' => 'INV-FRESH-001',
                'date' => '2026-04-20',
                'items' => [
                    ['product' => 'Sugar 1kg', 'qty' => 24],
                    ['product' => 'Rice 1kg', 'qty' => 20],
                    ['product' => 'Cooking Oil 1L', 'qty' => 12],
                    ['product' => 'Bread Large', 'qty' => 15],
                    ['product' => 'Biscuits Pack', 'qty' => 18],
                    ['product' => 'Salt 500g', 'qty' => 24],
                ],
            ],
            [
                'purchase_no' => 'PUR-TEST-0002',
                'supplier' => 'City Beverage Depot',
                'store' => 'Testing Main Store',
                'invoice' => 'INV-BEV-001',
                'date' => '2026-04-20',
                'items' => [
                    ['product' => 'Milk 500ml', 'qty' => 24],
                    ['product' => 'Soda 500ml', 'qty' => 36],
                    ['product' => 'Mineral Water 1L', 'qty' => 24],
                ],
            ],
            [
                'purchase_no' => 'PUR-TEST-0003',
                'supplier' => 'Home Choice Supplies',
                'store' => 'Testing Main Store',
                'invoice' => 'INV-HOME-001',
                'date' => '2026-04-20',
                'items' => [
                    ['product' => 'Laundry Soap Bar', 'qty' => 18],
                    ['product' => 'Toilet Tissue Roll', 'qty' => 16],
                    ['product' => 'Bathing Soap', 'qty' => 20],
                ],
            ],
            [
                'purchase_no' => 'PUR-TEST-0004',
                'supplier' => 'Freshline Wholesalers',
                'store' => 'Owner One Test Store',
                'invoice' => 'INV-FRESH-002',
                'date' => '2026-04-20',
                'items' => [
                    ['product' => 'Sugar 1kg', 'qty' => 12],
                    ['product' => 'Rice 1kg', 'qty' => 10],
                    ['product' => 'Bread Large', 'qty' => 10],
                    ['product' => 'Biscuits Pack', 'qty' => 12],
                ],
            ],
            [
                'purchase_no' => 'PUR-TEST-0005',
                'supplier' => 'City Beverage Depot',
                'store' => 'Owner Two Test Store',
                'invoice' => 'INV-BEV-002',
                'date' => '2026-04-20',
                'items' => [
                    ['product' => 'Milk 500ml', 'qty' => 12],
                    ['product' => 'Soda 500ml', 'qty' => 24],
                    ['product' => 'Mineral Water 1L', 'qty' => 12],
                ],
            ],
        ];

        foreach ($purchasePlans as $plan) {
            $purchase = Purchase::query()->firstOrCreate(
                ['purchase_no' => $plan['purchase_no']],
                [
                    'purchase_date' => $plan['date'],
                    'supplier_id' => $suppliers[$plan['supplier']]->id,
                    'store_id' => $stores[$plan['store']]->id,
                    'purchase_type' => 'cash',
                    'payment_mode_id' => $cashMode?->id,
                    'supplier_invoice_no' => $plan['invoice'],
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'vat_amount' => 0,
                    'total_amount' => 0,
                    'amount_paid' => 0,
                    'balance_due' => 0,
                    'status' => 'posted',
                    'remarks' => 'Starter stock for owner testing.',
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]
            );

            $subtotal = 0;

            foreach ($plan['items'] as $entry) {
                $unit = $units[$entry['product']];
                $product = $unit->product;
                $lineTotal = $entry['qty'] * (float) $unit->cost_price;
                $subtotal += $lineTotal;

                PurchaseItem::query()->firstOrCreate(
                    [
                        'purchase_id' => $purchase->id,
                        'product_unit_id' => $unit->id,
                    ],
                    [
                        'product_id' => $product->id,
                        'description' => $product->name,
                        'quantity' => $entry['qty'],
                        'unit_cost' => $unit->cost_price,
                        'line_total' => $lineTotal,
                    ]
                );

                InventoryTransaction::query()->updateOrCreate(
                    [
                        'reference_type' => 'purchase',
                        'reference_id' => $purchase->id,
                        'product_unit_id' => $unit->id,
                        'movement_type' => 'purchase',
                        'store_id' => $purchase->store_id,
                    ],
                    [
                        'reference_no' => $purchase->purchase_no,
                        'transaction_date' => $purchase->purchase_date,
                        'product_id' => $product->id,
                        'quantity_in' => $entry['qty'],
                        'quantity_out' => 0,
                        'unit_cost' => $unit->cost_price,
                        'unit_price' => $unit->selling_price,
                        'remarks' => 'Starter stock for owner testing.',
                        'created_by' => $adminUser?->id,
                    ]
                );
            }

            $purchase->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'amount_paid' => $subtotal,
                'balance_due' => 0,
            ]);
        }
    }
}
