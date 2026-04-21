<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Service;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;
use Pet\Domain\Work\Repository\RoleRepository;

class QuoteBlockCostSnapshotEnricher
{
    private CatalogItemRepository $catalogItemRepository;
    private RoleRepository $roleRepository;

    public function __construct(
        CatalogItemRepository $catalogItemRepository,
        RoleRepository $roleRepository
    ) {
        $this->catalogItemRepository = $catalogItemRepository;
        $this->roleRepository = $roleRepository;
    }

    public function enrichPayload(string $blockType, array $payload): array
    {
        switch ($blockType) {
            case QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE:
            case QuoteBlock::TYPE_HARDWARE:
            case QuoteBlock::TYPE_REPEAT_HARDWARE:
                return $this->enrichFlatPayload($payload);
            case QuoteBlock::TYPE_ONCE_OFF_PROJECT:
                return $this->enrichProjectPayload($payload);
            case QuoteBlock::TYPE_REPEAT_SERVICE:
                return $this->enrichRecurringPayload($payload);
            default:
                return $payload;
        }
    }

    private function enrichFlatPayload(array $payload): array
    {
        $quantity = $this->toNumber($payload['quantity'] ?? null);
        $unitCost = $this->toNumber($payload['unitCost'] ?? null) ?? $this->resolveUnitCostByReferences($payload);

        if ($unitCost !== null) {
            $payload['unitCost'] = $unitCost;

            if ($quantity !== null) {
                $payload['totalCost'] = $unitCost * $quantity;
            }
        }

        // Snapshot the catalog item's baseline sell price on first creation so we can
        // compute discount % later. Only write if not already set (immutable baseline).
        if (!isset($payload['baselineSellValue'])) {
            $catalogItemId = $this->toInt($payload['catalogItemId'] ?? null);
            if ($catalogItemId !== null && $catalogItemId > 0) {
                $catalogItem = $this->catalogItemRepository->findById($catalogItemId);
                if ($catalogItem !== null) {
                    $payload['baselineSellValue'] = $catalogItem->unitPrice();
                }
            }
        }

        return $payload;
    }

    private function enrichProjectPayload(array $payload): array
    {
        if (!isset($payload['phases']) || !is_array($payload['phases'])) {
            return $payload;
        }

        $phases = $payload['phases'];
        $projectHasCompleteCost = true;
        $projectTotalCost = 0.0;

        foreach ($phases as $phaseIndex => $phase) {
            if (!is_array($phase)) {
                $projectHasCompleteCost = false;
                continue;
            }

            $units = (isset($phase['units']) && is_array($phase['units'])) ? $phase['units'] : [];
            $phaseHasCompleteCost = !empty($units);
            $phaseTotalCost = 0.0;

            foreach ($units as $unitIndex => $unit) {
                if (!is_array($unit)) {
                    $phaseHasCompleteCost = false;
                    continue;
                }

                $quantity = $this->toNumber($unit['quantity'] ?? null);
                $unitCost = $this->toNumber($unit['unitCost'] ?? null) ?? $this->resolveUnitCostByReferences($unit);

                if ($unitCost !== null && $quantity !== null) {
                    $unit['unitCost'] = $unitCost;
                    $unit['totalCost'] = $unitCost * $quantity;
                }

                $unitTotalCost = $this->toNumber($unit['totalCost'] ?? null);
                if ($unitTotalCost === null) {
                    $phaseHasCompleteCost = false;
                } else {
                    $phaseTotalCost += $unitTotalCost;
                }

                $units[$unitIndex] = $unit;
            }

            $phase['units'] = $units;
            if ($phaseHasCompleteCost) {
                $phase['phaseTotalCost'] = $phaseTotalCost;
            }

            $phaseCost = $this->toNumber($phase['phaseTotalCost'] ?? null);
            if ($phaseCost === null) {
                $projectHasCompleteCost = false;
            } else {
                $projectTotalCost += $phaseCost;
            }

            $phases[$phaseIndex] = $phase;
        }

        $payload['phases'] = $phases;
        if ($projectHasCompleteCost && !empty($phases)) {
            $payload['totalCost'] = $projectTotalCost;
        }

        return $payload;
    }

    private function enrichRecurringPayload(array $payload): array
    {
        if ($this->toNumber($payload['totalCost'] ?? null) !== null) {
            return $payload;
        }

        $internalCostPerPeriod = $this->toNumber($payload['internalCostPerPeriod'] ?? null);
        $periods = $this->derivePeriods($payload);

        if ($internalCostPerPeriod === null || $periods === null) {
            return $payload;
        }

        $payload['totalCost'] = $internalCostPerPeriod * $periods;
        return $payload;
    }

    private function resolveUnitCostByReferences(array $payload): ?float
    {
        $catalogItemId = $this->toInt($payload['catalogItemId'] ?? null);
        if ($catalogItemId !== null && $catalogItemId > 0) {
            $catalogItem = $this->catalogItemRepository->findById($catalogItemId);
            if ($catalogItem !== null) {
                return $catalogItem->unitCost();
            }
        }

        $roleId = $this->toInt($payload['roleId'] ?? null);
        if ($roleId !== null && $roleId > 0) {
            $role = $this->roleRepository->findById($roleId);
            if ($role !== null && $role->baseInternalRate() !== null) {
                return $role->baseInternalRate();
            }
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

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && ctype_digit($trimmed)) {
                return (int) $trimmed;
            }
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }
}

