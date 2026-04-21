<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Work;

use Pet\Application\Support\Command\AssignTicketToTeamCommand;
use Pet\Application\Support\Command\AssignTicketToTeamHandler;
use Pet\Application\Support\Command\AssignTicketToUserCommand;
use Pet\Application\Support\Command\AssignTicketToUserHandler;
use Pet\Application\Work\Service\WorkQueueQueryService;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Domain\Configuration\Entity\Setting;
use Pet\Domain\Configuration\Repository\SettingRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Identity\Entity\Customer;
use Pet\Domain\Support\Event\TicketAssigned;
use Pet\Domain\Work\Service\DepartmentResolver;
use Pet\Domain\Work\Service\PriorityScoringService;
use Pet\Domain\Work\Service\SlaClockCalculator;
use Pet\Infrastructure\Event\InMemoryEventBus;
use Pet\Infrastructure\Persistence\Repository\SqlDepartmentQueueRepository;
use Pet\Infrastructure\Persistence\Repository\SqlTicketRepository;
use Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use Pet\Tests\Stub\FakeTransactionManager;
use PHPUnit\Framework\TestCase;

final class WorkOrchestrationRoutingTest extends TestCase
{
    private WpdbStub $wpdb;
    private SqlTicketRepository $tickets;
    private SqlWorkItemRepository $workItems;
    private SqlDepartmentQueueRepository $queues;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $pdo = $this->wpdb->getPdo();
        $prefix = $this->wpdb->prefix;

        $pdo->exec("CREATE TABLE {$prefix}pet_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            site_id INTEGER NULL,
            sla_id INTEGER NULL,
            subject TEXT NOT NULL,
            description TEXT NOT NULL,
            status TEXT NOT NULL,
            priority TEXT NOT NULL,
            queue_id TEXT NULL,
            owner_user_id TEXT NULL,
            malleable_data TEXT NOT NULL DEFAULT '{}',
            sla_snapshot_id TEXT NULL,
            response_due_at TEXT NULL,
            resolution_due_at TEXT NULL,
            opened_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE {$prefix}pet_work_items (
            id TEXT PRIMARY KEY,
            source_type TEXT NOT NULL,
            source_id TEXT NOT NULL,
            assigned_user_id TEXT NULL,
            department_id TEXT NOT NULL,
            assigned_team_id TEXT NULL,
            assignment_mode TEXT NULL,
            queue_key TEXT NULL,
            routing_reason TEXT NULL,
            sla_snapshot_id TEXT NULL,
            sla_time_remaining_minutes INTEGER NULL,
            priority_score REAL NOT NULL DEFAULT 0,
            scheduled_start_utc TEXT NULL,
            scheduled_due_utc TEXT NULL,
            capacity_allocation_percent REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'active',
            escalation_level INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            revenue REAL NOT NULL DEFAULT 0,
            client_tier INTEGER NOT NULL DEFAULT 1,
            manager_priority_override REAL NOT NULL DEFAULT 0,
            required_role_id INTEGER NULL,
            UNIQUE (source_type, source_id)
        )");

        $pdo->exec("CREATE TABLE {$prefix}pet_department_queues (
            id TEXT PRIMARY KEY,
            department_id TEXT NOT NULL,
            assigned_team_id TEXT NULL,
            work_item_id TEXT NOT NULL,
            assigned_user_id TEXT NULL,
            entered_queue_at TEXT NOT NULL,
            picked_up_at TEXT NULL
        )");

        $this->tickets = new SqlTicketRepository($this->wpdb);
        $this->workItems = new SqlWorkItemRepository($this->wpdb);
        $this->queues = new SqlDepartmentQueueRepository($this->wpdb);
    }

