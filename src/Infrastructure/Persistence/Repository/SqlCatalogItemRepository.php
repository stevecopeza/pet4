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

    public function findBySku(string $sku): ?CatalogItem
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}pet_catalog_items WHERE sku = %s LIMIT 1",
                $sku
            )
        );

        return $row ? $this->mapRowToEntity($row) : null;
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
            $this->wpdb->insert(
                $this->wpdb->prefix . 'pet_catalog_items',
                $data
            );
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
