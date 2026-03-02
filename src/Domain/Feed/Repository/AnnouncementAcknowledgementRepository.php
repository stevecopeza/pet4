<?php

namespace Pet\Domain\Feed\Repository;

use Pet\Domain\Feed\Entity\AnnouncementAcknowledgement;

interface AnnouncementAcknowledgementRepository
{
    public function save(AnnouncementAcknowledgement $ack): void;
    public function findByAnnouncementAndUser(string $announcementId, string $userId): ?AnnouncementAcknowledgement;
    public function findByAnnouncementId(string $announcementId): array;
}
