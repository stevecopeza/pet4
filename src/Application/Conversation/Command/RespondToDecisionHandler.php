<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Conversation\Repository\ConversationRepository;

class RespondToDecisionHandler
{
    private TransactionManager $transactionManager;
    private DecisionRepository $decisionRepository;
    private ConversationRepository $conversationRepository;

    public function __construct(TransactionManager $transactionManager, 
        DecisionRepository $decisionRepository,
        ConversationRepository $conversationRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->decisionRepository = $decisionRepository;
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(RespondToDecisionCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        // Use FOR UPDATE via repository method
        $decision = $this->decisionRepository->findByUuidForUpdate($command->decisionUuid());
        
        if (!$decision) {
            throw new \RuntimeException('Decision not found');
        }

        $decision->respond(
            $command->responderId(),
            $command->response(),
            $command->comment()
        );

        $this->decisionRepository->save($decision);

        // Add responder as participant
        $conversation = $this->conversationRepository->findById($decision->conversationId());
        if ($conversation) {
            $conversation->addParticipant($command->responderId(), $command->responderId());
            $this->conversationRepository->save($conversation);
        }
    
        });
    }
}
