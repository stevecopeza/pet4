<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\LeaveRequest;
use Pet\Domain\Work\Repository\LeaveRequestRepository;

final class SqlLeaveRequestRepository implements LeaveRequestRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(LeaveRequest $req): void
    {
        $table = $this->wpdb->prefix . 'pet_leave_requests';
        if ($req->id() === 0) {
            $this->wpdb->insert($table, [
                'uuid' => $req->uuid(),
                'employee_id' => $req->employeeId(),
                'leave_type_id' => $req->leaveTypeId(),
                'start_date' => $req->startDate()->format('Y-m-d'),
                'end_date' => $req->endDate()->format('Y-m-d'),
                'status' => $req->status(),
                'submitted_at' => null,
                'decided_by_employee_id' => null,
                'decided_at' => null,
                'decision_reason' => $req->decisionReason(),
                'notes' => $req->notes(),
                'created_at' => $req->createdAt()->format('Y-m-d H:i:s'),
                'updated_at' => $req->updatedAt()->format('Y-m-d H:i:s'),
            ]);
            $req->setId((int)$this->wpdb->insert_id);
        } else {
            $req->touch();
            $this->wpdb->update($table, [
                'status' => $req->status(),
                'updated_at' => $req->updatedAt()->format('Y-m-d H:i:s'),
            ], ['id' => $req->id()]);
        }
    }

    public function findById(int $id): ?LeaveRequest
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}pet_leave_requests WHERE id = %d", $id),
            ARRAY_A
        );
        if (!$row) return null;
        return new LeaveRequest(
            (int)$row['id'],
            $row['uuid'],
            (int)$row['employee_id'],
            (int)$row['leave_type_id'],
            new \DateTimeImmutable($row['start_date']),
            new \DateTimeImmutable($row['end_date']),
            $row['status'],
            $row['decided_by_employee_id'] !== null ? (int)$row['decided_by_employee_id'] : null,
            $row['decided_at'] ? new \DateTimeImmutable($row['decided_at']) : null,
            $row['decision_reason'] ?: null,
            $row['notes'] ?: null,
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at'])
        );
    }

    public function findByEmployee(int $employeeId, int $limit = 50): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}pet_leave_requests WHERE employee_id = %d ORDER BY id DESC LIMIT %d", $employeeId, $limit),
            ARRAY_A
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = new LeaveRequest(
                (int)$row['id'],
                $row['uuid'],
                (int)$row['employee_id'],
                (int)$row['leave_type_id'],
                new \DateTimeImmutable($row['start_date']),
                new \DateTimeImmutable($row['end_date']),
                $row['status'],
                $row['decided_by_employee_id'] !== null ? (int)$row['decided_by_employee_id'] : null,
                $row['decided_at'] ? new \DateTimeImmutable($row['decided_at']) : null,
                $row['decision_reason'] ?: null,
                $row['notes'] ?: null,
                new \DateTimeImmutable($row['created_at']),
                new \DateTimeImmutable($row['updated_at'])
            );
        }
        return $out;
    }

    public function setStatus(int $id, string $status, ?int $decidedByEmployeeId = null, ?\DateTimeImmutable $decidedAt = null, ?string $reason = null): void
    {
        $this->wpdb->update(
            $this->wpdb->prefix . 'pet_leave_requests',
            [
                'status' => $status,
                'decided_by_employee_id' => $decidedByEmployeeId,
                'decided_at' => $decidedAt ? $decidedAt->format('Y-m-d H:i:s') : null,
                'decision_reason' => $reason,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            ['id' => $id]
        );
    }

    public function isApprovedOnDate(int $employeeId, \DateTimeImmutable $date): bool
    {
        $table = $this->wpdb->prefix . 'pet_leave_requests';
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM $table WHERE employee_id = %d AND status = 'approved' AND start_date <= %s AND end_date >= %s LIMIT 1",
                $employeeId,
                $date->format('Y-m-d'),
                $date->format('Y-m-d')
            )
        );
        return (bool)$row;
    }
}

