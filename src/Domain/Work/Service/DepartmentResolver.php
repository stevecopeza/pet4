<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Service;

use Pet\Domain\Support\Entity\Ticket;

class DepartmentResolver
{
    public const DEPT_SUPPORT = 'dept_support';
    public const DEPT_DELIVERY = 'dept_delivery';
    public const DEPT_SALES = 'dept_sales';
    public const DEPT_ADMIN = 'dept_admin';

    public function resolveForTicket(Ticket $ticket): string
    {
        return match ($ticket->lifecycleOwner()) {
            'project' => self::DEPT_DELIVERY,
            'internal' => self::DEPT_ADMIN,
            default => self::DEPT_SUPPORT,
        };
    }

}
