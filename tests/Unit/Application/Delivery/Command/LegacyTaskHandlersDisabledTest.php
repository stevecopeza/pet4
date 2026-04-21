<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Delivery\Command;

use Pet\Application\Delivery\Command\AddTaskCommand;
use Pet\Application\Delivery\Command\AddTaskHandler;
use Pet\Application\Delivery\Command\MarkMilestoneCompleteCommand;
use Pet\Application\Delivery\Command\MarkMilestoneCompleteHandler;
use Pet\Application\Delivery\Service\ProjectHealthTransitionEmitter;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Tests\Integration\Support\WpdbStub;
use Pet\Tests\Stub\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

final class LegacyTaskHandlersDisabledTest extends TestCase
{
    private $previousWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->previousWpdb = $wpdb ?? null;
        $wpdb = new WpdbStub();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->previousWpdb;
        parent::tearDown();
    }

    public function testAddTaskHandlerThrowsDomainExceptionImmediately(): void
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::never())->method('findById');
        $projectRepository->expects(self::never())->method('save');

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('dispatch');

        $handler = new AddTaskHandler(
            new FakeTransactionManager(),
            $projectRepository,
            $eventBus
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Legacy project task creation is disabled');
        $handler->handle(new AddTaskCommand(1, 'Legacy Task', 2.0, 7));
    }

    public function testMarkMilestoneCompleteHandlerThrowsDomainExceptionImmediately(): void
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects(self::never())->method('findById');
        $projectRepository->expects(self::never())->method('save');

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('dispatch');

        $handler = new MarkMilestoneCompleteHandler(
            new FakeTransactionManager(),
            $projectRepository,
            $eventBus,
            new ProjectHealthTransitionEmitter()
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Legacy project milestone completion via tasks is disabled');
        $handler->handle(new MarkMilestoneCompleteCommand(1, 'Legacy milestone'));
    }
}
