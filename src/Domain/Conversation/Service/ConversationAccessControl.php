<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Service;

use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Identity\Repository\ContactRepository;

class ConversationAccessControl
{
    private TicketRepository $ticketRepository;
    private ProjectRepository $projectRepository;
    private EmployeeRepository $employeeRepository;
    private QuoteRepository $quoteRepository;
    private ContactRepository $contactRepository;

    public function __construct(
        TicketRepository $ticketRepository,
        ProjectRepository $projectRepository,
        EmployeeRepository $employeeRepository,
        QuoteRepository $quoteRepository,
        ContactRepository $contactRepository
    ) {
        $this->ticketRepository = $ticketRepository;
        $this->projectRepository = $projectRepository;
        $this->employeeRepository = $employeeRepository;
        $this->quoteRepository = $quoteRepository;
        $this->contactRepository = $contactRepository;
    }

    public function check(string $contextType, string $contextId, int $userId): bool
    {
        // 1. Admin/Superuser override
        if (user_can($userId, 'manage_options')) {
            return true;
        }

        switch ($contextType) {
            case 'ticket':
                return $this->canAccessTicket((int)$contextId, $userId);
            case 'project':
                return $this->canAccessProject((int)$contextId, $userId);
            case 'knowledge_article':
                return $this->canAccessKnowledgeArticle((int)$contextId, $userId);
            case 'quote':
                return false;
            default:
                return false;
        }
    }

    private function canAccessTicket(int $ticketId, int $userId): bool
    {
        $ticket = $this->ticketRepository->findById($ticketId);
        if (!$ticket) {
            return false;
        }

        // If not manager (checked above), check if assigned agent
        if ($ticket->ownerUserId() && (int)$ticket->ownerUserId() === $userId) {
            return true;
        }

        return false;
    }

    private function canAccessProject(int $projectId, int $userId): bool
    {
        $project = $this->projectRepository->findById($projectId);
        if (!$project) {
            return false;
        }

        // Projects are internal-only.
        // Since Project entity does not currently support granular team assignment,
        // we allow all employees to access project conversations.
        if ($this->isEmployee($userId)) {
            return true;
        }
        
        return false;
    }

    private function canAccessKnowledgeArticle(int $articleId, int $userId): bool
    {
        // "Knowledge: internal-only (employees/roles)"
        return $this->isEmployee($userId);
    }

    private function isEmployee(int $userId): bool
    {
        return $this->employeeRepository->findByWpUserId($userId) !== null;
    }
}
