<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;

class SqlTicketRepository implements TicketRepository
{
    private $wpdb;
    private ?array $columns = null;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(Ticket $ticket): void
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $this->ensureColumnsCached($table);

        $data = [
            'customer_id' => $ticket->customerId(),
            'site_id' => $ticket->siteId(),
            'sla_id' => $ticket->slaId(),
            'subject' => $ticket->subject(),
            'description' => $ticket->description(),
            'status' => $ticket->status(),
            'priority' => $ticket->priority(),
            'malleable_schema_version' => $ticket->malleableSchemaVersion(),
            'malleable_data' => !empty($ticket->malleableData()) ? json_encode($ticket->malleableData()) : null,
            'created_at' => $ticket->createdAt()->format('Y-m-d H:i:s'),
            'opened_at' => $ticket->openedAt() ? $ticket->openedAt()->format('Y-m-d H:i:s') : null,
            'closed_at' => $ticket->closedAt() ? $ticket->closedAt()->format('Y-m-d H:i:s') : null,
            'resolved_at' => $ticket->resolvedAt() ? $ticket->resolvedAt()->format('Y-m-d H:i:s') : null,
            'sla_snapshot_id' => $ticket->slaSnapshotId(),
            'response_due_at' => $ticket->responseDueAt() ? $ticket->responseDueAt()->format('Y-m-d H:i:s') : null,
            'resolution_due_at' => $ticket->resolutionDueAt() ? $ticket->resolutionDueAt()->format('Y-m-d H:i:s') : null,
            'responded_at' => $ticket->respondedAt() ? $ticket->respondedAt()->format('Y-m-d H:i:s') : null,
        ];

        $formats = [
            '%d',
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
        ];

        $this->conditionallyAddColumn($data, $formats, 'queue_id', $ticket->queueId(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'owner_user_id', $ticket->ownerUserId(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'category', $ticket->category(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'subcategory', $ticket->subcategory(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'intake_source', $ticket->intakeSource(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'contact_id', $ticket->contactId(), '%d');

        if ($ticket->id()) {
            $this->wpdb->update($table, $data, ['id' => $ticket->id()], $formats, ['%d']);
        } else {
            $this->wpdb->insert($table, $data, $formats);
            $insertId = (int) $this->wpdb->insert_id;

            if ($insertId > 0) {
                $ref = new \ReflectionObject($ticket);
                if ($ref->hasProperty('id')) {
                    $prop = $ref->getProperty('id');
                    $prop->setAccessible(true);
                    $prop->setValue($ticket, $insertId);
                }
            }
        }
    }

    public function findById(int $id): ?Ticket
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $rows = $this->wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByCustomerId(int $customerId): array
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $table WHERE customer_id = %d ORDER BY created_at DESC", $customerId));

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findActive(): array
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        // Active tickets are those not resolved or closed.
        // Assuming status 'resolved' and 'closed' are the terminal states.
        $rows = $this->wpdb->get_results(
            "SELECT * FROM $table 
            WHERE status NOT IN ('resolved', 'closed') 
            ORDER BY created_at ASC"
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    public function countActiveUnassigned(): int
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        // Unassigned = owner_user_id IS NULL AND queue_id IS NULL
        // Only among "active" tickets (same active filter as current demoWow uses).
        return (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $table 
            WHERE status NOT IN ('resolved', 'closed') 
            AND owner_user_id IS NULL 
            AND queue_id IS NULL"
        );
    }

    public function delete(int $id): void
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $this->wpdb->delete($table, ['id' => $id], ['%d']);
    }

    private function hydrate($row): Ticket
    {
        return new Ticket(
            (int) $row->customer_id,
            $row->subject,
            $row->description,
            $row->status,
            $row->priority,
            isset($row->site_id) ? (int) $row->site_id : null,
            isset($row->sla_id) ? (int) $row->sla_id : null,
            (int) $row->id,
            isset($row->malleable_schema_version) ? (int) $row->malleable_schema_version : null,
            isset($row->malleable_data) ? (json_decode($row->malleable_data, true) ?: []) : [],
            new \DateTimeImmutable($row->created_at),
            isset($row->opened_at) && $row->opened_at ? new \DateTimeImmutable($row->opened_at) : null,
            isset($row->closed_at) && $row->closed_at ? new \DateTimeImmutable($row->closed_at) : null,
            isset($row->resolved_at) && $row->resolved_at ? new \DateTimeImmutable($row->resolved_at) : null,
            isset($row->sla_snapshot_id) ? (int) $row->sla_snapshot_id : null,
            isset($row->response_due_at) && $row->response_due_at ? new \DateTimeImmutable($row->response_due_at) : null,
            isset($row->resolution_due_at) && $row->resolution_due_at ? new \DateTimeImmutable($row->resolution_due_at) : null,
            isset($row->responded_at) && $row->responded_at ? new \DateTimeImmutable($row->responded_at) : null,
            isset($row->queue_id) ? (string) $row->queue_id : null,
            isset($row->owner_user_id) ? (string) $row->owner_user_id : null,
            isset($row->category) ? (string) $row->category : null,
            isset($row->subcategory) ? (string) $row->subcategory : null,
            isset($row->intake_source) ? (string) $row->intake_source : null,
            isset($row->contact_id) ? (int) $row->contact_id : null
        );
    }

    private function ensureColumnsCached(string $table): void
    {
        if ($this->columns !== null) {
            return;
        }

        $this->columns = [];
        $results = $this->wpdb->get_results("SHOW COLUMNS FROM $table");
        if (!is_array($results)) {
            return;
        }

        foreach ($results as $column) {
            if (isset($column->Field)) {
                $this->columns[$column->Field] = true;
            }
        }
    }

    private function hasColumn(string $column): bool
    {
        return $this->columns !== null && isset($this->columns[$column]);
    }

    private function conditionallyAddColumn(array &$data, array &$formats, string $column, $value, string $format): void
    {
        if ($this->hasColumn($column)) {
            $data[$column] = $value;
            $formats[] = $format;
        }
    }
}
