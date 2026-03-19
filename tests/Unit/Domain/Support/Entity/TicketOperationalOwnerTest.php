<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Support\Entity;

use Pet\Domain\Support\Entity\Ticket;
use PHPUnit\Framework\TestCase;

final class TicketOperationalOwnerTest extends TestCase
{
    public function testHasOperationalOwnerFalseWhenNeitherSet(): void
    {
        $t = new Ticket(customerId: 1, subject: 'x', description: 'y', status: 'open', lifecycleOwner: 'support');
        $this->assertFalse($t->hasOperationalOwner());
    }

    public function testHasOperationalOwnerFalseWhenBothSet(): void
    {
        $t = new Ticket(customerId: 1, subject: 'x', description: 'y', status: 'open', lifecycleOwner: 'support', queueId: '3', ownerUserId: '10');
        $this->assertFalse($t->hasOperationalOwner());
    }

    public function testAssignToTeamSetsOnlyTeamOwner(): void
    {
        $t = new Ticket(customerId: 1, subject: 'x', description: 'y', status: 'open', lifecycleOwner: 'support');
        $t->assignToTeam('3');
        $this->assertTrue($t->hasOperationalOwner());
        $this->assertSame('3', $t->queueId());
        $this->assertNull($t->ownerUserId());
    }

    public function testAssignToEmployeeSetsOnlyUserOwner(): void
    {
        $t = new Ticket(customerId: 1, subject: 'x', description: 'y', status: 'open', lifecycleOwner: 'support', queueId: '3', ownerUserId: null);
        $t->assignToEmployee('10');
        $this->assertTrue($t->hasOperationalOwner());
        $this->assertNull($t->queueId());
        $this->assertSame('10', $t->ownerUserId());
    }
}

