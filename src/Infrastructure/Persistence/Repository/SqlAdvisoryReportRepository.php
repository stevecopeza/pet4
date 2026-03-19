<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Pet\Domain\Advisory\Entity\AdvisoryReport;
use Pet\Domain\Advisory\Repository\AdvisoryReportRepository;

class SqlAdvisoryReportRepository implements AdvisoryReportRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(AdvisoryReport $report): void
    {
        $table = $this->wpdb->prefix . 'pet_advisory_reports';
        $data = [
            'id' => $report->id(),
            'report_type' => $report->reportType(),
            'scope_type' => $report->scopeType(),
            'scope_id' => $report->scopeId(),
            'version_number' => $report->versionNumber(),
            'title' => $report->title(),
            'summary' => $report->summary(),
            'status' => $report->status(),
            'generated_at' => $report->generatedAt()->format('Y-m-d H:i:s'),
            'generated_by' => $report->generatedBy(),
            'content_json' => json_encode($report->content(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'source_snapshot_metadata_json' => $report->sourceSnapshotMetadata() ? json_encode($report->sourceSnapshotMetadata(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ];

        $this->wpdb->insert($table, $data);
    }

    public function findById(string $id): ?AdvisoryReport
    {
        $table = $this->wpdb->prefix . 'pet_advisory_reports';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id));
        return $row ? $this->mapRow($row) : null;
    }

    public function findByScope(string $reportType, string $scopeType, int $scopeId, int $limit = 50): array
    {
        $table = $this->wpdb->prefix . 'pet_advisory_reports';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE report_type = %s AND scope_type = %s AND scope_id = %d ORDER BY version_number DESC, generated_at DESC LIMIT %d",
            $reportType,
            $scopeType,
            $scopeId,
            $limit
        ));
        return array_map([$this, 'mapRow'], $rows ?: []);
    }

    public function findLatestByScope(string $reportType, string $scopeType, int $scopeId): ?AdvisoryReport
    {
        $table = $this->wpdb->prefix . 'pet_advisory_reports';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE report_type = %s AND scope_type = %s AND scope_id = %d ORDER BY version_number DESC, generated_at DESC LIMIT 1",
            $reportType,
            $scopeType,
            $scopeId
        ));
        return $row ? $this->mapRow($row) : null;
    }

    public function findNextVersionNumber(string $reportType, string $scopeType, int $scopeId): int
    {
        $table = $this->wpdb->prefix . 'pet_advisory_reports';
        $v = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(version_number) FROM $table WHERE report_type = %s AND scope_type = %s AND scope_id = %d",
            $reportType,
            $scopeType,
            $scopeId
        ));
        return $v + 1;
    }

    private function mapRow(object $row): AdvisoryReport
    {
        $content = json_decode((string)$row->content_json, true);
        $content = is_array($content) ? $content : [];

        $meta = null;
        if (isset($row->source_snapshot_metadata_json) && $row->source_snapshot_metadata_json) {
            $decoded = json_decode((string)$row->source_snapshot_metadata_json, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        return new AdvisoryReport(
            (string)$row->id,
            (string)$row->report_type,
            (string)$row->scope_type,
            (int)$row->scope_id,
            (int)$row->version_number,
            (string)$row->title,
            $row->summary !== null ? (string)$row->summary : null,
            (string)$row->status,
            new DateTimeImmutable((string)$row->generated_at),
            $row->generated_by !== null ? (int)$row->generated_by : null,
            $content,
            $meta
        );
    }
}

