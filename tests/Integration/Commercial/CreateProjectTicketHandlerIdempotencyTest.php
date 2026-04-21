<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Commercial;

use Pet\Application\Commercial\Command\CreateProjectTicketCommand;
use Pet\Application\Commercial\Command\CreateProjectTicketHandler;
use Pet\Domain\Support\Event\TicketCreated;
use Pet\Infrastructure\Persistence\Repository\SqlTicketRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use Pet\Tests\Stub\FakeTransactionManager;
use Pet\Tests\Stub\SpyEventBus;
use PHPUnit\Framework\TestCase;

final class CreateProjectTicketHandlerIdempotencyTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new class extends WpdbStub {
            public function get_results(string $sql, string $output = 'OBJECT'): array
            {
                if (preg_match('/^SHOW COLUMNS FROM\\s+(\\S+)/i', trim($sql), $m) === 1) {
                    $pragmaRows = parent::get_results("PRAGMA table_info({$m[1]})");
                    $mapped = [];
                    foreach ($pragmaRows as $row) {
                        $column = new \stdClass();
                        $column->Field = $row->name ?? null;
                        $mapped[] = $column;
                    }
                    return $mapped;
                }

                return parent::get_results($sql, $output);
            }
        };

        $p = $this->wpdb->prefix;
        $this->wpdb->query(
            "CREATE TABLE {$p}pet_tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                site_id INTEGER NULL,
                sla_id INTEGER NULL,
                subject TEXT NOT NULL,
                description TEXT NOT NULL,
                status TEXT NOT NULL,
                priority TEXT NOT NULL,
                malleable_schema_version INTEGER NULL,
                malleable_data TEXT NULL,
                created_at TEXT NOT NULL,
                opened_at TEXT NULL,
                closed_at TEXT NULL,
                resolved_at TEXT NULL,
                sla_snapshot_id INTEGER NULL,
                response_due_at TEXT NULL,
                resolution_due_at TEXT NULL,
                responded_at TEXT NULL,
                queue_id TEXT NULL,
                owner_user_id TEXT NULL,
                category TEXT NULL,
                subcategory TEXT NULL,
                intake_source TEXT NULL,
                contact_id INTEGER NULL,
                primary_container TEXT NULL,
                project_id INTEGER NULL,
                quote_id INTEGER NULL,
                phase_id INTEGER NULL,
                parent_ticket_id INTEGER NULL,
                parent_ticket_key INTEGER NOT NULL DEFAULT 0,
                root_ticket_id INTEGER NULL,
                ticket_kind TEXT NULL,
                department_id_ext INTEGER NULL,
                required_role_id INTEGER NULL,
                skill_level TEXT NULL,
                billing_context_type TEXT NULL,
                agreement_id INTEGER NULL,
                rate_plan_id INTEGER NULL,
                is_billable_default INTEGER NULL,
                sold_minutes INTEGER NULL,
                estimated_minutes INTEGER NULL,
                remaining_minutes INTEGER NULL,
                is_rollup INTEGER NULL,
                lifecycle_owner TEXT NULL,
                is_baseline_locked INTEGER NULL,
                change_order_source_ticket_id INTEGER NULL,
                sold_value_cents INTEGER NULL,
                source_type TEXT NULL,
                source_component_id INTEGER NULL
            )"
        );
        $this->wpdb->query(
            "CREATE UNIQUE INDEX uq_ticket_project_source_parent_key
            ON {$p}pet_tickets(project_id, source_component_id, parent_ticket_key)"
        );
    }

    public function testRepeatedRootProvisioningReturnsExistingTicketIdAndPersistsOnlyOneRow(): void
    {
        $eventBus = new SpyEventBus();
        $handler = $this->newHandler($eventBus);

        $command = new CreateProjectTicketCommand(
            11,
            501,
            9001,
            'Provision root ticket',
            'Root ticket for quote component',
            120,
            25000,
            120,
            null,
            7,
            null,
            null,
            'quote_component',
            3001,
            null,
            false
        );

        $firstId = $handler->handle($command);
        $secondId = $handler->handle($command);

        self::assertSame($firstId, $secondId);

        $p = $this->wpdb->prefix;
        $rowCount = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}pet_tickets
             WHERE project_id = 501 AND source_component_id = 3001 AND parent_ticket_id IS NULL"
        );
        self::assertSame(1, $rowCount, 'Root provisioning must be idempotent for NULL parent_ticket_id.');

        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$p}pet_tickets WHERE id = %d", $firstId));
        self::assertNotNull($row);
        self::assertSame('planned', (string)$row->status);
        self::assertSame('project', (string)$row->lifecycle_owner);
        self::assertSame('project', (string)$row->billing_context_type);
        self::assertSame(1, (int)$row->is_baseline_locked);
        self::assertSame('quote_component', (string)$row->source_type);
        self::assertNull($row->sla_id);
        self::assertNull($row->sla_snapshot_id);
        self::assertNull($row->response_due_at);
        self::assertNull($row->resolution_due_at);
        self::assertSame(0, (int)$row->parent_ticket_key);

        self::assertCount(1, $eventBus->dispatchedOfType(TicketCreated::class));
    }

    public function testChildProvisioningIsIdempotentPerParentTicketId(): void
    {
        $eventBus = new SpyEventBus();
        $handler = $this->newHandler($eventBus);

        $parentId = $handler->handle(new CreateProjectTicketCommand(
            11,
            501,
            9001,
            'Implementation rollup',
            'Rollup',
            240,
            48000,
            240,
            null,
            null,
            null,
            null,
            'quote_component',
            3100,
            null,
            true
        ));

        $child = new CreateProjectTicketCommand(
            11,
            501,
            9001,
            'Leaf task',
            'Leaf ticket',
            60,
            12000,
            60,
            null,
            12,
            null,
            null,
            'quote_component',
            3101,
            $parentId,
            false
        );

        $firstChildId = $handler->handle($child);
        $secondChildId = $handler->handle($child);

        self::assertSame($firstChildId, $secondChildId);

        $p = $this->wpdb->prefix;
        $childRows = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}pet_tickets WHERE project_id = %d AND source_component_id = %d AND parent_ticket_id = %d",
            501,
            3101,
            $parentId
        ));
        self::assertSame(1, $childRows);

        $childRow = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$p}pet_tickets WHERE id = %d", $firstChildId));
        self::assertNotNull($childRow);
        self::assertSame($parentId, (int)$childRow->parent_ticket_id);
        self::assertSame($parentId, (int)$childRow->parent_ticket_key);
        self::assertSame(0, (int)$childRow->is_rollup);

        self::assertCount(2, $eventBus->dispatchedOfType(TicketCreated::class), 'Only first parent+first child creation should emit events.');
    }

    private function newHandler(SpyEventBus $eventBus): CreateProjectTicketHandler
    {
        return new CreateProjectTicketHandler(
            new FakeTransactionManager(),
            new SqlTicketRepository($this->wpdb),
            $eventBus
        );
    }
}
