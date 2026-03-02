<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\CatalogItem;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;

class SqlCatalogItemRepository implements CatalogItemRepository
{
    private $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function findById(int $id): ?CatalogItem
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}pet_catalog_items WHERE id = %d",
                $id
            )
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findAll(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}pet_catalog_items ORDER BY name ASC"
        );

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function save(CatalogItem $item): void
    {
        $data = [
            'sku' => $item->sku(),
            'name' => $item->name(),
            'type' => $item->type(),
            'description' => $item->description(),
            'category' => $item->category(),
            'wbs_template' => json_encode($item->wbsTemplate()),
            'unit_price' => $item->unitPrice(),
            'unit_cost' => $item->unitCost(),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        if ($item->id()) {
            $this->wpdb->update(
                $this->wpdb->prefix . 'pet_catalog_items',
                $data,
                ['id' => $item->id()]
            );
        } else {
            $data['created_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $table = $this->wpdb->prefix . 'pet_catalog_items';
            $sql = "
                INSERT INTO $table (sku, name, type, description, category, wbs_template, unit_price, unit_cost, updated_at, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, %f, %f, %s, %s)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    type = VALUES(type),
                    description = VALUES(description),
                    category = VALUES(category),
                    wbs_template = VALUES(wbs_template),
                    unit_price = VALUES(unit_price),
                    unit_cost = VALUES(unit_cost),
                    updated_at = VALUES(updated_at)
            ";
            $this->wpdb->query($this->wpdb->prepare(
                $sql,
                $data['sku'],
                $data['name'],
                $data['type'],
                $data['description'],
                $data['category'],
                $data['wbs_template'],
                $data['unit_price'],
                $data['unit_cost'],
                $data['updated_at'],
                $data['created_at']
            ));
        }
    }

    public function delete(int $id): void
    {
        $this->wpdb->delete(
            $this->wpdb->prefix . 'pet_catalog_items',
            ['id' => $id]
        );
    }

    private function mapRowToEntity(object $row): CatalogItem
    {
        return new CatalogItem(
            $row->name,
            (float) $row->unit_price,
            (float) $row->unit_cost,
            $row->type ?? 'product',
            $row->sku,
            $row->description,
            $row->category,
            json_decode($row->wbs_template ?? '[]', true) ?: [],
            (int) $row->id,
            new \DateTimeImmutable($row->created_at),
            new \DateTimeImmutable($row->updated_at)
        );
    }
}
