<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Time\Command\LogTimeCommand;
use Pet\Application\Time\Command\LogTimeHandler;
use Pet\Application\Time\Command\UpdateDraftTimeEntryCommand;
use Pet\Application\Time\Command\UpdateDraftTimeEntryHandler;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\UI\Rest\Validation\InputValidation as V;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class TimeEntryController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'time-entries';

    private TimeEntryRepository $timeEntryRepository;
    private LogTimeHandler $logTimeHandler;
    private UpdateDraftTimeEntryHandler $updateDraftHandler;

    public function __construct(
        TimeEntryRepository $timeEntryRepository,
        LogTimeHandler $logTimeHandler,
        UpdateDraftTimeEntryHandler $updateDraftHandler
    ) {
        $this->timeEntryRepository = $timeEntryRepository;
        $this->logTimeHandler = $logTimeHandler;
        $this->updateDraftHandler = $updateDraftHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getTimeEntries'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'employee_id' => V::optionalIntArg(),
                    'ticket_id' => V::optionalIntArg(),
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'logTime'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'employeeId' => V::requiredIntArg(),
                    'ticketId' => V::requiredIntArg(),
                    'start' => V::requiredDatetimeArg(),
                    'end' => V::requiredDatetimeArg(),
                    'isBillable' => V::requiredBoolArg(),
                    'description' => V::requiredStringArg(),
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateDraftEntry'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'description' => V::requiredStringArg(),
                    'start' => V::requiredDatetimeArg(),
                    'end' => V::requiredDatetimeArg(),
                    'isBillable' => V::requiredBoolArg(),
                ],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveEntry'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/correct', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'correctEntry'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'description' => V::requiredStringArg(),
                    'start' => V::requiredDatetimeArg(),
                    'end' => V::requiredDatetimeArg(),
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getTimeEntries(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = $request->get_param('employee_id');
        $ticketId = $request->get_param('ticket_id');
        
        if ($employeeId) {
            $entries = $this->timeEntryRepository->findByEmployeeId((int) $employeeId);
        } elseif ($ticketId) {
            $entries = $this->timeEntryRepository->findByTicketId((int) $ticketId);
        } else {
            $entries = $this->timeEntryRepository->findAll();
        }

        $data = array_map(function ($entry) {
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
        }, $entries);

        return new WP_REST_Response($data, 200);
    }

    public function logTime(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new LogTimeCommand(
                (int) $params['employeeId'],
                (int) $params['ticketId'],
                new \DateTimeImmutable($params['start']),
                new \DateTimeImmutable($params['end']),
                (bool) $params['isBillable'],
                $params['description'],
                $params['malleableData'] ?? []
            );

            $entryId = $this->logTimeHandler->handle($command);

            return new WP_REST_Response(['message' => 'Time logged', 'id' => $entryId], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function correctEntry(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $original = $this->timeEntryRepository->findById($id);
            if (!$original) {
                return new WP_REST_Response(['error' => 'Time entry not found'], 404);
            }

            $correction = \Pet\Domain\Time\Entity\TimeEntry::createCorrection(
                $original,
                $params['description'] ?? '',
                new \DateTimeImmutable($params['start']),
                new \DateTimeImmutable($params['end']),
                (bool) ($params['isBillable'] ?? $original->isBillable())
            );

            $this->timeEntryRepository->save($correction);

            return new WP_REST_Response([
                'message' => 'Correction entry created',
                'id' => $correction->id(),
                'correctsEntryId' => $original->id(),
            ], 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function archiveEntry(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $entry = $this->timeEntryRepository->findById($id);
            if (!$entry) {
                return new WP_REST_Response(['error' => 'Time entry not found'], 404);
            }

            $entry->archive();
            $this->timeEntryRepository->save($entry);

            return new WP_REST_Response(['message' => 'Time entry archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function updateDraftEntry(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new UpdateDraftTimeEntryCommand(
                $id,
                $params['description'],
                new \DateTimeImmutable($params['start']),
                new \DateTimeImmutable($params['end']),
                (bool) $params['isBillable']
            );

            $this->updateDraftHandler->handle($command);

            return new WP_REST_Response(['message' => 'Draft entry updated'], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
