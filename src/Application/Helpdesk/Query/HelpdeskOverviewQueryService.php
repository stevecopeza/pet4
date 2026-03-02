<?php

declare(strict_types=1);

namespace Pet\Application\Helpdesk\Query;

use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Application\Identity\Directory\UserDirectory;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Feed\Repository\FeedEventRepository;

class HelpdeskOverviewQueryService
{
    private WorkItemRepository $workItemRepository;
    private TicketRepository $ticketRepository;
    private EmployeeRepository $employeeRepository;
    private FeedEventRepository $feedEventRepository;
    private CustomerRepository $customerRepository;
    private UserDirectory $userDirectory;

    public function __construct(
        WorkItemRepository $workItemRepository,
        TicketRepository $ticketRepository,
        EmployeeRepository $employeeRepository,
        FeedEventRepository $feedEventRepository,
        CustomerRepository $customerRepository,
        UserDirectory $userDirectory
    ) {
        $this->workItemRepository = $workItemRepository;
        $this->ticketRepository = $ticketRepository;
        $this->employeeRepository = $employeeRepository;
        $this->feedEventRepository = $feedEventRepository;
        $this->customerRepository = $customerRepository;
        $this->userDirectory = $userDirectory;
    }

    public function getOverview(string $teamMode, int $userId, bool $showFlow): array
    {
        $stats = [
            'open_tickets' => 0,
            'critical_tickets' => 0,
            'at_risk_tickets' => 0,
            'breached_tickets' => 0,
        ];

        $lanes = [
            'critical' => [],
            'risk' => [],
            'normal' => [],
        ];

        $flow = [
            'recent_created' => [],
            'recent_resolved' => [],
        ];

        $allowedDepartmentIds = null;
        if ($teamMode === 'current') {
            $employee = $this->employeeRepository->findByWpUserId($userId);
            if ($employee && $employee->id() !== null) {
                $teamIds = $employee->teamIds();
                $allowedDepartmentIds = array_map('strval', $teamIds);
            } else {
                $allowedDepartmentIds = [];
            }
        }

        $workItems = $this->workItemRepository->findActive();
        $ticketWorkItems = [];
        foreach ($workItems as $item) {
            if ($item->getSourceType() !== 'ticket') {
                continue;
            }
            if (is_array($allowedDepartmentIds)) {
                if (!in_array((string) $item->getDepartmentId(), $allowedDepartmentIds, true)) {
                    continue;
                }
            }
            $ticketId = (int) $item->getSourceId();
            $ticketWorkItems[$ticketId] = $item;
        }

        $tickets = $this->ticketRepository->findActive();

        foreach ($tickets as $ticket) {
            $ticketId = $ticket->id();
            if ($ticketId === null) {
                continue;
            }

            if (is_array($allowedDepartmentIds) && !isset($ticketWorkItems[$ticketId])) {
                continue;
            }

            $stats['open_tickets']++;

            $workItem = $ticketWorkItems[$ticketId] ?? null;

            $minutesRemaining = null;
            if ($workItem) {
                $minutesRemaining = $workItem->getSlaTimeRemainingMinutes();
            }

            $band = 'normal';
            if ($minutesRemaining !== null) {
                if ($minutesRemaining < 0) {
                    $band = 'critical';
                    $stats['breached_tickets']++;
                } elseif ($minutesRemaining < 60) {
                    $band = 'risk';
                }
            }

            if ($band === 'critical') {
                $stats['critical_tickets']++;
            } elseif ($band === 'risk') {
                $stats['at_risk_tickets']++;
            }

            $laneKey = $band === 'critical' ? 'critical' : ($band === 'risk' ? 'risk' : 'normal');

            $customer = $this->customerRepository->findById($ticket->customerId());
            $customerName = $customer ? $customer->name() : 'Unknown Customer';

            $assigneeName = 'Unassigned';
            $assigneeAvatar = '';
            $assigneeUserId = '';
            if ($ticket->ownerUserId()) {
                $ownerId = (int) $ticket->ownerUserId();
                $displayName = $this->userDirectory->getDisplayName($ownerId);

                if ($displayName) {
                    $assigneeName = $displayName;
                    $assigneeAvatar = (string) $this->userDirectory->getAvatarUrl($ownerId);
                    $assigneeUserId = (string) $ownerId;
                }
            }

            $relativeDue = '';
            if ($minutesRemaining !== null) {
                if ($minutesRemaining < 0) {
                    $relativeDue = abs(round($minutesRemaining)) . 'm overdue';
                } else {
                    $relativeDue = round($minutesRemaining) . 'm left';
                }
                
                if (abs($minutesRemaining) >= 60) {
                    $hours = round(abs($minutesRemaining) / 60, 1);
                    $relativeDue = $minutesRemaining < 0 ? $hours . 'h overdue' : $hours . 'h left';
                }
            }

            $lanes[$laneKey][] = [
                'ticket_id' => $ticketId,
                'subject' => $ticket->subject(),
                'band' => $band,
                'customer_name' => $customerName,
                'assignee_name' => $assigneeName,
                'assignee_avatar_url' => $assigneeAvatar,
                'assignee_user_id' => $assigneeUserId,
                'relative_due' => $relativeDue,
            ];
        }

        if ($showFlow) {
            $userIdStr = (string) $userId;
            $deptIdsStr = [];
            
            if (is_array($allowedDepartmentIds)) {
                $deptIdsStr = $allowedDepartmentIds;
            } else {
                 $currentUserEmployee = $this->employeeRepository->findByWpUserId((int) $userIdStr);
                 if ($currentUserEmployee) {
                     $deptIdsStr = array_map('strval', $currentUserEmployee->teamIds());
                 }
            }
            
            $recentEvents = $this->feedEventRepository->findRelevantForUser($userIdStr, $deptIdsStr, [], 20);
            
            foreach ($recentEvents as $event) {
                $type = $event->getEventType();
                if (strpos($type, 'created') !== false || strpos($type, 'opened') !== false) {
                    $flow['recent_created'][] = $event;
                } elseif (strpos($type, 'resolved') !== false || strpos($type, 'closed') !== false) {
                    $flow['recent_resolved'][] = $event;
                }
            }
            
            $flow['recent_created'] = array_slice($flow['recent_created'], 0, 5);
            $flow['recent_resolved'] = array_slice($flow['recent_resolved'], 0, 5);
        }

        return [
            'stats' => $stats,
            'lanes' => $lanes,
            'flow' => $flow,
        ];
    }
}
