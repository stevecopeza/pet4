<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\Skill;
use Pet\Domain\Work\Repository\SkillRepository;

class CreateSkillHandler
{
    private TransactionManager $transactionManager;
    private $skillRepository;

    public function __construct(TransactionManager $transactionManager, SkillRepository $skillRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->skillRepository = $skillRepository;
    }

    public function handle(CreateSkillCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $skill = new Skill(
            $command->capabilityId(),
            $command->name(),
            $command->description()
        );

        $this->skillRepository->save($skill);
    
        });
    }
}
