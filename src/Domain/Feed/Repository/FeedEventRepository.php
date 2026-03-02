<?php

namespace Pet\Domain\Feed\Repository;

use Pet\Domain\Feed\Entity\FeedEvent;

interface FeedEventRepository
{
    public function save(FeedEvent $event): void;
    public function findById(string $id): ?FeedEvent;
    
    /**
     * Find active feed events relevant to a user.
     * This includes global events, department events, role events, and direct user events.
     * 
     * @param string $userId
     * @param array $departmentIds
     * @param array $roleIds
     * @param int $limit
     * @return FeedEvent[]
     */
    public function findRelevantForUser(string $userId, array $departmentIds, array $roleIds, int $limit = 50): array;

    public function findLatestBySource(string $sourceEngine, string $sourceEntityId, string $eventType): ?FeedEvent;
}
