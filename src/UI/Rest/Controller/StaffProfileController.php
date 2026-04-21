<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Identity\Service\StaffEmployeeResolver;
use Pet\Domain\Work\Repository\KpiDefinitionRepository;
use Pet\Domain\Work\Repository\PersonKpiRepository;
use Pet\Domain\Work\Repository\PersonSkillRepository;
use Pet\Domain\Work\Repository\SkillRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Provides self-service profile and KPI endpoints for the staff portal.
 *
 * Routes:
 *   GET /pet/v1/staff/profile        — current user's employee record + skills
 *   GET /pet/v1/staff/profile/kpis   — current user's KPI history
 *
 * Permission: any logged-in WP user (no elevated cap required).
 * Data is always scoped to the requesting user; no employee ID param is accepted.
 */
final class StaffProfileController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE  = 'staff/profile';

    public function __construct(
        private StaffEmployeeResolver   $staffEmployeeResolver,
        private PersonSkillRepository   $personSkillRepository,
        private SkillRepository         $skillRepository,
        private PersonKpiRepository     $personKpiRepository,
        private KpiDefinitionRepository $kpiDefinitionRepository,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getProfile'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/kpis', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getKpis'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return is_user_logged_in();
    }

    public function getProfile(WP_REST_Request $request): WP_REST_Response
    {
        $resolved = $this->staffEmployeeResolver->resolve((int) get_current_user_id());

        if (!$resolved['ok']) {
            return new WP_REST_Response([
                'error' => $resolved['message'],
                'code'  => $resolved['code'],
            ], 404);
        }

        $employee = $resolved['employee'];

        // Build skill name lookup
        $allSkills    = $this->skillRepository->findAll();
        $skillNameMap = [];
        foreach ($allSkills as $skill) {
            $skillNameMap[$skill->id()] = $skill->name();
        }

        $personSkills = $this->personSkillRepository->findByEmployeeId((int) $employee->id());
        $skills = array_map(function ($ps) use ($skillNameMap) {
            return [
                'id'             => $ps->id(),
                'skill_id'       => $ps->skillId(),
                'skill_name'     => $skillNameMap[$ps->skillId()] ?? 'Unknown',
                'self_rating'    => $ps->selfRating(),
                'manager_rating' => $ps->managerRating(),
                'effective_date' => $ps->effectiveDate()->format('Y-m-d'),
            ];
        }, $personSkills);

        return new WP_REST_Response([
            'id'           => $employee->id(),
            'wpUserId'     => $employee->wpUserId(),
            'firstName'    => $employee->firstName(),
            'lastName'     => $employee->lastName(),
            'displayName'  => $employee->fullName(),
            'email'        => $employee->email(),
            'status'       => $employee->status(),
            'hireDate'     => $employee->hireDate() ? $employee->hireDate()->format('Y-m-d') : null,
            'managerId'    => $employee->managerId(),
            'teamIds'      => $employee->teamIds(),
            'malleableData'=> $employee->malleableData(),
            'skills'       => array_values($skills),
        ], 200);
    }

    public function getKpis(WP_REST_Request $request): WP_REST_Response
    {
        $resolved = $this->staffEmployeeResolver->resolve((int) get_current_user_id());

        if (!$resolved['ok']) {
            return new WP_REST_Response([
                'error' => $resolved['message'],
                'code'  => $resolved['code'],
            ], 404);
        }

        $employee = $resolved['employee'];
        $kpis     = $this->personKpiRepository->findByEmployeeId((int) $employee->id());

        // Build KPI definition lookup for name + unit enrichment
        $allDefs    = $this->kpiDefinitionRepository->findAll();
        $defMap = [];
        foreach ($allDefs as $def) {
            $defMap[$def->id()] = $def;
        }

        $data = array_map(function ($kpi) use ($defMap) {
            $def = $defMap[$kpi->kpiDefinitionId()] ?? null;
            return [
                'id'                 => $kpi->id(),
                'employee_id'        => $kpi->employeeId(),
                'kpi_definition_id'  => $kpi->kpiDefinitionId(),
                'kpi_name'           => $def?->name() ?? null,
                'kpi_unit'           => $def?->unit() ?? null,
                'period_start'       => $kpi->periodStart()->format('Y-m-d'),
                'period_end'         => $kpi->periodEnd()->format('Y-m-d'),
                'target_value'       => $kpi->targetValue(),
                'actual_value'       => $kpi->actualValue(),
                'score'              => $kpi->score(),
                'status'             => $kpi->status(),
            ];
        }, $kpis);

        return new WP_REST_Response($data, 200);
    }
}
