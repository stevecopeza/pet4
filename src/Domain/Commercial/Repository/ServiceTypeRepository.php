<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Repository;

use Pet\Domain\Commercial\Entity\ServiceType;

interface ServiceTypeRepository
{
    public function findById(int $id): ?ServiceType;

    /** @return ServiceType[] */
    public function findAll(): array;

    /** @return ServiceType[] */
    public function findActive(): array;

    public function save(ServiceType $serviceType): void;

    public function archive(int $id): void;
}
