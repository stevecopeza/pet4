<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\CatalogItem;

interface CatalogItemRepository
{
    public function findById(int $id): ?CatalogItem;
    public function findAll(): array;
    public function save(CatalogItem $item): void;
    public function delete(int $id): void;
}
