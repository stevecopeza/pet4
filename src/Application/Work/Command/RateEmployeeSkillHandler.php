<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\PersonSkill;
use Pet\Domain\Work\Repository\PersonSkillRepository;

class RateEmployeeSkillHandler
{
    private TransactionManager $transactionManager;
    private $personSkillRepository;

    public function __construct(TransactionManager $transactionManager, PersonSkillRepository $personSkillRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->personSkillRepository = $personSkillRepository;
    }

    public function handle(RateEmployeeSkillCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        // Check if a rating already exists for this date? 
        // The business rule usually implies a new rating creates a new history record or updates the current snapshot.
        // Our SQL repo `findByEmployeeAndSkill` gets the latest.
        // If we want to keep history, we just insert a new one.
        // If we want to update today's rating, we might check if one exists for today.
        
        // For simplicity and audit trail, we'll just create a new record.
        // However, if one exists for the EXACT same effective_date (e.g. today), maybe we update it to avoid spamming if user clicks save twice?
        // Let's assume append-only for now, or maybe the Repo handles it.
        // The entity has an ID, so if we wanted update we'd load it.
        // But here we are "Rating" which is an event.
        
        $personSkill = new PersonSkill(
            $command->employeeId(),
            $command->skillId(),
            $command->selfRating(),
            $command->managerRating(),
            $command->effectiveDate(),
            $command->reviewCycleId()
        );

        $this->personSkillRepository->save($personSkill);
    
        });
    }
}
