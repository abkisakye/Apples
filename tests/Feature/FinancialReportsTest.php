<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Store;
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
            ->assertSee('Estimated from latest purchase');
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
            ->assertSee($zeroSellingProduct->name)
            ->assertSee('Zero selling price')
            ->assertSee($bulkProduct->name)
            ->assertSee('Possible Pack Conversion Review')
            ->assertSee('N/A');
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
}
