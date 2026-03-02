<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

class CatalogComponent extends QuoteComponent
{
    /** @var QuoteCatalogItem[] */
    private array $items;

    public function __construct(
        array $items = [],
        ?string $description = null,
        ?int $id = null,
        string $section = 'General'
    ) {
        parent::__construct('catalog', $description, $id, $section);
        $this->items = $items;
    }

    /** @return QuoteCatalogItem[] */
    public function items(): array
    {
        return $this->items;
    }

    public function addItem(QuoteCatalogItem $item): void
    {
        $this->items[] = $item;
    }

    public function sellValue(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->sellValue();
        }
        return $total;
    }

    public function internalCost(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->internalCost();
        }
        return $total;
    }
}
