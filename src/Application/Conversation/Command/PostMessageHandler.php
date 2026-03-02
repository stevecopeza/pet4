<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Identity\Repository\ContactRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Domain\Conversation\Entity\Conversation;

class PostMessageHandler
{
    private TransactionManager $transactionManager;
    private ConversationRepository $conversationRepository;
    private ?EmployeeRepository $employeeRepository;
    private ?ContactRepository $contactRepository;
    private ?TeamRepository $teamRepository;

    public function __construct(TransactionManager $transactionManager, 
        ConversationRepository $conversationRepository,
        ?EmployeeRepository $employeeRepository = null,
        ?ContactRepository $contactRepository = null,
        ?TeamRepository $teamRepository = null
    ) {
        $this->transactionManager = $transactionManager;
        $this->conversationRepository = $conversationRepository;
        $this->employeeRepository = $employeeRepository;
        $this->contactRepository = $contactRepository;
        $this->teamRepository = $teamRepository;
    }

    public function handle(PostMessageCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $conversation = $this->conversationRepository->findByUuid($command->conversationUuid());
        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        $replyToMessageId = $command->replyToMessageId();
        if ($replyToMessageId !== null) {
            // Validate message exists in conversation
            if (!$this->conversationRepository->messageExistsInConversation($conversation->id(), $replyToMessageId)) {
                throw new \DomainException('Reply references a message that does not exist in this conversation');
            }
        }

        $conversation->postMessage(
            $command->body(),
            $command->mentions(),
            $command->attachments(),
            $command->actorId(),
            $replyToMessageId
        );

        // Spec: "Implicit Participant: Any user who posts, requests, or responds is auto-added as a participant."
        if (!$this->conversationRepository->isParticipant((int)$conversation->id(), $command->actorId())) {
            $conversation->addParticipant($command->actorId(), $command->actorId());
        }

        // Handle @mentions to auto-add participants
        $this->handleMentions($conversation, $command->mentions(), $command->actorId());

        $this->conversationRepository->save($conversation);
    
        });
    }

    private function handleMentions(Conversation $conversation, array $mentions, int $actorId): void
    {
        foreach ($mentions as $mention) {
            if (!is_array($mention) || !isset($mention['type'], $mention['id'])) {
                continue;
            }

            $type = $mention['type'];
            $id = (int)$mention['id'];

            if ($type === 'user' && $this->employeeRepository) {
                // Verify employee exists (optional but good for data integrity)
                // Mentions use WP user ID for users
                $employee = $this->employeeRepository->findByWpUserId($id);
                if ($employee) {
                    $conversation->addParticipant($id, $actorId);
                }
            } elseif ($type === 'team' && $this->teamRepository) {
                $team = $this->teamRepository->find($id);
                if ($team) {
                    $conversation->addTeamParticipant($id, $actorId);
                }
            } elseif ($type === 'contact' && $this->contactRepository) {
                $contact = $this->contactRepository->findById($id);
                if ($contact) {
                    $conversation->addContactParticipant($id, $actorId);
                }
            }
        }
    }
}
