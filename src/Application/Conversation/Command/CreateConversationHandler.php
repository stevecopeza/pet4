<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Identity\Repository\ContactRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;

class CreateConversationHandler
{
    private TransactionManager $transactionManager;
    private ConversationRepository $conversationRepository;
    private ?EmployeeRepository $employeeRepository;
    private ?ContactRepository $contactRepository;
    private ?TeamRepository $teamRepository;
    private ?QuoteRepository $quoteRepository;

    public function __construct(TransactionManager $transactionManager, 
        ConversationRepository $conversationRepository,
        ?EmployeeRepository $employeeRepository = null,
        ?ContactRepository $contactRepository = null,
        ?TeamRepository $teamRepository = null,
        ?QuoteRepository $quoteRepository = null
    ) {
        $this->transactionManager = $transactionManager;
        $this->conversationRepository = $conversationRepository;
        $this->employeeRepository = $employeeRepository;
        $this->contactRepository = $contactRepository;
        $this->teamRepository = $teamRepository;
        $this->quoteRepository = $quoteRepository;
    }

    public function handle(CreateConversationCommand $command): string
    {
        return $this->transactionManager->transactional(function () use ($command) {
        $contextVersion = $command->contextVersion();
        
        $existing = $this->conversationRepository->findByContext(
            $command->contextType(), 
            $command->contextId(),
            $contextVersion,
            $command->subjectKey()
        );
        
        if ($existing) {
            return $existing->uuid();
        }

        $uuid = wp_generate_uuid4();
        
        $conversation = Conversation::create(
            $uuid,
            $command->contextType(),
            $command->contextId(),
            $command->subject(),
            $command->subjectKey(),
            $command->actorId(),
            $contextVersion
        );

        // Always add creator as participant
        $conversation->addParticipant($command->actorId(), $command->actorId());

        if ($command->contextType() === 'quote') {
            $this->handleQuoteParticipants($conversation, $command);
        }

        $this->conversationRepository->save($conversation);

        return $uuid;
    
        });
    }

    private function handleQuoteParticipants(Conversation $conversation, CreateConversationCommand $command): void
    {
        // Add initial users
        if (!empty($command->initialUserIds()) && $this->employeeRepository) {
            foreach ($command->initialUserIds() as $userId) {
                // Validate existence/employee status if needed
                // Instruction: "Only employees/admin may add internal users"
                // We assume command validation happens upstream or we validate here.
                // We check if it's a valid employee/user.
                // For now, just check if user exists in WP (implied by Employee check if we use EmployeeRepo)
                // But user_ids are WP user IDs.
                $employee = $this->employeeRepository->findByWpUserId((int)$userId);
                if ($employee) {
                    $conversation->addParticipant((int)$userId, $command->actorId());
                }
            }
        }

        // Smart Seeding: Auto-add customer contacts from the Quote
        if ($this->quoteRepository && $this->contactRepository) {
            $quote = $this->quoteRepository->findById((int)$command->contextId());
            if ($quote) {
                // Auto-add contacts associated with the customer
                $contacts = $this->contactRepository->findByCustomerId($quote->customerId());
                foreach ($contacts as $contact) {
                    // Check if already added (idempotency handled by domain but good to check)
                    $conversation->addContactParticipant($contact->id(), $command->actorId());
                }
            }
        }

        // Add initial contacts explicitly requested
        if (!empty($command->initialContactIds()) && $this->contactRepository) {
            foreach ($command->initialContactIds() as $contactId) {
                $contact = $this->contactRepository->findById((int)$contactId);
                if ($contact) {
                    $conversation->addContactParticipant((int)$contactId, $command->actorId());
                }
            }
        }

        // Add initial teams
        if (!empty($command->initialTeamIds()) && $this->teamRepository) {
            foreach ($command->initialTeamIds() as $teamId) {
                $team = $this->teamRepository->find((int)$teamId);
                if ($team) {
                    $conversation->addTeamParticipant((int)$teamId, $command->actorId());
                }
            }
        }
    }
}
