<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Identity\Service\StaffEmployeeResolver;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Application\Time\Command\LogTimeCommand;
use Pet\Application\Time\Command\LogTimeHandler;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\UI\Rest\Validation\InputValidation as V;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class StaffTimeCaptureController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'staff/time-capture';

    public function __construct(
        private FeatureFlagService $featureFlags,
        private StaffEmployeeResolver $staffEmployeeResolver,
        private TimeEntryRepository $timeEntryRepository,
        private TicketRepository $ticketRepository,
        private WorkItemRepository $workItemRepository,
        private LogTimeHandler $logTimeHandler
    ) {
    }

    public function registerRoutes(): void
    {
        if (!$this->featureFlags->isStaffTimeCaptureEnabled()) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/context', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getContext'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/entries', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getEntries'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createEntry'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'ticketId' => V::requiredIntArg(),
                    'start' => V::requiredDatetimeArg(),
                    'end' => V::requiredDatetimeArg(),
                    'isBillable' => V::requiredBoolArg(),
                    'description' => V::requiredStringArg(),
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return $this->featureFlags->isStaffTimeCaptureEnabled() && is_user_logged_in();
    }

    public function getContext(WP_REST_Request $request): WP_REST_Response
    {
        $identity = $this->resolveIdentityResponse();
        if ($identity !== null) {
            return $identity;
        }

        $wpUserId = (int) get_current_user_id();
        $resolved = $this->staffEmployeeResolver->resolve($wpUserId);
        $employee = $resolved['employee'];
        $entries = $this->timeEntryRepository->findByEmployeeId((int) $employee->id());
        $ticketSuggestions = $this->resolveTicketSuggestions($wpUserId);

        $recent = array_slice($entries, 0, 5);
        $recentSuggestions = array_map([$this, 'serializeEntry'], $recent);

        return new WP_REST_Response([
            'employee' => [
                'id' => $employee->id(),
                'wpUserId' => $employee->wpUserId(),
                'firstName' => $employee->firstName(),
                'lastName' => $employee->lastName(),
                'displayName' => $employee->fullName(),
                'status' => $employee->status(),
            ],
            'ticketSuggestions' => $ticketSuggestions,
            'recentEntrySuggestions' => $recentSuggestions,
        ], 200);
    }

    public function getEntries(WP_REST_Request $request): WP_REST_Response
    {
        $identity = $this->resolveIdentityResponse();
        if ($identity !== null) {
            return $identity;
        }

        $resolved = $this->staffEmployeeResolver->resolve((int) get_current_user_id());
        $employee = $resolved['employee'];
        $entries = $this->timeEntryRepository->findByEmployeeId((int) $employee->id());

        return new WP_REST_Response(array_map([$this, 'serializeEntry'], $entries), 200);
    }

    public function createEntry(WP_REST_Request $request): WP_REST_Response
    {
        $identity = $this->resolveIdentityResponse();
        if ($identity !== null) {
            return $identity;
        }

        $resolved = $this->staffEmployeeResolver->resolve((int) get_current_user_id());
        $employee = $resolved['employee'];
        $params = $request->get_json_params();

        try {
            $command = new LogTimeCommand(
                (int) $employee->id(),
                (int) ($params['ticketId'] ?? 0),
                new \DateTimeImmutable((string) ($params['start'] ?? '')),
                new \DateTimeImmutable((string) ($params['end'] ?? '')),
                (bool) ($params['isBillable'] ?? false),
                (string) ($params['description'] ?? ''),
                is_array($params['malleableData'] ?? null) ? $params['malleableData'] : []
            );

            $entryId = $this->logTimeHandler->handle($command);

            return new WP_REST_Response(['message' => 'Time logged', 'id' => $entryId], 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    private function resolveIdentityResponse(): ?WP_REST_Response
    {
        $resolved = $this->staffEmployeeResolver->resolve((int) get_current_user_id());
        if ($resolved['ok']) {
            return null;
        }

        return new WP_REST_Response([
            'error' => $resolved['message'],
            'code' => $resolved['code'],
        ], 403);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveTicketSuggestions(int $wpUserId): array
    {
        $workItems = $this->workItemRepository->findByAssignedUser((string) $wpUserId);
        $tickets = [];

        foreach ($workItems as $workItem) {
            if ($workItem->getSourceType() !== 'ticket') {
                continue;
            }

            $ticketId = (int) $workItem->getSourceId();
            if ($ticketId <= 0 || isset($tickets[$ticketId])) {
                continue;
            }

            $ticket = $this->ticketRepository->findById($ticketId);
            if ($ticket === null || !$ticket->canAcceptTimeEntries()) {
                continue;
            }

            $tickets[$ticketId] = [
                'id' => $ticket->id(),
                'subject' => $ticket->subject(),
                'status' => $ticket->status(),
                'lifecycleOwner' => $ticket->lifecycleOwner(),
                'isBillableDefault' => $ticket->isBillableDefault(),
                'isRollup' => $ticket->isRollup(),
            ];
        }

        return array_values($tickets);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntry(TimeEntry $entry): array
    {
        return [
            'id' => $entry->id(),
            'employeeId' => $entry->employeeId(),
            'ticketId' => $entry->ticketId(),
            'start' => $entry->start()->format('Y-m-d H:i:s'),
            'end' => $entry->end()->format('Y-m-d H:i:s'),
            'duration' => $entry->durationMinutes(),
            'description' => $entry->description(),
            'billable' => $entry->isBillable(),
            'status' => $entry->status(),
            'malleableData' => $entry->malleableData(),
            'correctsEntryId' => $entry->correctsEntryId(),
            'isCorrection' => $entry->isCorrection(),
            'createdAt' => $entry->createdAt() ? $entry->createdAt()->format('Y-m-d H:i:s') : null,
            'archivedAt' => $entry->archivedAt() ? $entry->archivedAt()->format('Y-m-d H:i:s') : null,
        ];
    }
}
