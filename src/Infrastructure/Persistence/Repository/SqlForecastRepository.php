<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Forecast;
use Pet\Domain\Commercial\Repository\ForecastRepository;
use wpdb;

class SqlForecastRepository implements ForecastRepository
{
    private wpdb $db;
    private string $tableName;

    public function __construct(wpdb $db)
    {
        $this->db = $db;
        $this->tableName = $db->prefix . 'pet_forecasts';
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        $charset_collate = $this->db->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) NOT NULL,
            total_value decimal(10,2) NOT NULL,
            probability decimal(5,2) NOT NULL,
            status varchar(20) NOT NULL,
            breakdown longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) $charset_collate;";

        if (!defined('ABSPATH')) {
            throw new \RuntimeException('ABSPATH not defined');
        }
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function save(Forecast $forecast): void
    {
        $data = [
            'quote_id' => $forecast->quoteId(),
            'total_value' => $forecast->totalValue(),
            'probability' => $forecast->probability(),
            'status' => $forecast->status(),
            'breakdown' => json_encode($forecast->breakdown()),
        ];

        $format = ['%d', '%f', '%f', '%s', '%s'];

        if ($forecast->id()) {
            $this->db->update($this->tableName, $data, ['id' => $forecast->id()], $format, ['%d']);
        } else {
            $this->db->insert($this->tableName, $data, $format);
            // We can't easily update the ID on the immutable entity without cloning, 
            // but for now we rely on re-fetching or not needing the ID immediately.
        }
    }

    public function findByQuoteId(int $quoteId): ?Forecast
    {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->tableName} WHERE quote_id = %d",
            $quoteId
        ));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $rows = $this->db->get_results("SELECT * FROM {$this->tableName}");
        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate($row): Forecast
    {
        return new Forecast(
            (int) $row->quote_id,
            (float) $row->total_value,
            (float) $row->probability,
            $row->status,
            json_decode($row->breakdown, true) ?: [],
            (int) $row->id
        );
    }
}
