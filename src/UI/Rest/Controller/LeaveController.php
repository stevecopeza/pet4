<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Work\Repository\LeaveTypeRepository;
use Pet\Domain\Work\Repository\LeaveRequestRepository;
use Pet\Application\Work\Command\SubmitLeaveRequestCommand;
use Pet\Application\Work\Command\SubmitLeaveRequestHandler;
use Pet\Application\Work\Command\DecideLeaveRequestCommand;
use Pet\Application\Work\Command\DecideLeaveRequestHandler;
use Pet\Application\Work\Command\SetCapacityOverrideCommand;
use Pet\Application\Work\Command\SetCapacityOverrideHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class LeaveController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'leave';

    public function __construct(
        private LeaveTypeRepository $types,
        private LeaveRequestRepository $requests,
        private SubmitLeaveRequestHandler $submitHandler,
        private DecideLeaveRequestHandler $decideHandler,
        private SetCapacityOverrideHandler $overrideHandler
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/types', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listTypes'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/requests', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listMyRequests'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submit'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/requests/(?P<id>\\d+)/decide', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'decide'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/capacity-override', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'setOverride'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function listTypes(WP_REST_Request $request): WP_REST_Response
    {
        $data = array_map(function ($t) {
            return ['id' => $t->id(), 'name' => $t->name(), 'paid' => $t->paid()];
        }, $this->types->findAll());
        return new WP_REST_Response($data, 200);
    }

    public function listMyRequests(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int)$request->get_param('employeeId');
        $data = array_map(function ($r) {
            return [
                'id' => $r->id(),
                'uuid' => $r->uuid(),
                'employeeId' => $r->employeeId(),
                'leaveTypeId' => $r->leaveTypeId(),
                'startDate' => $r->startDate()->format('Y-m-d'),
                'endDate' => $r->endDate()->format('Y-m-d'),
                'status' => $r->status(),
                'decidedByEmployeeId' => $r->decidedByEmployeeId(),
                'decidedAt' => $r->decidedAt() ? $r->decidedAt()->format('Y-m-d H:i:s') : null,
                'decisionReason' => $r->decisionReason(),
                'notes' => $r->notes(),
                'createdAt' => $r->createdAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $r->updatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $this->requests->findByEmployee($employeeId));
        return new WP_REST_Response($data, 200);
    }

    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int)$request->get_param('employeeId');
        $leaveTypeId = (int)$request->get_param('leaveTypeId');
        $startStr = (string)$request->get_param('startDate');
        $endStr = (string)$request->get_param('endDate');
        if ($employeeId <= 0 || $leaveTypeId <= 0 || $startStr === '' || $endStr === '') {
            return new WP_REST_Response(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid parameters', 'details' => []]], 400);
        }
        $type = $this->types->findById($leaveTypeId);
        if (!$type) {
            return new WP_REST_Response(['error' => ['code' => 'NOT_FOUND', 'message' => 'Leave type not found', 'details' => ['leaveTypeId' => $leaveTypeId]]], 404);
        }
        try {
            $start = new \DateTimeImmutable($startStr);
            $end = new \DateTimeImmutable($endStr);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid dates', 'details' => []]], 400);
        }
        if ($end < $start) {
            return new WP_REST_Response(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'End date before start date', 'details' => []]], 400);
        }
        $notes = $request->get_param('notes');
        try {
            $id = $this->submitHandler->handle(new SubmitLeaveRequestCommand($employeeId, $leaveTypeId, $start, $end, $notes ? (string)$notes : null));
            return new WP_REST_Response(['id' => $id], 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to submit leave request', 'details' => []]], 500);
        }
    }

    public function decide(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $by = (int)$request->get_param('decidedByEmployeeId');
        $decision = (string)$request->get_param('decision');
        if ($id <= 0 || $by <= 0 || $decision === '') {
            return new WP_REST_Response(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid parameters', 'details' => []]], 400);
        }
        if (!in_array($decision, ['approved', 'rejected', 'cancelled'], true)) {
            return new WP_REST_Response(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid decision', 'details' => ['allowed' => ['approved','rejected','cancelled']]]], 400);
        }
        $reason = $request->get_param('reason');
        try {
            $this->decideHandler->handle(new DecideLeaveRequestCommand($id, $by, $decision, $reason ? (string)$reason : null));
            return new WP_REST_Response(['status' => $decision], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to decide leave request', 'details' => []]], 500);
        }
    }

    public function setOverride(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int)$request->get_param('employeeId');
        $dateStr = (string)$request->get_param('date');
        $pct = (int)$request->get_param('capacityPct');
        if ($employeeId <= 0 || $dateStr === '' || !is_int($pct)) {
            return new WP_REST_Response(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid parameters', 'details' => []]], 400);
        }
        try {
            $date = new \DateTimeImmutable($dateStr);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid date', 'details' => []]], 400);
        }
        if ($pct < 0 || $pct > 100) {
            return new WP_REST_Response(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid capacity percentage', 'details' => ['range' => [0,100]]]], 400);
        }
        $reason = $request->get_param('reason');
        try {
            $this->overrideHandler->handle(new SetCapacityOverrideCommand($employeeId, $date, $pct, $reason ? (string)$reason : null));
            return new WP_REST_Response(['status' => 'ok'], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => ['code' => 'DOMAIN_ERROR', 'message' => $e->getMessage(), 'details' => []]], 422);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to set capacity override', 'details' => []]], 500);
        }
    }
}
