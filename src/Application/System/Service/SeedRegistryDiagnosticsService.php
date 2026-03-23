<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

final class SeedRegistryDiagnosticsService
{
    private DemoPurgeService $demoPurgeService;
    private $wpdb;
    private string $registryTable;
    public function __construct(DemoPurgeService $demoPurgeService, $wpdb)
    {
        $this->demoPurgeService = $demoPurgeService;
        $this->wpdb = $wpdb;
        $this->registryTable = $this->wpdb->prefix . 'pet_demo_seed_registry';
    }

    public function diagnostics(): array
    {
        $runs = $this->runsSummary(false);
        $activeRuns = $this->runsSummary(true);
        $runIds = array_map(static function (array $run): string {
            return (string)($run['run_id'] ?? '');
        }, $runs);

        return [
            'runs' => $runs,
            'table_distribution' => $this->tableDistribution($runIds),
            'integrity_issues' => $this->integrityIssues(),
            'registry_summary' => $this->registrySummary($runIds, $activeRuns),
        ];
    }

    /**
     * @return array<int, array{run_id:string, created_at:?string, last_seen_at:?string, status:string, registry_row_count:int}>
     */
    private function runsSummary(bool $activeOnly): array
    {
        $trackedRuns = $this->demoPurgeService->listTrackedSeedRuns($activeOnly);
        return array_map(static function (array $row) use ($activeOnly): array {
            return [
                'run_id' => (string)($row['seed_run_id'] ?? ''),
                'created_at' => isset($row['first_seen_at']) ? (string)$row['first_seen_at'] : null,
                'last_seen_at' => isset($row['last_seen_at']) ? (string)$row['last_seen_at'] : null,
                'status' => $activeOnly ? 'active' : 'tracked',
                'registry_row_count' => (int)($row['registry_rows'] ?? 0),
            ];
        }, $trackedRuns);
    }

    /**
     * @param string[] $runIds
     * @return array<int, array<string, mixed>>
     */
    private function tableDistribution(array $runIds): array
    {
        if (!$this->tableExists($this->registryTable) || empty($runIds)) {
            return [];
        }

        $trackedTables = [
            'customers' => $this->wpdb->prefix . 'pet_customers',
            'quotes' => $this->wpdb->prefix . 'pet_quotes',
            'projects' => $this->wpdb->prefix . 'pet_projects',
            'tickets' => $this->wpdb->prefix . 'pet_tickets',
            'time_entries' => $this->wpdb->prefix . 'pet_time_entries',
            'person_skills' => $this->wpdb->prefix . 'pet_person_skills',
            'person_certifications' => $this->wpdb->prefix . 'pet_person_certifications',
        ];

        $distribution = [];
        foreach ($runIds as $runId) {
            if ($runId === '') {
                continue;
            }
            $row = ['run_id' => $runId];
            foreach ($trackedTables as $key => $tableName) {
                $row[$key] = $this->safeCount($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $this->registryTable WHERE seed_run_id = %s AND table_name = %s",
                    [$runId, $tableName]
                ));
            }
            $distribution[] = $row;
        }

        return $distribution;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function integrityIssues(): array
    {
        $issues = [];
        $p = $this->wpdb->prefix;
        $employeesTable = $p . 'pet_employees';
        $skillsTable = $p . 'pet_person_skills';
        $certsTable = $p . 'pet_person_certifications';
        $teamMembersTable = $p . 'pet_team_members';
        $teamsTable = $p . 'pet_teams';

        $duplicateEmployeeEmails = $this->tableExists($employeesTable)
            ? $this->safeCount(
                "SELECT COUNT(*) FROM (
                    SELECT email, COUNT(*) c
                    FROM $employeesTable
                    WHERE email IS NOT NULL AND LENGTH(email) > 0
                    GROUP BY email
                    HAVING COUNT(*) > 1
                ) x"
            )
            : 0;
        if ($duplicateEmployeeEmails > 0) {
            $issues[] = [
                'type' => 'duplicate_employee_emails',
                'count' => $duplicateEmployeeEmails,
                'severity' => 'high',
            ];
        }

