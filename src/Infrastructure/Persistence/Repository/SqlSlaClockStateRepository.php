<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\SlaClockStateRepository;

class SqlSlaClockStateRepository implements SlaClockStateRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function findByTicketIdForUpdate(int $ticketId): ?SlaClockState
    {
        $table = $this->wpdb->prefix . 'pet_sla_clock_state';

        // Use FOR UPDATE to lock the row for concurrency protection
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table WHERE ticket_id = %d FOR UPDATE",
                $ticketId
            )
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function initialize(Ticket $ticket, int $slaVersionId): SlaClockState
    {
        // Create a new clock state in memory (not saved yet)
        return new SlaClockState(
            $ticket->id(),
            'none',
            null,
            $slaVersionId,
            false, // paused
            0 // escalationStage
        );
    }

    public function save(SlaClockState $state): void
    {
        $table = $this->wpdb->prefix . 'pet_sla_clock_state';

        $data = [
            'ticket_id' => $state->getTicketId(),
            'last_event_dispatched' => $state->getLastEventDispatched(),
            'last_evaluated_at' => $state->getLastEvaluatedAt() ? $state->getLastEvaluatedAt()->format('Y-m-d H:i:s') : null,
            'sla_version_id' => $state->getSlaVersionId(),
            'paused_flag' => $state->isPaused() ? 1 : 0,
            'escalation_stage' => $state->getEscalationStage(),
            'active_tier_priority' => $state->getActiveTierPriority(),
            'tier_elapsed_business_minutes' => $state->getTierElapsedBusinessMinutes(),
            'carried_forward_percent' => $state->getCarriedForwardPercent(),
            'total_transitions' => $state->getTotalTransitions(),
        ];

        // Format for wpdb
        $format = ['%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%f', '%d'];

        $existing = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $table WHERE ticket_id = %d", $state->getTicketId()));

        if ($existing) {
            $this->wpdb->update(
                $table,
                $data,
                ['ticket_id' => $state->getTicketId()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert($table, $data, $format);
        }
    }

    public function getDashboardStats(): array
    {
        $table = $this->wpdb->prefix . 'pet_sla_clock_state';
        
        $warning = $this->wpdb->get_var("SELECT COUNT(*) FROM $table WHERE last_event_dispatched = 'warning'");
        $breached = $this->wpdb->get_var("SELECT COUNT(*) FROM $table WHERE last_event_dispatched = 'breached'");
        
        return [
            'warningCount' => (int)$warning,
            'breachedCount' => (int)$breached,
        ];
    }

    private function mapRowToEntity(object $row): SlaClockState
    {
        return new SlaClockState(
            (int)$row->ticket_id,
            $row->last_event_dispatched,
            $row->last_evaluated_at ? new \DateTimeImmutable($row->last_evaluated_at) : null,
            (int)$row->sla_version_id,
            (bool)$row->paused_flag,
            (int)$row->escalation_stage,
            isset($row->active_tier_priority) ? (int)$row->active_tier_priority : null,
            (int)($row->tier_elapsed_business_minutes ?? 0),
            isset($row->carried_forward_percent) ? (float)$row->carried_forward_percent : null,
            (int)($row->total_transitions ?? 0)
        );
    }
}
