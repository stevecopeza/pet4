<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

use Pet\Domain\Feed\Entity\Announcement;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\AnnouncementRepository;
use Pet\Domain\Feed\Repository\FeedEventRepository;

class DemoInstaller
{
    private AnnouncementRepository $announcementRepository;
    private FeedEventRepository $feedEventRepository;

    public function __construct(
        AnnouncementRepository $announcementRepository,
        FeedEventRepository $feedEventRepository
    ) {
        $this->announcementRepository = $announcementRepository;
        $this->feedEventRepository = $feedEventRepository;
    }

    public function run(): array
    {
        $created = [
            'announcements' => 0,
            'events' => 0,
        ];

        $adminUserId = (string) get_current_user_id();

        $ann1 = Announcement::create(
            $this->uuid(),
            'Welcome to PET',
            'This environment is pre-seeded for demo purposes.',
            'normal',
            true,
            true,
            false,
            null,
            'global',
            null,
            $adminUserId,
            null
        );
        $this->announcementRepository->save($ann1);
        $created['announcements']++;

        $ann2 = Announcement::create(
            $this->uuid(),
            'Safety Notice',
            'Please review the updated operational handbook.',
            'high',
            false,
            true,
            false,
            null,
            'role',
            'field_technician',
            $adminUserId,
            null
        );
        $this->announcementRepository->save($ann2);
        $created['announcements']++;

        $evt1 = FeedEvent::create(
            $this->uuid(),
            'QuoteAccepted',
            'Commercial',
            'Q-DEM-001',
            'operational',
            'Quote accepted',
            'Quote #Q-DEM-001 was accepted by ACME Corp.',
            ['quoteId' => 'Q-DEM-001', 'customer' => 'ACME Corp'],
            'global',
            null,
            true,
            null
        );
        $this->feedEventRepository->save($evt1);
        $created['events']++;

        $evt2 = FeedEvent::create(
            $this->uuid(),
            'TicketWarning',
            'Support',
            'T-DEM-404',
            'critical',
            'Ticket breached SLA warning threshold',
            'Ticket #T-DEM-404 is nearing breach.',
            ['ticketId' => 'T-DEM-404'],
            'department',
            'support',
            false,
            null
        );
        $this->feedEventRepository->save($evt2);
        $created['events']++;

        return $created;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
