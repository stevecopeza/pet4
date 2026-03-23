<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

final class DemoEnvironmentHealthService
{
    private DemoPurgeService $demoPurgeService;
    private $wpdb;

    public function __construct(DemoPurgeService $demoPurgeService, $wpdb)
    {
        $this->demoPurgeService = $demoPurgeService;
        $this->wpdb = $wpdb;
    }

    public function getHealth(): array
    {
        $activeRuns = $this->demoPurgeService->listTrackedSeedRuns(true);
        $trackedRuns = $this->demoPurgeService->listTrackedSeedRuns(false);
        $activeRunCount = count($activeRuns);

        $activeRun = $activeRunCount > 0 ? $activeRuns[0] : null;
        $integrity = $this->integrityMetrics();
        $environment = $this->environmentNotes($integrity, $activeRunCount);

        $seedErrorInLastRun = $activeRunCount > 0 && (int)($activeRun['registry_rows'] ?? 0) <= 0;
        $noActiveRun = $activeRunCount === 0;
        $hasDuplicatePairs = ((int)$integrity['duplicate_skill_pairs'] > 0) || ((int)$integrity['duplicate_certification_pairs'] > 0);
        $hasIntegrityViolation = $hasDuplicatePairs || ((int)$integrity['duplicate_employee_emails'] > 0) || $seedErrorInLastRun;
        $hasContaminationRisk = $activeRunCount !== 1 || (bool)$environment['has_untracked_rows'];

        $readiness = 'GREEN';
        if ($noActiveRun || $hasIntegrityViolation) {
            $readiness = 'RED';
        } elseif ($hasContaminationRisk) {
            $readiness = 'AMBER';
        }
        $readinessReasons = $this->readinessReasons(
            $noActiveRun,
            $seedErrorInLastRun,
            $hasDuplicatePairs,
            (int)$integrity['duplicate_employee_emails'] > 0,
            $activeRunCount,
            (bool)$environment['has_untracked_rows']
        );

        return [
            'readiness_status' => $readiness,
            'readiness_reasons' => $readinessReasons,
            'seed' => [
                'active_seed_run_id' => $activeRun['seed_run_id'] ?? null,
                'active_seed_run_created_at' => $activeRun['first_seen_at'] ?? null,
                'active_seed_run_last_seen_at' => $activeRun['last_seen_at'] ?? null,
                'active_seed_run_registry_rows' => (int)($activeRun['registry_rows'] ?? 0),
                'tracked_runs_count' => count($trackedRuns),
                'active_runs_count' => $activeRunCount,
                'last_clean_baseline_run' => $activeRun['first_seen_at'] ?? null,
                'seed_error_in_last_run' => $seedErrorInLastRun,
            ],
            'integrity' => $integrity,
            'environment' => $environment,
            'flags' => [
                'no_active_seed_run' => $noActiveRun,
                'has_duplicate_staff_metadata_pairs' => $hasDuplicatePairs,
                'has_integrity_violation' => $hasIntegrityViolation,
                'has_contamination_risk' => $hasContaminationRisk,
            ],
        ];
    }

    private function integrityMetrics(): array
    {
        $p = $this->wpdb->prefix;
        return [
            'duplicate_employee_emails' => $this->safeCount("SELECT COUNT(*) FROM (SELECT email, COUNT(*) c FROM {$p}pet_employees WHERE email IS NOT NULL AND LENGTH(email) > 0 GROUP BY email HAVING COUNT(*) > 1) x"),
            'duplicate_skill_pairs' => $this->safeCount("SELECT COUNT(*) FROM (SELECT employee_id, skill_id, COUNT(*) c FROM {$p}pet_person_skills GROUP BY employee_id, skill_id HAVING COUNT(*) > 1) x"),
            'duplicate_certification_pairs' => $this->safeCount("SELECT COUNT(*) FROM (SELECT employee_id, certification_id, COUNT(*) c FROM {$p}pet_person_certifications GROUP BY employee_id, certification_id HAVING COUNT(*) > 1) x"),
        ];
    }

