<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Service;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;

class QuoteBlockMarginCalculator
{
    public function calculate(string $blockType, array $payload): array
    {
        switch ($blockType) {
            case QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE:
            case QuoteBlock::TYPE_HARDWARE:
            case QuoteBlock::TYPE_REPEAT_HARDWARE:
                return $this->calculateFlatBlock($payload);
            case QuoteBlock::TYPE_REPEAT_SERVICE:
                return $this->calculateRecurringBlock($payload);
            case QuoteBlock::TYPE_ONCE_OFF_PROJECT:
                return $this->calculateProjectBlock($payload);
            default:
                return $this->buildResult($payload, null, null);
        }
    }

    private function calculateFlatBlock(array $payload): array
    {
        $lineSellValue = $this->extractSellValue($payload);
        $lineCostValue = $this->extractCostValue($payload);

        return $this->buildResult($payload, $lineSellValue, $lineCostValue);
    }

    private function calculateRecurringBlock(array $payload): array
    {
        $lineSellValue = $this->toNumber($payload['totalValue'] ?? null);
        if ($lineSellValue === null) {
            $sellPricePerPeriod = $this->toNumber($payload['sellPricePerPeriod'] ?? null);
            $periods = $this->derivePeriods($payload);
            if ($sellPricePerPeriod !== null && $periods !== null) {
                $lineSellValue = $sellPricePerPeriod * $periods;
            }
        }

        $lineCostValue = $this->toNumber($payload['totalCost'] ?? null);
        if ($lineCostValue === null) {
            $internalCostPerPeriod = $this->toNumber($payload['internalCostPerPeriod'] ?? null);
            $periods = $this->derivePeriods($payload);
            if ($internalCostPerPeriod !== null && $periods !== null) {
                $lineCostValue = $internalCostPerPeriod * $periods;
            }
        }

        return $this->buildResult($payload, $lineSellValue, $lineCostValue);
    }

    private function calculateProjectBlock(array $payload): array
    {
        if (!isset($payload['phases']) || !is_array($payload['phases'])) {
            $lineSellValue = $this->extractSellValue($payload);
            $lineCostValue = $this->extractCostValue($payload);
            return $this->buildResult($payload, $lineSellValue, $lineCostValue);
        }

        $phases = $payload['phases'];
        $allPhasesHaveSell = !empty($phases);
        $allPhasesHaveCost = !empty($phases);
        $phaseSellSum = 0.0;
        $phaseCostSum = 0.0;

        foreach ($phases as $phaseIndex => $phase) {
            if (!is_array($phase)) {
                $allPhasesHaveSell = false;
                $allPhasesHaveCost = false;
                continue;
            }

            $units = (isset($phase['units']) && is_array($phase['units'])) ? $phase['units'] : [];
            $allUnitsHaveSell = !empty($units);
            $allUnitsHaveCost = !empty($units);
            $unitSellSum = 0.0;
            $unitCostSum = 0.0;

            foreach ($units as $unitIndex => $unit) {
                if (!is_array($unit)) {
                    $allUnitsHaveSell = false;
                    $allUnitsHaveCost = false;
                    continue;
                }

                $unitSellValue = $this->extractSellValue($unit);
                $unitCostValue = $this->extractCostValue($unit);
                $unitMetrics = $this->buildMetrics($unitSellValue, $unitCostValue);

                $unit['lineSellValue'] = $unitSellValue;
                $unit['lineCostValue'] = $unitCostValue;
                $unit['marginAmount'] = $unitMetrics['marginAmount'];
                $unit['marginPercentage'] = $unitMetrics['marginPercentage'];
                $unit['hasMarginData'] = $unitMetrics['hasMarginData'];
                $units[$unitIndex] = $unit;

                if ($unitSellValue === null) {
                    $allUnitsHaveSell = false;
                } else {
                    $unitSellSum += $unitSellValue;
                }

                if ($unitCostValue === null) {
                    $allUnitsHaveCost = false;
                } else {
                    $unitCostSum += $unitCostValue;
                }
            }

            $phase['units'] = $units;

            $phaseSellValue = $this->toNumber($phase['phaseTotalValue'] ?? null);
            if ($phaseSellValue === null) {
                $phaseSellValue = (!empty($units) && $allUnitsHaveSell) ? $unitSellSum : null;
            }
            $phaseCostValue = null;
            if (!empty($units)) {
                $phaseCostValue = $allUnitsHaveCost ? $unitCostSum : null;
            } else {
                $phaseCostValue = $this->toNumber($phase['phaseTotalCost'] ?? null);
            }
            if ($phaseCostValue !== null) {
                $phase['phaseTotalCost'] = $phaseCostValue;
            }

            $phaseMetrics = $this->buildMetrics($phaseSellValue, $phaseCostValue);
            $phase['lineSellValue'] = $phaseSellValue;
            $phase['lineCostValue'] = $phaseCostValue;
            $phase['marginAmount'] = $phaseMetrics['marginAmount'];
            $phase['marginPercentage'] = $phaseMetrics['marginPercentage'];
            $phase['hasMarginData'] = $phaseMetrics['hasMarginData'];
            $phases[$phaseIndex] = $phase;

            if ($phaseSellValue === null) {
                $allPhasesHaveSell = false;
            } else {
                $phaseSellSum += $phaseSellValue;
            }

            if ($phaseCostValue === null) {
                $allPhasesHaveCost = false;
            } else {
                $phaseCostSum += $phaseCostValue;
            }
        }

        $payload['phases'] = $phases;

        $lineSellValue = $this->toNumber($payload['totalValue'] ?? null);
        if ($lineSellValue === null) {
            $lineSellValue = $allPhasesHaveSell ? $phaseSellSum : null;
        }
        $lineCostValue = $allPhasesHaveCost ? $phaseCostSum : null;
        if ($lineCostValue !== null) {
            $payload['totalCost'] = $lineCostValue;
        }

        return $this->buildResult($payload, $lineSellValue, $lineCostValue);
    }

