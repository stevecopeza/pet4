<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

final class CreateQuoteBlockCommand
{
    private int $quoteId;
    private ?int $sectionId;
    private string $type;
    private array $payload;

    public function __construct(int $quoteId, ?int $sectionId, string $type, array $payload = [])
    {
        $this->quoteId = $quoteId;
        $this->sectionId = $sectionId;
        $this->type = $type;
        $this->payload = $payload;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function sectionId(): ?int
    {
        return $this->sectionId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}