    private function environmentNotes(array $integrity, int $activeRunCount): array
    {
        $untrackedRowsByTable = $this->detectUntrackedRowsByTable([
            $this->wpdb->prefix . 'pet_quotes',
            $this->wpdb->prefix . 'pet_projects',
            $this->wpdb->prefix . 'pet_tickets',
        ]);
        $hasUntrackedRows = array_sum($untrackedRowsByTable) > 0;

        return [
            'has_untracked_rows' => $hasUntrackedRows,
            'untracked_rows_by_table' => $untrackedRowsByTable,
            'notes' => $this->buildNotes($integrity, $hasUntrackedRows, $activeRunCount),
        ];
    }

    /**
     * @param string[] $tables
     * @return array<string, int>
     */
    private function detectUntrackedRowsByTable(array $tables): array
    {
        $registryTable = $this->wpdb->prefix . 'pet_demo_seed_registry';
        $result = [];
        if (!$this->tableExists($registryTable)) {
            foreach ($tables as $table) {
                $result[$table] = 0;
            }
            return $result;
        }

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                $result[$table] = 0;
                continue;
            }
            $seedMarkerPredicate = '';
            if ($this->tableHasColumn($table, 'malleable_data')) {
                $seedMarkerPredicate = " AND JSON_EXTRACT(t.malleable_data, '$.seed_run_id') IS NOT NULL";
            } elseif ($this->tableHasColumn($table, 'metadata_json')) {
                $seedMarkerPredicate = " AND JSON_EXTRACT(t.metadata_json, '$.seed_run_id') IS NOT NULL";
            } else {
                // Not detectable on this table without explicit seed metadata field.
                $result[$table] = 0;
                continue;
            }
            $result[$table] = $this->safeCount($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table t
                 LEFT JOIN $registryTable r
                   ON r.table_name = %s
                  AND r.row_id = t.id
                 WHERE r.id IS NULL$seedMarkerPredicate",
                [$table]
            ));
        }

        return $result;
    }

    /**
     * @param array<string, int> $integrity
     * @return string[]
     */
    private function buildNotes(array $integrity, bool $hasUntrackedRows, int $activeRunCount): array
    {
        $notes = [];
        if ($activeRunCount === 0) {
            $notes[] = 'No active tracked seed run is available.';
        } elseif ($activeRunCount > 1) {
            $notes[] = 'Multiple active tracked seed runs detected.';
        }
        if ((int)$integrity['duplicate_skill_pairs'] > 0 || (int)$integrity['duplicate_certification_pairs'] > 0) {
            $notes[] = 'Duplicate staff metadata pairs detected.';
        }
        if ((int)$integrity['duplicate_employee_emails'] > 0) {
            $notes[] = 'Duplicate employee emails detected.';
        }
        if ($hasUntrackedRows) {
            $notes[] = 'Legacy or untracked rows detected in key seeded tables.';
        }
        if (empty($notes)) {
            $notes[] = 'No immediate readiness warnings.';
        }
        return $notes;
    }

    /**
     * @return string[]
     */
    private function readinessReasons(
        bool $noActiveRun,
        bool $seedErrorInLastRun,
        bool $hasDuplicatePairs,
        bool $hasDuplicateEmployeeEmails,
        int $activeRunCount,
        bool $hasUntrackedRows
    ): array {
        $reasons = [];
        if ($noActiveRun) {
            $reasons[] = 'no_active_seed_run';
        }
        if ($seedErrorInLastRun) {
            $reasons[] = 'seed_error_in_last_run';
        }
        if ($hasDuplicatePairs) {
            $reasons[] = 'duplicate_staff_metadata_pairs';
        }
        if ($hasDuplicateEmployeeEmails) {
            $reasons[] = 'duplicate_employee_emails';
        }
        if ($activeRunCount > 1) {
            $reasons[] = 'multiple_active_seed_runs';
        }
        if ($hasUntrackedRows) {
            $reasons[] = 'untracked_rows_detected';
        }

        return empty($reasons) ? ['no_immediate_readiness_warnings'] : $reasons;
    }

    private function tableExists(string $table): bool
    {
        return $this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        $columns = $this->wpdb->get_col("DESCRIBE $table", 0);
        return is_array($columns) && in_array($column, $columns, true);
    }

    private function safeCount(string $sql): int
    {
        $value = $this->wpdb->get_var($sql);
        return $value === null ? 0 : (int)$value;
    }
}

