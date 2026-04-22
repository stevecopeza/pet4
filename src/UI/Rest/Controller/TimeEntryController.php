<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Time\Command\LogTimeCommand;
use Pet\Application\Time\Command\LogTimeHandler;
use Pet\Application\Time\Command\UpdateDraftTimeEntryCommand;
use Pet\Application\Time\Command\UpdateDraftTimeEntryHandler;
use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
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
    private EmployeeRepository $employeeRepository;
    private \wpdb $wpdb;
    private bool $billingTablesAvailable;

    public function __construct(
        TimeEntryRepository $timeEntryRepository,
        LogTimeHandler $logTimeHandler,
        UpdateDraftTimeEntryHandler $updateDraftHandler,
        EmployeeRepository $employeeRepository,
        \wpdb $wpdb
    ) {
        $this->timeEntryRepository = $timeEntryRepository;
        $this->logTimeHandler = $logTimeHandler;
        $this->updateDraftHandler = $updateDraftHandler;
        $this->employeeRepository = $employeeRepository;
        $this->wpdb = $wpdb;

        $itemsTable = $this->wpdb->prefix . 'pet_billing_export_items';
        $exportsTable = $this->wpdb->prefix . 'pet_billing_exports';
        $this->billingTablesAvailable =
            ($this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $itemsTable)) === $itemsTable)
            && ($this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $exportsTable)) === $exportsTable);
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

        $billingByEntryId = $this->loadBillingLinkageByEntryId($entries);
        $data = array_map(function ($entry) use ($billingByEntryId) {
            $billingOverlay = $this->deriveBillingOverlay(
                $entry,
                isset($billingByEntryId[$entry->id()]) ? $billingByEntryId[$entry->id()] : null
            );
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
                'billingStatus' => $billingOverlay['billingStatus'],
                'billingBlockReason' => $billingOverlay['billingBlockReason'],
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
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
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

            // Ownership check: only the employee who owns the entry may correct it.
            // If the caller has an employee record, it must match the entry's employee.
            // Callers with manage_options but no employee record are permitted (pure admins).
            $callerEmployee = $this->employeeRepository->findByWpUserId(get_current_user_id());
            if ($callerEmployee !== null && $callerEmployee->id() !== $original->employeeId()) {
                return new WP_REST_Response(['error' => 'You may only correct your own time entries.'], 403);
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
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 422);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
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
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 500);
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
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 422);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    /**
     * @param TimeEntry[] $entries
     * @return array<int, array{exportStatus:?string}>
     */
    private function loadBillingLinkageByEntryId(array $entries): array
    {
        if (!$this->billingTablesAvailable || empty($entries)) {
            return [];
        }

        $entryIds = array_values(array_map(static fn (TimeEntry $entry): int => $entry->id(), $entries));
        $placeholders = implode(', ', array_fill(0, count($entryIds), '%d'));
        $itemsTable = $this->wpdb->prefix . 'pet_billing_export_items';
        $exportsTable = $this->wpdb->prefix . 'pet_billing_exports';
        $sql = "
            SELECT bei.source_id AS time_entry_id, be.status AS export_status
            FROM {$itemsTable} bei
            LEFT JOIN {$exportsTable} be ON be.id = bei.export_id
            WHERE bei.source_type = %s
              AND bei.source_id IN ({$placeholders})
            ORDER BY bei.id DESC
        ";
        $query = $this->wpdb->prepare($sql, ...array_merge(['time_entry'], $entryIds));
        $rows = $this->wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $linkedById = [];
        foreach ($rows as $row) {
            $timeEntryId = isset($row['time_entry_id']) ? (int) $row['time_entry_id'] : 0;
            if ($timeEntryId <= 0 || isset($linkedById[$timeEntryId])) {
                continue;
            }

            $linkedById[$timeEntryId] = [
                'exportStatus' => isset($row['export_status']) ? (string) $row['export_status'] : null,
            ];
        }

        return $linkedById;
    }

    /**
     * @param array{exportStatus:?string}|null $billingLink
     * @return array{billingStatus:string,billingBlockReason:?string}
     */
    private function deriveBillingOverlay(TimeEntry $entry, ?array $billingLink): array
    {
        if (!$entry->isBillable()) {
            return [
                'billingStatus' => 'non_billable',
                'billingBlockReason' => null,
            ];
        }

        if ($billingLink !== null) {
            return [
                'billingStatus' => 'billed',
                'billingBlockReason' => null,
            ];
        }

        $blockReason = $this->resolveBillingBlockReason($entry);
        if ($blockReason !== null) {
            return [
                'billingStatus' => 'blocked',
                'billingBlockReason' => $blockReason,
            ];
        }

        return [
            'billingStatus' => 'ready',
            'billingBlockReason' => null,
        ];
    }

    private function resolveBillingBlockReason(TimeEntry $entry): ?string
    {
        if ($entry->archivedAt() !== null) {
            return 'Archived entries cannot be billed.';
        }

        if (trim($entry->description()) === '') {
            return 'Description is required before billing.';
        }

        if ($entry->durationMinutes() <= 0) {
            return 'Duration must be greater than zero.';
        }

        $normalizedStatus = strtolower(trim($entry->status()));
        if (!in_array($normalizedStatus, ['approved', 'locked'], true)) {
            return sprintf('Status "%s" is not billing-ready.', $entry->status());
        }

        return null;
    }
}
