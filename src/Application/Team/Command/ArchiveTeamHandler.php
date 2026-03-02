<?php

declare(strict_types=1);

namespace Pet\Application\Team\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Team\Repository\TeamRepository;

class ArchiveTeamHandler
{
    private TransactionManager $transactionManager;
    private TeamRepository $teamRepository;

    public function __construct(TransactionManager $transactionManager, TeamRepository $teamRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->teamRepository = $teamRepository;
    }

    public function handle(ArchiveTeamCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        // TODO: Validate no active children before archiving?
        // Domain rule: "Cannot archive team with active child teams."
        // We should check this.
        
        $children = $this->teamRepository->findByParent($command->id());
        if (!empty($children)) {
             throw new \DomainException("Cannot archive team with active child teams.");
        }

        $this->teamRepository->delete($command->id());
    
        });
    }
}
