<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\ProductUnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductUnitConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductUnitConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductUnitConversionService();
    }

    public function test_it_converts_common_wholesale_and_retail_units_to_base_quantity(): void
    {
        $this->assertSame(5.0, $this->service->toBaseQuantity(5, $this->unit('Piece', 1)));
        $this->assertSame(24.0, $this->service->toBaseQuantity(2, $this->unit('Dozen', 12)));
        $this->assertSame(12.0, $this->service->toBaseQuantity(1, $this->unit('Half Carton', 12)));
        $this->assertSame(72.0, $this->service->toBaseQuantity(3, $this->unit('Carton', 24)));
        $this->assertSame(100.0, $this->service->toBaseQuantity(2, $this->unit('Sack', 50)));
        $this->assertSame(1.5, $this->service->toBaseQuantity(1.5, $this->unit('Kg', 1, true, 3)));
    }

    public function test_it_validates_decimal_quantity_permissions_and_precision(): void
    {
        $piece = $this->unit('Piece', 1, false, 0);
        $kg = $this->unit('Kg', 1, true, 2);

        $this->assertTrue($this->service->hasValidPrecision(3, $piece));
        $this->assertFalse($this->service->hasValidPrecision(1.5, $piece));
        $this->assertTrue($this->service->hasValidPrecision(1.25, $kg));
        $this->assertFalse($this->service->hasValidPrecision(1.257, $kg));

        $this->expectException(ValidationException::class);
        $this->service->validatePrecision(1.257, $kg);
    }

    public function test_it_falls_back_to_factor_one_for_missing_or_invalid_conversion_factor(): void
    {
        $nullFactor = new ProductUnit(['unit_name' => 'Unknown', 'conversion_factor' => null]);
        $zeroFactor = new ProductUnit(['unit_name' => 'Broken', 'conversion_factor' => 0]);

        $this->assertSame(1.0, $this->service->conversionFactorSnapshot(null));
        $this->assertSame(1.0, $this->service->conversionFactorSnapshot($nullFactor));
        $this->assertSame(1.0, $this->service->conversionFactorSnapshot($zeroFactor));
        $this->assertSame(7.0, $this->service->toBaseQuantity(7, $zeroFactor));
    }

    public function test_it_identifies_base_unit_and_formats_base_quantity(): void
    {
        $product = Product::create(['name' => 'Water', 'is_active' => true]);
        $piece = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Piece',
            'conversion_factor' => 1,
            'is_base_unit' => true,
            'is_active' => true,
        ]);
        ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Carton',
            'conversion_factor' => 24,
            'is_active' => true,
        ]);
        $product->update([
            'base_product_unit_id' => $piece->id,
            'base_unit_label' => 'Piece',
        ]);

        $this->assertSame($piece->id, $this->service->baseUnitForProduct($product->fresh())->id);
        $this->assertSame('48 Piece', $this->service->formatBaseQuantity(48, $product->fresh()));
    }

    public function test_it_calculates_cost_per_base_unit(): void
    {
        $this->assertSame(5000.0, $this->service->costPerBaseUnit(120000, $this->unit('Carton', 24)));
        $this->assertSame(2400.0, $this->service->costPerBaseUnit(120000, $this->unit('Sack', 50)));
    }

    private function unit(string $name, float $factor, bool $allowFractional = false, int $precision = 0): ProductUnit
    {
        return new ProductUnit([
            'unit_name' => $name,
            'conversion_factor' => $factor,
            'allow_fractional_quantity' => $allowFractional,
            'quantity_precision' => $precision,
        ]);
    }
}
