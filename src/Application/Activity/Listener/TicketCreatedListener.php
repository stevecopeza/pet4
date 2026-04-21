<?php

declare(strict_types=1);

namespace Pet\Application\Activity\Listener;

use Pet\Domain\Activity\Entity\ActivityLog;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Support\Event\TicketCreated;

class TicketCreatedListener
{
    private $activityLogRepository;

    public function __construct(ActivityLogRepository $activityLogRepository)
    {
        $this->activityLogRepository = $activityLogRepository;
    }

    public function __invoke(TicketCreated $event): void
    {
        $ticket = $event->ticket();
        if ($ticket->lifecycleOwner() === 'project' && $ticket->quoteId() !== null) {
            return;
        }
        
        // Idempotency Guard
        $existingLogs = $this->activityLogRepository->findByRelatedEntity('ticket', $ticket->id());
        foreach ($existingLogs as $log) {
            if ($log->type() === 'ticket_created') {
                return;
            }
        }

        $userId = get_current_user_id(); // WordPress function

        $log = new ActivityLog(
            'ticket_created',
            sprintf('Ticket "%s" created', $ticket->subject()),
            $userId ? (int)$userId : null,
            'ticket',
            $ticket->id()
        );

        $this->activityLogRepository->save($log);
    }
}
