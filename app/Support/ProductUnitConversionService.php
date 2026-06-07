<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Validation\ValidationException;

class ProductUnitConversionService
{
    public function conversionFactorSnapshot(?ProductUnit $unit): float
    {
        $factor = (float) ($unit?->conversion_factor ?? 1);

        return $factor > 0 ? round($factor, 6) : 1.0;
    }

    public function toBaseQuantity(float|int|string $quantity, ?ProductUnit $unit): float
    {
        return round((float) $quantity * $this->conversionFactorSnapshot($unit), 3);
    }

    public function hasValidPrecision(float|int|string $quantity, ?ProductUnit $unit): bool
    {
        $quantity = (string) $quantity;
        $decimalPart = str_contains($quantity, '.') ? rtrim(substr(strrchr($quantity, '.'), 1), '0') : '';
        $precision = strlen($decimalPart);

        if ($precision === 0) {
            return true;
        }

        if (! $unit?->allow_fractional_quantity) {
            return false;
        }

        return $precision <= max((int) ($unit->quantity_precision ?? 0), 0);
    }

    public function validatePrecision(float|int|string $quantity, ?ProductUnit $unit, string $field = 'quantity'): void
    {
        if ($this->hasValidPrecision($quantity, $unit)) {
            return;
        }

        $precision = max((int) ($unit?->quantity_precision ?? 0), 0);
        $unitName = $unit?->unit_name ?? 'selected unit';
        $message = $unit?->allow_fractional_quantity
            ? "{$unitName} allows up to {$precision} decimal place(s)."
            : "{$unitName} does not allow decimal quantities.";

        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    public function baseUnitForProduct(Product $product): ?ProductUnit
    {
        if ($product->relationLoaded('baseProductUnit') && $product->baseProductUnit) {
            return $product->baseProductUnit;
        }

        if ($product->base_product_unit_id) {
            return ProductUnit::query()
                ->where('product_id', $product->id)
                ->find($product->base_product_unit_id);
        }

        $units = $product->relationLoaded('units')
            ? $product->units
            : $product->units()->get();

        return $units->firstWhere('is_base_unit', true)
            ?? $units->first(fn (ProductUnit $unit) => $this->conversionFactorSnapshot($unit) === 1.0);
    }

    public function formatBaseQuantity(float|int|string $quantity, Product|ProductUnit|null $context = null): string
    {
        $label = null;

        if ($context instanceof Product) {
            $label = $context->base_unit_label ?: $this->baseUnitForProduct($context)?->unit_name;
        } elseif ($context instanceof ProductUnit) {
            $label = $context->product?->base_unit_label ?: $this->baseUnitForProduct($context->product()->first() ?? new Product())?->unit_name;
        }

        $formatted = number_format((float) $quantity, 3, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return trim($formatted.' '.($label ?: 'base unit(s)'));
    }

    public function costPerBaseUnit(float|int|string $unitCost, ?ProductUnit $unit): float
    {
        return round((float) $unitCost / $this->conversionFactorSnapshot($unit), 2);
    }
}
