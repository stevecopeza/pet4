<?php

namespace Pet\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\FeedEventRepository;

class SqlFeedEventRepository implements FeedEventRepository
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function save(FeedEvent $event): void
    {
        $table = $this->wpdb->prefix . 'pet_feed_events';
        $data = [
            'id' => $event->getId(),
            'event_type' => $event->getEventType(),
            'source_engine' => $event->getSourceEngine(),
            'source_entity_id' => $event->getSourceEntityId(),
            'classification' => $event->getClassification(),
            'title' => $event->getTitle(),
            'summary' => $event->getSummary(),
            'metadata_json' => json_encode($event->getMetadata()),
            'audience_scope' => $event->getAudienceScope(),
            'audience_reference_id' => $event->getAudienceReferenceId(),
            'pinned_flag' => $event->isPinned() ? 1 : 0,
            'expires_at' => $event->getExpiresAt()?->format('Y-m-d H:i:s'),
            'created_at' => $event->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->findById($event->getId());
        if ($existing) {
            $this->wpdb->update($table, $data, ['id' => $event->getId()]);
        } else {
            $this->wpdb->insert($table, $data);
        }
    }

    public function findById(string $id): ?FeedEvent
    {
        $table = $this->wpdb->prefix . 'pet_feed_events';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findRelevantForUser(string $userId, array $departmentIds, array $roleIds, int $limit = 50): array
    {
        $table = $this->wpdb->prefix . 'pet_feed_events';
        
        $whereClauses = ["audience_scope = 'global'"];
        
        // User specific
        $whereClauses[] = $this->wpdb->prepare("(audience_scope = 'user' AND audience_reference_id = %s)", $userId);
        
        // Departments
        if (!empty($departmentIds)) {
            $deptPlaceholders = implode(',', array_fill(0, count($departmentIds), '%s'));
            $whereClauses[] = $this->wpdb->prepare("(audience_scope = 'department' AND audience_reference_id IN ($deptPlaceholders))", ...$departmentIds);
        }
        
        // Roles
        if (!empty($roleIds)) {
            $rolePlaceholders = implode(',', array_fill(0, count($roleIds), '%s'));
            $whereClauses[] = $this->wpdb->prepare("(audience_scope = 'role' AND audience_reference_id IN ($rolePlaceholders))", ...$roleIds);
        }

        $whereSql = implode(' OR ', $whereClauses);
        
        // Also filter out expired events
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $expirationSql = "AND (expires_at IS NULL OR expires_at > '$now')";

        $sql = "SELECT * FROM $table WHERE ($whereSql) $expirationSql ORDER BY pinned_flag DESC, created_at DESC LIMIT %d";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $limit));

        return array_map([$this, 'mapRowToEntity'], $results);
    }

    public function findLatestBySource(string $sourceEngine, string $sourceEntityId, string $eventType): ?FeedEvent
    {
        $table = $this->wpdb->prefix . 'pet_feed_events';
        $sql = "SELECT * FROM $table WHERE source_engine = %s AND source_entity_id = %s AND event_type = %s ORDER BY created_at DESC LIMIT 1";
        $row = $this->wpdb->get_row($this->wpdb->prepare($sql, $sourceEngine, $sourceEntityId, $eventType));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    private function mapRowToEntity($row): FeedEvent
    {
        return new FeedEvent(
            $row->id,
            $row->event_type,
            $row->source_engine,
            $row->source_entity_id,
            $row->classification,
            $row->title,
            $row->summary,
            json_decode($row->metadata_json, true) ?: [],
            $row->audience_scope,
            $row->audience_reference_id,
            (bool)$row->pinned_flag,
            $row->expires_at ? new DateTimeImmutable($row->expires_at) : null,
            new DateTimeImmutable($row->created_at)
        );
    }
}
