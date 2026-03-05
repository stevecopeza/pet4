<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\CatalogProduct;

interface CatalogProductRepository
{
    public function findById(int $id): ?CatalogProduct;

    /** @return CatalogProduct[] */
    public function findAll(): array;

    /** @return CatalogProduct[] */
    public function findActive(): array;

    public function save(CatalogProduct $product): void;

    public function archive(int $id): void;
}
