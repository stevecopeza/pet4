<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Sla\Entity\EscalationRule;
use Pet\Domain\Sla\Repository\EscalationRuleRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EscalationRuleController
{
    private $repository;
    private $featureFlagService;

    public function __construct(
        EscalationRuleRepository $repository,
        FeatureFlagService $featureFlagService
    ) {
        $this->repository = $repository;
        $this->featureFlagService = $featureFlagService;
    }

    public function registerRoutes(): void
    {
        // Feature Flag Check: If disabled, do not register routes
        if (!$this->featureFlagService->isEscalationEngineEnabled()) {
            return;
        }

        register_rest_route('pet/v1', '/escalation-rules', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getRules'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createRule'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('pet/v1', '/escalation-rules/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE, // PATCH/PUT/POST
                'callback' => [$this, 'updateRule'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getRules(WP_REST_Request $request): WP_REST_Response
    {
        // Simple pagination
        $page = $request->get_param('page') ? (int)$request->get_param('page') : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $rules = $this->repository->findAll($limit, $offset);
        
        $data = array_map(function (EscalationRule $rule) {
            return $this->serializeRule($rule);
        }, $rules);

        return new WP_REST_Response($data, 200);
    }

    public function createRule(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        // Validate required fields
        if (empty($params['sla_id']) || empty($params['threshold_percent']) || empty($params['action'])) {
            return new WP_REST_Response(['code' => 'missing_params', 'message' => 'Missing required parameters'], 400);
        }

        $criteriaJson = isset($params['criteria_json']) 
            ? (is_string($params['criteria_json']) ? $params['criteria_json'] : json_encode($params['criteria_json'])) 
            : '{}';
        
        $isEnabled = isset($params['is_enabled']) ? (bool)$params['is_enabled'] : true;

        try {
            $rule = new EscalationRule(
                (int)$params['threshold_percent'],
                $params['action'],
                null,
                $criteriaJson,
                $isEnabled
            );

            $this->repository->save($rule, (int)$params['sla_id']);

            return new WP_REST_Response($this->serializeRule($rule), 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['code' => 'invalid_input', 'message' => $e->getMessage()], 400);
        }
    }

    public function updateRule(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $rule = $this->repository->findById($id);

        if (!$rule) {
            return new WP_REST_Response(['code' => 'not_found', 'message' => 'Rule not found'], 404);
        }

        $params = $request->get_json_params();

        // Update fields if provided
        $threshold = isset($params['threshold_percent']) ? (int)$params['threshold_percent'] : $rule->thresholdPercent();
        $action = isset($params['action']) ? $params['action'] : $rule->action();
        
        $criteriaJson = $rule->criteriaJson();
        if (isset($params['criteria_json'])) {
            $criteriaJson = is_string($params['criteria_json']) ? $params['criteria_json'] : json_encode($params['criteria_json']);
        }

        $isEnabled = isset($params['is_enabled']) ? (bool)$params['is_enabled'] : $rule->isEnabled();

        try {
            $updatedRule = new EscalationRule(
                $threshold,
                $action,
                $id,
                $criteriaJson,
                $isEnabled
            );

            $this->repository->save($updatedRule); // No slaId needed for update

            return new WP_REST_Response($this->serializeRule($updatedRule), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['code' => 'invalid_input', 'message' => $e->getMessage()], 400);
        }
    }

    private function serializeRule(EscalationRule $rule): array
    {
        return [
            'id' => $rule->id(),
            'threshold_percent' => $rule->thresholdPercent(),
            'action' => $rule->action(),
            'criteria_json' => json_decode($rule->criteriaJson()), // Return as object
            'is_enabled' => $rule->isEnabled(),
        ];
    }
}
