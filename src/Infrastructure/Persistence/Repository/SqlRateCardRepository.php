<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\RateCard;
use Pet\Domain\Commercial\Repository\RateCardRepository;

class SqlRateCardRepository implements RateCardRepository
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'pet_rate_cards';
    }

    public function findById(int $id): ?RateCard
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (isset($filters['role_id'])) {
            $where .= ' AND role_id = %d';
            $params[] = (int)$filters['role_id'];
        }
        if (isset($filters['service_type_id'])) {
            $where .= ' AND service_type_id = %d';
            $params[] = (int)$filters['service_type_id'];
        }
        if (isset($filters['contract_id'])) {
            $where .= ' AND contract_id = %d';
            $params[] = (int)$filters['contract_id'];
        }
        if (isset($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }

        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY role_id, service_type_id, valid_from ASC";
        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        $rows = $this->wpdb->get_results($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(RateCard $rateCard): void
    {
        $data = [
            'role_id' => $rateCard->roleId(),
            'service_type_id' => $rateCard->serviceTypeId(),
            'sell_rate' => $rateCard->sellRate(),
            'contract_id' => $rateCard->contractId(),
            'valid_from' => $rateCard->validFrom() ? $rateCard->validFrom()->format('Y-m-d') : null,
            'valid_to' => $rateCard->validTo() ? $rateCard->validTo()->format('Y-m-d') : null,
            'status' => $rateCard->status(),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $format = ['%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s'];

        if ($rateCard->id()) {
            $this->wpdb->update($this->table, $data, ['id' => $rateCard->id()], $format, ['%d']);
        } else {
            $data['created_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->wpdb->insert($this->table, $data, array_merge($format, ['%s']));

            $id = (int)$this->wpdb->insert_id;
            if ($id > 0) {
                $ref = new \ReflectionObject($rateCard);
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                $prop->setValue($rateCard, $id);
            }
        }
    }

    public function archive(int $id): void
    {
        $this->wpdb->update(
            $this->table,
            ['status' => 'archived', 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Two ranges overlap when:  start1 < end2 AND start2 < end1
     * With NULLs: NULL start = -infinity, NULL end = +infinity
     * So overlap condition becomes:
     *   (existing.valid_from IS NULL OR :newValidTo IS NULL OR existing.valid_from <= :newValidTo)
     *   AND (existing.valid_to IS NULL OR :newValidFrom IS NULL OR existing.valid_to >= :newValidFrom)
     */
    public function findOverlapping(
        int $roleId,
        int $serviceTypeId,
        ?int $contractId,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validTo,
        ?int $excludeId = null
    ): array {
        $conditions = [
            'role_id = %d',
            'service_type_id = %d',
            "status = 'active'",
        ];
        $params = [$roleId, $serviceTypeId];

        // Contract scope match (NULL = global)
        if ($contractId !== null) {
            $conditions[] = 'contract_id = %d';
            $params[] = $contractId;
        } else {
            $conditions[] = 'contract_id IS NULL';
        }

        // Overlap logic with open-ended ranges
        if ($validTo !== null) {
            $conditions[] = '(valid_from IS NULL OR valid_from <= %s)';
            $params[] = $validTo->format('Y-m-d');
        }
        // else new validTo is NULL (open end), so any existing valid_from satisfies

        if ($validFrom !== null) {
            $conditions[] = '(valid_to IS NULL OR valid_to >= %s)';
            $params[] = $validFrom->format('Y-m-d');
        }
        // else new validFrom is NULL (open start), so any existing valid_to satisfies

        if ($excludeId !== null) {
            $conditions[] = 'id != %d';
            $params[] = $excludeId;
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$where}";
        $sql = $this->wpdb->prepare($sql, ...$params);

        $rows = $this->wpdb->get_results($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findForResolution(
        int $roleId,
        int $serviceTypeId,
        ?int $contractId,
        \DateTimeImmutable $effectiveDate
    ): ?RateCard {
        $dateStr = $effectiveDate->format('Y-m-d');

        $conditions = [
            'role_id = %d',
            'service_type_id = %d',
            "status = 'active'",
            '(valid_from IS NULL OR valid_from <= %s)',
            '(valid_to IS NULL OR valid_to >= %s)',
        ];
        $params = [$roleId, $serviceTypeId, $dateStr, $dateStr];

        if ($contractId !== null) {
            $conditions[] = 'contract_id = %d';
            $params[] = $contractId;
        } else {
            $conditions[] = 'contract_id IS NULL';
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY valid_from DESC LIMIT 1";
        $sql = $this->wpdb->prepare($sql, ...$params);

        $row = $this->wpdb->get_row($sql);
        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(object $row): RateCard
    {
        return new RateCard(
            (int)$row->role_id,
            (int)$row->service_type_id,
            (float)$row->sell_rate,
            isset($row->contract_id) && $row->contract_id ? (int)$row->contract_id : null,
            $row->valid_from ? new \DateTimeImmutable($row->valid_from) : null,
            $row->valid_to ? new \DateTimeImmutable($row->valid_to) : null,
            $row->status,
            (int)$row->id,
            new \DateTimeImmutable($row->created_at),
            new \DateTimeImmutable($row->updated_at)
        );
    }
}
