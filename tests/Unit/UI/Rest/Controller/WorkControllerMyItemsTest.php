<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

use DateTimeImmutable;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\UI\Rest\Controller\WorkController;
use PHPUnit\Framework\TestCase;

final class WorkControllerMyItemsTest extends TestCase
{
    public function testGetMyWorkItemsReturnsMappedPayload(): void
    {
        $repo = $this->createMock(WorkItemRepository::class);
        $signals = $this->createMock(AdvisorySignalRepository::class);
        $flags = $this->createMock(FeatureFlagService::class);
        $flags->method('isAdvisoryEnabled')->willReturn(false);

        $item = WorkItem::create(
            'wi-1',
            'ticket',
            '42',
            'support',
            85.0,
            'active',
            new DateTimeImmutable('2026-03-20 10:00:00'),
            null,
            null,
            '1',
            'seed_staff_time_capture'
        );
        $repo->method('findByAssignedUser')->with('1')->willReturn([$item]);

        $controller = new WorkController($repo, $signals, $flags);
        $response = $controller->getMyWorkItems(new \WP_REST_Request('GET', '/pet/v1/work/my-items'));

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertIsArray($data);
        self::assertCount(1, $data);
        self::assertSame('wi-1', $data[0]['id']);
        self::assertSame('ticket', $data[0]['sourceType']);
        self::assertSame('42', $data[0]['sourceId']);
        self::assertSame('active', $data[0]['status']);
    }

    public function testGetMyWorkItemsReturnsEmptyArrayWhenNoItems(): void
    {
        $repo = $this->createMock(WorkItemRepository::class);
        $signals = $this->createMock(AdvisorySignalRepository::class);
        $flags = $this->createMock(FeatureFlagService::class);
        $flags->method('isAdvisoryEnabled')->willReturn(false);
        $repo->method('findByAssignedUser')->with('1')->willReturn([]);

        $controller = new WorkController($repo, $signals, $flags);
        $response = $controller->getMyWorkItems(new \WP_REST_Request('GET', '/pet/v1/work/my-items'));

        self::assertSame(200, $response->get_status());
        self::assertSame([], $response->get_data());
    }
}

