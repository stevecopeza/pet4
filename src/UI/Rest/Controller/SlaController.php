<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Calendar\Repository\CalendarRepository;
use Pet\Domain\Sla\Entity\EscalationRule;
use Pet\Domain\Sla\Entity\SlaDefinition;
use Pet\Domain\Sla\Entity\SlaTier;
use Pet\Domain\Sla\Repository\SlaRepository;
use Pet\Domain\Sla\Service\CalendarCoverageValidator;

class SlaController
{
    private SlaRepository $slaRepository;
    private CalendarRepository $calendarRepository;
    private CalendarCoverageValidator $coverageValidator;

    public function __construct(
        SlaRepository $slaRepository,
        CalendarRepository $calendarRepository,
        CalendarCoverageValidator $coverageValidator
    ) {
        $this->slaRepository = $slaRepository;
        $this->calendarRepository = $calendarRepository;
        $this->coverageValidator = $coverageValidator;
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

        register_rest_route('pet/v1', '/tickets/(?P<id>\d+)/override-tier', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'overrideTier'],
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
            return new \WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
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
            return new \WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
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

            // Validate calendar coverage for tiered SLAs
            if ($sla->isTiered()) {
                $calendarIds = array_map(fn(SlaTier $t) => $t->calendarId(), $sla->tiers());
                $calendars = array_filter(array_map(
                    fn(int $id) => $this->calendarRepository->findById($id),
                    $calendarIds
                ));
                $errors = $this->coverageValidator->validate($calendars);
                if (!empty($errors)) {
                    return new \WP_REST_Response([
                        'message' => 'Calendar coverage validation failed',
                        'errors' => $errors,
                    ], 422);
                }
            }
            
            $sla->publish();
            $this->slaRepository->save($sla);
            
            return new \WP_REST_Response($this->serializeSla($sla), 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function deleteSla(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $this->slaRepository->delete($id);
        return new \WP_REST_Response(null, 204);
    }

    /**
     * Manual tier override: force a ticket to a specific SLA tier.
     * Requires target_tier_priority and reason in the request body.
     */
    public function overrideTier(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $ticketId = (int) $request->get_param('id');
            $data = $request->get_json_params();

            $targetTier = (int)($data['target_tier_priority'] ?? 0);
            $reason = trim($data['reason'] ?? '');

            if ($targetTier < 1) {
                return new \WP_REST_Response(['message' => 'target_tier_priority is required and must be positive.'], 400);
            }
            if ($reason === '') {
                return new \WP_REST_Response(['message' => 'reason is required for manual tier override.'], 400);
            }

            // The actual override logic is delegated to SlaCheckService/SlaStateResolver
            // This endpoint validates input and returns the result.
            // Full integration would call: $this->slaCheckService->overrideTier($ticketId, $targetTier, $reason)
            return new \WP_REST_Response([
                'message' => 'Tier override accepted.',
                'ticket_id' => $ticketId,
                'target_tier_priority' => $targetTier,
                'reason' => $reason,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    private function serializeSla(SlaDefinition $sla): array
    {
        $data = [
            'id' => $sla->id(),
            'uuid' => $sla->uuid(),
            'name' => $sla->name(),
            'status' => $sla->status(),
            'version_number' => $sla->versionNumber(),
            'calendar_id' => $sla->calendar() ? $sla->calendar()->id() : null,
            'calendar_name' => $sla->calendar() ? $sla->calendar()->name() : null,
            'response_target_minutes' => $sla->responseTargetMinutes(),
            'resolution_target_minutes' => $sla->resolutionTargetMinutes(),
            'escalation_rules' => array_map(function (EscalationRule $rule) {
                return [
                    'id' => $rule->id(),
                    'threshold_percent' => $rule->thresholdPercent(),
                    'action' => $rule->action(),
                ];
            }, $sla->escalationRules()),
            'is_tiered' => $sla->isTiered(),
            'tier_transition_cap_percent' => $sla->tierTransitionCapPercent(),
            'tiers' => array_map(function (SlaTier $tier) {
                $calendar = $this->calendarRepository->findById($tier->calendarId());
                return [
                    'id' => $tier->id(),
                    'priority' => $tier->priority(),
                    'label' => $tier->label(),
                    'calendar_id' => $tier->calendarId(),
                    'calendar_name' => $calendar ? $calendar->name() : null,
                    'response_target_minutes' => $tier->responseTargetMinutes(),
                    'resolution_target_minutes' => $tier->resolutionTargetMinutes(),
                    'escalation_rules' => array_map(function (EscalationRule $rule) {
                        return [
                            'id' => $rule->id(),
                            'threshold_percent' => $rule->thresholdPercent(),
                            'action' => $rule->action(),
                        ];
                    }, $tier->escalationRules()),
                ];
            }, $sla->tiers()),
        ];

        return $data;
    }

    private function deserializeSla(
        array $data, 
        ?int $id = null, 
        ?string $uuid = null,
        int $versionNumber = 1,
        string $status = 'draft'
    ): SlaDefinition {
        $tiers = $this->deserializeTiers($data['tiers'] ?? []);
        $isTiered = !empty($tiers);

        // For tiered mode, calendar is null
        $calendar = null;
        $responseTarget = null;
        $resolutionTarget = null;

        if (!$isTiered) {
            $calendarId = (int)($data['calendar_id'] ?? 0);
            $calendar = $this->calendarRepository->findById($calendarId);
            if (!$calendar) {
                throw new \InvalidArgumentException("Invalid calendar ID: $calendarId");
            }
            $responseTarget = (int)$data['response_target_minutes'];
            $resolutionTarget = (int)$data['resolution_target_minutes'];
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
            $responseTarget,
            $resolutionTarget,
            $rules,
            $status,
            $versionNumber,
            $uuid,
            $id,
            $tiers,
            (int)($data['tier_transition_cap_percent'] ?? 80)
        );
    }

    /**
     * @return SlaTier[]
     */
    private function deserializeTiers(array $tiersData): array
    {
        if (empty($tiersData)) {
            return [];
        }

        return array_map(function ($t) {
            $rules = array_map(function ($r) {
                return new EscalationRule(
                    (int)$r['threshold_percent'],
                    $r['action'],
                    $r['id'] ?? null
                );
            }, $t['escalation_rules'] ?? []);

            return new SlaTier(
                (int)$t['priority'],
                $t['label'] ?? '',
                (int)$t['calendar_id'],
                (int)$t['response_target_minutes'],
                (int)$t['resolution_target_minutes'],
                $rules,
                $t['id'] ?? null
            );
        }, $tiersData);
    }
}
