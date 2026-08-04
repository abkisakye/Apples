<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseFundingSource;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsRole('admin');

        $this->store = Store::create(['name' => 'Main Shop', 'is_active' => true]);
        $this->category = Category::create(['name' => 'BATHING SOAP']);
    }

    public function test_stock_valuation_page_loads_and_values_base_stock_from_latest_purchase_cost(): void
    {
        [$product, $piece, $carton] = $this->productWithPieceAndCarton('DETTOL FRESH SOAP 90G');
        $this->postPurchaseCost($product, $carton, 1, 24000);
        $this->stockMovement($product, $carton, 1, 0, 24, 0, 'purchase');
        $this->stockMovement($product, $piece, 0, 3, 0, 3, 'sale');

        $this->get('/reports/stock-valuation?store_id='.$this->store->id)
            ->assertOk()
            ->assertSee('Stock Valuation')
            ->assertSee('Estimated report')
            ->assertSee('DETTOL FRESH SOAP 90G')
            ->assertSee('21 pieces')
            ->assertSee('UGX 1,000.00')
            ->assertSee('UGX 21,000')
            ->assertSee('Latest purchase cost');
    }

    public function test_stock_valuation_flags_missing_cost_and_filters_rows(): void
    {
        [$missingCostProduct, $piece] = $this->singleUnitProduct('MISSING COST SOAP', 0, 3500);
        $this->stockMovement($missingCostProduct, $piece, 5, 0, 5, 0, 'opening_stock');

        [$costedProduct, $costedPiece] = $this->singleUnitProduct('COSTED SOAP', 1000, 1500);
        $this->stockMovement($costedProduct, $costedPiece, 5, 0, 5, 0, 'opening_stock');

        $this->get('/reports/stock-valuation?cost_source=missing&include_zero_stock=1')
            ->assertOk()
            ->assertSee('MISSING COST SOAP')
            ->assertSee('Missing cost')
            ->assertSee(route('products.edit', ['product' => $missingCostProduct->id, 'focus' => 'units'], false), false)
            ->assertDontSee('COSTED SOAP');
    }

    public function test_price_margins_page_flags_below_cost_zero_selling_and_conversion_review(): void
    {
        [$belowCostProduct] = $this->singleUnitProduct('BELOW COST SOAP', 1200, 1000);
        [$zeroSellingProduct] = $this->singleUnitProduct('ZERO SELLING SOAP', 900, 0);
        $bulkProduct = Product::create(['name' => 'BULK BOX SOAP', 'category_id' => $this->category->id, 'is_active' => true]);
        ProductUnit::create([
            'product_id' => $bulkProduct->id,
            'unit_name' => 'Boxes',
            'conversion_factor' => 1,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);

        $this->get('/reports/price-margins')
            ->assertOk()
            ->assertSee('Cost vs Selling Price')
            ->assertSee($belowCostProduct->name)
            ->assertSee('Selling below cost')
            ->assertSee(route('products.edit', ['product' => $belowCostProduct->id, 'focus' => 'units'], false), false)
            ->assertSee($zeroSellingProduct->name)
            ->assertSee('Zero selling price')
            ->assertSee(route('products.edit', ['product' => $zeroSellingProduct->id, 'focus' => 'units'], false), false)
            ->assertSee($bulkProduct->name)
            ->assertSee('Possible Pack Conversion Review')
            ->assertSee(route('products.edit', ['product' => $bulkProduct->id, 'focus' => 'units'], false), false)
            ->assertSee('N/A');
    }

    public function test_missing_cost_margin_is_not_displayed_as_healthy_profit(): void
    {
        $this->singleUnitProduct('NO COST PROFIT TRAP', 0, 5000);

        $this->get('/reports/price-margins?q=NO+COST+PROFIT+TRAP')
            ->assertOk()
            ->assertSee('NO COST PROFIT TRAP')
            ->assertSee('Missing cost')
            ->assertSee('N/A')
            ->assertDontSee('100.00%')
            ->assertDontSee('<span class="badge success">Healthy margin</span>', false);
    }

    public function test_price_margin_filters_and_search_work(): void
    {
        $this->singleUnitProduct('AFRICA HEALTHY SOAP', 1000, 1500);
        $this->singleUnitProduct('AFRICA LOSS SOAP', 2000, 1500);

        $this->get('/reports/price-margins?q=AFRICA&status=selling_below_cost')
            ->assertOk()
            ->assertSee('AFRICA LOSS SOAP')
            ->assertSee('Selling below cost')
            ->assertDontSee('AFRICA HEALTHY SOAP');
    }

    public function test_price_margins_product_search_matches_products_page_fields(): void
    {
        [$abcProduct, $abcUnit] = $this->singleUnitProduct('ABC DENT', 1000, 1600);
        $abcProduct->update([
            'code' => 'ABC-DENT-001',
            'item_group' => 'DENTAL CARE',
            'supplier_id' => Supplier::create(['name' => 'ABC SUPPLIER', 'is_active' => true])->id,
        ]);
        $abcUnit->update([
            'unit_name' => 'Boxes',
            'barcode' => 'ABC-BAR-001',
            'part_number' => 'ABC-PART-001',
        ]);
        $this->singleUnitProduct('OTHER DENT', 1000, 1600);

        $this->get('/reports/price-margins?q=abc')
            ->assertOk()
            ->assertSee('ABC DENT')
            ->assertSee('Boxes')
            ->assertDontSee('OTHER DENT');
    }

    public function test_price_margins_unit_search_filters_to_matching_pack_rows(): void
    {
        [$product, $piece, $carton] = $this->productWithPieceAndCarton('UNIT SEARCH SOAP');
        $piece->update(['unit_name' => 'Pieces']);
        $carton->update(['unit_name' => 'Cartons']);
        ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Boxes',
            'conversion_factor' => 12,
            'cost_price' => 12000,
            'selling_price' => 18000,
            'is_active' => true,
        ]);

        $this->get('/reports/price-margins?q=Boxes')
            ->assertOk()
            ->assertSee('UNIT SEARCH SOAP')
            ->assertSee('Boxes')
            ->assertDontSee('Cartons')
            ->assertDontSee('Pieces');

        $this->get('/reports/price-margins?category_id=&q=Boxes&status=all')
            ->assertOk()
            ->assertSee('UNIT SEARCH SOAP')
            ->assertSee('Boxes')
            ->assertDontSee('Cartons')
            ->assertDontSee('Pieces');

        $this->get('/reports/price-margins?q=Cartons')
            ->assertOk()
            ->assertSee('UNIT SEARCH SOAP')
            ->assertSee('Cartons')
            ->assertDontSee('Boxes')
            ->assertDontSee('Pieces');
    }

    public function test_stock_valuation_search_matches_product_code_unit_barcode_and_part_number(): void
    {
        [$product, $unit] = $this->singleUnitProduct('SEARCHABLE STOCK ITEM', 800, 1200);
        $product->update(['code' => 'STOCK-CODE-ABC']);
        $unit->update([
            'unit_name' => 'Cartons',
            'barcode' => 'STOCK-BAR-ABC',
            'part_number' => 'STOCK-PART-ABC',
        ]);
        $this->stockMovement($product, $unit, 2, 0, 2, 0, 'opening_stock');
        [$otherProduct, $otherUnit] = $this->singleUnitProduct('UNMATCHED STOCK ITEM', 800, 1200);
        $this->stockMovement($otherProduct, $otherUnit, 2, 0, 2, 0, 'opening_stock');

        $this->get('/reports/stock-valuation?q=STOCK-PART-ABC')
            ->assertOk()
            ->assertSee('SEARCHABLE STOCK ITEM')
            ->assertDontSee('UNMATCHED STOCK ITEM');

        $this->get('/reports/stock-valuation?q=Cartons')
            ->assertOk()
            ->assertSee('SEARCHABLE STOCK ITEM')
            ->assertDontSee('UNMATCHED STOCK ITEM');
    }

    public function test_gross_profit_report_page_loads_and_links_from_management_centre(): void
    {
        $this->get('/reports/gross-profit')
            ->assertOk()
            ->assertSee('Estimated Gross Profit')
            ->assertSee('Sales Revenue')
            ->assertSee('Profit By Product')
            ->assertSee('Profit By Category')
            ->assertSee('Daily Profit')
            ->assertSee('Missing Cost Sales');

        $this->get('/management-centre')
            ->assertOk()
            ->assertSee('Estimated Gross Profit')
            ->assertSee(route('reports.gross-profit', [], false), false);
    }

    public function test_gross_profit_excludes_void_sales_and_uses_line_total_and_product_unit_cost(): void
    {
        [$product, $unit] = $this->singleUnitProduct('PROFIT SOAP', 700, 1000);
        $this->postSaleLine($product, $unit, '2026-07-10', 2, 1000, 2500, 0, 'posted');
        $this->postSaleLine($product, $unit, '2026-07-10', 9, 9999, 99999, 0, 'void');

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=PROFIT+SOAP')
            ->assertOk()
            ->assertSee('PROFIT SOAP')
            ->assertSee('Product unit cost')
            ->assertSee('UGX 2,500')
            ->assertSee('UGX 1,400')
            ->assertSee('UGX 1,100')
            ->assertSee('44.00%')
            ->assertDontSee('UGX 99,999');
    }

    public function test_gross_profit_uses_sale_cost_snapshot_before_current_unit_cost(): void
    {
        [$product, $unit] = $this->singleUnitProduct('SNAPSHOT SOAP', 1000, 2000);
        $this->postSaleLine($product, $unit, '2026-07-10', 2, 2000, 4000, 1200);

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=SNAPSHOT+SOAP')
            ->assertOk()
            ->assertSee('SNAPSHOT SOAP')
            ->assertSee('Sale cost snapshot')
            ->assertSee('UGX 2,400')
            ->assertSee('UGX 1,600');
    }

    public function test_gross_profit_missing_cost_line_is_flagged_and_margin_is_not_healthy(): void
    {
        [$product, $unit] = $this->singleUnitProduct('MISSING PROFIT COST', 0, 5000);
        $this->postSaleLine($product, $unit, '2026-07-10', 1, 5000, 5000, 0);

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=MISSING+PROFIT+COST')
            ->assertOk()
            ->assertSee('MISSING PROFIT COST')
            ->assertSee('Missing cost')
            ->assertSee('Missing cost review needed')
            ->assertSee(route('products.edit', ['product' => $product->id, 'focus' => 'units'], false), false)
            ->assertSee('N/A')
            ->assertDontSee('100.00%')
            ->assertDontSee('<span class="badge success">OK</span>', false);
    }

    public function test_gross_profit_groups_by_product_category_and_date_and_applies_filters(): void
    {
        [$soapProduct, $soapUnit] = $this->singleUnitProduct('AFRICA SOAP PROFIT', 600, 1000);
        $foodCategory = Category::create(['name' => 'FOODS']);
        $foodProduct = Product::create([
            'name' => 'AFRICA RICE PROFIT',
            'code' => 'AFRICA-RICE-PROFIT',
            'category_id' => $foodCategory->id,
            'base_unit_label' => 'Pieces',
            'is_active' => true,
        ]);
        $foodUnit = ProductUnit::create([
            'product_id' => $foodProduct->id,
            'unit_name' => 'Pieces',
            'conversion_factor' => 1,
            'cost_price' => 2000,
            'selling_price' => 3000,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $foodProduct->update(['base_product_unit_id' => $foodUnit->id]);

        $this->postSaleLine($soapProduct, $soapUnit, '2026-07-10', 3, 1000, 3000, 0);
        $this->postSaleLine($foodProduct, $foodUnit, '2026-07-11', 2, 3000, 6000, 0);
        $this->postSaleLine($foodProduct, $foodUnit, '2026-08-02', 1, 3000, 3000, 0);

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=AFRICA')
            ->assertOk()
            ->assertSee('AFRICA SOAP PROFIT')
            ->assertSee('AFRICA RICE PROFIT')
            ->assertSee('BATHING SOAP')
            ->assertSee('FOODS')
            ->assertSee('10 Jul 2026')
            ->assertSee('11 Jul 2026')
            ->assertDontSee('02 Aug 2026');

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&category_id='.$this->category->id)
            ->assertOk()
            ->assertSee('AFRICA SOAP PROFIT')
            ->assertDontSee('AFRICA RICE PROFIT');

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=RICE')
            ->assertOk()
            ->assertSee('AFRICA RICE PROFIT')
            ->assertDontSee('AFRICA SOAP PROFIT');
    }

    public function test_gross_profit_search_filters_summary_totals_to_matching_product_and_unit_fields(): void
    {
        [$product, $unit] = $this->singleUnitProduct('ABC DENT PROFIT', 700, 1000);
        $product->update([
            'code' => 'ABC-PROFIT-CODE',
            'item_group' => 'DENTAL PROFITS',
            'supplier_id' => Supplier::create(['name' => 'PROFIT SUPPLIER ABC', 'is_active' => true])->id,
        ]);
        $unit->update([
            'unit_name' => 'Boxes',
            'barcode' => 'PROFIT-BAR-ABC',
            'part_number' => 'PROFIT-PART-ABC',
        ]);
        [$otherProduct, $otherUnit] = $this->singleUnitProduct('OTHER PROFIT ITEM', 700, 1000);

        $this->postSaleLine($product, $unit, '2026-07-10', 3, 1000, 3000, 700);
        $this->postSaleLine($otherProduct, $otherUnit, '2026-07-10', 9, 1000, 9000, 700);

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=PROFIT-PART-ABC')
            ->assertOk()
            ->assertSee('ABC DENT PROFIT')
            ->assertSee('UGX 3,000')
            ->assertSee('UGX 2,100')
            ->assertSee('UGX 900')
            ->assertDontSee('OTHER PROFIT ITEM')
            ->assertDontSee('UGX 12,000');

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=Boxes&category_id='.$this->category->id.'&cost_status=has_cost')
            ->assertOk()
            ->assertSee('ABC DENT PROFIT')
            ->assertSee('UGX 3,000')
            ->assertDontSee('OTHER PROFIT ITEM');
    }

    public function test_gross_profit_unit_search_filters_to_selected_sale_item_unit_lines(): void
    {
        [$product, $piece, $carton] = $this->productWithPieceAndCarton('MIXED UNIT PROFIT');
        $this->postSaleLine($product, $carton, '2026-07-10', 1, 36000, 36000, 24000);
        $this->postSaleLine($product, $piece, '2026-07-10', 5, 1500, 7500, 1000);

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=Cartons')
            ->assertOk()
            ->assertSee('MIXED UNIT PROFIT')
            ->assertSee('UGX 36,000')
            ->assertSee('UGX 24,000')
            ->assertSee('UGX 12,000')
            ->assertDontSee('UGX 43,500')
            ->assertDontSee('UGX 29,000')
            ->assertDontSee('UGX 14,500');
    }

    public function test_gross_profit_report_displays_fractional_wholesale_quantities(): void
    {
        [$product, , $carton] = $this->productWithPieceAndCarton('FRACTIONAL PROFIT CARTON');
        $this->postSaleLine($product, $carton, '2026-07-10', 0.75, 36000, 27000, 18000);

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=FRACTIONAL+PROFIT+CARTON')
            ->assertOk()
            ->assertSee('FRACTIONAL PROFIT CARTON')
            ->assertSee('0.75')
            ->assertSee('UGX 27,000')
            ->assertSee('UGX 13,500');
    }

    public function test_gross_profit_report_nets_returns_expenses_and_purchase_funding_sources(): void
    {
        [$product, $unit] = $this->singleUnitProduct('RETURN PROFIT SOAP', 600, 1000);
        $saleItem = $this->postSaleLine($product, $unit, '2026-07-10', 10, 1000, 10000, 600);
        $this->postSaleReturnLine($saleItem, '2026-07-12', 2, 1000, 2000);
        $this->postSaleReturnLine($saleItem, '2026-07-13', 3, 1000, 3000, 'cancelled');
        $this->postExpense('2026-07-14', 'Transport', 700);
        $this->postExpense('2026-08-01', 'Outside Period Expense', 999);
        $this->postExpense('2026-07-14', 'Draft Expense', 888, 'draft');
        $this->postFundedPurchase('2026-07-15', 'Business Cash / Shop Cash', 5000, 5000, 0);
        $this->postFundedPurchase('2026-07-16', 'Supplier Credit / Not Paid Yet', 3000, 0, 3000);
        $this->postFundedPurchase('2026-08-01', 'Other', 999, 999, 0);

        $countsBefore = [
            'sales' => Sale::query()->count(),
            'sale_items' => SaleItem::query()->count(),
            'sale_returns' => SaleReturn::query()->count(),
            'sale_return_items' => SaleReturnItem::query()->count(),
            'purchases' => Purchase::query()->count(),
            'expenses' => Expense::query()->count(),
            'inventory_transactions' => InventoryTransaction::query()->count(),
            'product_units' => ProductUnit::query()->count(),
        ];
        $pricesBefore = ProductUnit::query()->pluck('selling_price', 'id')->all();

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&q=RETURN+PROFIT+SOAP')
            ->assertOk()
            ->assertSee('Gross Sales Revenue')
            ->assertSee('Returned / Refunded Sales')
            ->assertSee('Net Sales Revenue')
            ->assertSee('Estimated Returned COGS')
            ->assertSee('Net Estimated Gross Profit')
            ->assertSee('Expenses Vs Gross Profit')
            ->assertSee('Estimated Net Profit')
            ->assertSee('Purchase Funding Source Summary')
            ->assertSee('RETURN PROFIT SOAP')
            ->assertSee('BATHING SOAP')
            ->assertSee('12 Jul 2026')
            ->assertSee('Transport')
            ->assertSee('Business Cash / Shop Cash')
            ->assertSee('Supplier Credit / Not Paid Yet')
            ->assertSee('UGX 10,000')
            ->assertSee('UGX 2,000')
            ->assertSee('UGX 8,000')
            ->assertSee('UGX 6,000')
            ->assertSee('UGX 1,200')
            ->assertSee('UGX 4,800')
            ->assertSee('UGX 3,200')
            ->assertSee('UGX 700')
            ->assertSee('UGX 2,500')
            ->assertSee('UGX 5,000')
            ->assertSee('UGX 3,000')
            ->assertDontSee('Cancelled')
            ->assertDontSee('Outside Period Expense')
            ->assertDontSee('Draft Expense')
            ->assertDontSee('UGX 999')
            ->assertDontSee('UGX 888');

        $this->assertSame($countsBefore['sales'], Sale::query()->count());
        $this->assertSame($countsBefore['sale_items'], SaleItem::query()->count());
        $this->assertSame($countsBefore['sale_returns'], SaleReturn::query()->count());
        $this->assertSame($countsBefore['sale_return_items'], SaleReturnItem::query()->count());
        $this->assertSame($countsBefore['purchases'], Purchase::query()->count());
        $this->assertSame($countsBefore['expenses'], Expense::query()->count());
        $this->assertSame($countsBefore['inventory_transactions'], InventoryTransaction::query()->count());
        $this->assertSame($countsBefore['product_units'], ProductUnit::query()->count());
        $this->assertEquals($pricesBefore, ProductUnit::query()->pluck('selling_price', 'id')->all());
    }

    public function test_daily_closing_pack_summarizes_returns_expenses_profit_cashiers_and_funding_sources(): void
    {
        $cashier = auth()->user();
        $cashier->update(['name' => 'Cashier Allan']);
        $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $mobileMoney = PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);

        [$soapProduct, $soapUnit] = $this->singleUnitProduct('CLOSING SOAP', 600, 1000);
        $soapSaleItem = $this->postSaleLine($soapProduct, $soapUnit, '2026-07-10', 2, 1000, 2000, 600, 'posted', $cash->id);
        $soapSaleItem->sale->update(['created_by' => $cashier->id]);
        $this->postSaleReturnLine($soapSaleItem, '2026-07-10', 0.5, 1000, 500);

        [$lowMarginProduct, $lowMarginUnit] = $this->singleUnitProduct('LOW MARGIN CLOSING SOAP', 980, 1000);
        $lowMarginSaleItem = $this->postSaleLine($lowMarginProduct, $lowMarginUnit, '2026-07-10', 1, 1000, 1000, 980, 'posted', $cash->id);
        $lowMarginSaleItem->sale->update(['created_by' => $cashier->id]);

        [$missingCostProduct, $missingCostUnit] = $this->singleUnitProduct('MISSING COST CLOSING SOAP', 0, 1500);
        $missingCostSaleItem = $this->postSaleLine($missingCostProduct, $missingCostUnit, '2026-07-10', 0.25, 1500, 375, 0, 'posted', $cash->id);
        $missingCostSaleItem->sale->update(['created_by' => $cashier->id]);

        [$mobileProduct, $mobileUnit] = $this->singleUnitProduct('MOBILE CLOSING SOAP', 300, 500);
        $mobileSaleItem = $this->postSaleLine($mobileProduct, $mobileUnit, '2026-07-10', 1, 500, 500, 300, 'posted', $mobileMoney->id);
        $mobileSaleItem->sale->update(['created_by' => $cashier->id]);
        $this->postSaleLine($mobileProduct, $mobileUnit, '2026-07-10', 9, 9999, 9999, 300, 'void', $cash->id);

        $expense = $this->postExpense('2026-07-10', 'Transport', 300, 'posted', $cash->id);
        $expense->update(['created_by' => $cashier->id]);
        $this->postExpense('2026-08-01', 'Outside Period', 999, 'posted', $cash->id);
        $this->postFundedPurchase('2026-07-10', 'Business Cash / Shop Cash', 1000, 1000, 0);
        $this->postFundedPurchase('2026-07-10', 'Supplier Credit / Not Paid Yet', 2000, 0, 2000);

        CashShift::create([
            'shift_no' => 'SHIFT-DAILY-0001',
            'store_id' => $this->store->id,
            'user_id' => $cashier->id,
            'opened_at' => '2026-07-10 08:00:00',
            'closed_at' => '2026-07-10 20:00:00',
            'opening_balance' => 100,
            'cash_sales_total' => 3375,
            'cash_expenses_total' => 300,
            'expected_cash' => 1800,
            'counted_cash' => 1750,
            'shortage_overage' => -50,
            'status' => 'closed',
        ]);

        $countsBefore = [
            'sales' => Sale::query()->count(),
            'sale_items' => SaleItem::query()->count(),
            'sale_returns' => SaleReturn::query()->count(),
            'sale_return_items' => SaleReturnItem::query()->count(),
            'purchases' => Purchase::query()->count(),
            'expenses' => Expense::query()->count(),
            'cash_shifts' => CashShift::query()->count(),
            'inventory_transactions' => InventoryTransaction::query()->count(),
            'product_units' => ProductUnit::query()->count(),
        ];

        $response = $this->get('/reports/daily-closing-pack?date_from=2026-07-10&date_to=2026-07-10');

        $response->assertOk()
            ->assertSee('Daily Owner Closing Pack / Cashier Accountability Report')
            ->assertSee('Cashier / User Summary')
            ->assertSee('Payment Mode Summary')
            ->assertSee('Income and Expenditure Summary')
            ->assertSee('Profit Summary')
            ->assertSee('Top Items Sold')
            ->assertSee('Low Margin / Warning Items')
            ->assertSee('Purchases Funding Summary')
            ->assertSee('Cash handover data available')
            ->assertSee('Cashier Allan')
            ->assertSee('Cash')
            ->assertSee('Mobile Money')
            ->assertSee('CLOSING SOAP - Pieces')
            ->assertSee('LOW MARGIN CLOSING SOAP - Pieces')
            ->assertSee('MISSING COST CLOSING SOAP - Pieces')
            ->assertSee('0.25')
            ->assertSee('Low margin under 5%')
            ->assertSee('Missing cost')
            ->assertSee('Business Cash / Shop Cash')
            ->assertSee('Supplier Credit / Not Paid Yet')
            ->assertSee('B/F not available in this system yet')
            ->assertSee('UGX 3,875')
            ->assertSee('UGX 500')
            ->assertSee('UGX 3,375')
            ->assertSee('UGX 1,800')
            ->assertSee('UGX 1,750')
            ->assertSee('UGX -50')
            ->assertSee('UGX 300')
            ->assertSee('UGX 1,195')
            ->assertSee('UGX 895')
            ->assertSee('/sales?q=Cashier%20Allan', false)
            ->assertSee('/purchases?q=Business%20Cash%20%2F%20Shop%20Cash', false)
            ->assertDontSee('Outside Period')
            ->assertDontSee('UGX 9,999');

        $csv = $this->get('/reports/daily-closing-pack?date_from=2026-07-10&date_to=2026-07-10&export=csv');

        $csv->assertOk();
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('Section,Name,"Value 1"', $csvContent);
        $this->assertStringContainsString('"Payment Mode",Cash', $csvContent);
        $this->assertStringContainsString('Cashier,"Cashier Allan"', $csvContent);
        $this->assertStringContainsString('"Top Item","CLOSING SOAP - Pieces"', $csvContent);
        $this->assertStringContainsString('"Purchase Funding","Business Cash / Shop Cash"', $csvContent);

        $this->assertSame($countsBefore['sales'], Sale::query()->count());
        $this->assertSame($countsBefore['sale_items'], SaleItem::query()->count());
        $this->assertSame($countsBefore['sale_returns'], SaleReturn::query()->count());
        $this->assertSame($countsBefore['sale_return_items'], SaleReturnItem::query()->count());
        $this->assertSame($countsBefore['purchases'], Purchase::query()->count());
        $this->assertSame($countsBefore['expenses'], Expense::query()->count());
        $this->assertSame($countsBefore['cash_shifts'], CashShift::query()->count());
        $this->assertSame($countsBefore['inventory_transactions'], InventoryTransaction::query()->count());
        $this->assertSame($countsBefore['product_units'], ProductUnit::query()->count());
    }

    public function test_daily_closing_pack_defaults_to_today_and_reports_missing_cash_handover_data(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');

        try {
            $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
            [$todayProduct, $todayUnit] = $this->singleUnitProduct('TODAY CLOSING SOAP', 500, 1000);
            [$oldProduct, $oldUnit] = $this->singleUnitProduct('YESTERDAY CLOSING SOAP', 500, 1000);

            $this->postSaleLine($todayProduct, $todayUnit, '2026-07-10', 1, 1000, 1000, 500, 'posted', $cash->id);
            $this->postSaleLine($oldProduct, $oldUnit, '2026-07-09', 1, 1000, 1000, 500, 'posted', $cash->id);

            $this->get('/reports/daily-closing-pack')
                ->assertOk()
                ->assertSee('Period: 10 Jul 2026 to 10 Jul 2026')
                ->assertSee('TODAY CLOSING SOAP')
                ->assertSee('Cash handover data not available.')
                ->assertDontSee('YESTERDAY CLOSING SOAP');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_monthly_management_pack_summarizes_business_health_and_decision_alerts(): void
    {
        $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $mobileMoney = PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);

        [$profitProduct, $profitUnit] = $this->singleUnitProduct('MONTHLY PROFIT SOAP', 600, 1000);
        $profitSaleItem = $this->postSaleLine($profitProduct, $profitUnit, '2026-07-10', 5, 1000, 5000, 600, 'posted', $cash->id);
        $this->postSaleReturnLine($profitSaleItem, '2026-07-11', 1, 1000, 1000);

        [$lowMarginProduct, $lowMarginUnit] = $this->singleUnitProduct('MONTHLY LOW MARGIN SOAP', 980, 1000);
        $this->postSaleLine($lowMarginProduct, $lowMarginUnit, '2026-07-12', 1, 1000, 1000, 980, 'posted', $cash->id);

        [$missingCostProduct, $missingCostUnit] = $this->singleUnitProduct('MONTHLY MISSING COST SOAP', 0, 1500);
        $this->postSaleLine($missingCostProduct, $missingCostUnit, '2026-07-13', 1, 1500, 1500, 0, 'posted', $mobileMoney->id);
        $this->postSaleLine($missingCostProduct, $missingCostUnit, '2026-08-01', 1, 1500, 1500, 0, 'posted', $mobileMoney->id);

        $conversionProduct = Product::create(['name' => 'MONTHLY CONVERSION BOX SOAP', 'category_id' => $this->category->id, 'is_active' => true]);
        ProductUnit::create([
            'product_id' => $conversionProduct->id,
            'unit_name' => 'Boxes',
            'conversion_factor' => 1,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);

        [$slowProduct, $slowUnit] = $this->singleUnitProduct('MONTHLY SLOW STOCK SOAP', 100, 200);
        $this->stockMovement($slowProduct, $slowUnit, 10, 0, 10, 0, 'opening_stock');

        $this->postExpense('2026-07-14', 'Transport', 700, 'posted', $cash->id);
        $this->postExpense('2026-08-01', 'Outside Period', 999, 'posted', $cash->id);
        $this->postFundedPurchase('2026-07-15', 'Business Cash / Shop Cash', 5000, 5000, 0);
        $this->postFundedPurchase('2026-07-16', 'Supplier Credit / Not Paid Yet', 3000, 0, 3000);
        $this->postFundedPurchase('2026-08-01', 'Other', 999, 999, 0);

        $countsBefore = [
            'sales' => Sale::query()->count(),
            'sale_items' => SaleItem::query()->count(),
            'sale_returns' => SaleReturn::query()->count(),
            'sale_return_items' => SaleReturnItem::query()->count(),
            'purchases' => Purchase::query()->count(),
            'expenses' => Expense::query()->count(),
            'cash_shifts' => CashShift::query()->count(),
            'inventory_transactions' => InventoryTransaction::query()->count(),
            'product_units' => ProductUnit::query()->count(),
        ];

        $response = $this->get('/reports/monthly-management-pack?date_from=2026-07-01&date_to=2026-07-31');

        $response->assertOk()
            ->assertSee('Monthly Management Pack')
            ->assertSee('Executive Summary')
            ->assertSee('Daily Trend Table')
            ->assertSee('Top Profitable Products')
            ->assertSee('Top Selling Products By Revenue')
            ->assertSee('Top Selling Products By Quantity')
            ->assertSee('Low Margin Products')
            ->assertSee('Missing Cost Products Sold')
            ->assertSee('Category Performance')
            ->assertSee('Expense Intelligence')
            ->assertSee('Payment Collection Summary')
            ->assertSee('Funding Source Summary')
            ->assertSee('Stock & Margin Alerts', false)
            ->assertSee('Fast Moving')
            ->assertSee('Slow Moving')
            ->assertSee('MONTHLY PROFIT SOAP - Pieces')
            ->assertSee('MONTHLY LOW MARGIN SOAP - Pieces')
            ->assertSee('MONTHLY MISSING COST SOAP - Pieces')
            ->assertSee('MONTHLY CONVERSION BOX SOAP')
            ->assertSee('MONTHLY SLOW STOCK SOAP')
            ->assertSee('Products missing cost price')
            ->assertSee('Products with sales but missing cost')
            ->assertSee('Products with low margin')
            ->assertSee('Possible pack conversion review')
            ->assertSee('High stock value but low sales')
            ->assertSee('Watch stock / reorder soon')
            ->assertSee('Review price / promotion / stock level')
            ->assertSee('Business Cash / Shop Cash')
            ->assertSee('Supplier Credit / Not Paid Yet')
            ->assertSee('Cash')
            ->assertSee('Mobile Money')
            ->assertSee('Transport')
            ->assertSee('UGX 7,500')
            ->assertSee('UGX 1,000')
            ->assertSee('UGX 6,500')
            ->assertSee('UGX 3,380')
            ->assertSee('UGX 3,120')
            ->assertSee('UGX 700')
            ->assertSee('UGX 2,420')
            ->assertSee('UGX 8,000')
            ->assertSee('UGX 1,000')
            ->assertSee(route('products.edit', ['product' => $profitProduct->id, 'focus' => 'units'], false), false)
            ->assertSee('/sales?date_from=2026-07-10', false)
            ->assertSee('/purchases?q=Business%20Cash%20%2F%20Shop%20Cash', false)
            ->assertDontSee('Outside Period')
            ->assertDontSee('UGX 999');

        $csv = $this->get('/reports/monthly-management-pack?date_from=2026-07-01&date_to=2026-07-31&export=csv');

        $csv->assertOk();
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('Section,Name,"Value 1"', $csvContent);
        $this->assertStringContainsString('"Executive Summary","gross sales",7500', $csvContent);
        $this->assertStringContainsString('"Daily Trend",2026-07-10', $csvContent);
        $this->assertStringContainsString('"Top Profitable Products","MONTHLY PROFIT SOAP - Pieces"', $csvContent);
        $this->assertStringContainsString('Alerts,"MONTHLY CONVERSION BOX SOAP - Boxes","Possible pack conversion review"', $csvContent);
        $this->assertStringContainsString('"Slow Moving","MONTHLY SLOW STOCK SOAP - Pieces"', $csvContent);

        $this->assertSame($countsBefore['sales'], Sale::query()->count());
        $this->assertSame($countsBefore['sale_items'], SaleItem::query()->count());
        $this->assertSame($countsBefore['sale_returns'], SaleReturn::query()->count());
        $this->assertSame($countsBefore['sale_return_items'], SaleReturnItem::query()->count());
        $this->assertSame($countsBefore['purchases'], Purchase::query()->count());
        $this->assertSame($countsBefore['expenses'], Expense::query()->count());
        $this->assertSame($countsBefore['cash_shifts'], CashShift::query()->count());
        $this->assertSame($countsBefore['inventory_transactions'], InventoryTransaction::query()->count());
        $this->assertSame($countsBefore['product_units'], ProductUnit::query()->count());
    }

    public function test_monthly_management_pack_defaults_to_current_month_and_applies_filters(): void
    {
        Carbon::setTestNow('2026-07-20 09:00:00');

        try {
            $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
            $mobile = PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);
            $otherCategory = Category::create(['name' => 'FOODS']);
            [$soapProduct, $soapUnit] = $this->singleUnitProduct('FILTERED MONTH SOAP', 500, 1000);
            $foodProduct = Product::create(['name' => 'FILTERED FOOD RICE', 'category_id' => $otherCategory->id, 'is_active' => true]);
            $foodUnit = ProductUnit::create([
                'product_id' => $foodProduct->id,
                'unit_name' => 'Pieces',
                'conversion_factor' => 1,
                'cost_price' => 200,
                'selling_price' => 500,
                'is_active' => true,
            ]);

            $this->postSaleLine($soapProduct, $soapUnit, '2026-07-05', 1, 1000, 1000, 500, 'posted', $cash->id);
            $this->postSaleLine($foodProduct, $foodUnit, '2026-07-06', 1, 500, 500, 200, 'posted', $mobile->id);
            $this->postSaleLine($soapProduct, $soapUnit, '2026-06-30', 1, 1000, 1000, 500, 'posted', $cash->id);

            $this->get('/reports/monthly-management-pack?category_id='.$this->category->id.'&payment_mode_id='.$cash->id)
                ->assertOk()
                ->assertSee('Period: 01 Jul 2026 to 31 Jul 2026')
                ->assertSee('FILTERED MONTH SOAP')
                ->assertSee('UGX 1,000')
                ->assertDontSee('FILTERED FOOD RICE')
                ->assertDontSee('30 Jun 2026');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_owner_dashboard_shows_visual_health_alerts_exports_and_is_read_only(): void
    {
        $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        $mobile = PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);

        [$profitProduct, $profitUnit] = $this->singleUnitProduct('OWNER DASH PROFIT SOAP', 500, 1000);
        $profitSaleItem = $this->postSaleLine($profitProduct, $profitUnit, '2026-07-10', 5, 1000, 5000, 500, 'posted', $cash->id);
        $this->postSaleReturnLine($profitSaleItem, '2026-07-11', 3, 1000, 3000);

        [$lowMarginProduct, $lowMarginUnit] = $this->singleUnitProduct('OWNER DASH LOW MARGIN SOAP', 960, 1000);
        $this->postSaleLine($lowMarginProduct, $lowMarginUnit, '2026-07-10', 1, 1000, 1000, 960, 'posted', $cash->id);

        [$belowCostProduct, $belowCostUnit] = $this->singleUnitProduct('OWNER DASH BELOW COST SOAP', 1200, 1000);
        $this->postSaleLine($belowCostProduct, $belowCostUnit, '2026-07-10', 1, 1000, 1000, 1200, 'posted', $cash->id);

        [$missingProduct, $missingUnit] = $this->singleUnitProduct('OWNER DASH MISSING COST SOAP', 0, 1500);
        $this->postSaleLine($missingProduct, $missingUnit, '2026-07-10', 1, 1500, 1500, 0, 'posted', $mobile->id);

        [$fastProduct, $fastUnit] = $this->singleUnitProduct('OWNER DASH FAST LOW STOCK SOAP', 500, 1000);
        $fastProduct->update(['reorder_level' => 10]);
        $this->stockMovement($fastProduct, $fastUnit, 4, 0, 4, 0, 'opening_stock');
        $this->postSaleLine($fastProduct, $fastUnit, '2026-07-10', 5, 1000, 5000, 500, 'posted', $cash->id);

        $conversionProduct = Product::create(['name' => 'OWNER DASH CONVERSION BOX SOAP', 'category_id' => $this->category->id, 'is_active' => true]);
        ProductUnit::create([
            'product_id' => $conversionProduct->id,
            'unit_name' => 'Boxes',
            'conversion_factor' => 1,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_active' => true,
        ]);

        $zeroProduct = Product::create(['name' => 'OWNER DASH ZERO PRICE SOAP', 'category_id' => $this->category->id, 'is_active' => true]);
        ProductUnit::create([
            'product_id' => $zeroProduct->id,
            'unit_name' => 'Pieces',
            'conversion_factor' => 1,
            'cost_price' => 1000,
            'selling_price' => 0,
            'is_active' => true,
        ]);

        [$slowProduct, $slowUnit] = $this->singleUnitProduct('OWNER DASH SLOW STOCK SOAP', 100, 150);
        $this->stockMovement($slowProduct, $slowUnit, 100, 0, 100, 0, 'opening_stock');

        $this->postExpense('2026-07-12', 'Rent', 7000, 'posted', $cash->id);
        $this->postFundedPurchase('2026-07-13', 'Supplier Credit / Not Paid Yet', 3000, 0, 3000);
        $this->postFundedPurchase('2026-07-14', 'Loan / Borrowed Money', 4000, 4000, 0);

        CashShift::create([
            'shift_no' => 'SHIFT-OWNER-001',
            'store_id' => $this->store->id,
            'user_id' => auth()->id(),
            'status' => 'closed',
            'opened_at' => '2026-07-10 08:00:00',
            'closed_at' => '2026-07-10 18:00:00',
            'opening_balance' => 0,
            'cash_sales_total' => 1000,
            'cash_customer_payments_total' => 0,
            'cash_expenses_total' => 0,
            'expected_cash' => 1000,
            'counted_cash' => 950,
            'shortage_overage' => -50,
        ]);

        $countsBefore = [
            'sales' => Sale::query()->count(),
            'sale_items' => SaleItem::query()->count(),
            'sale_returns' => SaleReturn::query()->count(),
            'sale_return_items' => SaleReturnItem::query()->count(),
            'purchases' => Purchase::query()->count(),
            'expenses' => Expense::query()->count(),
            'cash_shifts' => CashShift::query()->count(),
            'inventory_transactions' => InventoryTransaction::query()->count(),
            'product_units' => ProductUnit::query()->count(),
        ];
        $pricesBefore = ProductUnit::query()->pluck('selling_price', 'id')->all();

        $response = $this->get('/reports/owner-dashboard?date_from=2026-07-01&date_to=2026-07-31');

        $response->assertOk()
            ->assertSee('Owner Dashboard / Business Health Dashboard')
            ->assertSee('Owner Health Score')
            ->assertSee('Needs Attention')
            ->assertSee('Owner Health Summary Cards')
            ->assertSee('Sales vs Expenses vs Estimated Net Profit Trend')
            ->assertSee('Net Sales by Payment Mode')
            ->assertSee('Top 10 Products by Profit')
            ->assertSee('Top 10 Products by Revenue')
            ->assertSee('Expense Breakdown')
            ->assertSee('Owner Alerts Panel')
            ->assertSee('Profit by Product after Returns')
            ->assertSee('Profit by Category after Returns')
            ->assertSee('Purchase Funding Source Summary')
            ->assertSee('Cash')
            ->assertSee('Mobile Money')
            ->assertSee('Missing cost on sold items')
            ->assertSee('Selling below cost')
            ->assertSee('Low margin below 5%')
            ->assertSee('Zero selling price')
            ->assertSee('Possible pack conversion review')
            ->assertSee('High returns/refunds')
            ->assertSee('Expenses too high vs sales')
            ->assertSee('Negative net profit')
            ->assertSee('Cash shortage/excess')
            ->assertSee('Supplier credit / unpaid purchases increasing')
            ->assertSee('Loan / borrowed money used')
            ->assertSee('Fast-moving low stock')
            ->assertSee('Slow-moving item with high stock value')
            ->assertSee(route('products.edit', ['product' => $belowCostProduct->id, 'focus' => 'units'], false), false)
            ->assertSee(route('cash-shifts.index', [], false), false)
            ->assertSee('Export Dashboard CSV')
            ->assertSee('Export Alerts CSV');

        $dashboardCsv = $this->get('/reports/owner-dashboard?date_from=2026-07-01&date_to=2026-07-31&export=csv');
        $dashboardCsv->assertOk();
        $dashboardCsvContent = $dashboardCsv->streamedContent();
        $this->assertStringContainsString('Health Score', $dashboardCsvContent);
        $this->assertStringContainsString('Trend,2026-07-10', $dashboardCsvContent);
        $this->assertStringContainsString('Purchase Funding Source', $dashboardCsvContent);

        $alertsCsv = $this->get('/reports/owner-dashboard?date_from=2026-07-01&date_to=2026-07-31&export=alerts_csv');
        $alertsCsv->assertOk();
        $alertsCsvContent = $alertsCsv->streamedContent();
        $this->assertStringContainsString('Severity,Issue,"Business Meaning"', $alertsCsvContent);
        $this->assertStringContainsString('Selling below cost', $alertsCsvContent);
        $this->assertStringContainsString('Cash shortage/excess', $alertsCsvContent);

        $this->get('/management-centre')
            ->assertOk()
            ->assertSee('Owner Dashboard')
            ->assertSee(route('reports.owner-dashboard', [], false), false);

        $this->get('/reports/financial-summary?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertSee('Owner Dashboard')
            ->assertSee(route('reports.owner-dashboard', [], false), false);

        $this->assertSame($countsBefore['sales'], Sale::query()->count());
        $this->assertSame($countsBefore['sale_items'], SaleItem::query()->count());
        $this->assertSame($countsBefore['sale_returns'], SaleReturn::query()->count());
        $this->assertSame($countsBefore['sale_return_items'], SaleReturnItem::query()->count());
        $this->assertSame($countsBefore['purchases'], Purchase::query()->count());
        $this->assertSame($countsBefore['expenses'], Expense::query()->count());
        $this->assertSame($countsBefore['cash_shifts'], CashShift::query()->count());
        $this->assertSame($countsBefore['inventory_transactions'], InventoryTransaction::query()->count());
        $this->assertSame($countsBefore['product_units'], ProductUnit::query()->count());
        $this->assertEquals($pricesBefore, ProductUnit::query()->pluck('selling_price', 'id')->all());
    }

    public function test_owner_dashboard_defaults_to_current_month_and_applies_filters(): void
    {
        Carbon::setTestNow('2026-07-20 09:00:00');

        try {
            $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
            $mobile = PaymentMode::create(['name' => 'Mobile Money', 'is_active' => true]);
            $foodCategory = Category::create(['name' => 'FOODS']);
            [$soapProduct, $soapUnit] = $this->singleUnitProduct('OWNER FILTERED SOAP', 500, 1000);
            $foodProduct = Product::create(['name' => 'OWNER FILTERED RICE', 'category_id' => $foodCategory->id, 'is_active' => true]);
            $foodUnit = ProductUnit::create([
                'product_id' => $foodProduct->id,
                'unit_name' => 'Pieces',
                'conversion_factor' => 1,
                'cost_price' => 200,
                'selling_price' => 500,
                'is_active' => true,
            ]);

            $this->postSaleLine($soapProduct, $soapUnit, '2026-07-05', 1, 1000, 1000, 500, 'posted', $cash->id);
            $this->postSaleLine($foodProduct, $foodUnit, '2026-07-06', 1, 500, 500, 200, 'posted', $mobile->id);
            $this->postSaleLine($soapProduct, $soapUnit, '2026-06-30', 1, 1000, 1000, 500, 'posted', $cash->id);

            $this->get('/reports/owner-dashboard?category_id='.$this->category->id.'&payment_mode_id='.$cash->id)
                ->assertOk()
                ->assertSee('Period: 01 Jul 2026 to 31 Jul 2026')
                ->assertSee('OWNER FILTERED SOAP')
                ->assertSee('UGX 1,000')
                ->assertDontSee('OWNER FILTERED RICE')
                ->assertDontSee('30 Jun 2026');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cash_sales_summary_page_and_csv_group_posted_sales_by_shop_and_payment_type(): void
    {
        $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        [$product, $unit] = $this->singleUnitProduct('OWNER CASH SOAP', 600, 1000);
        $saleItem = $this->postSaleLine($product, $unit, '2026-07-10', 0.25, 1000, 250, 600, 'posted', $cash->id);
        $this->postSaleLine($product, $unit, '2026-07-10', 3, 1000, 3000, 600, 'void', $cash->id);

        $response = $this->get('/reports/cash-sales-summary?date_from=2026-07-01&date_to=2026-07-31&payment_mode_id='.$cash->id);

        $response->assertOk()
            ->assertSee('Summary Cash Sales/Income by Shop Report')
            ->assertSee('SHOP: Main Shop')
            ->assertSee('Cash Sale')
            ->assertSee('OWNER CASH SOAP - Pieces')
            ->assertSee('0.25')
            ->assertSee('UGX 250')
            ->assertSee('Grand Total')
            ->assertSee(route('reports.consolidated-sales-detail', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
                'payment_mode_id' => $cash->id,
            ], false))
            ->assertDontSee('UGX 3,000');

        $csv = $this->get('/reports/cash-sales-summary?date_from=2026-07-01&date_to=2026-07-31&payment_mode_id='.$cash->id.'&export=csv');

        $csv->assertOk();
        $this->assertStringContainsString('Shop,"Sale Group",S/N,Item,Qty', $csv->streamedContent());
        $this->assertStringContainsString('OWNER CASH SOAP - Pieces', $csv->streamedContent());
        $this->assertStringContainsString('0.25', $csv->streamedContent());
    }

    public function test_income_expenditure_report_includes_sales_income_expenses_and_links(): void
    {
        $cash = PaymentMode::create(['name' => 'Cash', 'is_active' => true]);
        [$product, $unit] = $this->singleUnitProduct('INCOME SOAP', 600, 1000);
        $saleItem = $this->postSaleLine($product, $unit, '2026-07-10', 2, 1000, 2000, 600, 'posted', $cash->id);
        $expense = $this->postExpense('2026-07-11', 'Transport', 700, 'posted', $cash->id);
        $this->postExpense('2026-08-01', 'Outside Period', 999, 'posted', $cash->id);

        $response = $this->get('/reports/income-expenditure?date_from=2026-07-01&date_to=2026-07-31&payment_mode_id='.$cash->id);

        $response->assertOk()
            ->assertSee('Income and Expenditure by Account Detailed Report')
            ->assertSee('B/F not available in this system yet')
            ->assertSee('Cash Sales / Income')
            ->assertSee('Cash Expenses')
            ->assertSee('INCOME SOAP - Pieces')
            ->assertSee('Transport')
            ->assertSee('UGX 2,000')
            ->assertSee('UGX 700')
            ->assertSee('UGX 1,300')
            ->assertSee(route('sales.show', $saleItem->sale, false), false)
            ->assertSee(route('expenses.show', $expense, false), false)
            ->assertDontSee('Outside Period')
            ->assertDontSee('UGX 999');

        $csv = $this->get('/reports/income-expenditure?date_from=2026-07-01&date_to=2026-07-31&payment_mode_id='.$cash->id.'&export=csv');

        $csv->assertOk();
        $this->assertStringContainsString('Date,Reference,Section,Item,Qty,Rate,Income,Expenditure', $csv->streamedContent());
        $this->assertStringContainsString('INCOME SOAP - Pieces', $csv->streamedContent());
        $this->assertStringContainsString('Transport', $csv->streamedContent());
    }

    public function test_gross_margin_summary_uses_net_returns_and_avoids_fake_missing_cost_profit(): void
    {
        [$product, $unit] = $this->singleUnitProduct('MARGIN SOAP', 600, 1000);
        $saleItem = $this->postSaleLine($product, $unit, '2026-07-10', 5, 1000, 5000, 600);
        $this->postSaleReturnLine($saleItem, '2026-07-12', 1, 1000, 1000);
        [$missingProduct, $missingUnit] = $this->singleUnitProduct('MARGIN MISSING COST', 0, 1500);
        $this->postSaleLine($missingProduct, $missingUnit, '2026-07-10', 1, 1500, 1500, 0);

        $response = $this->get('/reports/gross-margin-summary?date_from=2026-07-01&date_to=2026-07-31');

        $response->assertOk()
            ->assertSee('Consolidated Sales Summary with Gross Margins')
            ->assertSee('MARGIN SOAP')
            ->assertSee('UGX 5,000')
            ->assertSee('UGX 2,400')
            ->assertSee('UGX 1,000')
            ->assertSee('UGX 1,600')
            ->assertSee('40.00%')
            ->assertSee('MARGIN MISSING COST')
            ->assertSee('N/A')
            ->assertSee(route('products.edit', ['product' => $product->id, 'focus' => 'units'], false), false)
            ->assertDontSee('100.00%');

        $csv = $this->get('/reports/gross-margin-summary?date_from=2026-07-01&date_to=2026-07-31&export=csv');

        $csv->assertOk();
        $this->assertStringContainsString('Item,Qty,"Sales Amount","Cost Amount"', $csv->streamedContent());
        $this->assertStringContainsString('MARGIN SOAP', $csv->streamedContent());
    }

    public function test_consolidated_sales_detail_page_and_csv_show_receipt_lines_and_fractional_quantities(): void
    {
        [$product, $unit] = $this->singleUnitProduct('DETAIL SOAP', 600, 1000);
        $saleItem = $this->postSaleLine($product, $unit, '2026-07-10', 0.5, 1000, 500, 600);
        $this->postSaleLine($product, $unit, '2026-07-10', 2, 1000, 2000, 600, 'void');

        $response = $this->get('/reports/consolidated-sales-detail?date_from=2026-07-01&date_to=2026-07-31');

        $response->assertOk()
            ->assertSee('Consolidated Cash Sales/Income Report')
            ->assertSee('DETAIL SOAP - Pieces')
            ->assertSee('0.5')
            ->assertSee('UGX 500')
            ->assertSee('Total Cash Collected')
            ->assertSee(route('sales.show', $saleItem->sale, false), false)
            ->assertDontSee('UGX 2,000');

        $csv = $this->get('/reports/consolidated-sales-detail?date_from=2026-07-01&date_to=2026-07-31&export=csv');

        $csv->assertOk();
        $this->assertStringContainsString('Store,Date,Reference,Item,Qty,Rate,"Total Amount"', $csv->streamedContent());
        $this->assertStringContainsString('DETAIL SOAP - Pieces', $csv->streamedContent());
        $this->assertStringContainsString('0.5', $csv->streamedContent());
    }

    public function test_owner_print_reports_are_read_only_and_linked_from_management_centre(): void
    {
        [$product, $unit] = $this->singleUnitProduct('OWNER READ ONLY SOAP', 500, 1000);
        $saleItem = $this->postSaleLine($product, $unit, '2026-07-10', 1, 1000, 1000, 500);
        $this->postSaleReturnLine($saleItem, '2026-07-11', 0.5, 1000, 500);
        $this->postExpense('2026-07-12', 'Rent', 300);

        $countsBefore = [
            'sales' => Sale::query()->count(),
            'sale_items' => SaleItem::query()->count(),
            'sale_returns' => SaleReturn::query()->count(),
            'sale_return_items' => SaleReturnItem::query()->count(),
            'purchases' => Purchase::query()->count(),
            'expenses' => Expense::query()->count(),
            'inventory_transactions' => InventoryTransaction::query()->count(),
            'product_units' => ProductUnit::query()->count(),
        ];
        $pricesBefore = ProductUnit::query()->pluck('selling_price', 'id')->all();

        $this->get('/reports/cash-sales-summary?date_from=2026-07-01&date_to=2026-07-31')->assertOk();
        $this->get('/reports/income-expenditure?date_from=2026-07-01&date_to=2026-07-31')->assertOk();
        $this->get('/reports/gross-margin-summary?date_from=2026-07-01&date_to=2026-07-31')->assertOk();
        $this->get('/reports/consolidated-sales-detail?date_from=2026-07-01&date_to=2026-07-31')->assertOk();
        $this->get('/reports/daily-closing-pack?date_from=2026-07-01&date_to=2026-07-31')->assertOk();
        $this->get('/reports/monthly-management-pack?date_from=2026-07-01&date_to=2026-07-31')->assertOk();
        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31&export=csv')->assertOk();

        $this->get('/management-centre')
            ->assertOk()
            ->assertSee('Monthly Management Pack')
            ->assertSee('Daily Closing Pack')
            ->assertSee('Cash Sales Summary')
            ->assertSee('Income &amp; Expenditure', false)
            ->assertSee('Gross Margin Summary')
            ->assertSee('Consolidated Sales Detail')
            ->assertSee(route('reports.monthly-management-pack', [], false), false)
            ->assertSee(route('reports.daily-closing-pack', [], false), false)
            ->assertSee(route('reports.cash-sales-summary', [], false), false);

        $this->assertSame($countsBefore['sales'], Sale::query()->count());
        $this->assertSame($countsBefore['sale_items'], SaleItem::query()->count());
        $this->assertSame($countsBefore['sale_returns'], SaleReturn::query()->count());
        $this->assertSame($countsBefore['sale_return_items'], SaleReturnItem::query()->count());
        $this->assertSame($countsBefore['purchases'], Purchase::query()->count());
        $this->assertSame($countsBefore['expenses'], Expense::query()->count());
        $this->assertSame($countsBefore['inventory_transactions'], InventoryTransaction::query()->count());
        $this->assertSame($countsBefore['product_units'], ProductUnit::query()->count());
        $this->assertEquals($pricesBefore, ProductUnit::query()->pluck('selling_price', 'id')->all());
    }

    public function test_gross_profit_report_is_read_only(): void
    {
        [$product, $unit] = $this->singleUnitProduct('READ ONLY PROFIT SOAP', 500, 1000);
        $this->postPurchaseCost($product, $unit, 2, 500);
        $this->stockMovement($product, $unit, 2, 0, 2, 0, 'purchase');
        $this->postSaleLine($product, $unit, '2026-07-10', 1, 1000, 1000, 500);

        $countsBefore = [
            'sales' => Sale::query()->count(),
            'sale_items' => SaleItem::query()->count(),
            'sale_returns' => SaleReturn::query()->count(),
            'sale_return_items' => SaleReturnItem::query()->count(),
            'purchases' => Purchase::query()->count(),
            'purchase_items' => PurchaseItem::query()->count(),
            'expenses' => Expense::query()->count(),
            'inventory_transactions' => InventoryTransaction::query()->count(),
            'product_units' => ProductUnit::query()->count(),
        ];
        $pricesBefore = ProductUnit::query()->pluck('cost_price', 'id')->all();

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31')->assertOk();

        $this->assertSame($countsBefore['sales'], Sale::query()->count());
        $this->assertSame($countsBefore['sale_items'], SaleItem::query()->count());
        $this->assertSame($countsBefore['sale_returns'], SaleReturn::query()->count());
        $this->assertSame($countsBefore['sale_return_items'], SaleReturnItem::query()->count());
        $this->assertSame($countsBefore['purchases'], Purchase::query()->count());
        $this->assertSame($countsBefore['purchase_items'], PurchaseItem::query()->count());
        $this->assertSame($countsBefore['expenses'], Expense::query()->count());
        $this->assertSame($countsBefore['inventory_transactions'], InventoryTransaction::query()->count());
        $this->assertSame($countsBefore['product_units'], ProductUnit::query()->count());
        $this->assertEquals($pricesBefore, ProductUnit::query()->pluck('cost_price', 'id')->all());
    }

    public function test_financial_reports_are_read_only(): void
    {
        [$product, $piece, $carton] = $this->productWithPieceAndCarton('READ ONLY SOAP');
        $this->postPurchaseCost($product, $carton, 1, 24000);
        $this->stockMovement($product, $carton, 1, 0, 24, 0, 'purchase');

        $countsBefore = [
            'sales' => Sale::query()->count(),
            'purchases' => Purchase::query()->count(),
            'purchase_items' => PurchaseItem::query()->count(),
            'inventory_transactions' => InventoryTransaction::query()->count(),
            'product_units' => ProductUnit::query()->count(),
        ];
        $pricesBefore = ProductUnit::query()->pluck('selling_price', 'id')->all();

        $this->get('/reports/stock-valuation')->assertOk();
        $this->get('/reports/price-margins')->assertOk();

        $this->assertSame($countsBefore['sales'], Sale::query()->count());
        $this->assertSame($countsBefore['purchases'], Purchase::query()->count());
        $this->assertSame($countsBefore['purchase_items'], PurchaseItem::query()->count());
        $this->assertSame($countsBefore['inventory_transactions'], InventoryTransaction::query()->count());
        $this->assertSame($countsBefore['product_units'], ProductUnit::query()->count());
        $this->assertEquals($pricesBefore, ProductUnit::query()->pluck('selling_price', 'id')->all());
    }

    private function productWithPieceAndCarton(string $name): array
    {
        $product = Product::create([
            'name' => $name,
            'code' => str_replace(' ', '-', strtoupper($name)),
            'category_id' => $this->category->id,
            'base_unit_label' => 'Pieces',
            'is_active' => true,
        ]);
        $piece = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pieces',
            'conversion_factor' => 1,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $carton = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Cartons',
            'conversion_factor' => 24,
            'cost_price' => 24000,
            'selling_price' => 36000,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $piece->id]);

        return [$product->fresh(['units', 'baseProductUnit', 'category']), $piece, $carton];
    }

    private function singleUnitProduct(string $name, float $costPrice, float $sellingPrice): array
    {
        $product = Product::create([
            'name' => $name,
            'category_id' => $this->category->id,
            'base_unit_label' => 'Pieces',
            'is_active' => true,
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pieces',
            'conversion_factor' => 1,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        $product->update(['base_product_unit_id' => $unit->id]);

        return [$product->fresh(['units', 'baseProductUnit', 'category']), $unit];
    }

    private function postPurchaseCost(Product $product, ProductUnit $unit, float $quantity, float $unitCost): void
    {
        $purchase = Purchase::create([
            'purchase_no' => sprintf('PUR-TEST-%04d', Purchase::query()->count() + 1),
            'purchase_date' => '2026-07-28',
            'supplier_id' => \App\Models\Supplier::query()->firstOrCreate(['name' => 'Report Supplier'], ['is_active' => true])->id,
            'store_id' => $this->store->id,
            'purchase_type' => 'cash',
            'subtotal' => $quantity * $unitCost,
            'total_amount' => $quantity * $unitCost,
            'amount_paid' => $quantity * $unitCost,
            'balance_due' => 0,
            'status' => 'posted',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => $quantity,
            'base_quantity' => $quantity * (float) $unit->conversion_factor,
            'conversion_factor_snapshot' => (float) $unit->conversion_factor,
            'unit_cost' => $unitCost,
            'line_total' => $quantity * $unitCost,
        ]);
    }

    private function stockMovement(Product $product, ProductUnit $unit, float $quantityIn, float $quantityOut, float $baseIn, float $baseOut, string $movementType): void
    {
        InventoryTransaction::create([
            'transaction_date' => '2026-07-28',
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'reference_type' => 'report_test',
            'reference_id' => random_int(1, 999999),
            'reference_no' => 'REPORT-TEST',
            'movement_type' => $movementType,
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'base_quantity_in' => $baseIn,
            'base_quantity_out' => $baseOut,
            'conversion_factor_snapshot' => (float) $unit->conversion_factor,
            'unit_cost' => $unit->cost_price,
        ]);
    }

    private function postSaleReturnLine(SaleItem $saleItem, string $date, float $quantity, float $unitPrice, float $lineTotal, string $status = 'posted'): SaleReturnItem
    {
        $saleReturn = SaleReturn::create([
            'return_no' => sprintf('RET-REPORT-%04d', SaleReturn::query()->count() + 1),
            'return_date' => $date,
            'sale_id' => $saleItem->sale_id,
            'customer_id' => null,
            'store_id' => $this->store->id,
            'return_type' => 'refund',
            'returned_total' => $lineTotal,
            'refund_amount' => $status === 'posted' ? $lineTotal : 0,
            'store_credit_amount' => 0,
            'status' => $status,
        ]);

        $unit = ProductUnit::query()->findOrFail($saleItem->product_unit_id);

        return SaleReturnItem::create([
            'sale_return_id' => $saleReturn->id,
            'sale_item_id' => $saleItem->id,
            'product_id' => $saleItem->product_id,
            'product_unit_id' => $saleItem->product_unit_id,
            'quantity' => $quantity,
            'base_quantity' => $quantity * (float) $unit->conversion_factor,
            'conversion_factor_snapshot' => (float) $unit->conversion_factor,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);
    }

    private function postExpense(string $date, string $category, float $amount, string $status = 'posted', ?int $paymentModeId = null): Expense
    {
        return Expense::create([
            'expense_no' => sprintf('EXP-REPORT-%04d', Expense::query()->count() + 1),
            'expense_date' => $date,
            'store_id' => $this->store->id,
            'payment_mode_id' => $paymentModeId,
            'category' => $category,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    private function postFundedPurchase(string $date, string $sourceName, float $total, float $paid, float $balance): Purchase
    {
        $fundingSource = PurchaseFundingSource::query()->firstOrCreate(
            ['name' => $sourceName],
            ['is_active' => true, 'sort_order' => PurchaseFundingSource::query()->count() * 10 + 10]
        );

        return Purchase::create([
            'purchase_no' => sprintf('PUR-FUNDING-%04d', Purchase::query()->count() + 1),
            'purchase_date' => $date,
            'supplier_id' => Supplier::query()->firstOrCreate(['name' => 'Funding Report Supplier'], ['is_active' => true])->id,
            'store_id' => $this->store->id,
            'purchase_type' => $balance > 0 ? 'credit' : 'cash',
            'purchase_funding_source_id' => $fundingSource->id,
            'subtotal' => $total,
            'total_amount' => $total,
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'status' => 'posted',
        ]);
    }

    private function postSaleLine(Product $product, ProductUnit $unit, string $date, float $quantity, float $unitPrice, float $lineTotal, float $costSnapshot = 0, string $status = 'posted', ?int $paymentModeId = null): SaleItem
    {
        $sale = Sale::create([
            'sale_no' => sprintf('SALE-REPORT-%04d', Sale::query()->count() + 1),
            'sale_date' => $date,
            'store_id' => $this->store->id,
            'sale_type' => 'cash',
            'payment_mode_id' => $paymentModeId,
            'subtotal' => $lineTotal,
            'total_amount' => $lineTotal,
            'amount_paid' => $status === 'posted' ? $lineTotal : 0,
            'balance_due' => 0,
            'status' => $status,
        ]);

        return SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'quantity' => $quantity,
            'base_quantity' => $quantity * (float) $unit->conversion_factor,
            'conversion_factor_snapshot' => (float) $unit->conversion_factor,
            'unit_price' => $unitPrice,
            'selling_price_snapshot' => $unitPrice,
            'cost_price_snapshot' => $costSnapshot,
            'line_total' => $lineTotal,
        ]);
    }
}
