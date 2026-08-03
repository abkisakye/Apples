<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Supplier;
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

    public function test_gross_profit_report_is_read_only(): void
    {
        [$product, $unit] = $this->singleUnitProduct('READ ONLY PROFIT SOAP', 500, 1000);
        $this->postPurchaseCost($product, $unit, 2, 500);
        $this->stockMovement($product, $unit, 2, 0, 2, 0, 'purchase');
        $this->postSaleLine($product, $unit, '2026-07-10', 1, 1000, 1000, 500);

        $countsBefore = [
            'sales' => Sale::query()->count(),
            'sale_items' => SaleItem::query()->count(),
            'purchases' => Purchase::query()->count(),
            'purchase_items' => PurchaseItem::query()->count(),
            'inventory_transactions' => InventoryTransaction::query()->count(),
            'product_units' => ProductUnit::query()->count(),
        ];
        $pricesBefore = ProductUnit::query()->pluck('cost_price', 'id')->all();

        $this->get('/reports/gross-profit?date_from=2026-07-01&date_to=2026-07-31')->assertOk();

        $this->assertSame($countsBefore['sales'], Sale::query()->count());
        $this->assertSame($countsBefore['sale_items'], SaleItem::query()->count());
        $this->assertSame($countsBefore['purchases'], Purchase::query()->count());
        $this->assertSame($countsBefore['purchase_items'], PurchaseItem::query()->count());
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

    private function postSaleLine(Product $product, ProductUnit $unit, string $date, float $quantity, float $unitPrice, float $lineTotal, float $costSnapshot = 0, string $status = 'posted'): SaleItem
    {
        $sale = Sale::create([
            'sale_no' => sprintf('SALE-REPORT-%04d', Sale::query()->count() + 1),
            'sale_date' => $date,
            'store_id' => $this->store->id,
            'sale_type' => 'cash',
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
