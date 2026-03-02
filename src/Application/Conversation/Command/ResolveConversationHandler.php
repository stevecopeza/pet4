<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Conversation\Repository\ConversationRepository;

class ResolveConversationHandler
{
    private TransactionManager $transactionManager;
    private ConversationRepository $conversationRepository;

    public function __construct(TransactionManager $transactionManager, ConversationRepository $conversationRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(ResolveConversationCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());
        
        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        $conversation->resolve($command->actorId());
        $this->conversationRepository->save($conversation);
    
        });
    }
}
