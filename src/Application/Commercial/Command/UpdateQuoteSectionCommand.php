<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

final class UpdateQuoteSectionCommand
{
    private int $quoteId;
    private int $sectionId;
    private string $name;
    private bool $showTotalValue;
    private bool $showItemCount;
    private bool $showTotalHours;

    public function __construct(
        int $quoteId,
        int $sectionId,
        string $name,
        bool $showTotalValue,
        bool $showItemCount,
        bool $showTotalHours
    ) {
        $this->quoteId = $quoteId;
        $this->sectionId = $sectionId;
        $this->name = $name;
        $this->showTotalValue = $showTotalValue;
        $this->showItemCount = $showItemCount;
        $this->showTotalHours = $showTotalHours;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function sectionId(): int
    {
        return $this->sectionId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function showTotalValue(): bool
    {
        return $this->showTotalValue;
    }

    public function showItemCount(): bool
    {
        return $this->showItemCount;
    }

    public function showTotalHours(): bool
    {
        return $this->showTotalHours;
    }
}

