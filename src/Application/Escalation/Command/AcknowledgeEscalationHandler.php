<?php

declare(strict_types=1);

namespace Pet\Application\Escalation\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Escalation\Entity\EscalationTransition;
use Pet\Domain\Escalation\Repository\EscalationRepository;
use Pet\Domain\Event\EventBus;

class AcknowledgeEscalationHandler
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

    public function handle(AcknowledgeEscalationCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            $escalation = $this->escalationRepository->findById($command->escalationId());
            if (!$escalation) {
                throw new \DomainException("Escalation not found: {$command->escalationId()}");
            }

            $previousStatus = $escalation->status();
            $escalation->acknowledge($command->actorId());
            $this->escalationRepository->save($escalation);

            $transition = new EscalationTransition(
                $escalation->id(),
                $previousStatus,
                $escalation->status(),
                $command->actorId()
            );
            $this->escalationRepository->saveTransition($transition);

            foreach ($escalation->releaseEvents() as $event) {
                $this->eventBus->dispatch($event);
            }
        });
    }
}
