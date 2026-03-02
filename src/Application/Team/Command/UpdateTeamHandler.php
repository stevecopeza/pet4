<?php

declare(strict_types=1);

namespace Pet\Application\Team\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Team\Entity\Team;
use Pet\Domain\Team\Repository\TeamRepository;

class UpdateTeamHandler
{
    private TransactionManager $transactionManager;
    private TeamRepository $teamRepository;

    public function __construct(TransactionManager $transactionManager, TeamRepository $teamRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->teamRepository = $teamRepository;
    }

    public function handle(UpdateTeamCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $existingTeam = $this->teamRepository->find($command->id());

        if (!$existingTeam) {
            throw new \InvalidArgumentException("Team not found: {$command->id()}");
        }

        // Check if visual changed to increment version
        $visualChanged = (
            $existingTeam->visualType() !== $command->visualType() ||
            $existingTeam->visualRef() !== $command->visualRef()
        );
        
        $newVersion = $existingTeam->visualVersion();
        $newVisualUpdatedAt = $existingTeam->visualUpdatedAt();
        
        if ($visualChanged) {
            $newVersion++;
            $newVisualUpdatedAt = new \DateTimeImmutable();
        }

        $updatedTeam = new Team(
            $command->name(),
            $command->id(),
            $command->parentTeamId(),
            $command->managerId(),
            $command->escalationManagerId(),
            $command->status(),
            $command->visualType(),
            $command->visualRef(),
            $newVersion,
            $newVisualUpdatedAt,
            $command->memberIds(),
            $existingTeam->createdAt(),
            $existingTeam->archivedAt()
        );

        $this->teamRepository->save($updatedTeam);
    
        });
    }
}
