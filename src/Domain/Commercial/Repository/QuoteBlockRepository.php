<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;

interface QuoteBlockRepository
{
    /**
     * @return QuoteBlock[]
     */
    public function findByQuoteId(int $quoteId): array;

    public function insert(QuoteBlock $block, int $quoteId): QuoteBlock;

    public function update(QuoteBlock $block, int $quoteId): void;

    public function delete(int $blockId): void;

    /**
     * @param array<int,array{id:int,orderIndex:int,sectionId:?int}> $changes
     */
    public function reorder(int $quoteId, array $changes): void;

    public function move(int $blockId, ?int $sectionId, int $orderIndex): void;
}