    public function testTeamToUserToReassignToReturnFlowMaintainsQueueHistory(): void
    {
        $ticketId = $this->seedTicket();

        $eventBus = new InMemoryEventBus();
        $departmentResolver = new DepartmentResolver();

        $customerRepo = new class implements CustomerRepository {
            public function save(Customer $customer): void {}
            public function findById(int $id): ?Customer { return null; }
            public function findAll(): array { return []; }
        };


        $advisoryRepo = new class implements AdvisorySignalRepository {
            public function save(AdvisorySignal $signal): void {}
            public function findByWorkItemId(string $workItemId): array { return []; }
            public function findByWorkItemIds(array $workItemIds): array { return []; }
            public function findActiveByWorkItemId(string $workItemId): array { return []; }
            public function findRecent(int $limit): array { return []; }
            public function clearForWorkItem(string $workItemId, ?string $generationRunId = null): void {}
        };

        $slaCalc = new SlaClockCalculator($this->workItems, new PriorityScoringService(), $advisoryRepo);

        $settingsRepo = new class implements SettingRepository {
            public function save(Setting $setting): void {}
            public function findByKey(string $key): ?Setting
            {
                if (in_array($key, ['pet_work_projection_enabled', 'pet_queue_visibility_enabled'], true)) {
                    return new Setting($key, 'true', 'boolean');
                }
                return null;
            }
            public function findAll(): array { return []; }
        };
        $featureFlags = new FeatureFlagService($settingsRepo);

        $projector = new \Pet\Application\Work\Projection\WorkItemProjector(
            $this->workItems,
            $this->queues,
            $departmentResolver,
            $slaCalc,
            $customerRepo,
            $featureFlags
        );

        $eventBus->subscribe(TicketAssigned::class, [$projector, 'onTicketAssigned']);

        $tx = new FakeTransactionManager();
        $assignTeam = new AssignTicketToTeamHandler($tx, $this->tickets, $eventBus);
        $assignUser = new AssignTicketToUserHandler($tx, $this->tickets, $eventBus);

        $assignTeam->handle(new AssignTicketToTeamCommand($ticketId, '3'));
        $reloadedTicket = $this->tickets->findById($ticketId);
        $this->assertNotNull($reloadedTicket);
        $this->assertSame('open', $reloadedTicket->status());
        $this->assertSame('high', $reloadedTicket->priority());

        $workItem = $this->workItems->findBySource('ticket', (string)$ticketId);
        $this->assertNotNull($workItem);
        $this->assertSame('3', $workItem->getAssignedTeamId());
        $this->assertNull($workItem->getAssignedUserId());
        $this->assertSame('TEAM_QUEUE', $workItem->getAssignmentMode());
        $this->assertSame('support:team:3', $workItem->getQueueKey());

        $activeQueue = $this->queues->findByWorkItemId($workItem->getId());
        $this->assertNotNull($activeQueue);
        $this->assertNull($activeQueue->getAssignedUserId());
        $this->assertSame('3', $activeQueue->getAssignedTeamId());

        $assignUser->handle(new AssignTicketToUserCommand($ticketId, '10'));

        $workItem = $this->workItems->findBySource('ticket', (string)$ticketId);
        $this->assertSame('10', $workItem->getAssignedUserId());
        $this->assertSame('USER_ASSIGNED', $workItem->getAssignmentMode());
        $this->assertSame('support:user:10', $workItem->getQueueKey());

        $this->assertNull($this->queues->findByWorkItemId($workItem->getId()));
        $pickedCount = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_department_queues WHERE work_item_id = %s AND assigned_user_id = %s",
            $workItem->getId(),
            '10'
        ));
        $this->assertSame(1, $pickedCount);

        $assignUser->handle(new AssignTicketToUserCommand($ticketId, '11'));
        $workItem = $this->workItems->findBySource('ticket', (string)$ticketId);
        $this->assertSame('11', $workItem->getAssignedUserId());
        $this->assertSame('support:user:11', $workItem->getQueueKey());

        $history11 = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_department_queues WHERE work_item_id = %s AND assigned_user_id = %s",
            $workItem->getId(),
            '11'
        ));
        $this->assertSame(1, $history11);

        $assignTeam->handle(new AssignTicketToTeamCommand($ticketId, '3'));
        $workItem = $this->workItems->findBySource('ticket', (string)$ticketId);
        $this->assertSame('TEAM_QUEUE', $workItem->getAssignmentMode());
        $this->assertSame('support:team:3', $workItem->getQueueKey());

        $activeQueue = $this->queues->findByWorkItemId($workItem->getId());
        $this->assertNotNull($activeQueue);
        $this->assertNull($activeQueue->getAssignedUserId());

        $activeCount = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_department_queues WHERE work_item_id = %s AND picked_up_at IS NULL",
            $workItem->getId()
        ));
        $this->assertSame(1, $activeCount);
    }

    public function testWorkQueueQueryIsReadOnly(): void
    {
        $spy = new class extends WpdbStub {
            public int $writes = 0;
            public function insert(string $table, array $data)
            {
                $this->writes++;
                return parent::insert($table, $data);
            }
            public function update(string $table, array $data, array $where)
            {
                $this->writes++;
                return parent::update($table, $data, $where);
            }
            public function query(string $sql)
            {
                $upper = strtoupper(ltrim($sql));
                if (str_starts_with($upper, 'INSERT') || str_starts_with($upper, 'UPDATE') || str_starts_with($upper, 'DELETE') || str_starts_with($upper, 'ALTER') || str_starts_with($upper, 'CREATE') || str_starts_with($upper, 'DROP')) {
                    $this->writes++;
                }
                return parent::query($sql);
            }
        };

        $pdo = $spy->getPdo();
        $prefix = $spy->prefix;

        $pdo->exec("CREATE TABLE {$prefix}pet_work_items (
            id TEXT PRIMARY KEY,
            source_type TEXT NOT NULL,
            source_id TEXT NOT NULL,
            assigned_user_id TEXT NULL,
            department_id TEXT NOT NULL,
            assigned_team_id TEXT NULL,
            assignment_mode TEXT NULL,
            queue_key TEXT NULL,
            routing_reason TEXT NULL,
            sla_snapshot_id TEXT NULL,
            sla_time_remaining_minutes INTEGER NULL,
            priority_score REAL NOT NULL DEFAULT 0,
            scheduled_start_utc TEXT NULL,
            scheduled_due_utc TEXT NULL,
            capacity_allocation_percent REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'active',
            escalation_level INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            revenue REAL NOT NULL DEFAULT 0,
            client_tier INTEGER NOT NULL DEFAULT 1,
            manager_priority_override REAL NOT NULL DEFAULT 0,
            required_role_id INTEGER NULL
        )");

        $pdo->exec("CREATE TABLE {$prefix}pet_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject TEXT NOT NULL,
            customer_id INTEGER NOT NULL,
            site_id INTEGER NULL,
            sla_id INTEGER NULL,
            status TEXT NOT NULL,
            priority TEXT NOT NULL,
            resolution_due_at TEXT NULL
        )");

        $spy->insert($prefix . 'pet_tickets', [
            'subject' => 'Example ticket',
            'customer_id' => 1,
            'site_id' => null,
            'sla_id' => null,
            'status' => 'open',
            'priority' => 'high',
            'resolution_due_at' => null,
        ]);
        $ticketId = (int)$spy->insert_id;

        $spy->insert($prefix . 'pet_work_items', [
            'id' => 'wi-1',
            'source_type' => 'ticket',
            'source_id' => (string)$ticketId,
            'assigned_user_id' => null,
            'department_id' => 'support',
            'assigned_team_id' => '3',
            'assignment_mode' => 'TEAM_QUEUE',
            'queue_key' => 'support:team:3',
            'routing_reason' => null,
            'priority_score' => 10.0,
            'status' => 'active',
        ]);

        $beforeWrites = $spy->writes;
        $query = new WorkQueueQueryService($spy);
        $items = $query->listItemsForQueue('support:team:3');
        $afterWrites = $spy->writes;

        $this->assertNotEmpty($items);
        $this->assertSame($beforeWrites, $afterWrites);
    }

    private function seedTicket(): int
    {
        $this->wpdb->insert($this->wpdb->prefix . 'pet_tickets', [
            'customer_id' => 1,
            'site_id' => null,
            'sla_id' => null,
            'subject' => 'Seed ticket',
            'description' => 'Test',
            'status' => 'open',
            'priority' => 'high',
            'queue_id' => null,
            'owner_user_id' => null,
            'malleable_data' => '{}',
            'sla_snapshot_id' => null,
            'response_due_at' => null,
            'resolution_due_at' => null,
            'opened_at' => null,
        ]);
        return (int)$this->wpdb->insert_id;
    }
}
