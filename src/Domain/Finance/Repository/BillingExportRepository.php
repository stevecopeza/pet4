<?php

declare(strict_types=1);

namespace Pet\Domain\Finance\Repository;

use Pet\Domain\Finance\Entity\BillingExport;
use Pet\Domain\Finance\Entity\BillingExportItem;

interface BillingExportRepository
{
    public function save(BillingExport $export): void;
    public function findById(int $id): ?BillingExport;
    public function findAll(int $limit = 50): array;
    public function addItem(BillingExportItem $item): void;
    public function findItems(int $exportId): array;
    public function setStatus(int $exportId, string $status): void;
    public function sumItemsTotal(int $exportId): float;
}
