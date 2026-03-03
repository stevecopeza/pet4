<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Sla\Entity\EscalationRule;
use Pet\Domain\Sla\Entity\SlaDefinition;
use Pet\Domain\Sla\Entity\SlaTier;
use Pet\Domain\Sla\Entity\SlaSnapshot;
use Pet\Domain\Sla\Repository\SlaRepository;
use Pet\Domain\Calendar\Repository\CalendarRepository;

class SqlSlaRepository implements SlaRepository
{
    private $wpdb;
    private CalendarRepository $calendarRepository;

    public function __construct(\wpdb $wpdb, CalendarRepository $calendarRepository)
    {
        $this->wpdb = $wpdb;
        $this->calendarRepository = $calendarRepository;
    }

    public function save(SlaDefinition $sla): void
    {
        $table = $this->wpdb->prefix . 'pet_slas';
        $data = [
            'uuid' => $sla->uuid(),
            'name' => $sla->name(),
            'status' => $sla->status(),
            'version_number' => $sla->versionNumber(),
            'calendar_id' => $sla->calendar() ? $sla->calendar()->id() : null,
            'response_target_minutes' => $sla->responseTargetMinutes(),
            'resolution_target_minutes' => $sla->resolutionTargetMinutes(),
            'tier_transition_cap_percent' => $sla->tierTransitionCapPercent(),
        ];

        if ($sla->id()) {
            $this->wpdb->update($table, $data, ['id' => $sla->id()]);
            $slaId = $sla->id();
            
            // Clear existing rules for replacement
            $this->wpdb->delete($this->wpdb->prefix . 'pet_sla_escalation_rules', ['sla_id' => $slaId]);
            // Clear existing tiers + tier rules for replacement
            $this->deleteTiersBySlaId($slaId);
        } else {
            $this->wpdb->insert($table, $data);
            $slaId = $this->wpdb->insert_id;
            
            // Set generated ID back on the entity
            if ($slaId) {
                $ref = new \ReflectionObject($sla);
                if ($ref->hasProperty('id')) {
                    $prop = $ref->getProperty('id');
                    $prop->setAccessible(true);
                    $prop->setValue($sla, (int)$slaId);
                }
            }
        }

        // Save escalation rules (single-tier mode)
        $rulesTable = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        foreach ($sla->escalationRules() as $rule) {
            $this->wpdb->insert($rulesTable, [
                'sla_id' => $slaId,
                'threshold_percent' => $rule->thresholdPercent(),
                'action' => $rule->action(),
            ]);
        }

        // Save tiers (tiered mode)
        if ($sla->isTiered()) {
            $this->saveTiers($slaId, $sla->tiers());
        }
    }

    /**
     * Persist tiers and their escalation rules.
     * @param SlaTier[] $tiers
     */
    private function saveTiers(int $slaId, array $tiers): void
    {
        $tiersTable = $this->wpdb->prefix . 'pet_sla_tiers';
        $tierRulesTable = $this->wpdb->prefix . 'pet_sla_tier_escalation_rules';

        foreach ($tiers as $tier) {
            $this->wpdb->insert($tiersTable, [
                'sla_id' => $slaId,
                'priority' => $tier->priority(),
                'label' => $tier->label(),
                'calendar_id' => $tier->calendarId(),
                'response_target_minutes' => $tier->responseTargetMinutes(),
                'resolution_target_minutes' => $tier->resolutionTargetMinutes(),
            ]);
            $tierId = $this->wpdb->insert_id;

            foreach ($tier->escalationRules() as $rule) {
                $this->wpdb->insert($tierRulesTable, [
                    'sla_tier_id' => $tierId,
                    'threshold_percent' => $rule->thresholdPercent(),
                    'action' => $rule->action(),
                ]);
            }
        }
    }

