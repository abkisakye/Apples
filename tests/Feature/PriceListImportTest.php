<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\PriceListImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PriceListImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_reads_simple_same_line_rows(): void
    {
        $parsed = app(PriceListImportService::class)->parseText(<<<'TXT'
BAKING FLOUR
1 AZAM 25KG - Bags 64,000Bags
2 AZAM 2KG - Pieces 7,500Pieces
TXT);

        $this->assertCount(2, $parsed['rows']);
        $this->assertSame('BAKING FLOUR', $parsed['rows'][0]['category']);
        $this->assertSame('AZAM 25KG', $parsed['rows'][0]['product_name']);
        $this->assertSame('Bags', $parsed['rows'][0]['unit_name']);
        $this->assertEquals(64000, $parsed['rows'][0]['selling_price']);
        $this->assertSame('Pieces', $parsed['rows'][1]['unit_name']);
    }

    public function test_parser_reads_split_line_rows(): void
    {
        $parsed = app(PriceListImportService::class)->parseText(<<<'TXT'
BATHING SOAP
1 DETTOL ACTIVE SOAP 90G - Boxes
298,000Boxes
2 DETTOL ACTIVE SOAP 90G - Pieces 3,500Pieces
TXT);

        $this->assertCount(2, $parsed['rows']);
        $this->assertSame('DETTOL ACTIVE SOAP 90G', $parsed['rows'][0]['product_name']);
        $this->assertSame('Boxes', $parsed['rows'][0]['unit_name']);
        $this->assertEquals(298000, $parsed['rows'][0]['selling_price']);
    }

    public function test_import_groups_product_units_under_one_product(): void
    {
        $path = $this->writePriceList(<<<'TXT'
BATHING SOAP
1 DETTOL ACTIVE SOAP 90G - Boxes 298,000Boxes
2 DETTOL ACTIVE SOAP 90G - Dozens 40,000Dozens
3 DETTOL ACTIVE SOAP 90G - Pieces 3,500Pieces
TXT);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--commit' => true,
        ])->assertSuccessful();

        $product = Product::query()->where('name', 'DETTOL ACTIVE SOAP 90G')->firstOrFail();
        $this->assertSame(1, Product::query()->where('name', 'DETTOL ACTIVE SOAP 90G')->count());
        $this->assertSame(3, $product->units()->count());
        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'unit_name' => 'Boxes']);
        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'unit_name' => 'Dozens']);
        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'unit_name' => 'Pieces']);
    }

    public function test_dry_run_does_not_write_to_database(): void
    {
        $path = $this->writePriceList(<<<'TXT'
BATTERIES
1 TIGER BATTERY AA - Pieces 1,000Pieces
TXT);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Mode: DRY RUN')
            ->expectsOutputToContain('Products to create')
            ->assertSuccessful();

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_units', 0);
    }

    public function test_commit_creates_categories_products_and_units_without_stock(): void
    {
        $path = $this->writePriceList(<<<'TXT'
SUGAR
1 KAKIRA SUGAR 1KG - Kgs 5,000Kgs
TXT);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--commit' => true,
        ])->assertSuccessful();

        $category = Category::query()->where('name', 'SUGAR')->firstOrFail();
        $product = Product::query()->where('name', 'KAKIRA SUGAR 1KG')->firstOrFail();
        $unit = ProductUnit::query()->where('product_id', $product->id)->where('unit_name', 'Kgs')->firstOrFail();

        $this->assertSame($category->id, $product->category_id);
        $this->assertEquals(5000, (float) $unit->selling_price);
        $this->assertEquals(1, (float) $unit->conversion_factor);
        $this->assertTrue((bool) $unit->allow_fractional_quantity);
        $this->assertSame(2, (int) $unit->quantity_precision);
        $this->assertEquals(0, (float) $unit->opening_stock_qty);
        $this->assertSame($unit->id, $product->base_product_unit_id);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_update_prices_updates_existing_unit_price_when_requested(): void
    {
        $category = Category::create(['name' => 'BATHING SOAP', 'is_active' => true]);
        $product = Product::create(['name' => 'DETTOL ACTIVE SOAP 90G', 'category_id' => $category->id, 'is_active' => true]);
        ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Pieces',
            'conversion_factor' => 1,
            'selling_price' => 3000,
            'cost_price' => 0,
            'is_active' => true,
        ]);
        $path = $this->writePriceList(<<<'TXT'
BATHING SOAP
1 DETTOL ACTIVE SOAP 90G - Pieces 3,500Pieces
TXT);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--commit' => true,
            '--update-prices' => true,
        ])->assertSuccessful();

        $this->assertEquals(3500, (float) ProductUnit::query()->where('product_id', $product->id)->where('unit_name', 'Pieces')->value('selling_price'));
    }

    public function test_conversion_review_is_reported_for_uncertain_units(): void
    {
        $path = $this->writePriceList(<<<'TXT'
BAKING FLOUR
1 AZAM 25KG - Bags 64,000Bags
TXT);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Conversion reviews       : 1')
            ->expectsOutputToContain('AZAM 25KG / Bags assumes conversion_factor=1')
            ->assertSuccessful();
    }

    public function test_safe_conversion_and_default_flags_are_applied(): void
    {
        $path = $this->writePriceList(<<<'TXT'
BATHING SOAP
1 DETTOL ACTIVE SOAP 90G - Boxes 298,000Boxes
2 DETTOL ACTIVE SOAP 90G - Dozens 40,000Dozens
3 DETTOL ACTIVE SOAP 90G - Pieces 3,500Pieces
TXT);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--commit' => true,
        ])->assertSuccessful();

        $product = Product::query()->where('name', 'DETTOL ACTIVE SOAP 90G')->firstOrFail();
        $pieces = $product->units()->where('unit_name', 'Pieces')->firstOrFail();
        $dozens = $product->units()->where('unit_name', 'Dozens')->firstOrFail();
        $boxes = $product->units()->where('unit_name', 'Boxes')->firstOrFail();

        $this->assertSame($pieces->id, $product->base_product_unit_id);
        $this->assertTrue((bool) $pieces->is_base_unit);
        $this->assertTrue((bool) $pieces->is_pos_unit);
        $this->assertEquals(12, (float) $dozens->conversion_factor);
        $this->assertEquals(1, (float) $boxes->conversion_factor);
        $this->assertFalse((bool) $boxes->allow_fractional_quantity);
    }

    public function test_category_filter_and_limit_are_applied(): void
    {
        $path = $this->writePriceList(<<<'TXT'
BAKING FLOUR
1 AZAM 25KG - Bags 64,000Bags
BATHING SOAP
1 DETTOL ACTIVE SOAP 90G - Pieces 3,500Pieces
2 GEISHA SOAP 90G - Pieces 2,500Pieces
TXT);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--commit' => true,
            '--category' => 'BATHING SOAP',
            '--limit' => 1,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('products', ['name' => 'AZAM 25KG']);
        $this->assertDatabaseHas('products', ['name' => 'DETTOL ACTIVE SOAP 90G']);
        $this->assertDatabaseMissing('products', ['name' => 'GEISHA SOAP 90G']);
    }

    public function test_excel_parser_reads_category_and_product_rows(): void
    {
        $path = $this->writeExcelPriceList([
            ['APPLES OF GOLD WHOLESALERS', null, null, null, null],
            ['CategoryName', 'ProductID', 'Date', 'Text143', 'SellingPrice'],
            ['BAKING FLOUR', null, null, null, null],
            [null, 1, 'AZAM 25KG - Bags', 64000, 'Bags'],
            [null, 2, 'AZAM 2KG - Cartons', 82000, 'Cartons'],
            [null, 3, 'AZAM 2KG - Pieces', 7500, 'Pieces'],
        ]);

        $parsed = app(PriceListImportService::class)->parseFile($path);

        $this->assertCount(3, $parsed['rows']);
        $this->assertSame('BAKING FLOUR', $parsed['rows'][0]['category']);
        $this->assertSame('AZAM 25KG', $parsed['rows'][0]['product_name']);
        $this->assertSame('Bags', $parsed['rows'][0]['unit_name']);
        $this->assertEquals(64000, $parsed['rows'][0]['selling_price']);
    }

    public function test_excel_parser_prefers_column_e_unit_and_reports_suffix_mismatch(): void
    {
        $path = $this->writeExcelPriceList([
            ['BATHING SOAP', null, null, null, null],
            [null, 1, 'DETTOL ACTIVE SOAP 90G - Box', 298000, 'Boxes'],
        ]);

        $parsed = app(PriceListImportService::class)->parseFile($path);

        $this->assertSame('DETTOL ACTIVE SOAP 90G', $parsed['rows'][0]['product_name']);
        $this->assertSame('Boxes', $parsed['rows'][0]['unit_name']);
        $this->assertNotEmpty($parsed['warnings']);
        $this->assertStringContainsString("unit suffix 'Box' differs from column E 'Boxes'", $parsed['warnings'][0]);
    }

    public function test_excel_dry_run_does_not_write_and_reports_zero_price(): void
    {
        $path = $this->writeExcelPriceList([
            ['BATHING SOAP', null, null, null, null],
            [null, 1, 'DETTOL ACTIVE SOAP 90G - Pieces', 0, 'Pieces'],
        ]);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Parsed rows              : 1')
            ->expectsOutputToContain('Zero-price rows          : 1')
            ->expectsOutputToContain('DETTOL ACTIVE SOAP 90G / Pieces in BATHING SOAP')
            ->assertSuccessful();

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_units', 0);
    }

    public function test_excel_commit_groups_units_and_creates_no_stock(): void
    {
        $path = $this->writeExcelPriceList([
            ['BATHING SOAP', null, null, null, null],
            [null, 1, 'DETTOL ACTIVE SOAP 90G - Boxes', 298000, 'Boxes'],
            [null, 2, 'DETTOL ACTIVE SOAP 90G - Dozens', 40000, 'Dozens'],
            [null, 3, 'DETTOL ACTIVE SOAP 90G - Pieces', 3500, 'Pieces'],
        ]);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--commit' => true,
        ])->assertSuccessful();

        $product = Product::query()->where('name', 'DETTOL ACTIVE SOAP 90G')->firstOrFail();
        $pieces = $product->units()->where('unit_name', 'Pieces')->firstOrFail();
        $dozens = $product->units()->where('unit_name', 'Dozens')->firstOrFail();
        $boxes = $product->units()->where('unit_name', 'Boxes')->firstOrFail();

        $this->assertSame(1, Product::query()->where('name', 'DETTOL ACTIVE SOAP 90G')->count());
        $this->assertSame(3, $product->units()->count());
        $this->assertSame($pieces->id, $product->base_product_unit_id);
        $this->assertEquals(12, (float) $dozens->conversion_factor);
        $this->assertEquals(1, (float) $boxes->conversion_factor);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertEquals(0, (float) $boxes->opening_stock_qty);
    }

    public function test_packets_import_with_conversion_review(): void
    {
        $path = $this->writeExcelPriceList([
            ['DETERGENT', null, null, null, null],
            [null, 1, 'OMO SACHET - Packets', 1200, 'Packets'],
        ]);

        $this->artisan('products:import-price-list', [
            'path' => $path,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Conversion reviews       : 1')
            ->expectsOutputToContain('OMO SACHET / Packets assumes conversion_factor=1')
            ->assertSuccessful();
    }

    private function writePriceList(string $contents): string
    {
        $path = storage_path('app/test-price-list-'.uniqid().'.txt');
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function writeExcelPriceList(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
            }
        }

        $path = storage_path('app/test-price-list-'.uniqid().'.xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
