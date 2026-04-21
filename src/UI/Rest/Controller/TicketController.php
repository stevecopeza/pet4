<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Support\Command\CreateTicketCommand;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\UpdateTicketCommand;
use Pet\Application\Support\Command\UpdateTicketHandler;
use Pet\Application\Support\Command\DeleteTicketCommand;
use Pet\Application\Support\Command\DeleteTicketHandler;
use Pet\Application\Support\Command\AssignTicketToTeamCommand;
use Pet\Application\Support\Command\AssignTicketToTeamHandler;
use Pet\Application\Support\Command\AssignTicketToUserCommand;
use Pet\Application\Support\Command\AssignTicketToUserHandler;
use Pet\Application\Support\Command\PullTicketCommand;
use Pet\Application\Support\Command\PullTicketHandler;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Support\ValueObject\TicketStatus;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\UI\Rest\Validation\InputValidation as V;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class TicketController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'tickets';

    private TicketRepository $ticketRepository;
    private CreateTicketHandler $createTicketHandler;
    private UpdateTicketHandler $updateTicketHandler;
    private DeleteTicketHandler $deleteTicketHandler;
    private AssignTicketToTeamHandler $assignTicketToTeamHandler;
    private AssignTicketToUserHandler $assignTicketToUserHandler;
    private PullTicketHandler $pullTicketHandler;
    private WorkItemRepository $workItemRepository;
    private FeatureFlagService $featureFlags;

    public function __construct(
        TicketRepository $ticketRepository,
        CreateTicketHandler $createTicketHandler,
        UpdateTicketHandler $updateTicketHandler,
        DeleteTicketHandler $deleteTicketHandler,
        AssignTicketToTeamHandler $assignTicketToTeamHandler,
        AssignTicketToUserHandler $assignTicketToUserHandler,
        PullTicketHandler $pullTicketHandler,
        WorkItemRepository $workItemRepository,
        FeatureFlagService $featureFlags
    ) {
        $this->ticketRepository = $ticketRepository;
        $this->createTicketHandler = $createTicketHandler;
        $this->updateTicketHandler = $updateTicketHandler;
        $this->deleteTicketHandler = $deleteTicketHandler;
        $this->assignTicketToTeamHandler = $assignTicketToTeamHandler;
        $this->assignTicketToUserHandler = $assignTicketToUserHandler;
        $this->pullTicketHandler = $pullTicketHandler;
        $this->workItemRepository = $workItemRepository;
        $this->featureFlags = $featureFlags;
    }

    public function registerRoutes(): void
    {
        // Gated by Feature Flag - Returns 404 if disabled (route not registered)
        if (!$this->featureFlags->isHelpdeskEnabled()) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getTickets'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => [
                    'customer_id' => V::optionalIntArg(),
                    'project_id' => V::optionalIntArg(),
                    'status' => ['required' => false, 'sanitize_callback' => [V::class, 'sanitizeString']],
                    'ticket_mode' => ['required' => false, 'sanitize_callback' => [V::class, 'sanitizeString']],
                    'lifecycle_owner' => ['required' => false, 'sanitize_callback' => [V::class, 'sanitizeString']],
                    'assigned_user_id' => ['required' => false, 'sanitize_callback' => [V::class, 'sanitizeString']],
                    'assigned' => ['required' => false, 'sanitize_callback' => [V::class, 'sanitizeString']],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createTicket'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'customerId' => V::requiredIntArg(),
                    'subject' => V::requiredStringArg(),
                    'description' => V::requiredTextareaArg(),
                    'priority' => [
                        'required' => false,
                        'default' => 'medium',
                        'sanitize_callback' => [V::class, 'sanitizeString'],
                        'validate_callback' => [V::class, 'validatePriority'],
                    ],
                    'source' => [
                        'required' => true,
                        'sanitize_callback' => [V::class, 'sanitizeString'],
                        'validate_callback' => [V::class, 'validateIntakeSource'],
                    ],
                    'siteId' => V::optionalIntArg(),
                    'slaId' => V::optionalIntArg(),
                    'contactId' => V::optionalIntArg(),
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateTicket'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'subject' => V::requiredStringArg(),
                    'description' => V::requiredTextareaArg(),
                    'priority' => [
                        'required' => true,
                        'sanitize_callback' => [V::class, 'sanitizeString'],
                        'validate_callback' => [V::class, 'validatePriority'],
                    ],
                    'status' => V::requiredStringArg(),
                    'siteId' => V::optionalIntArg(),
                    'slaId' => V::optionalIntArg(),
                ],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/status-options', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getStatusOptions'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => [
                    'lifecycle_owner' => [
                        'required' => false,
                        'default' => 'support',
                        'sanitize_callback' => [V::class, 'sanitizeString'],
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/assign/team', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'assignToTeam'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'queueId' => V::requiredStringArg(),
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/assign/employee', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'assignToEmployee'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'employeeUserId' => V::requiredStringArg(),
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/return-to-queue', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'returnToQueue'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'queueId' => V::requiredStringArg(),
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/reassign', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'reassignTicket'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'employeeUserId' => V::requiredStringArg(),
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/pull', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'pullTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // Close action — sets ticket status to 'resolved' preserving all other fields.
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/close', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'closeTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function checkReadPermission(): bool
    {
        return \Pet\UI\Rest\Support\PortalPermissionHelper::check('pet_sales', 'pet_hr', 'pet_manager');
    }

    public function getTickets(WP_REST_Request $request): WP_REST_Response
    {
        $profileToken = $this->beginBenchmarkWorkloadProfile('ticket.list');
        $customerId = $request->get_param('customer_id');
        $projectId = $request->get_param('project_id');
        $status = $request->get_param('status');
        if (is_string($status)) {
            $status = trim($status);
            if ($status === '') {
                $status = null;
            }
        }
        $ticketMode = $request->get_param('ticket_mode');
        $lifecycleOwner = $request->get_param('lifecycle_owner');
        $assignedUserId = $request->get_param('assigned_user_id');
        $assigned = $request->get_param('assigned');
        $unassigned = $request->get_param('unassigned');

        if ($customerId) {
            $tickets = $this->ticketRepository->findByCustomerId((int)$customerId);
        } elseif ($status === 'active') {
            $tickets = $this->ticketRepository->findActive();
        } else {
            $tickets = $this->ticketRepository->findAll();
        }

        if ($projectId) {
            $projectId = (int) $projectId;
            $tickets = array_filter($tickets, function ($ticket) use ($projectId) {
                return $ticket->projectId() === $projectId;
            });
        }

        if ($status && $status !== 'active') {
            $tickets = array_filter($tickets, function ($ticket) use ($status) {
                return $ticket->status() === $status;
            });
        }

        if ($lifecycleOwner) {
            $tickets = array_filter($tickets, function ($ticket) use ($lifecycleOwner) {
                return $ticket->lifecycleOwner() === $lifecycleOwner;
            });
        }

        // Backward compat: filter by ticket_mode from malleable data (legacy tickets)
        if ($ticketMode) {
            $tickets = array_filter($tickets, function ($ticket) use ($ticketMode) {
                $data = $ticket->malleableData();
                $mode = $data['ticket_mode'] ?? 'support';
                return $mode === $ticketMode;
            });
        }

        $ticketAssignments = $this->loadTicketAssignments();

        if ($assignedUserId || $unassigned || $assigned) {
            $tickets = array_filter($tickets, function ($ticket) use ($assignedUserId, $unassigned, $assigned, $ticketAssignments) {
                $id = $ticket->id();
                $assignment = $ticketAssignments[$id] ?? null;

                if ($unassigned && !$assignedUserId) {
                    return $assignment === null || $assignment === '';
                }

                if ($assignedUserId === 'unassigned') {
                    return $assignment === null || $assignment === '';
                }
                if ($assigned && !$assignedUserId) {
                    return $assignment !== null && $assignment !== '';
                }

                if ($assignedUserId) {
                    return $assignment !== null && (string)$assignment === (string)$assignedUserId;
                }

                return true;
            });
        }

        // Batch-load SLA snapshot names for tickets that have one
        global $wpdb;
        $snapshotNames = [];
        $snapshotIds = array_filter(array_unique(array_map(fn($t) => $t->slaSnapshotId(), $tickets)));
        if (!empty($snapshotIds)) {
            $placeholders = implode(',', array_fill(0, count($snapshotIds), '%d'));
            $snapTable = $wpdb->prefix . 'pet_contract_sla_snapshots';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, sla_name_at_binding FROM $snapTable WHERE id IN ($placeholders)",
                ...array_values($snapshotIds)
            ));
            foreach ($rows as $r) {
                $snapshotNames[(int)$r->id] = $r->sla_name_at_binding;
            }
        }

        $data = array_map(function ($ticket) use ($ticketAssignments, $snapshotNames) {
            $malleable = $ticket->malleableData();
            $mode = $malleable['ticket_mode'] ?? 'support';
            $assignedUserId = $ticketAssignments[$ticket->id()] ?? null;
            $snapId = $ticket->slaSnapshotId();

            return [
                'id' => $ticket->id(),
                'customerId' => $ticket->customerId(),
                'siteId' => $ticket->siteId(),
                'slaId' => $ticket->slaId(),
                'slaSnapshotId' => $snapId,
                'slaName' => $snapId ? ($snapshotNames[$snapId] ?? null) : null,
                'subject' => $ticket->subject(),
                'description' => $ticket->description(),
                'status' => $ticket->status(),
                'priority' => $ticket->priority(),
                'ticketMode' => $mode,
                'assignedUserId' => $assignedUserId,
                'category' => $ticket->category(),
                'subcategory' => $ticket->subcategory(),
                'intake_source' => $ticket->intakeSource(),
                'contactId' => $ticket->contactId(),
                'queueId' => $ticket->queueId(),
                'ownerUserId' => $ticket->ownerUserId(),
                'malleableData' => $malleable,
                'createdAt' => $ticket->createdAt()->format('Y-m-d H:i:s'),
                'openedAt' => $ticket->openedAt() ? $ticket->openedAt()->format('Y-m-d H:i:s') : null,
                'closedAt' => $ticket->closedAt() ? $ticket->closedAt()->format('Y-m-d H:i:s') : null,
                'resolvedAt' => $ticket->resolvedAt() ? $ticket->resolvedAt()->format('Y-m-d H:i:s') : null,
                'lifecycleOwner' => $ticket->lifecycleOwner(),
                'isBillableDefault' => $ticket->isBillableDefault(),
                'billingContextType' => $ticket->billingContextType(),
                // Backbone fields
                'soldMinutes' => $ticket->soldMinutes(),
                'estimatedMinutes' => $ticket->estimatedMinutes(),
                'isBaselineLocked' => $ticket->isBaselineLocked(),
                'isRollup' => $ticket->isRollup(),
                'parentTicketId' => $ticket->parentTicketId(),
                'rootTicketId' => $ticket->rootTicketId(),
                'changeOrderSourceTicketId' => $ticket->changeOrderSourceTicketId(),
                'projectId' => $ticket->projectId(),
                'quoteId' => $ticket->quoteId(),
                'ticketKind' => $ticket->ticketKind(),
                'soldValueCents' => $ticket->soldValueCents(),
                'sourceType' => $ticket->sourceType(),
                'sourceComponentId' => $ticket->sourceComponentId(),
            ];
        }, $tickets);
        $this->endBenchmarkWorkloadProfile($profileToken);

        return new WP_REST_Response(array_values($data), 200);
    }

    /**
     * @return array{run_id:int, workload_key:string, query_count_start:int, started_at:float}|null
     */
    private function beginBenchmarkWorkloadProfile(string $workloadKey): ?array
    {
        $activeRunId = $this->activeBenchmarkRunId();
        if ($activeRunId === null) {
            return null;
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return null;
        }

        return [
            'run_id' => $activeRunId,
            'workload_key' => $workloadKey,
            'query_count_start' => $this->queryCount($wpdb),
            'started_at' => microtime(true),
        ];
    }

    /**
     * @param array{run_id:int, workload_key:string, query_count_start:int, started_at:float}|null $token
     */
    private function endBenchmarkWorkloadProfile(?array $token): void
    {
        if ($token === null) {
            return;
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return;
        }

        $queryDelta = $this->queryCount($wpdb) - (int) $token['query_count_start'];
        if ($queryDelta < 0) {
            $queryDelta = 0;
        }

        $payload = [
            'workload_key' => (string) $token['workload_key'],
            'query_count' => $queryDelta,
            'execution_time_ms' => round((microtime(true) - (float) $token['started_at']) * 1000, 3),
        ];

        $metricsKey = 'pet_performance_workload_metrics_' . (int) $token['run_id'];
        $existing = get_transient($metricsKey);
        $rows = is_array($existing) ? $existing : [];
        $rows[] = $payload;
        set_transient($metricsKey, $rows, 10 * MINUTE_IN_SECONDS);
    }

    private function activeBenchmarkRunId(): ?int
    {
        $value = get_transient('pet_performance_active_run_id');
        if ($value === false || $value === null || !is_numeric($value)) {
            return null;
        }

        $runId = (int) $value;
        return $runId > 0 ? $runId : null;
    }

    private function queryCount(\wpdb $wpdb): int
    {
        if (property_exists($wpdb, 'num_queries') && is_numeric($wpdb->num_queries)) {
            return (int) $wpdb->num_queries;
        }
        if (defined('SAVEQUERIES') && SAVEQUERIES && property_exists($wpdb, 'queries') && is_array($wpdb->queries)) {
            return count($wpdb->queries);
        }
        return 0;
    }

    /**
     * Return a deterministic ticket assignment map without hydrating WorkItem entities.
     *
     * Some legacy/seeded datasets may contain work-item assignment combinations that are
     * invalid for strict WorkItem domain hydration, but ticket listing only needs the
     * assigned_user_id projection for filtering and response decoration.
     *
     * @return array<int, string|null>
     */
    private function loadTicketAssignments(): array
    {
        global $wpdb;

        if (!$wpdb || !isset($wpdb->prefix)) {
            return [];
        }

        $workTable = $wpdb->prefix . 'pet_work_items';
        $query = $wpdb->prepare(
            "SELECT source_id, assigned_user_id FROM $workTable WHERE source_type = %s",
            'ticket'
        );
        $rows = $wpdb->get_results($query);
        if (!is_array($rows)) {
            return [];
        }

        $ticketAssignments = [];
        foreach ($rows as $row) {
            if (!isset($row->source_id)) {
                continue;
            }
            $ticketId = (int)$row->source_id;
            if ($ticketId <= 0) {
                continue;
            }
            $assignedUserId = isset($row->assigned_user_id) ? (string)$row->assigned_user_id : null;
            $ticketAssignments[$ticketId] = ($assignedUserId === '') ? null : $assignedUserId;
        }

        return $ticketAssignments;
    }

    public function createTicket(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $malleableData = $params['malleableData'] ?? [];

            $category = $params['category'] ?? null;
            $subcategory = $params['subcategory'] ?? null;
            $source = $params['source'] ?? null;
            $contactId = isset($params['contactId']) ? (int)$params['contactId'] : null;

            if ($source === null) {
                return new WP_REST_Response(['error' => 'Missing required fields'], 400);
            }

            $allowedSources = ['portal', 'email', 'phone', 'api', 'monitoring'];
            if (!in_array($source, $allowedSources, true)) {
                return new WP_REST_Response(['error' => 'Invalid source'], 400);
            }

            if ($category !== null && $category !== '') {
                $malleableData['category'] = $category;
            }

            if ($subcategory !== null && $subcategory !== '') {
                $malleableData['subcategory'] = $subcategory;
            }
            $malleableData['intake_source'] = $source;
            if ($contactId !== null) {
                $malleableData['contact_id'] = $contactId;
            }

            $assignment = $params['assignment'] ?? null;
            if (is_string($assignment) && $assignment !== '') {
                if (strpos($assignment, 'queue:') === 0) {
                    $queueId = substr($assignment, 6);
                    if ($queueId !== '') {
                        $malleableData['queue_id'] = $queueId;
                    }
                } elseif (strpos($assignment, 'user:') === 0) {
                    $ownerUserId = substr($assignment, 5);
                    if ($ownerUserId !== '') {
                        $malleableData['owner_user_id'] = $ownerUserId;
                    }
                }
            }

            $command = new CreateTicketCommand(
                (int) $params['customerId'],
                isset($params['siteId']) ? (int) $params['siteId'] : null,
                isset($params['slaId']) ? (int) $params['slaId'] : null,
                $params['subject'],
                $params['description'],
                $params['priority'] ?? 'medium',
                $malleableData
            );

            $this->createTicketHandler->handle($command);

            return new WP_REST_Response(['message' => 'Ticket created'], 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to create ticket', 'details' => []]], 500);
        }
    }

    public function updateTicket(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            // Guard: baseline-locked tickets reject sold field mutations
            $existing = $this->ticketRepository->findById($id);
            if ($existing && $existing->isBaselineLocked()) {
                $malleableData = $params['malleableData'] ?? [];
                $forbidden = ['sold_minutes', 'sold_value_cents', 'is_baseline_locked'];
                foreach ($forbidden as $key) {
                    if (array_key_exists($key, $malleableData)) {
                        return new WP_REST_Response(
                            ['error' => "Cannot modify '$key' on a baseline-locked ticket."],
                            400
                        );
                    }
                }
            }

            $command = new UpdateTicketCommand(
                $id,
                isset($params['siteId']) ? (int) $params['siteId'] : null,
                isset($params['slaId']) ? (int) $params['slaId'] : null,
                $params['subject'],
                $params['description'],
                $params['priority'],
                $params['status'],
                $params['malleableData'] ?? []
            );

            $this->updateTicketHandler->handle($command);

            return new WP_REST_Response(['message' => 'Ticket updated'], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to update ticket', 'details' => []]], 500);
        }
    }

    public function getStatusOptions(WP_REST_Request $request): WP_REST_Response
    {
        $lifecycleOwner = $request->get_param('lifecycle_owner') ?? 'support';

        try {
            $statuses = TicketStatus::allForContext($lifecycleOwner);

            // Also provide transition info for each status
            $options = [];
            foreach ($statuses as $status) {
                $vo = TicketStatus::fromString($status, $lifecycleOwner);
                $options[] = [
                    'value' => $status,
                    'label' => ucfirst(str_replace('_', ' ', $status)),
                    'allowedTransitions' => $vo->allowedTransitions($lifecycleOwner),
                    'isTerminal' => $vo->isTerminal($lifecycleOwner),
                ];
            }

            return new WP_REST_Response($options, 200);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function deleteTicket(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new DeleteTicketCommand($id);
            $this->deleteTicketHandler->handle($command);

            return new WP_REST_Response(['message' => 'Ticket deleted'], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to delete ticket', 'details' => []]], 500);
        }
    }

    public function assignToTeam(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['queueId'])) {
            return new WP_REST_Response(['error' => 'queueId is required'], 400);
        }

        try {
            $ticket = $this->assignTicketToTeamHandler->handle(new AssignTicketToTeamCommand($id, (string)$params['queueId']));

            return new WP_REST_Response([
                'message' => 'Ticket assigned to team',
                'queueId' => $ticket->queueId(),
                'ownerUserId' => $ticket->ownerUserId(),
            ], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to assign ticket to team', 'details' => []]], 500);
        }
    }

    public function assignToEmployee(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['employeeUserId'])) {
            return new WP_REST_Response(['error' => 'employeeUserId is required'], 400);
        }

        try {
            $ticket = $this->assignTicketToUserHandler->handle(new AssignTicketToUserCommand($id, (string)$params['employeeUserId']));

            return new WP_REST_Response([
                'message' => 'Ticket assigned to employee',
                'queueId' => $ticket->queueId(),
                'ownerUserId' => $ticket->ownerUserId(),
            ], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to assign ticket to employee', 'details' => []]], 500);
        }
    }

    public function returnToQueue(WP_REST_Request $request): WP_REST_Response
    {
        return $this->assignToTeam($request);
    }

    public function reassignTicket(WP_REST_Request $request): WP_REST_Response
    {
        return $this->assignToEmployee($request);
    }

    public function pullTicket(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $currentUserId = (string) get_current_user_id();
            $ticket = $this->pullTicketHandler->handle(new PullTicketCommand($id, $currentUserId));

            return new WP_REST_Response([
                'message' => 'Ticket pulled',
                'queueId' => $ticket->queueId(),
                'ownerUserId' => $ticket->ownerUserId(),
            ], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to pull ticket', 'details' => []]], 500);
        }
    }

    /**
     * POST /pet/v1/tickets/{id}/close
     *
     * Resolves a ticket without requiring the caller to re-supply all ticket
     * fields. Fetches the current state, sets status → 'resolved', and saves.
     * Body: { resolution?: string }  (stored as malleable data, optional)
     */
    public function closeTicket(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $ticket = $this->ticketRepository->findById($id);
            if (!$ticket) {
                return new WP_REST_Response(['error' => 'Ticket not found'], 404);
            }

            $params = $request->get_json_params() ?? [];
            $malleableData = $ticket->malleableData();
            if (!empty($params['resolution'])) {
                $malleableData['resolution'] = (string) $params['resolution'];
            }

            $command = new UpdateTicketCommand(
                $id,
                $ticket->siteId(),
                $ticket->slaId(),
                $ticket->subject(),
                $ticket->description(),
                $ticket->priority(),
                'resolved',
                $malleableData
            );

            $this->updateTicketHandler->handle($command);

            return new WP_REST_Response(['message' => 'Ticket resolved', 'id' => $id], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to close ticket', 'details' => []]], 500);
        }
    }
}
