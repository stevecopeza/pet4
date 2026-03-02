<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Pet\Domain\Commercial\Entity\CostAdjustment;
use Pet\Domain\Commercial\Repository\CostAdjustmentRepository;
use RuntimeException;

class SqlCostAdjustmentRepository implements CostAdjustmentRepository
{
    private \wpdb $db;
    private string $table;

    public function __construct(?\wpdb $db = null)
    {
        global $wpdb;
        $this->db = $db ?? $wpdb;
        $this->table = $this->db->prefix . 'pet_cost_adjustments';
    }

    public function save(CostAdjustment $adjustment): void
    {
        $data = [
            'quote_id' => $adjustment->quoteId(),
            'description' => $adjustment->description(),
            'amount' => $adjustment->amount(),
            'reason' => $adjustment->reason(),
            'approved_by' => $adjustment->approvedBy(),
            'applied_at' => $adjustment->appliedAt()->format('Y-m-d H:i:s'),
        ];

        if ($adjustment->id()) {
            $this->db->update($this->table, $data, ['id' => $adjustment->id()]);
        } else {
            $result = $this->db->insert($this->table, $data);
            if ($result === false) {
                throw new RuntimeException("Failed to save cost adjustment: " . $this->db->last_error);
            }
        }
    }

    public function findByQuoteId(int $quoteId): array
    {
        $results = $this->db->get_results(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE quote_id = %d", $quoteId)
        );

        return array_map(function ($row) {
            return new CostAdjustment(
                (int) $row->quote_id,
                $row->description,
                (float) $row->amount,
                $row->reason,
                $row->approved_by,
                (int) $row->id,
                new DateTimeImmutable($row->applied_at)
            );
        }, $results);
    }

    public function delete(int $id): void
    {
        $this->db->delete($this->table, ['id' => $id], ['%d']);
    }

    public function createTable(): void
    {
        $charset_collate = $this->db->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) NOT NULL,
            description varchar(255) NOT NULL,
            amount decimal(10, 2) NOT NULL,
            reason text NOT NULL,
            approved_by varchar(100) NOT NULL,
            applied_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
