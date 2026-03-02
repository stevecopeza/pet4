<?php

namespace Pet\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Pet\Domain\Feed\Entity\Announcement;
use Pet\Domain\Feed\Repository\AnnouncementRepository;

class SqlAnnouncementRepository implements AnnouncementRepository
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function save(Announcement $announcement): void
    {
        $table = $this->wpdb->prefix . 'pet_announcements';
        $data = [
            'id' => $announcement->getId(),
            'title' => $announcement->getTitle(),
            'body' => $announcement->getBody(),
            'priority_level' => $announcement->getPriorityLevel(),
            'pinned_flag' => $announcement->isPinned() ? 1 : 0,
            'acknowledgement_required' => $announcement->isAcknowledgementRequired() ? 1 : 0,
            'gps_required' => $announcement->isGpsRequired() ? 1 : 0,
            'acknowledgement_deadline' => $announcement->getAcknowledgementDeadline()?->format('Y-m-d H:i:s'),
            'audience_scope' => $announcement->getAudienceScope(),
            'audience_reference_id' => $announcement->getAudienceReferenceId(),
            'author_user_id' => $announcement->getAuthorUserId(),
            'expires_at' => $announcement->getExpiresAt()?->format('Y-m-d H:i:s'),
            'created_at' => $announcement->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->findById($announcement->getId());
        if ($existing) {
            $this->wpdb->update($table, $data, ['id' => $announcement->getId()]);
        } else {
            $this->wpdb->insert($table, $data);
        }
    }

    public function findById(string $id): ?Announcement
    {
        $table = $this->wpdb->prefix . 'pet_announcements';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findRelevantForUser(string $userId, array $departmentIds, array $roleIds, int $limit = 50): array
    {
        $table = $this->wpdb->prefix . 'pet_announcements';
        
        $args = [];
        $clauses = ["audience_scope = 'global'"];
        
        if (!empty($departmentIds)) {
            $placeholders = implode(',', array_fill(0, count($departmentIds), '%s'));
            $clauses[] = "(audience_scope = 'department' AND audience_reference_id IN ($placeholders))";
            array_push($args, ...$departmentIds);
        }
        
        if (!empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '%s'));
            $clauses[] = "(audience_scope = 'role' AND audience_reference_id IN ($placeholders))";
            array_push($args, ...$roleIds);
        }
        
        $whereSql = implode(' OR ', $clauses);
        $fullSql = "SELECT * FROM $table WHERE ($whereSql) AND (expires_at IS NULL OR expires_at > %s) ORDER BY pinned_flag DESC, created_at DESC LIMIT %d";
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        array_push($args, $now, $limit);
        
        $preparedSql = $this->wpdb->prepare($fullSql, ...$args);
        $results = $this->wpdb->get_results($preparedSql);

        return array_map([$this, 'mapRowToEntity'], $results);
    }

    private function mapRowToEntity($row): Announcement
    {
        return new Announcement(
            $row->id,
            $row->title,
            $row->body,
            $row->priority_level,
            (bool)$row->pinned_flag,
            (bool)$row->acknowledgement_required,
            (bool)$row->gps_required,
            $row->acknowledgement_deadline ? new DateTimeImmutable($row->acknowledgement_deadline) : null,
            $row->audience_scope,
            $row->audience_reference_id,
            $row->author_user_id,
            $row->expires_at ? new DateTimeImmutable($row->expires_at) : null,
            new DateTimeImmutable($row->created_at)
        );
    }
}
