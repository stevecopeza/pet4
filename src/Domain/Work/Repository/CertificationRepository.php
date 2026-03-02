<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\Certification;

interface CertificationRepository
{
    public function save(Certification $certification): void;
    public function findById(int $id): ?Certification;
    /**
     * @return Certification[]
     */
    public function findAll(): array;
}
