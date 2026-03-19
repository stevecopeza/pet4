<?php

declare(strict_types=1);

namespace Pet\Application\Advisory\Service;

class CustomerAdvisoryAccessService
{
    private $wpdb;
    private string $ticketsTable;
    private string $projectsTable;
    private string $tasksTable;
    private string $workItemsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->ticketsTable = $wpdb->prefix . 'pet_tickets';
        $this->projectsTable = $wpdb->prefix . 'pet_projects';
        $this->tasksTable = $wpdb->prefix . 'pet_tasks';
        $this->workItemsTable = $wpdb->prefix . 'pet_work_items';
    }

    public function canAccessCustomer(int $wpUserId, int $customerId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        $userId = (string)$wpUserId;

        $ticketCount = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->workItemsTable} wi
             INNER JOIN {$this->ticketsTable} t ON t.id = wi.source_id
             WHERE wi.assigned_user_id = %s AND wi.source_type = %s AND t.customer_id = %d",
            $userId,
            'ticket',
            $customerId
        ));
        if ($ticketCount > 0) {
            return true;
        }

        $taskCount = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->workItemsTable} wi
             INNER JOIN {$this->tasksTable} task ON task.id = wi.source_id
             INNER JOIN {$this->projectsTable} p ON p.id = task.project_id
             WHERE wi.assigned_user_id = %s AND wi.source_type = %s AND p.customer_id = %d",
            $userId,
            'project_task',
            $customerId
        ));
        return $taskCount > 0;
    }
}

