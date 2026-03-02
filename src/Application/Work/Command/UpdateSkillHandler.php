<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\Skill;
use Pet\Domain\Work\Repository\SkillRepository;

class UpdateSkillHandler
{
    private TransactionManager $transactionManager;
    private SkillRepository $skillRepository;

    public function __construct(TransactionManager $transactionManager, SkillRepository $skillRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->skillRepository = $skillRepository;
    }

    public function handle(UpdateSkillCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $existing = $this->skillRepository->findById($command->id());

        if (!$existing) {
            throw new \RuntimeException('Skill not found');
        }

        $updated = new Skill(
            $command->capabilityId(),
            $command->name(),
            $command->description(),
            $existing->id(),
            $existing->status(),
            $existing->createdAt()
        );

        $this->skillRepository->save($updated);
    
        });
    }
}

