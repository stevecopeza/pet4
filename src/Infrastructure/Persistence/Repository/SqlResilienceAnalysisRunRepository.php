<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Pet\Domain\Resilience\Entity\ResilienceAnalysisRun;
use Pet\Domain\Resilience\Repository\ResilienceAnalysisRunRepository;

class SqlResilienceAnalysisRunRepository implements ResilienceAnalysisRunRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(ResilienceAnalysisRun $run): void
    {
        $table = $this->wpdb->prefix . 'pet_resilience_analysis_runs';
        $data = [
            'id' => $run->id(),
            'scope_type' => $run->scopeType(),
            'scope_id' => $run->scopeId(),
            'version_number' => $run->versionNumber(),
            'status' => $run->status(),
            'started_at' => $run->startedAt()->format('Y-m-d H:i:s'),
            'completed_at' => $run->completedAt() ? $run->completedAt()->format('Y-m-d H:i:s') : null,
            'generated_by' => $run->generatedBy(),
            'summary_json' => $run->summary() ? json_encode($run->summary(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $run->startedAt()->format('Y-m-d H:i:s'),
        ];

        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $table WHERE id = %s",
            $run->id()
        ));

        if ($exists) {
            $this->wpdb->update($table, $data, ['id' => $run->id()]);
            return;
        }

        $this->wpdb->insert($table, $data);
    }

    public function findById(string $id): ?ResilienceAnalysisRun
    {
        $table = $this->wpdb->prefix . 'pet_resilience_analysis_runs';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id));
        return $row ? $this->mapRow($row) : null;
    }

    public function findLatestByScope(string $scopeType, int $scopeId): ?ResilienceAnalysisRun
    {
        $table = $this->wpdb->prefix . 'pet_resilience_analysis_runs';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE scope_type = %s AND scope_id = %d ORDER BY version_number DESC, started_at DESC LIMIT 1",
            $scopeType,
            $scopeId
        ));
        return $row ? $this->mapRow($row) : null;
    }

    public function findByScope(string $scopeType, int $scopeId, int $limit = 50): array
    {
        $table = $this->wpdb->prefix . 'pet_resilience_analysis_runs';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE scope_type = %s AND scope_id = %d ORDER BY version_number DESC, started_at DESC LIMIT %d",
            $scopeType,
            $scopeId,
            $limit
        ));
        return array_map([$this, 'mapRow'], $rows ?: []);
    }

    public function findNextVersionNumber(string $scopeType, int $scopeId): int
    {
        $table = $this->wpdb->prefix . 'pet_resilience_analysis_runs';
        $v = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(version_number) FROM $table WHERE scope_type = %s AND scope_id = %d",
            $scopeType,
            $scopeId
        ));
        return $v + 1;
    }

    private function mapRow(object $row): ResilienceAnalysisRun
    {
        $summary = null;
        if (isset($row->summary_json) && $row->summary_json) {
            $decoded = json_decode((string)$row->summary_json, true);
            $summary = is_array($decoded) ? $decoded : null;
        }

        return new ResilienceAnalysisRun(
            (string)$row->id,
            (string)$row->scope_type,
            (int)$row->scope_id,
            (int)$row->version_number,
            (string)$row->status,
            new DateTimeImmutable((string)$row->started_at),
            $row->completed_at ? new DateTimeImmutable((string)$row->completed_at) : null,
            $row->generated_by !== null ? (int)$row->generated_by : null,
            $summary
        );
    }
}

