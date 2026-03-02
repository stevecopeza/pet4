<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Support\Command\CreateTicketCommand;
use Pet\Application\Support\Command\CreateTicketHandler;
use Pet\Application\Support\Command\UpdateTicketCommand;
use Pet\Application\Support\Command\UpdateTicketHandler;
use Pet\Application\Support\Command\DeleteTicketCommand;
use Pet\Application\Support\Command\DeleteTicketHandler;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Application\System\Service\FeatureFlagService;
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
    private WorkItemRepository $workItemRepository;
    private FeatureFlagService $featureFlags;

    public function __construct(
        TicketRepository $ticketRepository,
        CreateTicketHandler $createTicketHandler,
        UpdateTicketHandler $updateTicketHandler,
        DeleteTicketHandler $deleteTicketHandler,
        WorkItemRepository $workItemRepository,
        FeatureFlagService $featureFlags
    ) {
        $this->ticketRepository = $ticketRepository;
        $this->createTicketHandler = $createTicketHandler;
        $this->updateTicketHandler = $updateTicketHandler;
        $this->deleteTicketHandler = $deleteTicketHandler;
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
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteTicket'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getTickets(WP_REST_Request $request): WP_REST_Response
    {
        $customerId = $request->get_param('customer_id');
        $status = $request->get_param('status');
        $ticketMode = $request->get_param('ticket_mode');
        $assignedUserId = $request->get_param('assigned_user_id');
        $unassigned = $request->get_param('unassigned');

        if ($customerId) {
            $tickets = $this->ticketRepository->findByCustomerId((int)$customerId);
        } elseif ($status === 'active') {
            $tickets = $this->ticketRepository->findActive();
        } else {
            $tickets = $this->ticketRepository->findAll();
        }

        if ($status && $status !== 'active') {
            $tickets = array_filter($tickets, function ($ticket) use ($status) {
                return $ticket->status() === $status;
            });
        }

        if ($ticketMode) {
            $tickets = array_filter($tickets, function ($ticket) use ($ticketMode) {
                $data = $ticket->malleableData();
                $mode = $data['ticket_mode'] ?? 'support';
                return $mode === $ticketMode;
            });
        }

        $ticketAssignments = [];

        $workItems = $this->workItemRepository->findAll();

        foreach ($workItems as $item) {
            if ($item->getSourceType() !== 'ticket') {
                continue;
            }

            $ticketId = (int)$item->getSourceId();
            $ticketAssignments[$ticketId] = $item->getAssignedUserId();
        }

        if ($assignedUserId || $unassigned) {
            $tickets = array_filter($tickets, function ($ticket) use ($assignedUserId, $unassigned, $ticketAssignments) {
                $id = $ticket->id();
                $assignment = $ticketAssignments[$id] ?? null;

                if ($unassigned && !$assignedUserId) {
                    return $assignment === null || $assignment === '';
                }

                if ($assignedUserId === 'unassigned') {
                    return $assignment === null || $assignment === '';
                }

                if ($assignedUserId) {
                    return $assignment !== null && (string)$assignment === (string)$assignedUserId;
                }

                return true;
            });
        }

        $data = array_map(function ($ticket) use ($ticketAssignments) {
            $malleable = $ticket->malleableData();
            $mode = $malleable['ticket_mode'] ?? 'support';
            $assignedUserId = $ticketAssignments[$ticket->id()] ?? null;

            return [
                'id' => $ticket->id(),
                'customerId' => $ticket->customerId(),
                'siteId' => $ticket->siteId(),
                'slaId' => $ticket->slaId(),
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
            ];
        }, $tickets);

        return new WP_REST_Response(array_values($data), 200);
    }

    public function createTicket(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $malleableData = $params['malleableData'] ?? [];
            if (!isset($malleableData['ticket_mode'])) {
                $malleableData['ticket_mode'] = 'support';
            }

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
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function updateTicket(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
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
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function deleteTicket(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new DeleteTicketCommand($id);
            $this->deleteTicketHandler->handle($command);

            return new WP_REST_Response(['message' => 'Ticket deleted'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
