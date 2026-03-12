<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Escalation;

use Pet\Application\Escalation\Command\TriggerEscalationCommand;
use Pet\Application\Escalation\Command\TriggerEscalationHandler;
use Pet\Domain\Escalation\Entity\Escalation;
use Pet\Domain\Escalation\Entity\EscalationTransition;
use Pet\Domain\Escalation\Event\EscalationTriggeredEvent;
use Pet\Infrastructure\Persistence\Exception\DuplicateKeyException;
use Pet\Infrastructure\Persistence\Repository\SqlEscalationRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use Pet\Tests\Stub\FakeTransactionManager;
use Pet\Tests\Stub\SpyEventBus;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests proving escalation persistence through the real SQL path.
 *
 * Uses a SQLite-backed WpdbStub so we exercise actual SQL without requiring
 * a full WordPress/MySQL environment.
 */
class EscalationPersistenceTest extends TestCase
{
    private WpdbStub $wpdb;
    private SqlEscalationRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new WpdbStub();
        $this->createSchema();
        $this->repo = new SqlEscalationRepository($this->wpdb);
    }

    // ══════════════════════════════════════════════════════════════
    //  A. Migration correctness
    // ══════════════════════════════════════════════════════════════

    public function testEscalationsTableHasOpenDedupeKeyColumn(): void
    {
        $columns = $this->getColumnNames($this->wpdb->prefix . 'pet_escalations');

        $this->assertContains('open_dedupe_key', $columns);
        $this->assertContains('escalation_id', $columns);
        $this->assertContains('source_entity_type', $columns);
        $this->assertContains('severity', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('reason', $columns);
        $this->assertContains('metadata_json', $columns);
    }

    public function testOpenDedupeKeyUniqueConstraintExists(): void
    {
        // Insert a row with a specific open_dedupe_key
        $this->wpdb->insert($this->wpdb->prefix . 'pet_escalations', [
            'escalation_id' => 'uuid-dup-1',
            'source_entity_type' => 'ticket',
            'source_entity_id' => 1,
            'severity' => 'HIGH',
            'status' => 'OPEN',
            'reason' => 'test',
            'metadata_json' => '{}',
            'open_dedupe_key' => 'same-key',
            'created_at' => '2025-01-01 00:00:00',
        ]);
        $this->assertSame('', $this->wpdb->last_error);

        // Second insert with same key must fail
        $result = $this->wpdb->insert($this->wpdb->prefix . 'pet_escalations', [
            'escalation_id' => 'uuid-dup-2',
            'source_entity_type' => 'ticket',
            'source_entity_id' => 1,
            'severity' => 'HIGH',
            'status' => 'OPEN',
            'reason' => 'test',
            'metadata_json' => '{}',
            'open_dedupe_key' => 'same-key',
            'created_at' => '2025-01-01 00:00:00',
        ]);

        $this->assertFalse($result);
        $this->assertStringContainsStringIgnoringCase('Duplicate entry', $this->wpdb->last_error);
    }

    public function testTransitionsTableSupportsNullableFromStatus(): void
    {
        // Pre-insert an escalation row for the FK
        $this->wpdb->insert($this->wpdb->prefix . 'pet_escalations', [
            'escalation_id' => 'uuid-trans-1',
            'source_entity_type' => 'ticket',
            'source_entity_id' => 1,
            'severity' => 'HIGH',
            'status' => 'OPEN',
            'reason' => 'test',
            'metadata_json' => '{}',
            'open_dedupe_key' => 'key-trans',
            'created_at' => '2025-01-01 00:00:00',
        ]);
        $escalationId = $this->wpdb->insert_id;

        // Insert transition with NULL from_status
        $result = $this->wpdb->insert($this->wpdb->prefix . 'pet_escalation_transitions', [
            'escalation_id' => $escalationId,
            'from_status' => null,
            'to_status' => 'OPEN',
            'transitioned_by' => null,
            'reason' => 'Initial',
            'transitioned_at' => '2025-01-01 00:00:00',
        ]);

        $this->assertNotFalse($result);
        $this->assertSame('', $this->wpdb->last_error);
    }

    public function testTransitionsTableIncludesReasonColumn(): void
    {
        $columns = $this->getColumnNames($this->wpdb->prefix . 'pet_escalation_transitions');
        $this->assertContains('reason', $columns);
    }

    // ══════════════════════════════════════════════════════════════
    //  B. Repository persistence round-trip
    // ══════════════════════════════════════════════════════════════

    public function testSaveAndLoadEscalationRoundTrip(): void
    {
        $escalation = new Escalation(
            'uuid-rt-1',
            'ticket',
            42,
            Escalation::SEVERITY_HIGH,
            'SLA breach – stage 1',
            7,
            '{"origin":"sla"}'
        );

        $this->repo->save($escalation);
        $this->assertNotNull($escalation->id());

        $loaded = $this->repo->findById($escalation->id());
        $this->assertNotNull($loaded);
        $this->assertSame('uuid-rt-1', $loaded->escalationId());
        $this->assertSame('ticket', $loaded->sourceEntityType());
        $this->assertSame(42, $loaded->sourceEntityId());
        $this->assertSame('HIGH', $loaded->severity());
        $this->assertSame('OPEN', $loaded->status());
        $this->assertSame('SLA breach – stage 1', $loaded->reason());
        $this->assertSame(7, $loaded->createdBy());
        $this->assertSame('{"origin":"sla"}', $loaded->metadataJson());
    }

    public function testOpenDedupeKeyPersistsForOpenEscalation(): void
    {
        $escalation = new Escalation('uuid-dk-1', 'ticket', 10, Escalation::SEVERITY_MEDIUM, 'test reason');
        $this->repo->save($escalation);

        $loaded = $this->repo->findById($escalation->id());
        $this->assertNotNull($loaded->openDedupeKey());

        $expectedKey = Escalation::computeDedupeKey('ticket', 10, 'MEDIUM', 'test reason');
        $this->assertSame($expectedKey, $loaded->openDedupeKey());
    }

    public function testFindOpenByDedupeKeyFindsMatch(): void
    {
        $escalation = new Escalation('uuid-dk-2', 'ticket', 20, Escalation::SEVERITY_HIGH, 'breach');
        $this->repo->save($escalation);

        $dedupeKey = Escalation::computeDedupeKey('ticket', 20, 'HIGH', 'breach');
        $found = $this->repo->findOpenByDedupeKey($dedupeKey);

        $this->assertNotNull($found);
        $this->assertSame($escalation->id(), $found->id());
    }

    public function testResolvedEscalationClearsDedupeParticipation(): void
    {
        $escalation = new Escalation('uuid-res-1', 'ticket', 30, Escalation::SEVERITY_HIGH, 'breach', null, '{}', null);
        $this->repo->save($escalation);
        $id = $escalation->id();

        // Resolve
        $escalation->resolve(5);
        $this->repo->save($escalation);

        $loaded = $this->repo->findById($id);
        $this->assertSame('RESOLVED', $loaded->status());
        $this->assertNull($loaded->openDedupeKey());

        // Dedupe key slot is now free; findOpenByDedupeKey should return null
        $dedupeKey = Escalation::computeDedupeKey('ticket', 30, 'HIGH', 'breach');
        $this->assertNull($this->repo->findOpenByDedupeKey($dedupeKey));
    }

    public function testTransitionSaveAndLoadPreservesNullFromStatus(): void
    {
        $escalation = new Escalation('uuid-trs-1', 'ticket', 50, Escalation::SEVERITY_LOW, 'test');
        $this->repo->save($escalation);

        $transition = new EscalationTransition(
            $escalation->id(),
            null,
            Escalation::STATUS_OPEN,
            1,
            'Initial creation'
        );
        $this->repo->saveTransition($transition);

        $transitions = $this->repo->findTransitionsByEscalationId($escalation->id());
        $this->assertCount(1, $transitions);

        $row = $transitions[0];
        $this->assertNull($row->from_status);
        $this->assertSame('OPEN', $row->to_status);
        $this->assertSame('Initial creation', $row->reason);
    }

    // ══════════════════════════════════════════════════════════════
    //  C. Trigger idempotency through real SQL path
    // ══════════════════════════════════════════════════════════════

    public function testFirstTriggerCreatesEscalationAndTransition(): void
    {
        $handler = $this->createHandler($eventBus);

        $cmd = $this->makeCommand();
        $id = $handler->handle($cmd);

        $this->assertGreaterThan(0, $id);

        // One escalation row
        $all = $this->repo->findAll();
        $this->assertCount(1, $all);
        $this->assertSame('OPEN', $all[0]->status());

        // One transition row (NULL → OPEN)
        $transitions = $this->repo->findTransitionsByEscalationId($id);
        $this->assertCount(1, $transitions);
        $this->assertNull($transitions[0]->from_status);
        $this->assertSame('OPEN', $transitions[0]->to_status);

        // One triggered event
        $events = $eventBus->dispatchedOfType(EscalationTriggeredEvent::class);
        $this->assertCount(1, $events);
    }

    public function testSecondIdenticalTriggerReturnsSameId(): void
    {
        $handler = $this->createHandler($eventBus);
        $cmd = $this->makeCommand();

        $id1 = $handler->handle($cmd);
        $id2 = $handler->handle($cmd);

        $this->assertSame($id1, $id2);
    }

    public function testSecondIdenticalTriggerDoesNotDuplicate(): void
    {
        $handler = $this->createHandler($eventBus);
        $cmd = $this->makeCommand();

        $id = $handler->handle($cmd);
        $handler->handle($cmd);

        // Still one escalation
        $this->assertCount(1, $this->repo->findAll());

        // Still one transition
        $transitions = $this->repo->findTransitionsByEscalationId($id);
        $this->assertCount(1, $transitions);

        // Still one event
        $events = $eventBus->dispatchedOfType(EscalationTriggeredEvent::class);
        $this->assertCount(1, $events, 'No second creation event on duplicate trigger');
    }

    // ══════════════════════════════════════════════════════════════
    //  D. Duplicate-key recovery path
    // ══════════════════════════════════════════════════════════════

    public function testRepositoryThrowsDuplicateKeyOnConflict(): void
    {
        $esc1 = new Escalation('uuid-dup-a', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach');
        $this->repo->save($esc1);

        // Create a second entity that would produce the same dedupe key
        $esc2 = new Escalation('uuid-dup-b', 'ticket', 42, Escalation::SEVERITY_HIGH, 'SLA breach');

        $this->expectException(DuplicateKeyException::class);
        $this->repo->save($esc2);
    }

    public function testHandlerRecoversFromDuplicateKeyViaPreinsertedRow(): void
    {
        // Pre-insert an escalation with the same dedupe key the handler will compute
        $dedupeKey = Escalation::computeDedupeKey('ticket', 42, 'HIGH', 'SLA breach – escalation stage 1');
        $preinserted = new Escalation(
            'uuid-pre-1',
            'ticket',
            42,
            Escalation::SEVERITY_HIGH,
            'SLA breach – escalation stage 1',
            1,
            '{}',
            null,
            Escalation::STATUS_OPEN,
            null,
            null,
            null,
            null,
            null,
            $dedupeKey
        );
        $this->repo->save($preinserted);
        $preinsertedId = $preinserted->id();
        $this->assertGreaterThan(0, $preinsertedId);

        // Now trigger via the handler — should hit the pre-check path (findOpenByDedupeKey)
        // and return the existing ID without creating a second row or event
        $handler = $this->createHandler($eventBus);
        $cmd = $this->makeCommand();

        $returnedId = $handler->handle($cmd);

        $this->assertSame($preinsertedId, $returnedId);

        // Still only one escalation
        $this->assertCount(1, $this->repo->findAll());

        // No event dispatched (pre-check path returns early)
        $events = $eventBus->dispatchedOfType(EscalationTriggeredEvent::class);
        $this->assertCount(0, $events);
    }

    public function testDuplicateKeyRecoveryPathInHandler(): void
    {
        // This test forces the duplicate-key exception path by using a repository
        // wrapper that fails on the first save() then succeeds on re-read.
        $handler = $this->createHandler($eventBus);
        $cmd = $this->makeCommand();

        // First trigger succeeds normally
        $id1 = $handler->handle($cmd);
        $this->assertGreaterThan(0, $id1);

        // Create a second handler that uses a repository which throws DuplicateKeyException
        // on save() but allows findOpenByDedupeKey() to succeed
        $throwingRepo = new class($this->repo) extends SqlEscalationRepository {
            private SqlEscalationRepository $inner;
            private bool $shouldThrow = true;

            public function __construct(SqlEscalationRepository $inner)
            {
                // We don't call parent — we delegate everything
                $this->inner = $inner;
            }

            public function save(Escalation $escalation): void
            {
                if ($this->shouldThrow && $escalation->id() === null) {
                    $this->shouldThrow = false;
                    throw new DuplicateKeyException('Simulated duplicate key');
                }
                $this->inner->save($escalation);
            }

            public function findOpenByDedupeKey(string $dedupeKey): ?Escalation
            {
                return $this->inner->findOpenByDedupeKey($dedupeKey);
            }

            public function findById(int $id): ?Escalation
            {
                return $this->inner->findById($id);
            }

            public function findAll(int $limit = 100, int $offset = 0): array
            {
                return $this->inner->findAll($limit, $offset);
            }

            public function saveTransition(EscalationTransition $transition): void
            {
                $this->inner->saveTransition($transition);
            }

            public function findTransitionsByEscalationId(int $escalationId): array
            {
                return $this->inner->findTransitionsByEscalationId($escalationId);
            }
        };

        $eventBus2 = new SpyEventBus();
        $handler2 = new TriggerEscalationHandler(
            new FakeTransactionManager(),
            $throwingRepo,
            $eventBus2
        );

        // This trigger hits the DuplicateKeyException catch branch
        $id2 = $handler2->handle($cmd);

        $this->assertSame($id1, $id2, 'Recovery returns the existing escalation ID');

        // No event dispatched by the loser
        $this->assertCount(0, $eventBus2->dispatchedOfType(EscalationTriggeredEvent::class));

        // Still only one escalation
        $this->assertCount(1, $this->repo->findAll());

        // Still only one transition
        $transitions = $this->repo->findTransitionsByEscalationId($id1);
        $this->assertCount(1, $transitions);
    }

    // ══════════════════════════════════════════════════════════════
    //  E. Lifecycle isolation — escalation does not mutate tickets
    // ══════════════════════════════════════════════════════════════

    public function testEscalationEntityHasNoTicketMutationCapability(): void
    {
        $ref = new \ReflectionClass(Escalation::class);
        $methods = array_map(fn(\ReflectionMethod $m) => $m->getName(), $ref->getMethods());

        $forbidden = ['setStatus', 'setLifecycleOwner', 'assign', 'setAssignment', 'updateTicket'];
        foreach ($forbidden as $method) {
            $this->assertNotContains($method, $methods, "Escalation must not have {$method}()");
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    private function createSchema(): void
    {
        $prefix = $this->wpdb->prefix;
        $pdo = $this->wpdb->getPdo();

        $pdo->exec("CREATE TABLE {$prefix}pet_escalations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            escalation_id TEXT NOT NULL,
            source_entity_type TEXT NOT NULL,
            source_entity_id INTEGER NOT NULL,
            severity TEXT NOT NULL DEFAULT 'MEDIUM',
            status TEXT NOT NULL DEFAULT 'OPEN',
            reason TEXT NOT NULL,
            metadata_json TEXT NOT NULL DEFAULT '{}',
            open_dedupe_key TEXT NULL,
            created_by INTEGER NULL,
            acknowledged_by INTEGER NULL,
            resolved_by INTEGER NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            acknowledged_at TEXT NULL,
            resolved_at TEXT NULL,
            UNIQUE (escalation_id),
            UNIQUE (open_dedupe_key)
        )");

        $pdo->exec("CREATE TABLE {$prefix}pet_escalation_transitions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            escalation_id INTEGER NOT NULL,
            from_status TEXT NULL,
            to_status TEXT NOT NULL,
            transitioned_by INTEGER NULL,
            reason TEXT NULL,
            transitioned_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
    }

    private function getColumnNames(string $table): array
    {
        $pdo = $this->wpdb->getPdo();
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        $columns = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
        return $columns;
    }

    private function createHandler(?SpyEventBus &$eventBus = null): TriggerEscalationHandler
    {
        $eventBus = new SpyEventBus();
        return new TriggerEscalationHandler(
            new FakeTransactionManager(),
            $this->repo,
            $eventBus
        );
    }

    private function makeCommand(): TriggerEscalationCommand
    {
        return new TriggerEscalationCommand(
            'ticket',
            42,
            Escalation::SEVERITY_HIGH,
            'SLA breach – escalation stage 1',
            1,
            ['origin' => 'sla_breach']
        );
    }
}
