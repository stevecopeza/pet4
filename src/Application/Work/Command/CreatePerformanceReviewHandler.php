<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\PerformanceReview;
use Pet\Domain\Work\Repository\PerformanceReviewRepository;
use Pet\Domain\Work\Repository\AssignmentRepository;

class CreatePerformanceReviewHandler
{
    private TransactionManager $transactionManager;
    private $repository;
    private $assignmentRepository;
    private $generateKpisHandler;

    public function __construct(TransactionManager $transactionManager, 
        PerformanceReviewRepository $repository,
        AssignmentRepository $assignmentRepository,
        GeneratePersonKpisHandler $generateKpisHandler
    ) {
        $this->transactionManager = $transactionManager;
        $this->repository = $repository;
        $this->assignmentRepository = $assignmentRepository;
        $this->generateKpisHandler = $generateKpisHandler;
    }

    public function handle(CreatePerformanceReviewCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command) {
        $review = new PerformanceReview(
            $command->employeeId(),
            $command->reviewerId(),
            $command->periodStart(),
            $command->periodEnd()
        );

        $id = $this->repository->save($review);

        // Auto-generate KPIs based on active assignments during this period
        $assignments = $this->assignmentRepository->findByEmployeeId($command->employeeId());
        $periodStart = $command->periodStart();
        $periodEnd = $command->periodEnd();

        foreach ($assignments as $assignment) {
            // Check for overlap
            $assignStart = $assignment->startDate();
            $assignEnd = $assignment->endDate();

            // Overlap logic: StartA <= EndB AND EndA >= StartB
            // Treat null end date as infinite
            $assignEndTs = $assignEnd ? $assignEnd->getTimestamp() : PHP_INT_MAX;

            if ($assignStart->getTimestamp() <= $periodEnd->getTimestamp() && 
                $assignEndTs >= $periodStart->getTimestamp()) {
                
                // Trigger KPI generation for this role
                $kpiCommand = new GeneratePersonKpisCommand(
                    $command->employeeId(),
                    $assignment->roleId(),
                    $periodStart->format('Y-m-d'),
                    $periodEnd->format('Y-m-d')
                );
                
                $this->generateKpisHandler->handle($kpiCommand);
            }
        }

        return $id;
    
        });
    }
}
