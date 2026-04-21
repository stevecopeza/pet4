<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Service;

use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Configuration\Repository\SettingRepository;

/**
 * Evaluates whether a quote requires manager approval before it can be sent.
 *
 * Two independent thresholds (both configurable via Settings):
 *   - pet_quote_approval_value_threshold      : quote total value (0 or unset = disabled)
 *   - pet_quote_approval_discount_threshold_pct: max discount % on any line (0 or unset = disabled)
 *
 * Either threshold being exceeded is sufficient to require approval.
 */
class QuoteApprovalRulesService
{
    private SettingRepository $settings;

    public function __construct(SettingRepository $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Returns true if this quote must pass through manager approval before sending.
     */
    public function requiresApproval(Quote $quote, float $maxDiscountPct): bool
    {
        if ($this->exceedsValueThreshold($quote->totalValue())) {
            return true;
        }

        if ($this->exceedsDiscountThreshold($maxDiscountPct)) {
            return true;
        }

        return false;
    }

    /**
     * Returns human-readable reasons why approval is required (for UI display).
     *
     * @return string[]
     */
    public function approvalReasons(Quote $quote, float $maxDiscountPct): array
    {
        $reasons = [];

        $valueThreshold = $this->getValueThreshold();
        if ($valueThreshold > 0 && $quote->totalValue() >= $valueThreshold) {
            $reasons[] = sprintf(
                'Quote value (%s %.2f) meets or exceeds the approval threshold (%s %.2f).',
                $quote->currency() ?? '',
                $quote->totalValue(),
                $quote->currency() ?? '',
                $valueThreshold
            );
        }

        $discountThreshold = $this->getDiscountThreshold();
        if ($discountThreshold > 0 && $maxDiscountPct >= $discountThreshold) {
            $reasons[] = sprintf(
                'A line item discount of %.1f%% meets or exceeds the approval threshold (%.1f%%).',
                $maxDiscountPct,
                $discountThreshold
            );
        }

        return $reasons;
    }

    private function exceedsValueThreshold(float $totalValue): bool
    {
        $threshold = $this->getValueThreshold();
        return $threshold > 0 && $totalValue >= $threshold;
    }

    private function exceedsDiscountThreshold(float $maxDiscountPct): bool
    {
        $threshold = $this->getDiscountThreshold();
        return $threshold > 0 && $maxDiscountPct >= $threshold;
    }

    private function getValueThreshold(): float
    {
        $setting = $this->settings->findByKey('pet_quote_approval_value_threshold');
        return $setting ? (float) $setting->value() : 0.0;
    }

    private function getDiscountThreshold(): float
    {
        $setting = $this->settings->findByKey('pet_quote_approval_discount_threshold_pct');
        return $setting ? (float) $setting->value() : 0.0;
    }
}
