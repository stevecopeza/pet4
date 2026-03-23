<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

final class CleanDemoBaselineService
{
    private DemoPurgeService $demoPurgeService;
    /** @var callable(string,string):array */
    private $seedRunner;
    private $wpdb;

    /**
     * @param callable(string,string):array $seedRunner
     */
    public function __construct(DemoPurgeService $demoPurgeService, callable $seedRunner, $wpdb)
    {
        $this->demoPurgeService = $demoPurgeService;
        $this->seedRunner = $seedRunner;
        $this->wpdb = $wpdb;
    }

    public function run(string $seedProfile = 'demo_full'): array
    {
        $purgeResult = $this->demoPurgeService->purgeAllActiveTrackedRuns();
        if (!$purgeResult['all_purges_succeeded']) {
            return [
                'operation' => 'clean_demo_baseline',
                'purge' => $purgeResult,
                'seed' => null,
                'counts' => $this->keyCounts(),
                'registry' => $this->trackingSummary(),
                'contract' => [
                    'violations' => ['purge_failed'],
                ],
                'overall' => 'FAIL',
                'error' => 'purge_failed',
            ];
        }

        $seedRunId = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('seed_', true);
        $seedSummary = ($this->seedRunner)($seedRunId, $seedProfile);
        $counts = $this->keyCounts();
        $registry = $this->trackingSummary();
        $contractViolations = $this->postBaselineContractViolations($seedRunId, $counts, $registry);

        return [
            'operation' => 'clean_demo_baseline',
            'purge' => $purgeResult,
            'seed' => [
                'seed_run_id' => $seedRunId,
                'seed_profile' => $seedProfile,
                'summary' => $seedSummary,
            ],
            'counts' => $counts,
            'registry' => $registry,
            'contract' => [
                'violations' => $contractViolations,
            ],
            'overall' => empty($contractViolations) ? 'PASS' : 'FAIL',
            'error' => empty($contractViolations) ? null : 'post_baseline_contract_violation',
        ];
    }

    private function keyCounts(): array
    {
        $p = $this->wpdb->prefix;
        return [
            'employees' => (int)$this->safeCount("{$p}pet_employees"),
            'person_skills' => (int)$this->safeCount("{$p}pet_person_skills"),
            'person_certifications' => (int)$this->safeCount("{$p}pet_person_certifications"),
            'customers' => (int)$this->safeCount("{$p}pet_customers"),
            'quotes' => (int)$this->safeCount("{$p}pet_quotes"),
            'projects' => (int)$this->safeCount("{$p}pet_projects"),
            'tickets' => (int)$this->safeCount("{$p}pet_tickets"),
            'time_entries' => (int)$this->safeCount("{$p}pet_time_entries"),
            'seed_registry_rows' => (int)$this->safeCount("{$p}pet_demo_seed_registry"),
        ];
    }

    private function trackingSummary(): array
    {
        $runs = $this->demoPurgeService->listTrackedSeedRuns(true);
        return [
            'active_seed_runs' => count($runs),
            'runs' => $runs,
            'registry_rows' => (int)$this->safeCount($this->wpdb->prefix . 'pet_demo_seed_registry'),
        ];
    }

    private function safeCount(string $table): int
    {
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        return (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * @param array<string, mixed> $counts
     * @param array<string, mixed> $registry
     * @return string[]
     */
    private function postBaselineContractViolations(string $expectedSeedRunId, array $counts, array $registry): array
    {
        $violations = [];
        $activeRuns = (int)($registry['active_seed_runs'] ?? 0);
        if ($activeRuns !== 1) {
            $violations[] = 'expected_single_active_run';
        }

        $runs = is_array($registry['runs'] ?? null) ? $registry['runs'] : [];
        $activeRunId = isset($runs[0]['seed_run_id']) ? (string)$runs[0]['seed_run_id'] : '';
        if ($activeRunId === '' || $activeRunId !== $expectedSeedRunId) {
            $violations[] = 'active_run_id_mismatch';
        }

        if ((int)($counts['seed_registry_rows'] ?? 0) <= 0) {
            $violations[] = 'missing_seed_registry_rows';
        }

        if ($this->duplicateSkillPairsCount() > 0) {
            $violations[] = 'duplicate_skill_pairs';
        }
        if ($this->duplicateCertificationPairsCount() > 0) {
            $violations[] = 'duplicate_certification_pairs';
        }
        if ($this->duplicateEmployeeEmailsCount() > 0) {
            $violations[] = 'duplicate_employee_emails';
        }

        return $violations;
    }

    private function duplicateSkillPairsCount(): int
    {
        $table = $this->wpdb->prefix . 'pet_person_skills';
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT employee_id, skill_id, COUNT(*) c
                FROM $table
                GROUP BY employee_id, skill_id
                HAVING COUNT(*) > 1
            ) x"
        );
    }

    private function duplicateCertificationPairsCount(): int
    {
        $table = $this->wpdb->prefix . 'pet_person_certifications';
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT employee_id, certification_id, COUNT(*) c
                FROM $table
                GROUP BY employee_id, certification_id
                HAVING COUNT(*) > 1
            ) x"
        );
    }

    private function duplicateEmployeeEmailsCount(): int
    {
        $table = $this->wpdb->prefix . 'pet_employees';
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT email, COUNT(*) c
                FROM $table
                WHERE email IS NOT NULL AND LENGTH(email) > 0
                GROUP BY email
                HAVING COUNT(*) > 1
            ) x"
        );
    }

    private function tableExists(string $table): bool
    {
        return $this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }
}