    private function deleteTiersBySlaId(int $slaId): void
    {
        $tiersTable = $this->wpdb->prefix . 'pet_sla_tiers';
        $tierRulesTable = $this->wpdb->prefix . 'pet_sla_tier_escalation_rules';

        // Delete tier rules first (child rows)
        $tierIds = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM $tiersTable WHERE sla_id = %d",
            $slaId
        ));
        if (!empty($tierIds)) {
            $placeholders = implode(',', array_fill(0, count($tierIds), '%d'));
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM $tierRulesTable WHERE sla_tier_id IN ($placeholders)",
                ...$tierIds
            ));
        }

        $this->wpdb->delete($tiersTable, ['sla_id' => $slaId]);
    }

    public function findById(int $id): ?SlaDefinition
    {
        $table = $this->wpdb->prefix . 'pet_slas';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByUuid(string $uuid): ?SlaDefinition
    {
        $table = $this->wpdb->prefix . 'pet_slas';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE uuid = %s", $uuid));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_slas';
        $rows = $this->wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function delete(int $id): void
    {
        $this->wpdb->delete($this->wpdb->prefix . 'pet_slas', ['id' => $id]);
    }

    public function saveSnapshot(SlaSnapshot $snapshot): int
    {
        $table = $this->wpdb->prefix . 'pet_contract_sla_snapshots';

        $calendarJson = $snapshot->calendarSnapshot();
        if ($snapshot->isTiered()) {
            // Encode tier snapshots into the calendar JSON field for backward-compatible storage
            $calendarJson = [
                '_tiered' => true,
                'tier_snapshots' => $snapshot->tierSnapshots(),
                'tier_transition_cap_percent' => $snapshot->tierTransitionCapPercent(),
            ];
        }

        $data = [
            'uuid' => $snapshot->uuid(),
            'project_id' => $snapshot->projectId(),
            'sla_original_id' => $snapshot->slaOriginalId(),
            'sla_version_at_binding' => $snapshot->slaVersionAtBinding(),
            'sla_name_at_binding' => $snapshot->slaNameAtBinding(),
            'response_target_minutes' => $snapshot->responseTargetMinutes(),
            'resolution_target_minutes' => $snapshot->resolutionTargetMinutes(),
            'calendar_snapshot_json' => json_encode($calendarJson),
            'bound_at' => $snapshot->boundAt()->format('Y-m-d H:i:s'),
        ];

        if ($snapshot->id()) {
            $this->wpdb->update($table, $data, ['id' => $snapshot->id()]);
            return $snapshot->id();
        } else {
            $this->wpdb->insert($table, $data);
            return $this->wpdb->insert_id;
        }
    }

    public function findSnapshotById(int $id): ?SlaSnapshot
    {
        $table = $this->wpdb->prefix . 'pet_contract_sla_snapshots';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        $calendarData = json_decode($row->calendar_snapshot_json ?? '[]', true);
        $tierSnapshots = null;
        $tierTransitionCapPercent = null;

        if (!empty($calendarData['_tiered'])) {
            // Tiered snapshot stored in the calendar JSON field
            $tierSnapshots = $calendarData['tier_snapshots'] ?? [];
            $tierTransitionCapPercent = $calendarData['tier_transition_cap_percent'] ?? 80;
            $calendarData = []; // Clear calendar data for tiered
        }

        return new SlaSnapshot(
            isset($row->project_id) ? (int)$row->project_id : null,
            (int)$row->sla_original_id,
            (int)$row->sla_version_at_binding,
            $row->sla_name_at_binding,
            $row->response_target_minutes !== null ? (int)$row->response_target_minutes : null,
            $row->resolution_target_minutes !== null ? (int)$row->resolution_target_minutes : null,
            $calendarData,
            $row->uuid,
            (int)$row->id,
            new \DateTimeImmutable($row->bound_at),
            $tierSnapshots,
            $tierTransitionCapPercent
        );
    }

    private function mapRowToEntity($row): SlaDefinition
    {
        $slaId = (int)$row->id;
        $rules = $this->findRulesBySlaId($slaId);
        $tiers = $this->findTiersBySlaId($slaId);

        // In tiered mode, calendar_id may be null
        $calendar = null;
        if ($row->calendar_id) {
            $calendar = $this->calendarRepository->findById((int)$row->calendar_id);
            if (!$calendar) {
                throw new \RuntimeException("SLA {$row->id} references missing calendar {$row->calendar_id}");
            }
        }

        return new SlaDefinition(
            $row->name,
            $calendar,
            $row->response_target_minutes !== null ? (int)$row->response_target_minutes : null,
            $row->resolution_target_minutes !== null ? (int)$row->resolution_target_minutes : null,
            $rules,
            $row->status,
            (int)$row->version_number,
            $row->uuid,
            $slaId,
            $tiers,
            (int)($row->tier_transition_cap_percent ?? 80)
        );
    }

    private function findRulesBySlaId(int $slaId): array
    {
        $table = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $table WHERE sla_id = %d", $slaId));

        return array_map(function ($row) {
            return new EscalationRule(
                (int)$row->threshold_percent,
                $row->action,
                (int)$row->id
            );
        }, $rows);
    }

    /**
     * @return SlaTier[]
     */
    private function findTiersBySlaId(int $slaId): array
    {
        $tiersTable = $this->wpdb->prefix . 'pet_sla_tiers';
        $tierRulesTable = $this->wpdb->prefix . 'pet_sla_tier_escalation_rules';

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $tiersTable WHERE sla_id = %d ORDER BY priority ASC",
            $slaId
        ));

        $tiers = [];
        foreach ($rows as $row) {
            $tierId = (int)$row->id;
            $ruleRows = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM $tierRulesTable WHERE sla_tier_id = %d",
                $tierId
            ));

            $tierRules = array_map(function ($ruleRow) {
                return new EscalationRule(
                    (int)$ruleRow->threshold_percent,
                    $ruleRow->action,
                    (int)$ruleRow->id
                );
            }, $ruleRows);

            $tiers[] = new SlaTier(
                (int)$row->priority,
                $row->label ?? '',
                (int)$row->calendar_id,
                (int)$row->response_target_minutes,
                (int)$row->resolution_target_minutes,
                $tierRules,
                $tierId
            );
        }

        return $tiers;
    }
}
