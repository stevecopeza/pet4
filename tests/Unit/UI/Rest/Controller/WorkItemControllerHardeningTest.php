<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

use Pet\Application\System\Service\FeatureFlagService;
use Pet\Application\Work\Command\AssignWorkItemHandler;
use Pet\Application\Work\Command\OverrideWorkItemPriorityHandler;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\UI\Rest\Controller\WorkItemController;
use PHPUnit\Framework\TestCase;

final class WorkItemControllerHardeningTest extends TestCase
{
    public function testAssignItemReturns422ForDomainException(): void
    {
        $controller = $this->makeController(new \DomainException('assign invalid'));
        $request = new \WP_REST_Request('POST', '/pet/v1/work-items/wi-1/assign');
        $request->set_param('id', 'wi-1');
        $request->set_param('assigned_user_id', '17');

        $response = $controller->assignItem($request);
        self::assertSame(422, $response->get_status());
        $data = $response->get_data();
        self::assertSame('DOMAIN_ERROR', $data['error']['code'] ?? null);
    }

    public function testAssignItemReturns500ForUnexpectedThrowable(): void
    {
        $controller = $this->makeController(new \RuntimeException('assign failure'));
        $request = new \WP_REST_Request('POST', '/pet/v1/work-items/wi-1/assign');
        $request->set_param('id', 'wi-1');
        $request->set_param('assigned_user_id', '17');

        $response = $controller->assignItem($request);
        self::assertSame(500, $response->get_status());
        $data = $response->get_data();
        self::assertSame('INTERNAL_ERROR', $data['error']['code'] ?? null);
    }

    public function testPrioritizeItemReturns422ForDomainException(): void
    {
        $controller = $this->makeController(null, new \DomainException('priority invalid'));
        $request = new \WP_REST_Request('POST', '/pet/v1/work-items/wi-1/prioritize');
        $request->set_param('id', 'wi-1');
        $request->set_param('override_value', 10);

        $response = $controller->prioritizeItem($request);
        self::assertSame(422, $response->get_status());
        $data = $response->get_data();
        self::assertSame('DOMAIN_ERROR', $data['error']['code'] ?? null);
    }

    public function testPrioritizeItemReturns500ForUnexpectedThrowable(): void
    {
        $controller = $this->makeController(null, new \RuntimeException('priority failure'));
        $request = new \WP_REST_Request('POST', '/pet/v1/work-items/wi-1/prioritize');
        $request->set_param('id', 'wi-1');
        $request->set_param('override_value', 10);

        $response = $controller->prioritizeItem($request);
        self::assertSame(500, $response->get_status());
        $data = $response->get_data();
        self::assertSame('INTERNAL_ERROR', $data['error']['code'] ?? null);
    }

    private function makeController(?\Throwable $assignThrow = null, ?\Throwable $prioritizeThrow = null): WorkItemController
    {
        $repository = $this->createMock(WorkItemRepository::class);
        $signals = $this->createMock(AdvisorySignalRepository::class);
        $flags = $this->createMock(FeatureFlagService::class);
        $assign = $this->createMock(AssignWorkItemHandler::class);
        $prioritize = $this->createMock(OverrideWorkItemPriorityHandler::class);

        $item = $this->getMockBuilder(WorkItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSourceType'])
            ->getMock();
        $item->method('getSourceType')->willReturn('admin');
        $repository->method('findById')->willReturn($item);

        if ($assignThrow) {
            $assign->method('handle')->willThrowException($assignThrow);
        }
        if ($prioritizeThrow) {
            $prioritize->method('handle')->willThrowException($prioritizeThrow);
        }

        return new WorkItemController($repository, $signals, $flags, $assign, $prioritize);
    }
}
