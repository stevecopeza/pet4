<?php

declare(strict_types=1);

namespace Pet\Application\Escalation\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Entity\EscalationTransition;
use Pet\Domain\Escalation\Event\EscalationTriggeredEvent;
use Pet\Domain\Escalation\Repository\EscalationRepository;
use Pet\Domain\Event\EventBus;
use Pet\Infrastructure\Persistence\Exception\DuplicateKeyException;

class TriggerEscalationHandler
{
    private TransactionManager $transactionManager;
    private EscalationRepository $escalationRepository;
    private EventBus $eventBus;

    public function __construct(
        TransactionManager $transactionManager,
        EscalationRepository $escalationRepository,
        EventBus $eventBus
    ) {
        $this->transactionManager = $transactionManager;
        $this->escalationRepository = $escalationRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(TriggerEscalationCommand $command): int
    {
        // Idempotency: check for an existing open escalation with the same trigger identity
        $dedupeKey = Escalation::computeDedupeKey(
            $command->sourceEntityType(),
            $command->sourceEntityId(),
            $command->severity(),
            $command->reason()
        );

        $existing = $this->escalationRepository->findOpenByDedupeKey($dedupeKey);
        if ($existing !== null) {
            return $existing->id() ?? 0;
        }

        $escalationDbId = 0;

        $this->transactionManager->transactional(function () use ($command, $dedupeKey, &$escalationDbId) {
            $escalationId = wp_generate_uuid4();

            $escalation = new Escalation(
                $escalationId,
                $command->sourceEntityType(),
                $command->sourceEntityId(),
                $command->severity(),
                $command->reason(),
                $command->createdBy(),
                json_encode($command->metadata()) ?: '{}'
            );

            try {
                $this->escalationRepository->save($escalation);
            } catch (DuplicateKeyException $e) {
                // Concurrent race: another process won the insert. Re-read the winner.
                $winner = $this->escalationRepository->findOpenByDedupeKey($dedupeKey);
                if ($winner !== null) {
                    $escalationDbId = $winner->id() ?? 0;
                    return;
                }
                // Defensive: duplicate-key thrown but row not found — should not happen
                throw new \RuntimeException(
                    "Duplicate-key on escalation insert but existing row not found for dedupe key: {$dedupeKey}",
                    0,
                    $e
                );
            }

            $escalationDbId = $escalation->id() ?? 0;

            // Persist the initial NULL → OPEN transition atomically
            $transition = new EscalationTransition(
                $escalation->id(),
                null,
                Escalation::STATUS_OPEN,
                $command->createdBy(),
                $command->reason()
            );
            $this->escalationRepository->saveTransition($transition);

            $this->eventBus->dispatch(new EscalationTriggeredEvent(
                $escalationId,
                $command->sourceEntityType(),
                $command->sourceEntityId(),
                $command->severity(),
                $command->reason()
            ));
        });

        return $escalationDbId;
    }
}
