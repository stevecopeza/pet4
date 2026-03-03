<?php

declare(strict_types=1);

namespace Pet\Application\Sla\Service;

use Pet\Domain\Work\Entity\KpiDefinition;
use Pet\Domain\Work\Repository\KpiDefinitionRepository;

/**
 * Seeds KPI definitions for tiered SLA metrics.
 * Idempotent — checks for existing definitions before creating.
 */
class TieredSlaKpiSeeder
{
    private KpiDefinitionRepository $kpiRepo;

    public function __construct(KpiDefinitionRepository $kpiRepo)
    {
        $this->kpiRepo = $kpiRepo;
    }

    /**
     * Seed tiered SLA KPI definitions if they don't already exist.
     */
    public function seed(): void
    {
        $definitions = [
            [
                'name' => 'SLA Breach Count by Tier',
                'description' => 'Number of SLA breaches grouped by tier priority. Tracks which time-band tiers are experiencing the most breaches.',
                'frequency' => 'monthly',
                'unit' => 'count',
            ],
            [
                'name' => 'Tier Transition Count',
                'description' => 'Total number of tier transitions (automatic + manual) across all tickets. High counts may indicate SLA boundaries are too tight.',
                'frequency' => 'monthly',
                'unit' => 'count',
            ],
            [
                'name' => 'Response Time Compliance by Tier',
                'description' => 'Percentage of tickets meeting response SLA target within each tier. Measures per-tier effectiveness.',
                'frequency' => 'monthly',
                'unit' => '%',
            ],
            [
                'name' => 'Resolution Time Compliance by Tier',
                'description' => 'Percentage of tickets meeting resolution SLA target within each tier.',
                'frequency' => 'monthly',
                'unit' => '%',
            ],
            [
                'name' => 'Average Carry-Forward Percentage',
                'description' => 'Average carried-forward percentage at tier transitions. Values consistently near the cap may indicate workload distribution issues.',
                'frequency' => 'monthly',
                'unit' => '%',
            ],
            [
                'name' => 'Manual Tier Override Count',
                'description' => 'Number of manual tier overrides performed. Tracks governance and exception handling.',
                'frequency' => 'monthly',
                'unit' => 'count',
            ],
        ];

        $existing = $this->kpiRepo->findAll();
        $existingNames = array_map(fn(KpiDefinition $k) => $k->name(), $existing);

        foreach ($definitions as $def) {
            if (in_array($def['name'], $existingNames, true)) {
                continue;
            }

            $kpi = new KpiDefinition(
                $def['name'],
                $def['description'],
                $def['frequency'],
                $def['unit']
            );

            $this->kpiRepo->save($kpi);
        }
    }
}
