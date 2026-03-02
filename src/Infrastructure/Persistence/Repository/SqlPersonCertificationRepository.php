<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\PersonCertification;
use Pet\Domain\Work\Repository\PersonCertificationRepository;

class SqlPersonCertificationRepository implements PersonCertificationRepository
{
    private \wpdb $db;
    private string $table;

    public function __construct(\wpdb $db)
    {
        $this->db = $db;
        $this->table = $db->prefix . 'pet_person_certifications';
    }

    public function save(PersonCertification $personCertification): void
    {
        $data = [
            'employee_id' => $personCertification->employeeId(),
            'certification_id' => $personCertification->certificationId(),
            'obtained_date' => $personCertification->obtainedDate()->format('Y-m-d'),
            'expiry_date' => $personCertification->expiryDate() ? $personCertification->expiryDate()->format('Y-m-d') : null,
            'evidence_url' => $personCertification->evidenceUrl(),
            'status' => $personCertification->status(),
        ];

        $format = ['%d', '%d', '%s', '%s', '%s', '%s'];

        if ($personCertification->id()) {
            $this->db->update($this->table, $data, ['id' => $personCertification->id()], $format, ['%d']);
        } else {
            $data['created_at'] = $personCertification->createdAt()->format('Y-m-d H:i:s');
            $format[] = '%s';
            $this->db->insert($this->table, $data, $format);
        }
    }

    public function findByEmployeeId(int $employeeId): array
    {
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT pc.*, c.name as certification_name, c.issuing_body 
                 FROM {$this->table} pc 
                 JOIN {$this->db->prefix}pet_certifications c ON pc.certification_id = c.id 
                 WHERE pc.employee_id = %d 
                 ORDER BY pc.obtained_date DESC",
                $employeeId
            )
        );

        // Note: The entity doesn't have certification_name, that's for display.
        // But for repository purity we return entities.
        // We might need a separate read model or just load basic entity.
        // For now, let's return the basic entity.
        // Wait, if I want to display the name in the UI list, I usually need it.
        // The Entity PersonCertification strictly maps to the table.
        // I might need a DTO or just fetch the certification separately, or enrich it.
        // Let's stick to the Entity for now and let the Controller/Service handle enrichment if needed,
        // OR add a transient property to the entity (but that's not pure).
        // Actually, for the UI list, I will likely return a DTO from the controller.
        
        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    private function mapRowToEntity(object $row): PersonCertification
    {
        return new PersonCertification(
            (int)$row->employee_id,
            (int)$row->certification_id,
            new \DateTimeImmutable($row->obtained_date),
            $row->expiry_date ? new \DateTimeImmutable($row->expiry_date) : null,
            $row->evidence_url,
            $row->status,
            (int)$row->id,
            new \DateTimeImmutable($row->created_at)
        );
    }
}
