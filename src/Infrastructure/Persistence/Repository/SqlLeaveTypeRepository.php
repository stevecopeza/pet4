<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\LeaveType;
use Pet\Domain\Work\Repository\LeaveTypeRepository;

final class SqlLeaveTypeRepository implements LeaveTypeRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function findAll(): array
    {
        $rows = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}pet_leave_types ORDER BY id ASC", ARRAY_A);
        return array_map(function ($r) {
            return new LeaveType((int)$r['id'], $r['name'], (bool)$r['paid_flag']);
        }, $rows);
    }

    public function findById(int $id): ?LeaveType
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}pet_leave_types WHERE id = %d", $id),
            ARRAY_A
        );
        if (!$row) return null;
        return new LeaveType((int)$row['id'], $row['name'], (bool)$row['paid_flag']);
    }
}

