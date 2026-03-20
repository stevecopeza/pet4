<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Support;

use Pet\Application\Support\Command\AssignTicketToTeamHandler;
use Pet\Application\Support\Command\AssignTicketToUserHandler;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\DeleteTicketHandler;
use Pet\Application\Support\Command\PullTicketHandler;
use Pet\Application\Support\Command\UpdateTicketHandler;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Infrastructure\Persistence\Repository\SqlTicketRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\UI\Rest\Controller\TicketController;
use PHPUnit\Framework\TestCase;

final class TicketEndpointStatusDeterminismTest extends TestCase
{
    private $previousWpdb;
    private WpdbStub $wpdb;
    private TicketController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->previousWpdb = $wpdb ?? null;
        $this->wpdb = new WpdbStub();
        $wpdb = $this->wpdb;

        $p = $this->wpdb->prefix;
        $this->wpdb->query("CREATE TABLE {$p}pet_tickets (
            id INTEGER PRIMARY KEY,
            customer_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            description TEXT NOT NULL,
            status TEXT NOT NULL,
            priority TEXT NOT NULL,
            created_at TEXT NOT NULL
        )");
        $this->wpdb->query("CREATE TABLE {$p}pet_work_items (
            id TEXT PRIMARY KEY,
            source_type TEXT NOT NULL,
            source_id TEXT NOT NULL,
            assigned_user_id TEXT NULL,
            assigned_team_id TEXT NULL,
            assignment_mode TEXT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )");
        $this->wpdb->query("CREATE TABLE {$p}pet_contract_sla_snapshots (
            id INTEGER PRIMARY KEY,
            sla_name_at_binding TEXT NOT NULL
        )");

        $this->wpdb->insert($p . 'pet_tickets', [
            'id' => 101,
            'customer_id' => 1,
            'subject' => 'Open ticket',
            'description' => 'Seed-like open ticket',
            'status' => 'open',
            'priority' => 'high',
            'created_at' => '2026-01-01 10:00:00',
        ]);
        $this->wpdb->insert($p . 'pet_tickets', [
            'id' => 102,
            'customer_id' => 1,
            'subject' => 'Resolved ticket',
            'description' => 'Seed-like resolved ticket',
            'status' => 'resolved',
            'priority' => 'medium',
            'created_at' => '2026-01-01 11:00:00',
        ]);
        $this->wpdb->insert($p . 'pet_tickets', [
            'id' => 103,
            'customer_id' => 2,
            'subject' => 'In-progress ticket',
            'description' => 'Seed-like in-progress ticket',
            'status' => 'in_progress',
            'priority' => 'low',
            'created_at' => '2026-01-01 12:00:00',
        ]);

        // Deliberately malformed seeded-like state (team + user assignment simultaneously).
        // This previously could crash getTickets when hydrating WorkItem entities.
        $this->wpdb->insert($p . 'pet_work_items', [
            'id' => 'wi-101',
            'source_type' => 'ticket',
            'source_id' => '101',
            'assigned_user_id' => '9',
            'assigned_team_id' => '3',
            'assignment_mode' => 'TEAM_QUEUE',
            'status' => 'active',
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-01 10:00:00',
        ]);

        $ticketRepository = new SqlTicketRepository($this->wpdb);
        $workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->controller = new TicketController(
            $ticketRepository,
            $this->createMock(CreateTicketHandler::class),
            $this->createMock(UpdateTicketHandler::class),
            $this->createMock(DeleteTicketHandler::class),
            $this->createMock(AssignTicketToTeamHandler::class),
            $this->createMock(AssignTicketToUserHandler::class),
            $this->createMock(PullTicketHandler::class),
            $workItemRepository,
            $this->createMock(FeatureFlagService::class)
        );
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->previousWpdb;
        parent::tearDown();
    }

    public function testBlankStatusReturnsAllTicketsEvenWithMalformedWorkItemRows(): void
    {
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets');
        $request->set_param('status', '');

        $response = $this->controller->getTickets($request);
        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertCount(3, $data);
        self::assertSame('9', $data[2]['assignedUserId'] ?? null);
    }

    public function testOmittedStatusReturnsAllTicketsDeterministically(): void
    {
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets');

        $response = $this->controller->getTickets($request);
        self::assertSame(200, $response->get_status());
        self::assertCount(3, $response->get_data());
    }

    public function testValidStatusReturnsFilteredTickets(): void
    {
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets');
        $request->set_param('status', 'open');

        $response = $this->controller->getTickets($request);
        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertCount(1, $data);
        self::assertSame('open', $data[0]['status']);
    }

    public function testInvalidStatusReturnsEmptySet(): void
    {
        $request = new \WP_REST_Request('GET', '/pet/v1/tickets');
        $request->set_param('status', 'not-real');

        $response = $this->controller->getTickets($request);
        self::assertSame(200, $response->get_status());
        self::assertCount(0, $response->get_data());
    }
}

