<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Repository;

use Pet\Domain\Work\Entity\PersonCertification;

interface PersonCertificationRepository
{
    public function save(PersonCertification $personCertification): void;
    /**
     * @return PersonCertification[]
     */
    public function findByEmployeeId(int $employeeId): array;
}
