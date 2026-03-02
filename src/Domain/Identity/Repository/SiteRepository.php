<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Repository;

use Pet\Domain\Identity\Entity\Site;

interface SiteRepository
{
    public function save(Site $site): void;
    public function findById(int $id): ?Site;
    public function findByCustomerId(int $customerId): array;
    public function findAll(): array;
    public function delete(int $id): void;
}
