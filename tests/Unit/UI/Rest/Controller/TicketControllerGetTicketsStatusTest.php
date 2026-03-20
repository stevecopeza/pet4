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
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\UI\Rest\Controller\TicketController;
use PHPUnit\Framework\TestCase;

final class TicketControllerGetTicketsStatusTest extends TestCase
{
    private $previousWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->previousWpdb = $wpdb ?? null;
        $wpdb = new class {
            public string $prefix = 'wp_';

            public function prepare(string $query, ...$args): string
            {
                if (count($args) === 1 && is_array($args[0])) {
                    $args = $args[0];
                }
                $i = 0;
                return (string) preg_replace_callback('/%[sd]/', static function (array $matches) use (&$i, $args): string {
                    $value = $args[$i] ?? '';
                    $i++;
                    if ($matches[0] === '%d') {
                        return (string) ((int) $value);
                    }
                    return "'" . str_replace("'", "''", (string) $value) . "'";
                }, $query);
            }

            public function get_results(string $query): array
            {
                if (str_contains($query, 'pet_work_items')) {
                    return [
                        (object) ['source_id' => '10', 'assigned_user_id' => '42'],
                        (object) ['source_id' => '11', 'assigned_user_id' => null],
                    ];
                }
                return [];
            }
        };
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->previousWpdb;
        parent::tearDown();
    }

    public function testGetTicketsTreatsBlankStatusAsNoFilter(): void
    {
        $repository = $this->createMock(TicketRepository::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn($this->seedLikeTickets());
        $repository->expects(self::never())->method('findActive');

        $controller = $this->makeController($repository);
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets');
        $request->set_param('status', '');

        $response = $controller->getTickets($request);
        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertCount(3, $data);
        self::assertSame('42', $data[0]['assignedUserId'] ?? null);
    }

    public function testGetTicketsWithOmittedStatusReturnsAllDeterministically(): void
    {
        $repository = $this->createMock(TicketRepository::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn($this->seedLikeTickets());
        $repository->expects(self::never())->method('findActive');

        $controller = $this->makeController($repository);
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets');

        $response = $controller->getTickets($request);
        self::assertSame(200, $response->get_status());
        self::assertCount(3, $response->get_data());
    }

    public function testGetTicketsWithActiveStatusUsesActiveQuery(): void
    {
        $repository = $this->createMock(TicketRepository::class);
        $repository->expects(self::once())
            ->method('findActive')
            ->willReturn([$this->ticket(10, 'open'), $this->ticket(12, 'in_progress')]);
        $repository->expects(self::never())->method('findAll');

        $controller = $this->makeController($repository);
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets');
        $request->set_param('status', 'active');

        $response = $controller->getTickets($request);
        self::assertSame(200, $response->get_status());
        self::assertCount(2, $response->get_data());
    }

    public function testGetTicketsWithInvalidStatusReturnsEmptySet(): void
    {
        $repository = $this->createMock(TicketRepository::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn($this->seedLikeTickets());
        $repository->expects(self::never())->method('findActive');

        $controller = $this->makeController($repository);
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets');
        $request->set_param('status', 'not_a_real_status');

        $response = $controller->getTickets($request);
        self::assertSame(200, $response->get_status());
        self::assertCount(0, $response->get_data());
    }

    private function makeController(TicketRepository $repository): TicketController
    {
        $create = $this->createMock(CreateTicketHandler::class);
        $update = $this->createMock(UpdateTicketHandler::class);
        $delete = $this->createMock(DeleteTicketHandler::class);
        $assignTeam = $this->createMock(AssignTicketToTeamHandler::class);
        $assignUser = $this->createMock(AssignTicketToUserHandler::class);
        $pull = $this->createMock(PullTicketHandler::class);
        $flags = $this->createMock(FeatureFlagService::class);

        // Regression guard: malformed seeded work items should not break getTickets path.
        $workItems = $this->createMock(WorkItemRepository::class);
        $workItems->method('findAll')->willThrowException(
            new \InvalidArgumentException('Work item cannot be both team-queued and user-assigned.')
        );

        return new TicketController(
            $repository,
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

    /**
     * @return Ticket[]
     */
    private function seedLikeTickets(): array
    {
        return [
            $this->ticket(10, 'open'),
            $this->ticket(11, 'resolved'),
            $this->ticket(12, 'in_progress'),
        ];
    }

    private function ticket(int $id, string $status): Ticket
    {
        return new Ticket(
            1,
            "Ticket {$id}",
            'Seed-like ticket',
            $status,
            'medium',
            null,
            null,
            $id,
            null,
            [],
            new \DateTimeImmutable('2026-01-01 10:00:00')
        );
    }
}

