<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\Certification;
use Pet\Domain\Work\Repository\CertificationRepository;

class SqlCertificationRepository implements CertificationRepository
{
    private \wpdb $db;
    private string $table;

    public function __construct(\wpdb $db)
    {
        $this->db = $db;
        $this->table = $db->prefix . 'pet_certifications';
    }

    public function save(Certification $certification): void
    {
        $data = [
            'name' => $certification->name(),
            'issuing_body' => $certification->issuingBody(),
            'expiry_months' => $certification->expiryMonths(),
            'status' => $certification->status(),
        ];

        $format = ['%s', '%s', '%d', '%s'];

        if ($certification->id()) {
            $this->db->update($this->table, $data, ['id' => $certification->id()], $format, ['%d']);
        } else {
            $data['created_at'] = $certification->createdAt()->format('Y-m-d H:i:s');
            $format[] = '%s';
            $this->db->insert($this->table, $data, $format);
        }
    }

    public function findById(int $id): ?Certification
    {
        $row = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findAll(): array
    {
        $rows = $this->db->get_results("SELECT * FROM {$this->table} ORDER BY name ASC");

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    private function mapRowToEntity(object $row): Certification
    {
        return new Certification(
            $row->name,
            $row->issuing_body,
            (int)$row->expiry_months,
            $row->status,
            (int)$row->id,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
