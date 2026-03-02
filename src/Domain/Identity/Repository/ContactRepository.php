<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Repository;

use Pet\Domain\Identity\Entity\Contact;

interface ContactRepository
{
    public function save(Contact $contact): void;
    public function findById(int $id): ?Contact;
    public function findByCustomerId(int $customerId): array;
    public function findBySiteId(int $siteId): array;
    public function findAll(): array;
}
