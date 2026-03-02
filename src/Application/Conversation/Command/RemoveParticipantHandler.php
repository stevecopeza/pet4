<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Conversation\Repository\ConversationRepository;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

class RemoveParticipantHandler
{
    private TransactionManager $transactionManager;
    private ConversationRepository $conversationRepository;

    public function __construct(TransactionManager $transactionManager, ConversationRepository $conversationRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->conversationRepository = $conversationRepository;
    }

    public function handle(RemoveParticipantCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());

        if (!$conversation) {
            throw new RuntimeException('Conversation not found');
        }

        // Enforce "Last Internal Coverage" rule
        if (in_array($command->participantType(), ['user', 'team'])) {
                $internalCount = $this->conversationRepository->getInternalParticipantCount($conversation->id());
                if ($internalCount <= 1) {
                    throw new DomainException('Cannot remove the last internal participant from the conversation.');
                }
            }

        switch ($command->participantType()) {
            case 'user':
                $conversation->removeParticipant($command->participantId(), $command->actorId());
                break;
            case 'contact':
                $conversation->removeContactParticipant($command->participantId(), $command->actorId());
                break;
            case 'team':
                $conversation->removeTeamParticipant($command->participantId(), $command->actorId());
                break;
            default:
                throw new InvalidArgumentException('Invalid participant type: ' . $command->participantType());
        }

        $this->conversationRepository->save($conversation);
    
        });
    }
}
