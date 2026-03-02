<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;

class SqlTimeEntryRepository implements TimeEntryRepository
{
    private \wpdb $db;
    private string $table;
    private bool $hasMalleableColumn = false;
    private bool $hasArchivedColumn = false;

    public function __construct(\wpdb $db)
    {
        $this->db = $db;
        $this->table = $db->prefix . 'pet_time_entries';
        $this->hasMalleableColumn = ($this->db->get_var("SHOW COLUMNS FROM {$this->table} LIKE 'malleable_data'") !== null);
        $this->hasArchivedColumn = ($this->db->get_var("SHOW COLUMNS FROM {$this->table} LIKE 'archived_at'") !== null);
    }

    public function save(TimeEntry $timeEntry): void
    {
        $data = [
            'employee_id' => $timeEntry->employeeId(),
            'ticket_id' => $timeEntry->ticketId(),
            'start_time' => $timeEntry->start()->format('Y-m-d H:i:s'),
            'end_time' => $timeEntry->end()->format('Y-m-d H:i:s'),
            'duration_minutes' => $timeEntry->durationMinutes(),
            'is_billable' => $timeEntry->isBillable() ? 1 : 0,
            'description' => $timeEntry->description(),
            'status' => $timeEntry->status(),
        ];
        if ($this->hasMalleableColumn) {
            $data['malleable_data'] = json_encode($timeEntry->malleableData());
        }
        if ($this->hasArchivedColumn) {
            $data['archived_at'] = $timeEntry->archivedAt() ? $timeEntry->archivedAt()->format('Y-m-d H:i:s') : null;
        }

        if ($timeEntry->id()) {
            $this->db->update(
                $this->table,
                $data,
                ['id' => $timeEntry->id()]
            );
        } else {
            $this->db->insert($this->table, $data);

            // In a real implementation we would set the ID back on the entity via reflection or a setter
            // $timeEntry->setId($this->db->insert_id);
        }
    }

    public function delete(int $id): void
    {
        $this->db->update(
            $this->table,
            ['archived_at' => current_time('mysql')],
            ['id' => $id]
        );
    }

    public function findById(int $id): ?TimeEntry
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        );
        
        $row = $this->db->get_row($query);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $query = "SELECT * FROM {$this->table} ORDER BY start_time DESC";
        $results = $this->db->get_results($query);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByEmployeeId(int $employeeId): array
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE employee_id = %d ORDER BY start_time DESC",
            $employeeId
        );
        
        $results = $this->db->get_results($query);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByTicketId(int $ticketId): array
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE ticket_id = %d ORDER BY start_time DESC",
            $ticketId
        );
        
        $results = $this->db->get_results($query);

        return array_map([$this, 'hydrate'], $results);
    }

    public function sumBillableHours(): float
    {
        $query = "SELECT SUM(duration_minutes) FROM {$this->table} WHERE is_billable = 1";
        $minutes = (int) $this->db->get_var($query);
        return round($minutes / 60, 2);
    }

    private function hydrate(object $row): TimeEntry
    {
        return new TimeEntry(
            (int) $row->employee_id,
            (int) $row->ticket_id,
            new \DateTimeImmutable($row->start_time),
            new \DateTimeImmutable($row->end_time),
            (bool) $row->is_billable,
            $row->description,
            $row->status,
            (int) $row->id,
            json_decode(isset($row->malleable_data) ? $row->malleable_data : '[]', true),
            isset($row->created_at) ? new \DateTimeImmutable($row->created_at) : null,
            isset($row->archived_at) ? new \DateTimeImmutable($row->archived_at) : null
        );
    }
}
