<?php

namespace Pet\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Pet\Domain\Feed\Entity\FeedReaction;
use Pet\Domain\Feed\Repository\FeedReactionRepository;

class SqlFeedReactionRepository implements FeedReactionRepository
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function save(FeedReaction $reaction): void
    {
        $table = $this->wpdb->prefix . 'pet_feed_reactions';
        $data = [
            'id' => $reaction->getId(),
            'feed_event_id' => $reaction->getFeedEventId(),
            'user_id' => $reaction->getUserId(),
            'reaction_type' => $reaction->getReactionType(),
            'created_at' => $reaction->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        // Use replace to handle potential duplicates (though logic should prevent it)
        $this->wpdb->replace($table, $data);
    }

    public function findByEventAndUser(string $feedEventId, string $userId): ?FeedReaction
    {
        $table = $this->wpdb->prefix . 'pet_feed_reactions';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE feed_event_id = %s AND user_id = %s",
            $feedEventId,
            $userId
        ));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByEventId(string $feedEventId): array
    {
        $table = $this->wpdb->prefix . 'pet_feed_reactions';
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE feed_event_id = %s ORDER BY created_at ASC",
            $feedEventId
        ));

        return array_map([$this, 'mapRowToEntity'], $results);
    }

    private function mapRowToEntity($row): FeedReaction
    {
        return new FeedReaction(
            $row->id,
            $row->feed_event_id,
            $row->user_id,
            $row->reaction_type,
            new DateTimeImmutable($row->created_at)
        );
    }
}