    private function buildResult(array $payload, ?float $lineSellValue, ?float $lineCostValue): array
    {
        $metrics = $this->buildMetrics($lineSellValue, $lineCostValue);

        return [
            'payload' => $payload,
            'lineSellValue' => $lineSellValue,
            'lineCostValue' => $lineCostValue,
            'marginAmount' => $metrics['marginAmount'],
            'marginPercentage' => $metrics['marginPercentage'],
            'hasMarginData' => $metrics['hasMarginData'],
        ];
    }

    private function buildMetrics(?float $lineSellValue, ?float $lineCostValue): array
    {
        if ($lineSellValue === null || $lineCostValue === null) {
            return [
                'marginAmount' => null,
                'marginPercentage' => null,
                'hasMarginData' => false,
            ];
        }

        $marginAmount = $lineSellValue - $lineCostValue;
        $marginPercentage = $this->isZero($lineSellValue)
            ? null
            : ($marginAmount / $lineSellValue) * 100;

        return [
            'marginAmount' => $marginAmount,
            'marginPercentage' => $marginPercentage,
            'hasMarginData' => true,
        ];
    }

    private function extractSellValue(array $payload): ?float
    {
        $totalValue = $this->toNumber($payload['totalValue'] ?? null);
        if ($totalValue !== null) {
            return $totalValue;
        }

        $sellValue = $this->toNumber($payload['sellValue'] ?? null);
        if ($sellValue === null) {
            return null;
        }

        $quantity = $this->toNumber($payload['quantity'] ?? null);
        if ($quantity !== null) {
            return $sellValue * $quantity;
        }

        return $sellValue;
    }

    private function extractCostValue(array $payload): ?float
    {
        $totalCost = $this->toNumber($payload['totalCost'] ?? null);
        if ($totalCost !== null) {
            return $totalCost;
        }

        $unitCost = $this->toNumber($payload['unitCost'] ?? null);
        $quantity = $this->toNumber($payload['quantity'] ?? null);
        if ($unitCost !== null && $quantity !== null) {
            return $unitCost * $quantity;
        }

        return null;
    }

    private function derivePeriods(array $payload): ?float
    {
        $termMonths = $this->toNumber($payload['termMonths'] ?? null);
        if ($termMonths === null) {
            return null;
        }

        $cadence = isset($payload['cadence']) && is_string($payload['cadence'])
            ? strtolower(trim($payload['cadence']))
            : '';

        $monthsPerPeriod = match ($cadence) {
            'monthly' => 1.0,
            'quarterly' => 3.0,
            'annually' => 12.0,
            default => null,
        };

        if ($monthsPerPeriod === null || $monthsPerPeriod <= 0.0) {
            return null;
        }

        return $termMonths / $monthsPerPeriod;
    }

    private function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && is_numeric($trimmed)) {
                return (float) $trimmed;
            }
        }

        return null;
    }

    private function isZero(float $value): bool
    {
        return abs($value) < 0.000001;
    }
}

