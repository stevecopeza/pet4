<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\Repository;

use Pet\Domain\Conversation\Entity\Decision;

interface DecisionRepository
{
    public function save(Decision $decision): void;
    public function findById(int $id): ?Decision;
    public function findByUuid(string $uuid): ?Decision;
    public function findByConversationId(int $conversationId): array;
    public function findByUuidForUpdate(string $uuid): ?Decision;
    public function findPendingByUserId(int $userId): array;
}
