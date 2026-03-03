<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Support\Entity\TierTransition;
use Pet\Domain\Support\Repository\TierTransitionRepository;

class SqlTierTransitionRepository implements TierTransitionRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(TierTransition $transition): void
    {
        $table = $this->wpdb->prefix . 'pet_sla_clock_tier_transitions';

        $this->wpdb->insert($table, [
            'ticket_id' => $transition->ticketId(),
            'from_tier_priority' => $transition->fromTierPriority(),
            'to_tier_priority' => $transition->toTierPriority(),
            'actual_percent_at_transition' => $transition->actualPercentAtTransition(),
            'carried_percent' => $transition->carriedPercent(),
            'override_reason' => $transition->overrideReason(),
            'transitioned_at' => $transition->transitionedAt()->format('Y-m-d H:i:s'),
        ], ['%d', '%d', '%d', '%f', '%f', '%s', '%s']);
    }

    /**
     * @return TierTransition[]
     */
    public function findByTicketId(int $ticketId): array
    {
        $table = $this->wpdb->prefix . 'pet_sla_clock_tier_transitions';

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_id = %d ORDER BY transitioned_at ASC",
            $ticketId
        ));

        return array_map(function ($row) {
            return new TierTransition(
                (int)$row->ticket_id,
                isset($row->from_tier_priority) ? (int)$row->from_tier_priority : null,
                (int)$row->to_tier_priority,
                (float)$row->actual_percent_at_transition,
                (float)$row->carried_percent,
                $row->override_reason,
                new \DateTimeImmutable($row->transitioned_at),
                (int)$row->id
            );
        }, $rows);
    }
}
