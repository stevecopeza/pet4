<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\QuoteSection;

interface QuoteSectionRepository
{
    /**
     * @return QuoteSection[]
     */
    public function findByQuoteId(int $quoteId): array;

    public function save(QuoteSection $section): QuoteSection;

    public function delete(int $sectionId): void;

    /**
     * @param QuoteSection[] $sections
     */
    public function saveOrdering(int $quoteId, array $sections): void;
}

