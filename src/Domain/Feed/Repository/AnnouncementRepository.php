<?php

namespace Pet\Domain\Feed\Repository;

use Pet\Domain\Feed\Entity\Announcement;

interface AnnouncementRepository
{
    public function save(Announcement $announcement): void;
    public function findById(string $id): ?Announcement;
    
    /**
     * Find active announcements relevant to a user.
     * 
     * @param string $userId
     * @param array $departmentIds
     * @param array $roleIds
     * @param int $limit
     * @return Announcement[]
     */
    public function findRelevantForUser(string $userId, array $departmentIds, array $roleIds, int $limit = 50): array;
}
