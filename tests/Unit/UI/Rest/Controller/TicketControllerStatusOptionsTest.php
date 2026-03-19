<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

use Pet\Application\Support\Command\AssignTicketToTeamHandler;
use Pet\Application\Support\Command\AssignTicketToUserHandler;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\DeleteTicketHandler;
use Pet\Application\Support\Command\PullTicketHandler;
use Pet\Application\Support\Command\UpdateTicketHandler;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\UI\Rest\Controller\TicketController;
use PHPUnit\Framework\TestCase;

final class TicketControllerStatusOptionsTest extends TestCase
{
    public function testGetStatusOptionsReturnsAuthoritativeLifecycleStatuses(): void
    {
        $controller = $this->makeController();
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets/status-options');
        $request->set_param('lifecycle_owner', 'project');

        $response = $controller->getStatusOptions($request);
        self::assertSame(200, $response->get_status());

        $data = $response->get_data();
        $values = array_map(static fn(array $opt): string => (string)($opt['value'] ?? ''), $data);
        self::assertContains('planned', $values);
        self::assertContains('in_progress', $values);
        self::assertContains('closed', $values);
    }

    public function testGetStatusOptionsReturns400ForInvalidLifecycle(): void
    {
        $controller = $this->makeController();
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets/status-options');
        $request->set_param('lifecycle_owner', 'unknown');

        $response = $controller->getStatusOptions($request);
        self::assertSame(400, $response->get_status());
    }

    private function makeController(): TicketController
    {
        $tickets = $this->createMock(TicketRepository::class);
        $create = $this->createMock(CreateTicketHandler::class);
        $update = $this->createMock(UpdateTicketHandler::class);
        $delete = $this->createMock(DeleteTicketHandler::class);
        $assignTeam = $this->createMock(AssignTicketToTeamHandler::class);
        $assignUser = $this->createMock(AssignTicketToUserHandler::class);
        $pull = $this->createMock(PullTicketHandler::class);
        $workItems = $this->createMock(WorkItemRepository::class);
        $flags = $this->createMock(FeatureFlagService::class);

        return new TicketController(
            $tickets,
            $create,
            $update,
            $delete,
            $assignTeam,
            $assignUser,
            $pull,
            $workItems,
            $flags
        );
    }
}

