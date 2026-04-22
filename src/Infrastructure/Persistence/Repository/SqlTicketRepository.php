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
            '%d',  // customer_id
            '%d',  // site_id
            '%d',  // sla_id
            '%s',  // subject
            '%s',  // description
            '%s',  // status
            '%s',  // priority
            '%d',  // malleable_schema_version
            '%s',  // malleable_data
            '%s',  // created_at
            '%s',  // opened_at
            '%s',  // closed_at
            '%s',  // resolved_at
            '%d',  // sla_snapshot_id
            '%s',  // response_due_at
            '%s',  // resolution_due_at
            '%s',  // responded_at
        ];
        $this->conditionallyAddColumn($data, $formats, 'queue_id', $ticket->queueId(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'owner_user_id', $ticket->ownerUserId(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'category', $ticket->category(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'subcategory', $ticket->subcategory(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'intake_source', $ticket->intakeSource(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'contact_id', $ticket->contactId(), '%d');

        // Backbone fields (C1)
        $this->conditionallyAddColumn($data, $formats, 'primary_container', $ticket->primaryContainer(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'project_id', $ticket->projectId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'quote_id', $ticket->quoteId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'phase_id', $ticket->phaseId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'parent_ticket_id', $ticket->parentTicketId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'parent_ticket_key', $ticket->parentTicketId() ?? 0, '%d');
        $this->conditionallyAddColumn($data, $formats, 'root_ticket_id', $ticket->rootTicketId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'ticket_kind', $ticket->ticketKind(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'department_id_ext', $ticket->departmentIdExt(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'required_role_id', $ticket->requiredRoleId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'skill_level', $ticket->skillLevel(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'billing_context_type', $ticket->billingContextType(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'agreement_id', $ticket->agreementId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'rate_plan_id', $ticket->ratePlanId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'is_billable_default', $ticket->isBillableDefault() ? 1 : 0, '%d');
        $this->conditionallyAddColumn($data, $formats, 'sold_minutes', $ticket->soldMinutes(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'estimated_minutes', $ticket->estimatedMinutes(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'remaining_minutes', $ticket->remainingMinutes(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'is_rollup', $ticket->isRollup() ? 1 : 0, '%d');
        $this->conditionallyAddColumn($data, $formats, 'lifecycle_owner', $ticket->lifecycleOwner(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'is_baseline_locked', $ticket->isBaselineLocked() ? 1 : 0, '%d');
        $this->conditionallyAddColumn($data, $formats, 'change_order_source_ticket_id', $ticket->changeOrderSourceTicketId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'sold_value_cents', $ticket->soldValueCents(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'source_type', $ticket->sourceType(), '%s');
        $this->conditionallyAddColumn($data, $formats, 'source_component_id', $ticket->sourceComponentId(), '%d');
        $this->conditionallyAddColumn($data, $formats, 'sla_status', $ticket->slaStatus(), '%s');

        if ($ticket->id()) {
            $this->wpdb->update($table, $data, ['id' => $ticket->id()], $formats, ['%d']);
        } else {
            $result = $this->wpdb->insert($table, $data, $formats);
            if ($result === false) {
                $message = $this->wpdb->last_error ?: 'Unknown ticket insert failure';
                throw new \RuntimeException('Ticket persistence failed: ' . $message);
            }
            $insertId = (int) $this->wpdb->insert_id;

            if ($insertId > 0) {
                $ref = new \ReflectionObject($ticket);
                if ($ref->hasProperty('id')) {
                    $prop = $ref->getProperty('id');
                    $prop->setAccessible(true);
                    $prop->setValue($ticket, $insertId);
                }

                // Post-insert: set root_ticket_id = self for top-level tickets
                if ($ticket->rootTicketId() === null && $this->hasColumn('root_ticket_id')) {
                    $ticket->setRootTicketId($insertId);
                    $this->wpdb->update($table, ['root_ticket_id' => $insertId], ['id' => $insertId]);
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

    public function findByProvisioningKey(int $projectId, int $sourceComponentId, ?int $parentTicketId): ?Ticket
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $this->ensureColumnsCached($table);

        if (!$this->hasColumn('project_id') || !$this->hasColumn('source_component_id')) {
            return null;
        }
        if ($this->hasColumn('parent_ticket_key')) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM $table
                 WHERE project_id = %d
                   AND source_component_id = %d
                   AND parent_ticket_key = %d
                 LIMIT 1",
                $projectId,
                $sourceComponentId,
                $parentTicketId ?? 0
            );
        } elseif ($parentTicketId === null) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM $table
                 WHERE project_id = %d
                   AND source_component_id = %d
                   AND parent_ticket_id IS NULL
                 LIMIT 1",
                $projectId,
                $sourceComponentId
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM $table
                 WHERE project_id = %d
                   AND source_component_id = %d
                   AND parent_ticket_id = %d
                 LIMIT 1",
                $projectId,
                $sourceComponentId,
                $parentTicketId
            );
        }

        $row = $this->wpdb->get_row($sql);
        return $row ? $this->hydrate($row) : null;
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

    public function findByQuoteId(int $quoteId): array
    {
        $table = $this->wpdb->prefix . 'pet_tickets';
        $this->ensureColumnsCached($table);

        if (!$this->hasColumn('quote_id')) {
            return [];
        }

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE quote_id = %d ORDER BY id ASC",
            $quoteId
        ));

        return array_map([$this, 'hydrate'], $rows);
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
            isset($row->contact_id) ? (int) $row->contact_id : null,
            // Backbone fields (C1)
            isset($row->primary_container) ? (string) $row->primary_container : 'support',
            isset($row->project_id) && $row->project_id ? (int) $row->project_id : null,
            isset($row->quote_id) && $row->quote_id ? (int) $row->quote_id : null,
            isset($row->phase_id) && $row->phase_id ? (int) $row->phase_id : null,
            isset($row->parent_ticket_id) && $row->parent_ticket_id ? (int) $row->parent_ticket_id : null,
            isset($row->root_ticket_id) && $row->root_ticket_id ? (int) $row->root_ticket_id : null,
            isset($row->ticket_kind) ? (string) $row->ticket_kind : 'work',
            isset($row->department_id_ext) && $row->department_id_ext ? (int) $row->department_id_ext : null,
            isset($row->required_role_id) && $row->required_role_id ? (int) $row->required_role_id : null,
            isset($row->skill_level) ? (string) $row->skill_level : null,
            isset($row->billing_context_type) ? (string) $row->billing_context_type : 'adhoc',
            isset($row->agreement_id) && $row->agreement_id ? (int) $row->agreement_id : null,
            isset($row->rate_plan_id) && $row->rate_plan_id ? (int) $row->rate_plan_id : null,
            isset($row->is_billable_default) ? (bool) $row->is_billable_default : true,
            isset($row->sold_minutes) && $row->sold_minutes !== null ? (int) $row->sold_minutes : null,
            isset($row->estimated_minutes) && $row->estimated_minutes !== null ? (int) $row->estimated_minutes : null,
            isset($row->remaining_minutes) && $row->remaining_minutes !== null ? (int) $row->remaining_minutes : null,
            isset($row->is_rollup) ? (bool) $row->is_rollup : false,
            isset($row->lifecycle_owner) ? (string) $row->lifecycle_owner : 'support',
            isset($row->is_baseline_locked) ? (bool) $row->is_baseline_locked : false,
            isset($row->change_order_source_ticket_id) && $row->change_order_source_ticket_id ? (int) $row->change_order_source_ticket_id : null,
            isset($row->sold_value_cents) && $row->sold_value_cents !== null ? (int) $row->sold_value_cents : null,
            isset($row->source_type) ? (string) $row->source_type : null,
            isset($row->source_component_id) && $row->source_component_id !== null ? (int) $row->source_component_id : null,
            isset($row->sla_status) && $row->sla_status ? (string) $row->sla_status : null
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
