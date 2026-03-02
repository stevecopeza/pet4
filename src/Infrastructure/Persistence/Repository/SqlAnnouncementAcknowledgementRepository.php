<?php

namespace Pet\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Pet\Domain\Feed\Entity\AnnouncementAcknowledgement;
use Pet\Domain\Feed\Repository\AnnouncementAcknowledgementRepository;

class SqlAnnouncementAcknowledgementRepository implements AnnouncementAcknowledgementRepository
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function save(AnnouncementAcknowledgement $ack): void
    {
        $table = $this->wpdb->prefix . 'pet_announcement_acknowledgements';
        $data = [
            'id' => $ack->getId(),
            'announcement_id' => $ack->getAnnouncementId(),
            'user_id' => $ack->getUserId(),
            'acknowledged_at' => $ack->getAcknowledgedAt()->format('Y-m-d H:i:s'),
            'device_info' => $ack->getDeviceInfo(),
            'gps_lat' => $ack->getGpsLat(),
            'gps_lng' => $ack->getGpsLng(),
        ];

        // Use replace to handle potential duplicates (idempotency)
        $this->wpdb->replace($table, $data);
    }

    public function findByAnnouncementAndUser(string $announcementId, string $userId): ?AnnouncementAcknowledgement
    {
        $table = $this->wpdb->prefix . 'pet_announcement_acknowledgements';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE announcement_id = %s AND user_id = %s",
            $announcementId,
            $userId
        ));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByAnnouncementId(string $announcementId): array
    {
        $table = $this->wpdb->prefix . 'pet_announcement_acknowledgements';
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE announcement_id = %s ORDER BY acknowledged_at ASC",
            $announcementId
        ));

        return array_map([$this, 'mapRowToEntity'], $results);
    }

    private function mapRowToEntity($row): AnnouncementAcknowledgement
    {
        return new AnnouncementAcknowledgement(
            $row->id,
            $row->announcement_id,
            $row->user_id,
            new DateTimeImmutable($row->acknowledged_at),
            $row->device_info,
            $row->gps_lat ? (float)$row->gps_lat : null,
            $row->gps_lng ? (float)$row->gps_lng : null
        );
    }
}
