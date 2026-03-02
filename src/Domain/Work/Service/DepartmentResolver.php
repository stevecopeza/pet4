<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Service;

use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\Entity\Project;

class DepartmentResolver
{
    public const DEPT_SUPPORT = 'dept_support';
    public const DEPT_DELIVERY = 'dept_delivery';
    public const DEPT_SALES = 'dept_sales';
    public const DEPT_ADMIN = 'dept_admin';

    public function resolveForTicket(Ticket $ticket): string
    {
        return self::DEPT_SUPPORT;
    }

    public function resolveForProjectTask(Project $project, Task $task): string
    {
        return self::DEPT_DELIVERY;
    }
}
