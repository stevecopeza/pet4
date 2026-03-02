<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class ConvertLeadToQuoteCommand
{
    private int $leadId;
    private string $title;
    private ?string $description;
    private string $currency;

    public function __construct(
        int $leadId,
        string $title = '',
        ?string $description = null,
        string $currency = 'USD'
    ) {
        $this->leadId = $leadId;
        $this->title = $title;
        $this->description = $description;
        $this->currency = $currency;
    }

    public function leadId(): int
    {
        return $this->leadId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function currency(): string
    {
        return $this->currency;
    }
}
