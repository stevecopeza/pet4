<?php

declare(strict_types=1);

namespace Pet\Domain\Advisory\Repository;

use Pet\Domain\Advisory\Entity\AdvisoryReport;

interface AdvisoryReportRepository
{
    public function save(AdvisoryReport $report): void;

    public function findById(string $id): ?AdvisoryReport;

    /**
     * @return AdvisoryReport[]
     */
    public function findByScope(string $reportType, string $scopeType, int $scopeId, int $limit = 50): array;

    public function findLatestByScope(string $reportType, string $scopeType, int $scopeId): ?AdvisoryReport;

    public function findNextVersionNumber(string $reportType, string $scopeType, int $scopeId): int;
}

