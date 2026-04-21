<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\System;

use Pet\Application\System\Service\DemoPreFlightCheck;
use Pet\Domain\Support\Entity\SlaClockState;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Repository\SlaClockStateRepository;
use Pet\Infrastructure\Event\InMemoryEventBus;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class DemoPreFlightCheckTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['wpdb']);
    }

    public function testRunDoesNotSeedEmployeesOrLeaveData(): void
    {
        $employeesTable = $this->wpdb->prefix . 'pet_employees';
        $leaveTypesTable = $this->wpdb->prefix . 'pet_leave_types';

        $service = new DemoPreFlightCheck(
            new InMemoryEventBus(),
            $this->createSlaRepositoryStub()
        );
        $result = $service->run();

        $this->assertSame(0, (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $employeesTable"));
        $this->assertSame(0, (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $leaveTypesTable"));
        $this->assertArrayHasKey('overall', $result);
        $this->assertArrayHasKey('checks', $result);
    }

    private function createSchema(): void
    {
        $p = $this->wpdb->prefix;

        // Core tables referenced by preflight checks.
        $this->wpdb->query("CREATE TABLE {$p}pet_customers (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_sites (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_contacts (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_quotes (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_quote_catalog_items (id INTEGER PRIMARY KEY AUTOINCREMENT, sku TEXT, role_id INTEGER, type TEXT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_projects (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_tickets (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_time_entries (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_sla_clock_state (id INTEGER PRIMARY KEY AUTOINCREMENT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_leave_types (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, paid_flag INTEGER)");
        $this->wpdb->query("CREATE TABLE {$p}pet_leave_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, employee_id INTEGER, leave_type_id INTEGER, start_date TEXT, end_date TEXT, status TEXT, submitted_at TEXT, decided_by_employee_id INTEGER, decided_at TEXT, decision_reason TEXT, notes TEXT, created_at TEXT, updated_at TEXT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_capacity_overrides (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id INTEGER, effective_date TEXT, capacity_pct INTEGER, reason TEXT, created_at TEXT)");
        $this->wpdb->query("CREATE TABLE {$p}pet_employees (id INTEGER PRIMARY KEY AUTOINCREMENT, wp_user_id INTEGER, first_name TEXT, last_name TEXT, email TEXT, created_at TEXT, archived_at TEXT)");
    }

    private function createSlaRepositoryStub(): SlaClockStateRepository
    {
        return new class implements SlaClockStateRepository {
            public function findByTicketIdForUpdate(int $ticketId): ?SlaClockState
            {
                return null;
            }

            public function initialize(Ticket $ticket, int $slaVersionId): SlaClockState
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function save(SlaClockState $state): void
            {
            }

            public function getDashboardStats(): array
            {
                return [];
            }
        };
    }
}

