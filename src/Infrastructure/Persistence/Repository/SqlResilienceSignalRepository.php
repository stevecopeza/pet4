<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Pet\Domain\Resilience\Entity\ResilienceSignal;
use Pet\Domain\Resilience\Repository\ResilienceSignalRepository;

class SqlResilienceSignalRepository implements ResilienceSignalRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(ResilienceSignal $signal): void
    {
        $table = $this->wpdb->prefix . 'pet_resilience_signals';
        $data = [
            'id' => $signal->id(),
            'analysis_run_id' => $signal->analysisRunId(),
            'scope_type' => $signal->scopeType(),
            'scope_id' => $signal->scopeId(),
            'signal_type' => $signal->signalType(),
            'severity' => $signal->severity(),
            'title' => $signal->title(),
            'summary' => $signal->summary(),
            'employee_id' => $signal->employeeId(),
            'team_id' => $signal->teamId(),
            'role_id' => $signal->roleId(),
            'source_entity_type' => $signal->sourceEntityType(),
            'source_entity_id' => $signal->sourceEntityId(),
            'status' => $signal->status(),
            'created_at' => $signal->createdAt()->format('Y-m-d H:i:s'),
            'resolved_at' => $signal->resolvedAt() ? $signal->resolvedAt()->format('Y-m-d H:i:s') : null,
            'metadata_json' => $signal->metadata() ? json_encode($signal->metadata(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ];

        $this->wpdb->insert($table, $data);
    }

    public function findByAnalysisRunId(string $analysisRunId): array
    {
        $table = $this->wpdb->prefix . 'pet_resilience_signals';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE analysis_run_id = %s ORDER BY severity DESC, created_at DESC",
            $analysisRunId
        ));
        return array_map([$this, 'mapRow'], $rows ?: []);
    }

    public function findActiveByScope(string $scopeType, int $scopeId, int $limit = 200): array
    {
        $table = $this->wpdb->prefix . 'pet_resilience_signals';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE scope_type = %s AND scope_id = %d AND status = %s ORDER BY severity DESC, created_at DESC LIMIT %d",
            $scopeType,
            $scopeId,
            'ACTIVE',
            $limit
        ));
        return array_map([$this, 'mapRow'], $rows ?: []);
    }

    public function deactivateActiveForScope(string $scopeType, int $scopeId, ?string $resolvedAtUtc = null): void
    {
        $table = $this->wpdb->prefix . 'pet_resilience_signals';
        $resolvedAtUtc = $resolvedAtUtc ?: gmdate('Y-m-d H:i:s');

        $this->wpdb->update(
            $table,
            [
                'status' => 'INACTIVE',
                'resolved_at' => $resolvedAtUtc,
            ],
            [
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'status' => 'ACTIVE',
            ]
        );
    }

    private function mapRow(object $row): ResilienceSignal
    {
        $meta = null;
        if (isset($row->metadata_json) && $row->metadata_json) {
            $decoded = json_decode((string)$row->metadata_json, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        return new ResilienceSignal(
            (string)$row->id,
            (string)$row->analysis_run_id,
            (string)$row->scope_type,
            (int)$row->scope_id,
            (string)$row->signal_type,
            (string)$row->severity,
            (string)$row->title,
            (string)$row->summary,
            new DateTimeImmutable((string)$row->created_at),
            $row->employee_id !== null ? (int)$row->employee_id : null,
            $row->team_id !== null ? (int)$row->team_id : null,
            $row->role_id !== null ? (int)$row->role_id : null,
            $row->source_entity_type !== null ? (string)$row->source_entity_type : null,
            $row->source_entity_id !== null ? (string)$row->source_entity_id : null,
            (string)$row->status,
            $row->resolved_at ? new DateTimeImmutable((string)$row->resolved_at) : null,
            $meta
        );
    }
}

