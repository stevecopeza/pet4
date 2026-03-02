<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Work\Entity;

use Pet\Domain\Work\Entity\WorkItem;
use PHPUnit\Framework\TestCase;

class WorkItemTest extends TestCase
{
    private function makeItem(
        string $sourceType = 'ticket',
        string $status = 'active',
        float $priority = 50.0
    ): WorkItem {
        return WorkItem::create(
            'wi-001',
            $sourceType,
            'src-42',
            'support',
            $priority,
            $status,
            new \DateTimeImmutable('2026-01-15 09:00')
        );
    }

    // ── Create factory ──

    public function testCreateSetsDefaults(): void
    {
        $item = $this->makeItem();
        $this->assertSame('wi-001', $item->getId());
        $this->assertSame('ticket', $item->getSourceType());
        $this->assertSame('src-42', $item->getSourceId());
        $this->assertSame('support', $item->getDepartmentId());
        $this->assertSame(50.0, $item->getPriorityScore());
        $this->assertSame('active', $item->getStatus());
        $this->assertNull($item->getAssignedUserId());
        $this->assertSame(0, $item->getEscalationLevel());
        $this->assertSame(0.0, $item->getCapacityAllocationPercent());
    }

    // ── Source type validation ──

    /** @dataProvider validSourceTypesProvider */
    public function testValidSourceTypes(string $type): void
    {
        $item = $this->makeItem(sourceType: $type);
        $this->assertSame($type, $item->getSourceType());
    }

    public function validSourceTypesProvider(): array
    {
        return [['ticket'], ['project_task'], ['escalation'], ['admin']];
    }

    public function testInvalidSourceTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid source type');
        $this->makeItem(sourceType: 'unknown');
    }

    // ── Status validation ──

    /** @dataProvider validStatusesProvider */
    public function testValidStatuses(string $status): void
    {
        $item = $this->makeItem(status: $status);
        $this->assertSame($status, $item->getStatus());
    }

    public function validStatusesProvider(): array
    {
        return [['active'], ['waiting'], ['completed']];
    }

    public function testInvalidStatusThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status');
        $this->makeItem(status: 'deleted');
    }

    public function testUpdateStatusValidates(): void
    {
        $item = $this->makeItem();
        $item->updateStatus('completed');
        $this->assertSame('completed', $item->getStatus());
    }

    public function testUpdateStatusInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $item = $this->makeItem();
        $item->updateStatus('archived');
    }

    // ── Mutators ──

    public function testAssignUser(): void
    {
        $item = $this->makeItem();
        $item->assignUser('user-5');
        $this->assertSame('user-5', $item->getAssignedUserId());
    }

    public function testAssignUserNull(): void
    {
        $item = $this->makeItem();
        $item->assignUser('user-5');
        $item->assignUser(null);
        $this->assertNull($item->getAssignedUserId());
    }

    public function testUpdateDepartment(): void
    {
        $item = $this->makeItem();
        $item->updateDepartment('delivery');
        $this->assertSame('delivery', $item->getDepartmentId());
    }

    public function testUpdatePriorityScore(): void
    {
        $item = $this->makeItem();
        $item->updatePriorityScore(95.5);
        $this->assertSame(95.5, $item->getPriorityScore());
    }

    public function testEscalate(): void
    {
        $item = $this->makeItem();
        $this->assertSame(0, $item->getEscalationLevel());
        $item->escalate(2);
        $this->assertSame(2, $item->getEscalationLevel());
    }

    public function testUpdateSlaState(): void
    {
        $item = $this->makeItem();
        $item->updateSlaState('snap-1', 45);
        $this->assertSame('snap-1', $item->getSlaSnapshotId());
        $this->assertSame(45, $item->getSlaTimeRemainingMinutes());
    }

    public function testUpdateCommercialInfo(): void
    {
        $item = $this->makeItem();
        $item->updateCommercialInfo(15000.0, 3);
        $this->assertSame(15000.0, $item->getRevenue());
        $this->assertSame(3, $item->getClientTier());
    }

    public function testSetManagerPriorityOverride(): void
    {
        $item = $this->makeItem();
        $item->setManagerPriorityOverride(10.0);
        $this->assertSame(10.0, $item->getManagerPriorityOverride());
    }

    public function testUpdateScheduling(): void
    {
        $item = $this->makeItem();
        $start = new \DateTimeImmutable('2026-02-01 09:00');
        $due = new \DateTimeImmutable('2026-02-01 17:00');
        $item->updateScheduling($start, $due);
        $this->assertEquals($start, $item->getScheduledStartUtc());
        $this->assertEquals($due, $item->getScheduledDueUtc());
    }

    public function testUpdateCapacityAllocation(): void
    {
        $item = $this->makeItem();
        $item->updateCapacityAllocation(0.75);
        $this->assertSame(0.75, $item->getCapacityAllocationPercent());
    }
}
