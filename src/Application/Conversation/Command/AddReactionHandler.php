<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Conversation\Repository\ConversationRepository;

class AddReactionHandler
{
    private TransactionManager $transactionManager;
    private ConversationRepository $conversationRepository;

    public function __construct(TransactionManager $transactionManager, ConversationRepository $conversationRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(AddReactionCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());
        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        // Validate message exists in conversation
        if (!$this->conversationRepository->messageExistsInConversation($conversation->id(), $command->messageId())) {
            throw new \DomainException('Message does not exist in this conversation');
        }

        // Check if user is participant
        if (!$this->conversationRepository->isParticipant($conversation->id(), $command->actorId())) {
            throw new \DomainException('Only participants can react to messages');
        }

        // Idempotency check
        if ($this->conversationRepository->hasReaction(
            $conversation->id(),
            $command->messageId(),
            $command->actorId(),
            $command->reactionType()
        )) {
            return; // No-op
        }

        $conversation->addReaction(
            $command->messageId(),
            $command->reactionType(),
            $command->actorId()
        );

        $this->conversationRepository->save($conversation);
    
        });
    }
}
