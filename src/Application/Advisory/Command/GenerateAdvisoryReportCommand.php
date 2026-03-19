<?php

declare(strict_types=1);

namespace Pet\Application\Advisory\Command;

class GenerateAdvisoryReportCommand
{
    public function __construct(
        private int $customerId,
        private string $reportType,
        private int $generatedByUserId
    ) {
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function reportType(): string
    {
        return $this->reportType;
    }

    public function generatedByUserId(): int
    {
        return $this->generatedByUserId;
    }
}

