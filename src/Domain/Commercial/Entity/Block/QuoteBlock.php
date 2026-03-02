<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Block;

use Pet\Domain\Commercial\Entity\Component\QuoteComponent;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\RecurringServiceComponent;

final class QuoteBlock
{
    public const TYPE_ONCE_OFF_SIMPLE_SERVICE = 'OnceOffSimpleServiceBlock';
    public const TYPE_ONCE_OFF_PROJECT = 'OnceOffProjectBlock';
    public const TYPE_REPEAT_SERVICE = 'RepeatServiceBlock';
    public const TYPE_REPEAT_HARDWARE = 'RepeatHardwareBlock';
    public const TYPE_HARDWARE = 'HardwareBlock';
    public const TYPE_PRICE_ADJUSTMENT = 'PriceAdjustmentBlock';
    public const TYPE_PAYMENT_PLAN = 'PaymentPlanBlock';
    public const TYPE_TEXT = 'TextBlock';

    private ?int $id;
    private int $position;
    private string $type;
    private ?int $componentId;
    private float $sellValue;
    private float $internalCost;
    private bool $priced;
    private ?int $sectionId;
    private array $payload;

    public function __construct(
        int $position,
        string $type,
        ?int $componentId,
        float $sellValue,
        float $internalCost,
        bool $priced = true,
        ?int $sectionId = null,
        array $payload = [],
        ?int $id = null
    ) {
        $this->id = $id;
        $this->position = $position;
        $this->type = $type;
        $this->componentId = $componentId;
        $this->sellValue = $sellValue;
        $this->internalCost = $internalCost;
        $this->priced = $priced;
        $this->sectionId = $sectionId;
        $this->payload = $payload;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function componentId(): ?int
    {
        return $this->componentId;
    }

    public function sellValue(): float
    {
        return $this->sellValue;
    }

    public function internalCost(): float
    {
        return $this->internalCost;
    }

    public function isPriced(): bool
    {
        return $this->priced;
    }

    public function sectionId(): ?int
    {
        return $this->sectionId;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @param QuoteComponent[] $components
     * @return QuoteBlock[]
     */
    public static function fromComponents(array $components): array
    {
        $blocks = [];
        $position = 0;

        foreach ($components as $component) {
            if (!$component instanceof QuoteComponent) {
                continue;
            }

            $type = self::TYPE_HARDWARE;

            if ($component instanceof OnceOffServiceComponent) {
                $type = $component->topology() === OnceOffServiceComponent::TOPOLOGY_SIMPLE
                    ? self::TYPE_ONCE_OFF_SIMPLE_SERVICE
                    : self::TYPE_ONCE_OFF_PROJECT;
            } elseif ($component instanceof RecurringServiceComponent) {
                $type = self::TYPE_REPEAT_SERVICE;
            }

            $blocks[] = new self(
                $position,
                $type,
                $component->id(),
                $component->sellValue(),
                $component->internalCost(),
                true,
                null
            );

            $position++;
        }

        return $blocks;
    }

    /**
     * @param QuoteBlock[] $blocks
     */
    public static function totalSellValue(array $blocks): float
    {
        $total = 0.0;

        foreach ($blocks as $block) {
            if (!$block instanceof self) {
                continue;
            }

            if (!$block->isPriced()) {
                continue;
            }

            $total += $block->sellValue();
        }

        return $total;
    }

    /**
     * @param QuoteBlock[] $blocks
     */
    public static function totalInternalCost(array $blocks): float
    {
        $total = 0.0;

        foreach ($blocks as $block) {
            if (!$block instanceof self) {
                continue;
            }

            if (!$block->isPriced()) {
                continue;
            }

            $total += $block->internalCost();
        }

        return $total;
    }
}
