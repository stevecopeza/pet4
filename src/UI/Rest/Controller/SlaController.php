<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Calendar\Repository\CalendarRepository;
use Pet\Domain\Sla\Entity\EscalationRule;
use Pet\Domain\Sla\Entity\SlaDefinition;
use Pet\Domain\Sla\Repository\SlaRepository;

class SlaController
{
    private SlaRepository $slaRepository;
    private CalendarRepository $calendarRepository;

    public function __construct(SlaRepository $slaRepository, CalendarRepository $calendarRepository)
    {
        $this->slaRepository = $slaRepository;
        $this->calendarRepository = $calendarRepository;
    }

    public function registerRoutes(): void
    {
        register_rest_route('pet/v1', '/slas', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSlas'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createSla'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('pet/v1', '/slas/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSla'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST', // Update
                'callback' => [$this, 'updateSla'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteSla'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
        
        register_rest_route('pet/v1', '/slas/(?P<id>\d+)/publish', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'publishSla'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getSlas(): \WP_REST_Response
    {
        $slas = $this->slaRepository->findAll();
        $data = array_map([$this, 'serializeSla'], $slas);
        return new \WP_REST_Response($data, 200);
    }

    public function getSla(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $sla = $this->slaRepository->findById($id);

        if (!$sla) {
            return new \WP_REST_Response(['message' => 'SLA not found'], 404);
        }

        return new \WP_REST_Response($this->serializeSla($sla), 200);
    }

    public function createSla(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $data = $request->get_json_params();
            $sla = $this->deserializeSla($data);
            $this->slaRepository->save($sla);
            return new \WP_REST_Response($this->serializeSla($sla), 201);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    public function updateSla(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = (int) $request->get_param('id');
            $existing = $this->slaRepository->findById($id);
            if (!$existing) {
                return new \WP_REST_Response(['message' => 'SLA not found'], 404);
            }
            
            if ($existing->status() !== 'draft') {
                return new \WP_REST_Response(['message' => 'Cannot edit a published SLA. Create a new version instead.'], 400);
            }

            $data = $request->get_json_params();
            $sla = $this->deserializeSla($data, $existing->id(), $existing->uuid(), $existing->versionNumber(), $existing->status());
            
            $this->slaRepository->save($sla);
            return new \WP_REST_Response($this->serializeSla($sla), 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    public function publishSla(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = (int) $request->get_param('id');
            $sla = $this->slaRepository->findById($id);
            if (!$sla) {
                return new \WP_REST_Response(['message' => 'SLA not found'], 404);
            }
            
            $sla->publish();
            $this->slaRepository->save($sla);
            
            return new \WP_REST_Response($this->serializeSla($sla), 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    public function deleteSla(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $this->slaRepository->delete($id);
        return new \WP_REST_Response(null, 204);
    }

    private function serializeSla(SlaDefinition $sla): array
    {
        return [
            'id' => $sla->id(),
            'uuid' => $sla->uuid(),
            'name' => $sla->name(),
            'status' => $sla->status(),
            'version_number' => $sla->versionNumber(),
            'calendar_id' => $sla->calendar()->id(),
            'calendar_name' => $sla->calendar()->name(),
            'response_target_minutes' => $sla->responseTargetMinutes(),
            'resolution_target_minutes' => $sla->resolutionTargetMinutes(),
            'escalation_rules' => array_map(function (EscalationRule $rule) {
                return [
                    'id' => $rule->id(),
                    'threshold_percent' => $rule->thresholdPercent(),
                    'action' => $rule->action(),
                ];
            }, $sla->escalationRules()),
        ];
    }

    private function deserializeSla(
        array $data, 
        ?int $id = null, 
        ?string $uuid = null,
        int $versionNumber = 1,
        string $status = 'draft'
    ): SlaDefinition {
        $calendarId = (int) $data['calendar_id'];
        $calendar = $this->calendarRepository->findById($calendarId);
        
        if (!$calendar) {
            throw new \InvalidArgumentException("Invalid calendar ID: $calendarId");
        }

        $rules = array_map(function ($r) {
            return new EscalationRule(
                (int)$r['threshold_percent'],
                $r['action'],
                $r['id'] ?? null
            );
        }, $data['escalation_rules'] ?? []);

        return new SlaDefinition(
            $data['name'],
            $calendar,
            (int)$data['response_target_minutes'],
            (int)$data['resolution_target_minutes'],
            $rules,
            $status,
            $versionNumber,
            $uuid,
            $id
        );
    }
}
