<?php

declare(strict_types=1);

namespace Pet\Application\Advisory\Service;

class CustomerAdvisoryAccessService
{
    private $wpdb;
    private string $ticketsTable;
    private string $workItemsTable;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->ticketsTable = $wpdb->prefix . 'pet_tickets';
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
        return $ticketCount > 0;
    }
}

