<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Conversation\Repository\ConversationRepository;

class AddParticipantHandler
{
    private TransactionManager $transactionManager;
    private ConversationRepository $conversationRepository;

    public function __construct(TransactionManager $transactionManager, ConversationRepository $conversationRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(AddParticipantCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());

        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        switch ($command->participantType()) {
            case 'user':
                $conversation->addParticipant($command->participantId(), $command->actorId());
                break;
            case 'contact':
                $conversation->addContactParticipant($command->participantId(), $command->actorId());
                break;
            case 'team':
                $conversation->addTeamParticipant($command->participantId(), $command->actorId());
                break;
            default:
                throw new \InvalidArgumentException('Invalid participant type: ' . $command->participantType());
        }

        $this->conversationRepository->save($conversation);
    
        });
    }
}
