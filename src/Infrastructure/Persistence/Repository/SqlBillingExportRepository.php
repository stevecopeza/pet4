<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Finance\Entity\BillingExport;
use Pet\Domain\Finance\Entity\BillingExportItem;
use Pet\Domain\Finance\Repository\BillingExportRepository;

class SqlBillingExportRepository implements BillingExportRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(BillingExport $export): void
    {
        $table = $this->wpdb->prefix . 'pet_billing_exports';
        if ($export->id() === 0) {
            $this->wpdb->insert($table, [
                'uuid' => $export->uuid(),
                'customer_id' => $export->customerId(),
                'period_start' => $export->periodStart()->format('Y-m-d'),
                'period_end' => $export->periodEnd()->format('Y-m-d'),
                'status' => $export->status(),
                'created_by_employee_id' => $export->createdByEmployeeId(),
                'created_at' => $export->createdAt()->format('Y-m-d H:i:s'),
                'updated_at' => $export->updatedAt()->format('Y-m-d H:i:s'),
            ]);
            $export->setId((int)$this->wpdb->insert_id);
        } else {
            $export->touch();
            $this->wpdb->update($table, [
                'status' => $export->status(),
                'updated_at' => $export->updatedAt()->format('Y-m-d H:i:s'),
            ], ['id' => $export->id()]);
        }
    }

    public function findById(int $id): ?BillingExport
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}pet_billing_exports WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        if (!$row) return null;
        return BillingExport::fromStorage(
            (int)$row['id'],
            $row['uuid'],
            (int)$row['customer_id'],
            new \DateTimeImmutable($row['period_start']),
            new \DateTimeImmutable($row['period_end']),
            $row['status'],
            (int)$row['created_by_employee_id'],
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at'])
        );
    }

    public function findAll(int $limit = 50): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}pet_billing_exports ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = BillingExport::fromStorage(
                (int)$row['id'],
                $row['uuid'],
                (int)$row['customer_id'],
                new \DateTimeImmutable($row['period_start']),
                new \DateTimeImmutable($row['period_end']),
                $row['status'],
                (int)$row['created_by_employee_id'],
                new \DateTimeImmutable($row['created_at']),
                new \DateTimeImmutable($row['updated_at'])
            );
        }
        return $out;
    }

    public function addItem(BillingExportItem $item): void
    {
        $table = $this->wpdb->prefix . 'pet_billing_export_items';
        $this->wpdb->insert($table, [
            'export_id' => $item->exportId(),
            'source_type' => $item->sourceType(),
            'source_id' => $item->sourceId(),
            'quantity' => $item->quantity(),
            'unit_price' => $item->unitPrice(),
            'amount' => $item->amount(),
            'description' => $item->description(),
            'qb_item_ref' => $item->qbItemRef(),
            'status' => $item->status(),
            'created_at' => $item->createdAt()->format('Y-m-d H:i:s'),
        ]);
        $item->setId((int)$this->wpdb->insert_id);
    }

    public function findItems(int $exportId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}pet_billing_export_items WHERE export_id = %d ORDER BY id ASC",
                $exportId
            ),
            ARRAY_A
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = new BillingExportItem(
                (int)$row['id'],
                (int)$row['export_id'],
                $row['source_type'],
                (int)$row['source_id'],
                (float)$row['quantity'],
                (float)$row['unit_price'],
                (float)$row['amount'],
                $row['description'],
                $row['qb_item_ref'] ?: null,
                $row['status'],
                new \DateTimeImmutable($row['created_at'])
            );
        }
        return $out;
    }

    public function setStatus(int $exportId, string $status): void
    {
        $this->wpdb->update(
            $this->wpdb->prefix . 'pet_billing_exports',
            ['status' => $status, 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $exportId]
        );
    }

    public function sumItemsTotal(int $exportId): float
    {
        $table = $this->wpdb->prefix . 'pet_billing_export_items';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT SUM(amount) AS total FROM $table WHERE export_id = %d", $exportId),
            ARRAY_A
        );
        $total = $row && $row['total'] !== null ? (float)$row['total'] : 0.0;
        return round($total, 2);
    }
}
