<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Repository;

use Pet\Domain\Identity\Entity\Customer;

interface CustomerRepository
{
    public function save(Customer $customer): void;

    public function findById(int $id): ?Customer;

    /**
     * @return Customer[]
     */
    public function findAll(): array;
}
