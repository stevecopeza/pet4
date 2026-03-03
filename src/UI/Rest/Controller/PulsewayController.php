<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Infrastructure\Integration\Pulseway\CredentialEncryptionService;
use Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository;
use Pet\Application\Integration\Pulseway\Service\NotificationIngestionService;
use Pet\Application\Integration\Pulseway\Service\DeviceSnapshotService;
use Pet\Application\System\Service\FeatureFlagService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class PulsewayController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'pulseway';

    private SqlPulsewayIntegrationRepository $repo;
    private CredentialEncryptionService $encryption;
    private NotificationIngestionService $ingestionService;
    private DeviceSnapshotService $deviceService;
    private FeatureFlagService $flags;

    public function __construct(
        SqlPulsewayIntegrationRepository $repo,
        CredentialEncryptionService $encryption,
        NotificationIngestionService $ingestionService,
        DeviceSnapshotService $deviceService,
        FeatureFlagService $flags
    ) {
        $this->repo = $repo;
        $this->encryption = $encryption;
        $this->ingestionService = $ingestionService;
        $this->deviceService = $deviceService;
        $this->flags = $flags;
    }

    public function registerRoutes(): void
    {
        // ── Integrations ──
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listIntegrations'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createIntegration'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getIntegration'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'PUT,PATCH',
                'callback' => [$this, 'updateIntegration'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // ── Health & actions ──
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/test', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'testConnection'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/poll', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'triggerPoll'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/sync-devices', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'triggerDeviceSync'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/reset-circuit', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'resetCircuitBreaker'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // ── Org Mappings ──
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/mappings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listMappings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createMapping'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/mappings/(?P<id>\d+)', [
            [
                'methods' => 'PUT,PATCH',
                'callback' => [$this, 'updateMapping'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // ── Read-only views ──
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/notifications', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listNotifications'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/devices', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listDevices'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getStats'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // ── Ticket Rules ──
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/integrations/(?P<id>\d+)/rules', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listRules'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createRule'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/rules/(?P<id>\d+)', [
            [
                'methods' => 'PUT,PATCH',
                'callback' => [$this, 'updateRule'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    // ── Integration CRUD ──

    public function listIntegrations(WP_REST_Request $request): WP_REST_Response
    {
        $integrations = $this->repo->findAllIntegrations();

        // Redact encrypted credentials from response
        $data = array_map(function ($row) {
            unset($row['token_id_encrypted'], $row['token_secret_encrypted']);
            $row['has_credentials'] = true;
            return $row;
        }, $integrations);

        return new WP_REST_Response($data, 200);
    }

    public function getIntegration(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $integration = $this->repo->findIntegrationById($id);

        if (!$integration) {
            return new WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        unset($integration['token_id_encrypted'], $integration['token_secret_encrypted']);
        $integration['has_credentials'] = true;

        return new WP_REST_Response($integration, 200);
    }

    public function createIntegration(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['label']) || empty($params['token_id']) || empty($params['token_secret'])) {
            return new WP_REST_Response(['error' => 'label, token_id, and token_secret are required'], 400);
        }

        try {
            $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('pw_', true);

            $data = [
                'uuid' => $uuid,
                'label' => sanitize_text_field($params['label']),
                'api_base_url' => sanitize_url($params['api_base_url'] ?? 'https://api.pulseway.com/v3'),
                'token_id_encrypted' => $this->encryption->encrypt($params['token_id']),
                'token_secret_encrypted' => $this->encryption->encrypt($params['token_secret']),
                'poll_interval_seconds' => (int) ($params['poll_interval_seconds'] ?? 300),
                'is_active' => 1,
            ];

            // Store default assignment settings as JSON in a meta field if provided
            if (isset($params['default_department_id']) || isset($params['default_assignee_id'])) {
                // These will be stored via org mappings with null pulseway IDs (global defaults)
            }

            $id = $this->repo->insertIntegration($data);

            return new WP_REST_Response(['id' => $id, 'uuid' => $uuid, 'message' => 'Integration created'], 201);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function updateIntegration(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();
        $existing = $this->repo->findIntegrationById($id);

        if (!$existing) {
            return new WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        try {
            $data = [];

            if (isset($params['label'])) {
                $data['label'] = sanitize_text_field($params['label']);
            }
            if (isset($params['api_base_url'])) {
                $data['api_base_url'] = sanitize_url($params['api_base_url']);
            }
            if (isset($params['token_id']) && $params['token_id'] !== '') {
                $data['token_id_encrypted'] = $this->encryption->encrypt($params['token_id']);
            }
            if (isset($params['token_secret']) && $params['token_secret'] !== '') {
                $data['token_secret_encrypted'] = $this->encryption->encrypt($params['token_secret']);
            }
            if (isset($params['poll_interval_seconds'])) {
                $data['poll_interval_seconds'] = (int) $params['poll_interval_seconds'];
            }
            if (isset($params['is_active'])) {
                $data['is_active'] = $params['is_active'] ? 1 : 0;
            }
            if (isset($params['archived']) && $params['archived']) {
                $data['archived_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            }

            if (!empty($data)) {
                $this->repo->updateIntegration($id, $data);
            }

            return new WP_REST_Response(['message' => 'Integration updated'], 200);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    // ── Actions ──

    public function testConnection(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $integration = $this->repo->findIntegrationById($id);

        if (!$integration) {
            return new WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        try {
            $tokenId = $this->encryption->decrypt($integration['token_id_encrypted']);
            $tokenSecret = $this->encryption->decrypt($integration['token_secret_encrypted']);
            $baseUrl = $integration['api_base_url'] ?? 'https://api.pulseway.com/v3';

            $client = new \Pet\Infrastructure\Integration\Pulseway\PulsewayApiClient(
                $baseUrl, $tokenId, $tokenSecret
            );

            // Attempt a simple API call to verify credentials
            $response = $client->getDevices(1, 0);

            $this->repo->recordSuccess($id);

            return new WP_REST_Response([
                'status' => 'connected',
                'message' => 'Connection successful',
            ], 200);
        } catch (\Throwable $e) {
            $this->repo->recordFailure($id, $e->getMessage());
            return new WP_REST_Response([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 200); // 200 because this is not an API error, it's a test result
        }
    }

    public function triggerPoll(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = $this->ingestionService->pollIntegrationById($id);
        return new WP_REST_Response($result, 200);
    }

    public function triggerDeviceSync(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = $this->deviceService->syncIntegrationById($id);
        return new WP_REST_Response($result, 200);
    }

    public function resetCircuitBreaker(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $integration = $this->repo->findIntegrationById($id);

        if (!$integration) {
            return new WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        $this->repo->updateIntegration($id, [
            'consecutive_failures' => 0,
            'last_error_at' => null,
            'last_error_message' => null,
        ]);

        return new WP_REST_Response(['message' => 'Circuit breaker reset'], 200);
    }

    // ── Org Mappings ──

    public function listMappings(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $mappings = $this->repo->findMappingsByIntegration($id);
        return new WP_REST_Response($mappings, 200);
    }

    public function createMapping(WP_REST_Request $request): WP_REST_Response
    {
        $integrationId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $data = [
            'integration_id' => $integrationId,
            'pulseway_org_id' => $params['pulseway_org_id'] ?? null,
            'pulseway_site_id' => $params['pulseway_site_id'] ?? null,
            'pulseway_group_id' => $params['pulseway_group_id'] ?? null,
            'pet_customer_id' => isset($params['pet_customer_id']) ? (int) $params['pet_customer_id'] : null,
            'pet_site_id' => isset($params['pet_site_id']) ? (int) $params['pet_site_id'] : null,
            'pet_team_id' => isset($params['pet_team_id']) ? (int) $params['pet_team_id'] : null,
            'is_active' => 1,
        ];

        $mappingId = $this->repo->insertOrgMapping($data);
        return new WP_REST_Response(['id' => $mappingId, 'message' => 'Mapping created'], 201);
    }

    public function updateMapping(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $data = [];
        foreach (['pulseway_org_id', 'pulseway_site_id', 'pulseway_group_id', 'pet_customer_id', 'pet_site_id', 'pet_team_id', 'is_active'] as $field) {
            if (array_key_exists($field, $params)) {
                $data[$field] = $params[$field];
            }
        }
        if (isset($params['archived']) && $params['archived']) {
            $data['archived_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        if (!empty($data)) {
            $this->repo->updateOrgMapping($id, $data);
        }

        return new WP_REST_Response(['message' => 'Mapping updated'], 200);
    }

    // ── Read-only views ──

    public function listNotifications(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $limit = (int) ($request->get_param('limit') ?? 50);
        $notifications = $this->repo->findRecentNotifications($id, min($limit, 200));
        return new WP_REST_Response($notifications, 200);
    }

    public function listDevices(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $devices = $this->repo->findAssetsByIntegration($id);
        return new WP_REST_Response($devices, 200);
    }

    public function getStats(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $integration = $this->repo->findIntegrationById($id);

        if (!$integration) {
            return new WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        $notificationCounts = $this->repo->countNotificationsByStatus($id);
        $devices = $this->repo->findAssetsByIntegration($id);

        return new WP_REST_Response([
            'integration_id' => $id,
            'label' => $integration['label'],
            'is_active' => (bool) $integration['is_active'],
            'last_poll_at' => $integration['last_poll_at'],
            'last_success_at' => $integration['last_success_at'],
            'last_error_at' => $integration['last_error_at'],
            'last_error_message' => $integration['last_error_message'],
            'consecutive_failures' => (int) $integration['consecutive_failures'],
            'circuit_open' => (int) $integration['consecutive_failures'] >= 6,
            'notifications' => $notificationCounts,
            'device_count' => count($devices),
        ], 200);
    }

    // ── Ticket Rules ──

    public function listRules(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $rules = $this->repo->findRulesByIntegration($id);
        return new WP_REST_Response($rules, 200);
    }

    public function createRule(WP_REST_Request $request): WP_REST_Response
    {
        $integrationId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $data = [
            'integration_id' => $integrationId,
            'rule_name' => sanitize_text_field($params['rule_name'] ?? 'Unnamed Rule'),
            'match_severity' => $params['match_severity'] ?? null,
            'match_category' => $params['match_category'] ?? null,
            'match_pulseway_org_id' => $params['match_pulseway_org_id'] ?? null,
            'match_pulseway_site_id' => $params['match_pulseway_site_id'] ?? null,
            'match_pulseway_group_id' => $params['match_pulseway_group_id'] ?? null,
            'output_ticket_kind' => sanitize_text_field($params['output_ticket_kind'] ?? 'incident'),
            'output_priority' => sanitize_text_field($params['output_priority'] ?? 'medium'),
            'output_queue_id' => $params['output_queue_id'] ?? null,
            'output_owner_user_id' => $params['output_owner_user_id'] ?? null,
            'sort_order' => (int) ($params['sort_order'] ?? 0),
            'is_active' => 1,
        ];

        $ruleId = $this->repo->insertRule($data);
        return new WP_REST_Response(['id' => $ruleId, 'message' => 'Rule created'], 201);
    }

    public function updateRule(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $allowed = [
            'rule_name', 'match_severity', 'match_category', 'match_pulseway_org_id',
            'match_pulseway_site_id', 'match_pulseway_group_id', 'output_ticket_kind',
            'output_priority', 'output_queue_id', 'output_owner_user_id', 'sort_order', 'is_active',
        ];

        $data = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $params)) {
                $data[$field] = $params[$field];
            }
        }
        if (isset($params['archived']) && $params['archived']) {
            $data['archived_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        if (!empty($data)) {
            $this->repo->updateRule($id, $data);
        }

        return new WP_REST_Response(['message' => 'Rule updated'], 200);
    }
}
