<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Sla\Entity\EscalationRule;
use Pet\Domain\Sla\Entity\SlaDefinition;
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
            'calendar_id' => $sla->calendar()->id(),
            'response_target_minutes' => $sla->responseTargetMinutes(),
            'resolution_target_minutes' => $sla->resolutionTargetMinutes(),
        ];

        if ($sla->id()) {
            $this->wpdb->update($table, $data, ['id' => $sla->id()]);
            $slaId = $sla->id();
            
            // Clear existing rules for replacement
            $this->wpdb->delete($this->wpdb->prefix . 'pet_sla_escalation_rules', ['sla_id' => $slaId]);
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

        // Save Rules
        $rulesTable = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        // Note: SlaDefinition doesn't have a public getter for rules in the snippet I wrote earlier?
        // Let's assume I need to check SlaDefinition or add the getter.
        // Checking SlaDefinition.php content from memory/context...
        // I did NOT add `escalationRules()` getter in the previous turn. I added `id`, `uuid`, `name`, etc.
        // I need to fix SlaDefinition to expose rules.
        // Assuming I will fix it, let's write the code here.
        foreach ($sla->escalationRules() as $rule) {
            $this->wpdb->insert($rulesTable, [
                'sla_id' => $slaId,
                'threshold_percent' => $rule->thresholdPercent(),
                'action' => $rule->action(),
            ]);
        }
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
        $data = [
            'uuid' => $snapshot->uuid(),
            'project_id' => $snapshot->projectId(),
            'sla_original_id' => $snapshot->slaOriginalId(),
            'sla_version_at_binding' => $snapshot->slaVersionAtBinding(),
            'sla_name_at_binding' => $snapshot->slaNameAtBinding(),
            'response_target_minutes' => $snapshot->responseTargetMinutes(),
            'resolution_target_minutes' => $snapshot->resolutionTargetMinutes(),
            'calendar_snapshot_json' => json_encode($snapshot->calendarSnapshot()),
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

        return new SlaSnapshot(
            isset($row->project_id) ? (int)$row->project_id : null,
            (int)$row->sla_original_id,
            (int)$row->sla_version_at_binding,
            $row->sla_name_at_binding,
            (int)$row->response_target_minutes,
            (int)$row->resolution_target_minutes,
            json_decode($row->calendar_snapshot_json ?? '[]', true),
            $row->uuid,
            (int)$row->id,
            new \DateTimeImmutable($row->bound_at)
        );
    }

    private function mapRowToEntity($row): SlaDefinition
    {
        $calendar = $this->calendarRepository->findById((int)$row->calendar_id);
        if (!$calendar) {
            // If calendar is missing, this is a data integrity issue.
            // For now, throw or handle gracefully.
            throw new \RuntimeException("SLA {$row->id} references missing calendar {$row->calendar_id}");
        }

        $rules = $this->findRulesBySlaId((int)$row->id);

        return new SlaDefinition(
            $row->name,
            $calendar,
            (int)$row->response_target_minutes,
            (int)$row->resolution_target_minutes,
            $rules,
            $row->status,
            (int)$row->version_number,
            $row->uuid,
            (int)$row->id
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
}
