<?php

declare(strict_types=1);

namespace Pet\Application\Team\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Team\Entity\Team;
use Pet\Domain\Team\Repository\TeamRepository;

class CreateTeamHandler
{
    private TransactionManager $transactionManager;
    private TeamRepository $teamRepository;

    public function __construct(TransactionManager $transactionManager, TeamRepository $teamRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->teamRepository = $teamRepository;
    }

    public function handle(CreateTeamCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $team = new Team(
            $command->name(),
            null, // id
            $command->parentTeamId(),
            $command->managerId(),
            $command->escalationManagerId(),
            $command->status(),
            $command->visualType(),
            $command->visualRef(),
            1, // visualVersion
            null, // visualUpdatedAt
            $command->memberIds(),
            new \DateTimeImmutable()
        );

        $this->teamRepository->save($team);
    
        });
    }
}
