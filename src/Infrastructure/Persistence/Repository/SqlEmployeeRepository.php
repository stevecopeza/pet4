<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;

class SqlEmployeeRepository implements EmployeeRepository
{
    private $wpdb;
    private $tableName;
    private $membersTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_employees';
        $this->membersTable = $wpdb->prefix . 'pet_team_members';
    }

    public function save(Employee $employee): void
    {
        $data = [
            'wp_user_id' => $employee->wpUserId(),
            'first_name' => $employee->firstName(),
            'last_name' => $employee->lastName(),
            'email' => $employee->email(),
            'status' => $employee->status(),
            'hire_date' => $employee->hireDate() ? $employee->hireDate()->format('Y-m-d') : null,
            'manager_id' => $employee->managerId(),
            'calendar_id' => $employee->calendarId(),
            'malleable_schema_version' => $employee->malleableSchemaVersion(),
            'malleable_data' => !empty($employee->malleableData()) ? json_encode($employee->malleableData()) : null,
            'created_at' => $employee->createdAt()->format('Y-m-d H:i:s'),
            'archived_at' => $employee->archivedAt() ? $employee->archivedAt()->format('Y-m-d H:i:s') : null,
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s'];

        if ($employee->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $employee->id()],
                $format,
                ['%d']
            );
            $employeeId = $employee->id();
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            $employeeId = $this->wpdb->insert_id;
        }

        $this->updateTeamMemberships($employeeId, $employee->teamIds());
    }

    public function findById(int $id): ?Employee
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByWpUserId(int $wpUserId): ?Employee
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE wp_user_id = %d LIMIT 1",
            $wpUserId
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->tableName} ORDER BY last_name ASC, first_name ASC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    private function hydrate(object $row): Employee
    {
        $teamIds = $this->getTeamIds((int) $row->id);

        return new Employee(
            (int) $row->wp_user_id,
            $row->first_name,
            $row->last_name,
            $row->email,
            (int) $row->id,
            isset($row->status) ? $row->status : 'active',
            !empty($row->hire_date) ? new \DateTimeImmutable($row->hire_date) : null,
            !empty($row->manager_id) ? (int) $row->manager_id : null,
            !empty($row->calendar_id) ? (int) $row->calendar_id : null,
            isset($row->malleable_schema_version) ? (int) $row->malleable_schema_version : null,
            isset($row->malleable_data) ? (json_decode($row->malleable_data, true) ?: []) : [],
            $teamIds,
            new \DateTimeImmutable($row->created_at),
            $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null
        );
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }

    private function getTeamIds(int $employeeId): array
    {
        $result = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT team_id FROM {$this->membersTable} WHERE employee_id = %d AND removed_at IS NULL",
            $employeeId
        ));
        return array_map('intval', $result ?: []);
    }

    private function updateTeamMemberships(int $employeeId, array $teamIds): void
    {
        // Get existing teams
        $existing = $this->getTeamIds($employeeId);
        
        // Determine additions and removals
        $toAdd = array_diff($teamIds, $existing);
        $toRemove = array_diff($existing, $teamIds);
        
        // Add new memberships
        foreach ($toAdd as $teamId) {
             $recordId = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->membersTable} WHERE team_id = %d AND employee_id = %d",
                $teamId,
                $employeeId
            ));

            if ($recordId) {
                 $this->wpdb->update(
                    $this->membersTable,
                    ['removed_at' => null, 'role' => 'member'],
                    ['id' => $recordId]
                );
            } else {
                $this->wpdb->insert(
                    $this->membersTable,
                    [
                        'team_id' => $teamId,
                        'employee_id' => $employeeId,
                        'role' => 'member',
                        'assigned_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%s']
                );
            }
        }
        
        // Remove memberships
        if (!empty($toRemove)) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '%d'));
            $sql = $this->wpdb->prepare(
                "UPDATE {$this->membersTable} SET removed_at = %s WHERE employee_id = %d AND team_id IN ($placeholders)",
                array_merge([current_time('mysql'), $employeeId], $toRemove)
            );
            $this->wpdb->query($sql);
        }
    }
}
