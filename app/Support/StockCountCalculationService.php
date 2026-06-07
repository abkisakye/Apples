<?php

namespace App\Support;

use App\Models\ProductUnit;
use Illuminate\Support\Collection;

class StockCountCalculationService
{
    public function __construct(private ProductUnitConversionService $conversionService)
    {
    }

    public function baseQuantity(float|int|string $enteredQuantity, ?ProductUnit $unit): float
    {
        return $this->conversionService->toBaseQuantity($enteredQuantity, $unit);
    }

    /**
     * @param  iterable<int, array{quantity: float|int|string, unit: ProductUnit|null}>  $entries
     */
    public function physicalBaseQuantity(iterable $entries): float
    {
        return round(collect($entries)->sum(fn (array $entry) => $this->baseQuantity(
            $entry['quantity'] ?? 0,
            $entry['unit'] ?? null,
        )), 3);
    }

    public function varianceBaseQuantity(float|int|string $physicalBaseQty, float|int|string $systemBaseQty): float
    {
        return round((float) $physicalBaseQty - (float) $systemBaseQty, 3);
    }

    /**
     * @param  iterable<int, array{quantity: float|int|string, unit: ProductUnit|null}>  $entries
     * @return array{physical_base_qty: float, system_base_qty: float, variance_base_qty: float}
     */
    public function countTotals(iterable $entries, float|int|string $systemBaseQty): array
    {
        $physicalBaseQty = $this->physicalBaseQuantity($entries);
        $systemBaseQty = round((float) $systemBaseQty, 3);

        return [
            'physical_base_qty' => $physicalBaseQty,
            'system_base_qty' => $systemBaseQty,
            'variance_base_qty' => $this->varianceBaseQuantity($physicalBaseQty, $systemBaseQty),
        ];
    }

    public function validateQuantity(float|int|string $enteredQuantity, ?ProductUnit $unit, string $field = 'quantity'): void
    {
        $this->conversionService->validatePrecision($enteredQuantity, $unit, $field);
    }

    public function conversionFactorSnapshot(?ProductUnit $unit): float
    {
        return $this->conversionService->conversionFactorSnapshot($unit);
    }

    /**
     * @return array{product_id: int|null, product_unit_id: int|null, entered_quantity: float, conversion_factor_snapshot: float, base_quantity: float}
     */
    public function unitEntryAttributes(float|int|string $enteredQuantity, ?ProductUnit $unit): array
    {
        $this->validateQuantity($enteredQuantity, $unit);
        $quantity = round((float) $enteredQuantity, 3);
        $factor = $this->conversionFactorSnapshot($unit);

        return [
            'product_id' => $unit?->product_id,
            'product_unit_id' => $unit?->id,
            'entered_quantity' => $quantity,
            'conversion_factor_snapshot' => $factor,
            'base_quantity' => round($quantity * $factor, 3),
        ];
    }

    /**
     * @param  iterable<int, array{quantity: float|int|string, unit: ProductUnit|null}>  $entries
     */
    public function unitEntryAttributesFor(iterable $entries): Collection
    {
        return collect($entries)->map(fn (array $entry) => $this->unitEntryAttributes(
            $entry['quantity'] ?? 0,
            $entry['unit'] ?? null,
        ));
    }
}
