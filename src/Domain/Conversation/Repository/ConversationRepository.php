<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Repository;

use Pet\Domain\Conversation\Entity\Conversation;

interface ConversationRepository
{
    public function save(Conversation $conversation): void;
    public function findById(int $id): ?Conversation;
    public function findByUuid(string $uuid): ?Conversation;
    public function findByContext(string $contextType, string $contextId, ?string $contextVersion = null, ?string $subjectKey = null, bool $strict = false): ?Conversation;
    public function markAsRead(int $conversationId, int $userId, int $lastEventId): void;
    public function getUnreadCounts(int $userId): array;
    public function getTimelineData(int $conversationId, int $limit = 50, ?int $beforeEventId = null): array;
    public function getParticipants(int $conversationId): array;
    public function findRecentByUserId(int $userId, int $limit = 10): array;
    public function isParticipant(int $conversationId, int $userId): bool;
    public function getInternalParticipantCount(int $conversationId): int;
    public function messageExistsInConversation(int $conversationId, int $messageId): bool;
    public function hasReaction(int $conversationId, int $messageId, int $actorId, string $type): bool;
    public function findOpenSubjectKeysByContext(string $contextType, string $contextId): array;
    public function getSummaryForContexts(string $contextType, array $contextIds, int $userId): array;
}
