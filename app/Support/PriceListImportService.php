<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PriceListImportService
{
    /**
     * @return array<string, mixed>
     */
    public function parseFile(string $path, ?string $categoryFilter = null, ?int $limit = null): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'xls', 'xlsx'], true)) {
            return $this->parseSpreadsheetFile($path, $categoryFilter, $limit);
        }

        return $this->parseText((string) file_get_contents($path), $categoryFilter, $limit);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, categories: array<int, string>, skipped: array<int, array<string, mixed>>, warnings: array<int, string>, zero_price_rows: array<int, array<string, mixed>>}
     */
    public function parseText(string $text, ?string $categoryFilter = null, ?int $limit = null): array
    {
        $lines = preg_split('/\R/u', str_replace("\xEF\xBB\xBF", '', $text)) ?: [];
        $currentCategory = null;
        $buffer = '';
        $bufferLine = 0;
        $rows = [];
        $categories = [];
        $skipped = [];
        $warnings = [];
        $zeroPriceRows = [];
        $categoryFilter = $categoryFilter ? $this->normalizeKey($categoryFilter) : null;

        $flush = function () use (&$buffer, &$bufferLine, &$rows, &$skipped, &$warnings, &$zeroPriceRows, &$currentCategory, $categoryFilter, $limit): void {
            if ($buffer === '') {
                return;
            }

            $parsed = $this->parseRowBuffer($buffer, $currentCategory, $bufferLine);

            if ($parsed) {
                if (! $categoryFilter || $this->normalizeKey((string) $parsed['category']) === $categoryFilter) {
                    if ($limit === null || count($rows) < $limit) {
                        $rows[] = $parsed;
                        if ((float) $parsed['selling_price'] === 0.0) {
                            $zeroPriceRows[] = $parsed;
                        }
                    }
                }
            } else {
                $skipped[] = [
                    'line' => $bufferLine,
                    'text' => $buffer,
                    'reason' => 'Could not parse product/unit/price.',
                ];
                $warnings[] = "Skipped line {$bufferLine}: {$buffer}";
            }

            $buffer = '';
            $bufferLine = 0;
        };

        foreach ($lines as $index => $rawLine) {
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }

            $line = $this->cleanLine($rawLine);
            $lineNumber = $index + 1;

            if ($line === '' || $this->isReportNoise($line)) {
                continue;
            }

            if ($this->isCategoryLine($line)) {
                $flush();
                $currentCategory = $this->normalizeName($line);
                $categories[$this->normalizeKey($currentCategory)] = $currentCategory;
                continue;
            }

            if ($this->startsNewItem($line)) {
                $flush();
                $buffer = $this->stripItemNumber($line);
                $bufferLine = $lineNumber;
            } elseif ($buffer !== '') {
                $buffer .= ' '.$line;
            } elseif ($this->looksLikeProductRow($line)) {
                $buffer = $this->stripItemNumber($line);
                $bufferLine = $lineNumber;
            } else {
                $skipped[] = [
                    'line' => $lineNumber,
                    'text' => $line,
                    'reason' => 'Line was not a category or product row.',
                ];
                continue;
            }

            if ($this->parseRowBuffer($buffer, $currentCategory, $bufferLine)) {
                $flush();
            }
        }

        $flush();

        return [
            'rows' => $rows,
            'categories' => array_values($categories),
            'skipped' => $skipped,
            'warnings' => $warnings,
            'zero_price_rows' => $zeroPriceRows,
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, categories: array<int, string>, skipped: array<int, array<string, mixed>>, warnings: array<int, string>, zero_price_rows: array<int, array<string, mixed>>}
     */
    public function parseSpreadsheetFile(string $path, ?string $categoryFilter = null, ?int $limit = null): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $currentCategory = null;
        $rows = [];
        $categories = [];
        $skipped = [];
        $warnings = [];
        $zeroPriceRows = [];
        $categoryFilter = $categoryFilter ? $this->normalizeKey($categoryFilter) : null;

        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }

            $a = $this->cellString($sheet->getCell("A{$rowIndex}")->getCalculatedValue());
            $b = $this->cellString($sheet->getCell("B{$rowIndex}")->getCalculatedValue());
            $c = $this->cellString($sheet->getCell("C{$rowIndex}")->getCalculatedValue());
            $d = $this->cellString($sheet->getCell("D{$rowIndex}")->getCalculatedValue());
            $e = $this->cellString($sheet->getCell("E{$rowIndex}")->getCalculatedValue());

            if ($this->isSpreadsheetNoiseRow([$a, $b, $c, $d, $e])) {
                continue;
            }

            if ($a !== '' && $b === '' && $c === '' && $d === '' && $e === '') {
                $currentCategory = $this->normalizeName($a);
                $categories[$this->normalizeKey($currentCategory)] = $currentCategory;
                continue;
            }

            if ($c !== '' && $d !== '' && $e !== '') {
                if (! $currentCategory) {
                    $skipped[] = [
                        'line' => $rowIndex,
                        'text' => implode(' | ', array_filter([$a, $b, $c, $d, $e])),
                        'reason' => 'Product row appeared before a category row.',
                    ];
                    continue;
                }

                [$productName, $suffixUnit] = $this->splitExcelProductAndUnit($c);
                $unitName = $this->normalizeName($e);
                $price = $this->moneyValue($d);

                if ($productName === '' || $unitName === '' || $price < 0) {
                    $skipped[] = [
                        'line' => $rowIndex,
                        'text' => implode(' | ', array_filter([$a, $b, $c, $d, $e])),
                        'reason' => 'Could not parse product/unit/price.',
                    ];
                    continue;
                }

                if ($suffixUnit !== '' && $this->normalizeKey($suffixUnit) !== $this->normalizeKey($unitName)) {
                    $warnings[] = "Row {$rowIndex}: unit suffix '{$suffixUnit}' differs from column E '{$unitName}', using column E.";
                }

                if (! $categoryFilter || $this->normalizeKey($currentCategory) === $categoryFilter) {
                    $parsed = [
                        'line' => $rowIndex,
                        'category' => $currentCategory,
                        'product_name' => $productName,
                        'unit_name' => $unitName,
                        'selling_price' => $price,
                        'raw' => implode(' | ', array_filter([$a, $b, $c, $d, $e])),
                    ];

                    $rows[] = $parsed;

                    if ($price === 0.0) {
                        $zeroPriceRows[] = $parsed;
                        $warnings[] = "Row {$rowIndex}: zero price for {$productName} / {$unitName}.";
                    }
                }

                continue;
            }

            if (implode('', [$a, $b, $c, $d, $e]) !== '') {
                $skipped[] = [
                    'line' => $rowIndex,
                    'text' => implode(' | ', array_filter([$a, $b, $c, $d, $e])),
                    'reason' => 'Spreadsheet row did not match category or product-unit shape.',
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'rows' => $rows,
            'categories' => array_values($categories),
            'skipped' => $skipped,
            'warnings' => $warnings,
            'zero_price_rows' => $zeroPriceRows,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function analyze(array $rows): array
    {
        $grouped = $this->groupRows($rows);
        $categoryKeys = collect($rows)->pluck('category')->map(fn ($category) => $this->normalizeKey((string) $category))->unique()->values();
        $existingCategories = Category::query()
            ->whereIn(DB::raw('LOWER(name)'), $categoryKeys->all())
            ->get(['id', 'name'])
            ->keyBy(fn (Category $category) => $this->normalizeKey($category->name));

        $productsToCreate = 0;
        $productsToUpdate = 0;
        $unitsToCreate = 0;
        $unitsToUpdate = 0;
        $conversionReviews = [];

        foreach ($grouped as $productKey => $productRows) {
            $productName = (string) $productRows[0]['product_name'];
            $existingProduct = Product::query()->whereRaw('LOWER(name) = ?', [$this->normalizeKey($productName)])->first(['id', 'name']);
            $existingProduct ? $productsToUpdate++ : $productsToCreate++;
            $baseUnitName = $this->chooseBaseUnitName($productRows);

            foreach ($productRows as $row) {
                $existingUnit = $existingProduct
                    ? ProductUnit::query()
                        ->where('product_id', $existingProduct->id)
                        ->whereRaw('LOWER(unit_name) = ?', [$this->normalizeKey((string) $row['unit_name'])])
                        ->first(['id'])
                    : null;

                $existingUnit ? $unitsToUpdate++ : $unitsToCreate++;
                $rule = $this->unitRule((string) $row['unit_name'], $baseUnitName);

                if ($rule['needs_review']) {
                    $conversionReviews[] = [
                        'category' => $row['category'],
                        'product_name' => $row['product_name'],
                        'unit_name' => $row['unit_name'],
                        'assumption' => 'conversion_factor=1',
                    ];
                }
            }
        }

        return [
            'total_rows' => count($rows),
            'categories_found' => $categoryKeys->count(),
            'categories_to_create' => max($categoryKeys->count() - $existingCategories->count(), 0),
            'categories_to_update' => $existingCategories->count(),
            'products_to_create' => $productsToCreate,
            'products_to_update' => $productsToUpdate,
            'units_to_create' => $unitsToCreate,
            'units_to_update' => $unitsToUpdate,
            'conversion_reviews' => $conversionReviews,
            'zero_price_rows' => array_values(array_filter($rows, fn ($row) => (float) $row['selling_price'] === 0.0)),
            'example_rows' => array_slice($rows, 0, 5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function importFile(string $path, array $options = []): array
    {
        $parsed = $this->parseFile(
            $path,
            $options['category'] ?? null,
            isset($options['limit']) ? (int) $options['limit'] : null
        );
        $analysis = $this->analyze($parsed['rows']);

        if (empty($options['commit'])) {
            return [
                'mode' => 'dry-run',
                'parsed' => $parsed,
                'analysis' => $analysis,
                'changes' => [],
            ];
        }

        $changes = DB::transaction(fn () => $this->commitRows($parsed['rows'], (bool) ($options['update_prices'] ?? false)));

        return [
            'mode' => 'commit',
            'parsed' => $parsed,
            'analysis' => $analysis,
            'changes' => $changes,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function commitRows(array $rows, bool $updatePrices): array
    {
        $changes = [
            'categories_created' => 0,
            'categories_updated' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'units_created' => 0,
            'units_updated' => 0,
            'conversion_review_count' => 0,
        ];
        $categories = [];
        $products = [];

        foreach ($this->groupRows($rows) as $productRows) {
            $categoryName = (string) $productRows[0]['category'];
            $categoryKey = $this->normalizeKey($categoryName);
            $category = $categories[$categoryKey] ?? null;

            if (! $category) {
                $category = Category::query()->whereRaw('LOWER(name) = ?', [$categoryKey])->first();

                if ($category) {
                    $category->update(['is_active' => true]);
                    $changes['categories_updated']++;
                } else {
                    $category = Category::query()->create([
                        'name' => $categoryName,
                        'is_active' => true,
                    ]);
                    $changes['categories_created']++;
                }

                $categories[$categoryKey] = $category;
            }

            $productName = (string) $productRows[0]['product_name'];
            $productKey = $this->normalizeKey($productName);
            $product = $products[$productKey] ?? Product::query()->whereRaw('LOWER(name) = ?', [$productKey])->first();

            if ($product) {
                $product->update([
                    'category_id' => $category->id,
                    'is_active' => true,
                ]);
                $changes['products_updated']++;
            } else {
                $product = Product::query()->create([
                    'name' => $productName,
                    'code' => $this->nextImportCode(),
                    'category_id' => $category->id,
                    'base_cost_price' => 0,
                    'reorder_level' => 0,
                    'is_vat_applicable' => false,
                    'is_active' => true,
                ]);
                $changes['products_created']++;
            }

            $products[$productKey] = $product;
            $baseUnitName = $this->chooseBaseUnitName($productRows);
            $posUnitName = $this->choosePosUnitName($productRows);
            $unitModels = [];

            foreach ($productRows as $row) {
                $unitName = (string) $row['unit_name'];
                $unitKey = $this->normalizeKey($unitName);
                $rule = $this->unitRule($unitName, $baseUnitName);
                $isBaseUnit = $this->normalizeKey($unitName) === $this->normalizeKey($baseUnitName);
                $isPosUnit = $this->normalizeKey($unitName) === $this->normalizeKey($posUnitName);
                $unit = $product->units()->whereRaw('LOWER(unit_name) = ?', [$unitKey])->first();

                if ($rule['needs_review']) {
                    $changes['conversion_review_count']++;
                }

                if ($unit) {
                    $updates = [
                        'allow_fractional_quantity' => $rule['allow_fractional_quantity'],
                        'quantity_precision' => $rule['quantity_precision'],
                        'is_base_unit' => $isBaseUnit,
                        'is_pos_unit' => $isPosUnit,
                        'is_active' => true,
                    ];

                    if ($updatePrices) {
                        $updates['selling_price'] = (float) $row['selling_price'];
                    }

                    $unit->update($updates);
                    $changes['units_updated']++;
                } else {
                    $unit = $product->units()->create([
                        'unit_name' => $unitName,
                        'conversion_factor' => $rule['conversion_factor'],
                        'selling_price' => (float) $row['selling_price'],
                        'cost_price' => 0,
                        'opening_stock_qty' => 0,
                        'is_pos_unit' => $isPosUnit,
                        'allow_fractional_quantity' => $rule['allow_fractional_quantity'],
                        'quantity_precision' => $rule['quantity_precision'],
                        'is_base_unit' => $isBaseUnit,
                        'is_active' => true,
                    ]);
                    $changes['units_created']++;
                }

                $unitModels[$unitKey] = $unit->fresh();
            }

            $baseUnit = $unitModels[$this->normalizeKey($baseUnitName)] ?? $product->units()->where('is_base_unit', true)->first();
            $product->update([
                'base_product_unit_id' => $baseUnit?->id,
                'base_unit_label' => $baseUnit?->unit_name,
            ]);
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupRows(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = $this->normalizeKey((string) $row['product_name']);
            $unitKey = $this->normalizeKey((string) $row['unit_name']);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            if (! collect($grouped[$key])->contains(fn ($existing) => $this->normalizeKey((string) $existing['unit_name']) === $unitKey)) {
                $grouped[$key][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function chooseBaseUnitName(array $rows): string
    {
        $priority = ['pieces', 'piece', 'pcs', 'pc', 'kgs', 'kg', 'bottle', 'bottles', 'tins', 'tin'];

        return $this->chooseUnitByPriority($rows, $priority);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function choosePosUnitName(array $rows): string
    {
        $priority = ['pieces', 'piece', 'pcs', 'pc', 'kgs', 'kg', 'bottle', 'bottles', 'tins', 'tin'];

        return $this->chooseUnitByPriority($rows, $priority);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $priority
     */
    private function chooseUnitByPriority(array $rows, array $priority): string
    {
        foreach ($priority as $wanted) {
            foreach ($rows as $row) {
                if ($this->unitKey((string) $row['unit_name']) === $this->unitKey($wanted)) {
                    return (string) $row['unit_name'];
                }
            }
        }

        return (string) ($rows[0]['unit_name'] ?? 'Unit');
    }

    /**
     * @return array{conversion_factor: float, needs_review: bool, allow_fractional_quantity: bool, quantity_precision: int}
     */
    private function unitRule(string $unitName, string $baseUnitName): array
    {
        $unitKey = $this->unitKey($unitName);
        $baseKey = $this->unitKey($baseUnitName);
        $factor = 1.0;
        $needsReview = false;

        if (in_array($unitKey, ['kg', 'kgs', 'kilogram', 'kilograms'], true)) {
            return $this->rule(1, false, true, 2);
        }

        if (in_array($unitKey, ['piece', 'pieces', 'pc', 'pcs', 'bottle', 'bottles', 'tin', 'tins', 'book', 'books', 'roll', 'rolls'], true)) {
            return $this->rule(1, false, false, 0);
        }

        if (preg_match('/^pack\s*[- ]?\s*6\s*(pc|pcs|piece|pieces)?$/i', trim($unitName))) {
            return $this->rule(6, false, false, 0);
        }

        $baseIsCountable = in_array($baseKey, ['piece', 'pieces', 'pc', 'pcs', 'bottle', 'bottles', 'tin', 'tins', 'book', 'books', 'roll', 'rolls'], true);

        if (in_array($unitKey, ['pair', 'pairs'], true) && $baseIsCountable) {
            return $this->rule(2, false, false, 0);
        }

        if (in_array($unitKey, ['dozen', 'dozens'], true) && $baseIsCountable) {
            return $this->rule(12, false, false, 0);
        }

        $needsReview = true;

        return $this->rule($factor, $needsReview, false, 0);
    }

    /**
     * @return array{conversion_factor: float, needs_review: bool, allow_fractional_quantity: bool, quantity_precision: int}
     */
    private function rule(float $factor, bool $needsReview, bool $allowFractional, int $precision): array
    {
        return [
            'conversion_factor' => $factor,
            'needs_review' => $needsReview,
            'allow_fractional_quantity' => $allowFractional,
            'quantity_precision' => $precision,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseRowBuffer(string $buffer, ?string $category, int $lineNumber): ?array
    {
        if (! $category || ! preg_match('/\s+-\s+/', $buffer)) {
            return null;
        }

        [$productName, $right] = preg_split('/\s+-\s+/', $buffer, 2) ?: [null, null];
        $productName = $this->normalizeName((string) $productName);
        $right = $this->normalizeName((string) $right);

        if ($productName === '' || $right === '') {
            return null;
        }

        if (! preg_match('/^(?<unit>.+?)\s+(?<price>\d[\d,]*)(?<tail>[A-Za-z].*)?$/', $right, $matches)) {
            return null;
        }

        $unitName = $this->normalizeName((string) $matches['unit']);
        $price = (float) str_replace(',', '', (string) $matches['price']);

        if ($unitName === '' || $price < 0) {
            return null;
        }

        return [
            'line' => $lineNumber,
            'category' => $category,
            'product_name' => $productName,
            'unit_name' => $unitName,
            'selling_price' => $price,
            'raw' => $buffer,
        ];
    }

    private function nextImportCode(): string
    {
        $max = Product::query()
            ->where('code', 'like', 'IMP%')
            ->pluck('code')
            ->map(fn ($code) => (int) preg_replace('/\D+/', '', (string) $code))
            ->max() ?? 0;

        do {
            $max++;
            $code = 'IMP'.str_pad((string) $max, 6, '0', STR_PAD_LEFT);
        } while (Product::query()->where('code', $code)->exists());

        return $code;
    }

    private function cleanLine(string $line): string
    {
        return $this->normalizeName(str_replace(["\t", "\r"], ' ', $line));
    }

    private function cellString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return $this->normalizeName((string) $value);
    }

    private function moneyValue(string $value): float
    {
        $value = str_replace(',', '', $value);
        $value = preg_replace('/[^\d.\-]/', '', $value) ?? '';

        return $value === '' ? 0.0 : round((float) $value, 2);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitExcelProductAndUnit(string $value): array
    {
        $value = $this->normalizeName($value);
        $position = strrpos($value, ' - ');

        if ($position === false) {
            return [$value, ''];
        }

        return [
            $this->normalizeName(substr($value, 0, $position)),
            $this->normalizeName(substr($value, $position + 3)),
        ];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function isSpreadsheetNoiseRow(array $values): bool
    {
        $joined = $this->normalizeName(implode(' ', array_filter($values, fn ($value) => $value !== '')));

        if ($joined === '') {
            return true;
        }

        $upper = strtoupper($joined);

        if (in_array(strtoupper($values[0] ?? ''), ['CATEGORYNAME', 'CATEGORY'], true)) {
            return true;
        }

        foreach (['APPLES OF GOLD', 'WHOLESALERS', 'PRICE LIST', 'PRODUCTID', 'TEXT143', 'SELLINGPRICE'] as $noise) {
            if (str_contains($upper, $noise)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeName(string $value): string
    {
        $value = str_replace(chr(194).chr(160), ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function normalizeKey(string $value): string
    {
        return Str::lower($this->normalizeName($value));
    }

    private function unitKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', $this->normalizeKey($value)) ?: '';
    }

    private function startsNewItem(string $line): bool
    {
        return (bool) preg_match('/^\d+\s+/', $line);
    }

    private function stripItemNumber(string $line): string
    {
        return $this->normalizeName(preg_replace('/^\d+\s+/', '', $line) ?? $line);
    }

    private function looksLikeProductRow(string $line): bool
    {
        return str_contains($line, ' - ') && (bool) preg_match('/\d[\d,]*[A-Za-z]*$/', $line);
    }

    private function isCategoryLine(string $line): bool
    {
        if ($this->startsNewItem($line) || $this->looksLikeProductRow($line) || str_contains($line, ' - ')) {
            return false;
        }

        if (preg_match('/\d[\d,]*[A-Za-z]*$/', $line)) {
            return false;
        }

        $letters = preg_replace('/[^A-Za-z]+/', '', $line) ?: '';
        if ($letters === '' || strlen($line) > 80) {
            return false;
        }

        return strtoupper($letters) === $letters;
    }

    private function isReportNoise(string $line): bool
    {
        $upper = strtoupper($line);

        foreach (['APPLES OF GOLD', 'WHOLESALERS', 'PRICE LIST', 'RPTPRICE', 'PAGE ', 'PRINTED', 'DATE:', 'TEL:', 'USER:'] as $noise) {
            if (str_contains($upper, $noise)) {
                return true;
            }
        }

        return str_starts_with($line, '=') || str_starts_with($line, '- -');
    }
}
