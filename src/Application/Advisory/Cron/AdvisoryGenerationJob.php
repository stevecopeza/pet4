<?php

declare(strict_types=1);

namespace Pet\Application\Advisory\Cron;

use Pet\Domain\Advisory\Service\AdvisoryGenerator;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Application\System\Service\FeatureFlagService;

class AdvisoryGenerationJob
{
    public function __construct(
        private EmployeeRepository $employeeRepository,
        private AdvisoryGenerator $advisoryGenerator,
        private FeatureFlagService $featureFlags
    ) {}

    public function run(): void
    {
        if (!$this->featureFlags->isAdvisoryEnabled()) {
            return;
        }

        $employees = $this->employeeRepository->findAll();

        foreach ($employees as $employee) {
            if ($employee->isArchived() || $employee->status() !== 'active') {
                continue;
            }

            // Using WP User ID as the link between Employee and Work Items
            $userId = (string) $employee->wpUserId();
            $this->advisoryGenerator->generateForUser($userId);
        }
    }
}
