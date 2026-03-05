<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\CatalogProduct;
use Pet\Domain\Commercial\Repository\CatalogProductRepository;

class SqlCatalogProductRepository implements CatalogProductRepository
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'pet_catalog_products';
    }

    public function findById(int $id): ?CatalogProduct
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $rows = $this->wpdb->get_results("SELECT * FROM {$this->table} ORDER BY name ASC");
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findActive(): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE status = %s ORDER BY name ASC", 'active')
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(CatalogProduct $product): void
    {
        $data = [
            'sku' => $product->sku(),
            'name' => $product->name(),
            'description' => $product->description(),
            'category' => $product->category(),
            'unit_price' => $product->unitPrice(),
            'unit_cost' => $product->unitCost(),
            'status' => $product->status(),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        if ($product->id()) {
            $this->wpdb->update(
                $this->table,
                $data,
                ['id' => $product->id()],
                ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s'],
                ['%d']
            );
        } else {
            $data['created_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $sql = "INSERT INTO {$this->table} (sku, name, description, category, unit_price, unit_cost, status, updated_at, created_at)
                    VALUES (%s, %s, %s, %s, %f, %f, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        description = VALUES(description),
                        category = VALUES(category),
                        unit_price = VALUES(unit_price),
                        unit_cost = VALUES(unit_cost),
                        status = VALUES(status),
                        updated_at = VALUES(updated_at)";

            $this->wpdb->query($this->wpdb->prepare(
                $sql,
                $data['sku'],
                $data['name'],
                $data['description'],
                $data['category'],
                $data['unit_price'],
                $data['unit_cost'],
                $data['status'],
                $data['updated_at'],
                $data['created_at']
            ));

            $id = (int)$this->wpdb->insert_id;
            if ($id > 0) {
                $ref = new \ReflectionObject($product);
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                $prop->setValue($product, $id);
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

    private function hydrate(object $row): CatalogProduct
    {
        return new CatalogProduct(
            $row->sku,
            $row->name,
            (float)$row->unit_price,
            (float)$row->unit_cost,
            $row->description,
            $row->category,
            $row->status,
            (int)$row->id,
            new \DateTimeImmutable($row->created_at),
            new \DateTimeImmutable($row->updated_at)
        );
    }
}