        $duplicateSkillPairs = $this->tableExists($skillsTable)
            ? $this->safeCount(
                "SELECT COUNT(*) FROM (
                    SELECT employee_id, skill_id, COUNT(*) c
                    FROM $skillsTable
                    GROUP BY employee_id, skill_id
                    HAVING COUNT(*) > 1
                ) x"
            )
            : 0;
        if ($duplicateSkillPairs > 0) {
            $issues[] = [
                'type' => 'duplicate_skill_pairs',
                'count' => $duplicateSkillPairs,
                'severity' => 'high',
            ];
        }
        $duplicateCertPairs = $this->tableExists($certsTable)
            ? $this->safeCount(
                "SELECT COUNT(*) FROM (
                    SELECT employee_id, certification_id, COUNT(*) c
                    FROM $certsTable
                    GROUP BY employee_id, certification_id
                    HAVING COUNT(*) > 1
                ) x"
            )
            : 0;
        if ($duplicateCertPairs > 0) {
            $issues[] = [
                'type' => 'duplicate_certification_pairs',
                'count' => $duplicateCertPairs,
                'severity' => 'high',
            ];
        }
        $orphanedTeamMemberships = ($this->tableExists($teamMembersTable) && $this->tableExists($employeesTable) && $this->tableExists($teamsTable))
            ? $this->safeCount(
                "SELECT COUNT(*) FROM $teamMembersTable tm
                 LEFT JOIN $employeesTable e ON e.id = tm.employee_id
                 LEFT JOIN $teamsTable t ON t.id = tm.team_id
                 WHERE e.id IS NULL OR t.id IS NULL"
            )
            : 0;
        if ($orphanedTeamMemberships > 0) {
            $issues[] = [
                'type' => 'orphaned_team_memberships',
                'count' => $orphanedTeamMemberships,
                'severity' => 'medium',
            ];
        }

        return $issues;
    }

    /**
     * @param string[] $runIds
     * @param array<int, array{run_id:string, created_at:?string, last_seen_at:?string, status:string, registry_row_count:int}> $activeRuns
     * @return array<string, mixed>
     */
    private function registrySummary(array $runIds, array $activeRuns): array
    {
        if (!$this->tableExists($this->registryTable)) {
            return [
                'total_registry_rows' => 0,
                'runs_count' => 0,
                'active_runs_count' => 0,
                'rows_linked_to_missing_entities' => 0,
            ];
        }
        $tableCandidates = [];
        $rawTables = $this->wpdb->get_col("SELECT DISTINCT table_name FROM $this->registryTable");
        if (is_array($rawTables)) {
            foreach ($rawTables as $tableName) {
                $tableName = (string)$tableName;
                if ($tableName === '' || !$this->tableExists($tableName) || !$this->tableHasColumn($tableName, 'id')) {
                    continue;
                }
                $tableCandidates[$tableName] = true;
            }
        }

        $rowsLinkedToMissingEntities = 0;
        foreach ($runIds as $runId) {
            if ($runId === '') {
                continue;
            }
            $rows = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT table_name, row_id
                 FROM $this->registryTable
                 WHERE seed_run_id = %s",
                [$runId]
            ), defined('ARRAY_A') ? ARRAY_A : 'ARRAY_A');

            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $tableName = (string)($row['table_name'] ?? '');
                $rowId = (string)($row['row_id'] ?? '');
                if ($tableName === '' || $rowId === '' || !isset($tableCandidates[$tableName])) {
                    continue;
                }

                $exists = $this->safeCount($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $tableName WHERE id = %s",
                    [$rowId]
                ));
                if ($exists <= 0) {
                    $rowsLinkedToMissingEntities++;
                }
            }
        }

        return [
            'total_registry_rows' => $this->safeCount("SELECT COUNT(*) FROM $this->registryTable"),
            'runs_count' => count(array_filter($runIds, static fn(string $id): bool => $id !== '')),
            'active_runs_count' => count($activeRuns),
            'rows_linked_to_missing_entities' => $rowsLinkedToMissingEntities,
        ];
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
        $cols = $this->wpdb->get_col("DESCRIBE $table", 0);
        return is_array($cols) && in_array($column, $cols, true);
    }

    private function safeCount(string $sql): int
    {
        $value = $this->wpdb->get_var($sql);
        return $value === null ? 0 : (int)$value;
    }
}

