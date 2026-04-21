<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Projection\Listener;

use Pet\Application\Activity\Listener\TicketCreatedListener;
use Pet\Application\Projection\Listener\FeedProjectionListener;
use Pet\Domain\Activity\Entity\ActivityLog;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Domain\Support\Entity\Ticket;
use Pet\Domain\Support\Event\TicketCreated;
use PHPUnit\Framework\TestCase;

final class TicketLifecycleFeedSuppressionTest extends TestCase
{
    public function testProjectLifecycleTicketCreatedIsSuppressedInActivityProjection(): void
    {
        $activityRepo = new class implements ActivityLogRepository {
            public int $saveCount = 0;
            public int $findByRelatedEntityCount = 0;
            public function save(ActivityLog $log): void { $this->saveCount++; }
            public function findAll(int $limit = 50): array { return []; }
            public function findByRelatedEntity(string $entityType, int $entityId): array
            {
                $this->findByRelatedEntityCount++;
                return [];
            }
        };

        $listener = new TicketCreatedListener($activityRepo);
        $listener(new TicketCreated($this->projectLifecycleTicket(123)));

        self::assertSame(0, $activityRepo->saveCount);
        self::assertSame(0, $activityRepo->findByRelatedEntityCount);
    }

    public function testProjectLifecycleTicketCreatedIsSuppressedInFeedProjection(): void
    {
        $activityRepo = new class implements ActivityLogRepository {
            public int $saveCount = 0;
            public function save(ActivityLog $log): void { $this->saveCount++; }
            public function findAll(int $limit = 50): array { return []; }
            public function findByRelatedEntity(string $entityType, int $entityId): array { return []; }
        };

        $feedRepo = new class implements FeedEventRepository {
            public int $saveCount = 0;
            public int $findLatestBySourceCount = 0;
            public function save(FeedEvent $event): void { $this->saveCount++; }
            public function findById(string $id): ?FeedEvent { return null; }
            public function findRelevantForUser(string $userId, array $departmentIds, array $roleIds, int $limit = 50): array { return []; }
            public function findLatestBySource(string $sourceEngine, string $sourceEntityId, string $eventType): ?FeedEvent
            {
                $this->findLatestBySourceCount++;
                return null;
            }
        };

        $listener = new FeedProjectionListener(
            $activityRepo,
            $feedRepo,
            $this->nullConversationRepository(),
            $this->nullDecisionRepository()
        );

        $listener->onTicketCreated(new TicketCreated($this->projectLifecycleTicket(456)));

        self::assertSame(0, $activityRepo->saveCount);
        self::assertSame(0, $feedRepo->saveCount);
        self::assertSame(0, $feedRepo->findLatestBySourceCount);
    }

    public function testSupportTicketCreatedStillProducesActivityAndFeedEvents(): void
    {
        $activityRepo = new class implements ActivityLogRepository {
            public int $saveCount = 0;
            public function save(ActivityLog $log): void { $this->saveCount++; }
            public function findAll(int $limit = 50): array { return []; }
            public function findByRelatedEntity(string $entityType, int $entityId): array { return []; }
        };

        $feedRepo = new class implements FeedEventRepository {
            public int $saveCount = 0;
            public int $findLatestBySourceCount = 0;
            public function save(FeedEvent $event): void { $this->saveCount++; }
            public function findById(string $id): ?FeedEvent { return null; }
            public function findRelevantForUser(string $userId, array $departmentIds, array $roleIds, int $limit = 50): array { return []; }
            public function findLatestBySource(string $sourceEngine, string $sourceEntityId, string $eventType): ?FeedEvent
            {
                $this->findLatestBySourceCount++;
                return null;
            }
        };

        $listener = new FeedProjectionListener(
            $activityRepo,
            $feedRepo,
            $this->nullConversationRepository(),
            $this->nullDecisionRepository()
        );

        $listener->onTicketCreated(new TicketCreated(new Ticket(
            customerId: 7,
            subject: 'Support ticket',
            description: 'Standard support flow',
            status: 'open',
            priority: 'high',
            id: 789
        )));

        self::assertSame(1, $activityRepo->saveCount);
        self::assertSame(1, $feedRepo->findLatestBySourceCount);
        self::assertSame(1, $feedRepo->saveCount);
    }

    private function projectLifecycleTicket(int $id): Ticket
    {
        return new Ticket(
            customerId: 7,
            subject: 'Project lifecycle ticket',
            description: 'Provisioned from quote acceptance',
            status: 'planned',
            priority: 'medium',
            id: $id,
            intakeSource: 'quote',
            primaryContainer: 'project',
            projectId: 501,
            quoteId: 9001,
            billingContextType: 'project',
            soldMinutes: 120,
            estimatedMinutes: 120,
            lifecycleOwner: 'project',
            isBaselineLocked: true,
            sourceType: 'quote_component',
            sourceComponentId: 3001
        );
    }

    private function nullConversationRepository(): ConversationRepository
    {
        return new class implements ConversationRepository {
            public function save(\Pet\Domain\Conversation\Entity\Conversation $conversation): void {}
            public function findById(int $id): ?\Pet\Domain\Conversation\Entity\Conversation { return null; }
            public function findByUuid(string $uuid): ?\Pet\Domain\Conversation\Entity\Conversation { return null; }
            public function findByContext(string $contextType, string $contextId, ?string $contextVersion = null, ?string $subjectKey = null, bool $strict = false): ?\Pet\Domain\Conversation\Entity\Conversation { return null; }
            public function markAsRead(int $conversationId, int $userId, int $lastEventId): void {}
            public function getUnreadCounts(int $userId): array { return []; }
            public function getTimelineData(int $conversationId, int $limit = 50, ?int $beforeEventId = null): array { return []; }
            public function getParticipants(int $conversationId): array { return []; }
            public function findRecentByUserId(int $userId, int $limit = 10): array { return []; }
            public function isParticipant(int $conversationId, int $userId): bool { return false; }
            public function getInternalParticipantCount(int $conversationId): int { return 0; }
            public function messageExistsInConversation(int $conversationId, int $messageId): bool { return false; }
            public function hasReaction(int $conversationId, int $messageId, int $actorId, string $type): bool { return false; }
            public function findOpenSubjectKeysByContext(string $contextType, string $contextId): array { return []; }
            public function getSummaryForContexts(string $contextType, array $contextIds, int $userId): array { return []; }
        };
    }

    private function nullDecisionRepository(): DecisionRepository
    {
        return new class implements DecisionRepository {
            public function save(\Pet\Domain\Conversation\Entity\Decision $decision): void {}
            public function findById(int $id): ?\Pet\Domain\Conversation\Entity\Decision { return null; }
            public function findByUuid(string $uuid): ?\Pet\Domain\Conversation\Entity\Decision { return null; }
            public function findByConversationId(int $conversationId): array { return []; }
            public function findByUuidForUpdate(string $uuid): ?\Pet\Domain\Conversation\Entity\Decision { return null; }
            public function findPendingByUserId(int $userId): array { return []; }
        };
    }
}
