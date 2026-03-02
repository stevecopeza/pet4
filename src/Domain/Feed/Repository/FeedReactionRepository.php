<?php

namespace Pet\Domain\Feed\Repository;

use Pet\Domain\Feed\Entity\FeedReaction;

interface FeedReactionRepository
{
    public function save(FeedReaction $reaction): void;
    public function findByEventAndUser(string $feedEventId, string $userId): ?FeedReaction;
    public function findByEventId(string $feedEventId): array;
}
