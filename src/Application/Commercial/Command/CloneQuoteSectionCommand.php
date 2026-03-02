<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

final class CloneQuoteSectionCommand
{
    private int $quoteId;
    private int $sectionId;

    public function __construct(int $quoteId, int $sectionId)
    {
        $this->quoteId = $quoteId;
        $this->sectionId = $sectionId;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function sectionId(): int
    {
        return $this->sectionId;
    }
}

