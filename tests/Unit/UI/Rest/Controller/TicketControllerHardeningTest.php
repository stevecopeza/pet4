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

final class TicketControllerHardeningTest extends TestCase
{
    public function testMutationEndpointsReturn422ForDomainException(): void
    {
        $cases = [
            ['method' => 'createTicket', 'throw_key' => 'create', 'request' => $this->makeCreateRequest()],
            ['method' => 'updateTicket', 'throw_key' => 'update', 'request' => $this->makeUpdateRequest()],
            ['method' => 'deleteTicket', 'throw_key' => 'delete', 'request' => $this->makeDeleteRequest()],
            ['method' => 'assignToTeam', 'throw_key' => 'assign_team', 'request' => $this->makeAssignTeamRequest()],
            ['method' => 'assignToEmployee', 'throw_key' => 'assign_user', 'request' => $this->makeAssignUserRequest()],
            ['method' => 'pullTicket', 'throw_key' => 'pull', 'request' => $this->makePullRequest()],
        ];

        foreach ($cases as $case) {
            $controller = $this->makeController([$case['throw_key'] => new \DomainException('domain failure')]);
            $response = $controller->{$case['method']}($case['request']);
            self::assertSame(422, $response->get_status(), $case['method']);
            $data = $response->get_data();
            self::assertSame('DOMAIN_ERROR', $data['error']['code'] ?? null, $case['method']);
        }
    }

    public function testMutationEndpointsReturn500ForUnexpectedThrowable(): void
    {
        $cases = [
            ['method' => 'createTicket', 'throw_key' => 'create', 'request' => $this->makeCreateRequest()],
            ['method' => 'updateTicket', 'throw_key' => 'update', 'request' => $this->makeUpdateRequest()],
            ['method' => 'deleteTicket', 'throw_key' => 'delete', 'request' => $this->makeDeleteRequest()],
            ['method' => 'assignToTeam', 'throw_key' => 'assign_team', 'request' => $this->makeAssignTeamRequest()],
            ['method' => 'assignToEmployee', 'throw_key' => 'assign_user', 'request' => $this->makeAssignUserRequest()],
            ['method' => 'pullTicket', 'throw_key' => 'pull', 'request' => $this->makePullRequest()],
        ];

        foreach ($cases as $case) {
            $controller = $this->makeController([$case['throw_key'] => new \RuntimeException('unexpected failure')]);
            $response = $controller->{$case['method']}($case['request']);
            self::assertSame(500, $response->get_status(), $case['method']);
            $data = $response->get_data();
            self::assertSame('INTERNAL_ERROR', $data['error']['code'] ?? null, $case['method']);
        }
    }

    private function makeController(array $throws = []): TicketController
    {
        $tickets = $this->createMock(TicketRepository::class);
        $workItems = $this->createMock(WorkItemRepository::class);
        $flags = $this->createMock(FeatureFlagService::class);

        $create = $this->createMock(CreateTicketHandler::class);
        $update = $this->createMock(UpdateTicketHandler::class);
        $delete = $this->createMock(DeleteTicketHandler::class);
        $assignTeam = $this->createMock(AssignTicketToTeamHandler::class);
        $assignUser = $this->createMock(AssignTicketToUserHandler::class);
        $pull = $this->createMock(PullTicketHandler::class);

        if (isset($throws['create'])) {
            $create->method('handle')->willThrowException($throws['create']);
        }
        if (isset($throws['update'])) {
            $update->method('handle')->willThrowException($throws['update']);
        }
        if (isset($throws['delete'])) {
            $delete->method('handle')->willThrowException($throws['delete']);
        }
        if (isset($throws['assign_team'])) {
            $assignTeam->method('handle')->willThrowException($throws['assign_team']);
        }
        if (isset($throws['assign_user'])) {
            $assignUser->method('handle')->willThrowException($throws['assign_user']);
        }
        if (isset($throws['pull'])) {
            $pull->method('handle')->willThrowException($throws['pull']);
        }

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

    private function makeCreateRequest(): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/pet/v1/tickets');
        $request->set_json_params([
            'customerId' => 1,
            'subject' => 'Hardening test ticket',
            'description' => 'Body',
            'priority' => 'medium',
            'source' => 'portal',
        ]);
        return $request;
    }

    private function makeUpdateRequest(): \WP_REST_Request
    {
        $request = new \WP_REST_Request('PUT', '/pet/v1/tickets/11');
        $request->set_param('id', 11);
        $request->set_json_params([
            'subject' => 'Updated',
            'description' => 'Updated body',
            'priority' => 'high',
            'status' => 'open',
        ]);
        return $request;
    }

    private function makeDeleteRequest(): \WP_REST_Request
    {
        $request = new \WP_REST_Request('DELETE', '/pet/v1/tickets/11');
        $request->set_param('id', 11);
        return $request;
    }

    private function makeAssignTeamRequest(): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/pet/v1/tickets/11/assign/team');
        $request->set_param('id', 11);
        $request->set_json_params(['queueId' => '5']);
        return $request;
    }

    private function makeAssignUserRequest(): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/pet/v1/tickets/11/assign/employee');
        $request->set_param('id', 11);
        $request->set_json_params(['employeeUserId' => '22']);
        return $request;
    }

    private function makePullRequest(): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/pet/v1/tickets/11/pull');
        $request->set_param('id', 11);
        return $request;
    }
}
