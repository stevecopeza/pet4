<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Sla\Entity\EscalationRule;
use Pet\Domain\Sla\Repository\EscalationRuleRepository;

class SqlEscalationRuleRepository implements EscalationRuleRepository
{
    private $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $table = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        $sql = "SELECT * FROM $table LIMIT %d OFFSET %d";
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $limit, $offset));

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findById(int $id): ?EscalationRule
    {
        $table = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        $sql = "SELECT * FROM $table WHERE id = %d";
        $row = $this->wpdb->get_row($this->wpdb->prepare($sql, $id));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function save(EscalationRule $rule, ?int $slaId = null): void
    {
        $table = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        $data = [
            'threshold_percent' => $rule->thresholdPercent(),
            'action' => $rule->action(),
            'criteria_json' => $rule->criteriaJson(),
            'is_enabled' => $rule->isEnabled() ? 1 : 0,
        ];

        if ($rule->id()) {
            $this->wpdb->update($table, $data, ['id' => $rule->id()]);
        } else {
            if ($slaId === null) {
                throw new \InvalidArgumentException('SLA ID is required for creating a new escalation rule.');
            }
            $data['sla_id'] = $slaId;
            $this->wpdb->insert($table, $data);
            
            $insertId = $this->wpdb->insert_id;
            if ($insertId) {
                $ref = new \ReflectionObject($rule);
                if ($ref->hasProperty('id')) {
                    $prop = $ref->getProperty('id');
                    $prop->setAccessible(true);
                    $prop->setValue($rule, (int)$insertId);
                }
            }
        }
    }

    public function enable(int $id): void
    {
        $table = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        $this->wpdb->update($table, ['is_enabled' => 1], ['id' => $id]);
    }

    public function disable(int $id): void
    {
        $table = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        $this->wpdb->update($table, ['is_enabled' => 0], ['id' => $id]);
    }

    public function getDashboardStats(): array
    {
        $table = $this->wpdb->prefix . 'pet_sla_escalation_rules';
        
        $total = $this->wpdb->get_var("SELECT COUNT(*) FROM $table");
        $enabled = $this->wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_enabled = 1");
        
        return [
            'totalCount' => (int)$total,
            'enabledCount' => (int)$enabled,
        ];
    }

    private function mapRowToEntity($row): EscalationRule
    {
        return new EscalationRule(
            (int)$row->threshold_percent,
            $row->action,
            (int)$row->id,
            $row->criteria_json ?? '{}',
            (bool)($row->is_enabled ?? true)
        );
    }
}
