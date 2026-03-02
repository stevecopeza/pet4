<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\RateEmployeeSkillCommand;
use Pet\Application\Work\Command\RateEmployeeSkillHandler;
use Pet\Domain\Work\Repository\PersonSkillRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EmployeeSkillController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'employees';

    private $personSkillRepository;
    private $rateEmployeeSkillHandler;

    public function __construct(
        PersonSkillRepository $personSkillRepository,
        RateEmployeeSkillHandler $rateEmployeeSkillHandler
    ) {
        $this->personSkillRepository = $personSkillRepository;
        $this->rateEmployeeSkillHandler = $rateEmployeeSkillHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/skills', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getEmployeeSkills'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rateEmployeeSkill'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getEmployeeSkills(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int) $request->get_param('id');
        $reviewCycleId = $request->get_param('review_cycle_id');

        if ($reviewCycleId) {
            // If filtering by review cycle, we might want to check employee ownership too, 
            // but repository findByReviewCycleId doesn't enforce employee. 
            // Assuming the caller knows what they are doing or we filter post-fetch.
            // For safety, let's just filter the employee's skills by review cycle ID if possible, 
            // or fetch by review cycle and filter by employee.
            // But PersonSkillRepository::findByReviewCycleId exists now.
            $skills = $this->personSkillRepository->findByReviewCycleId((int) $reviewCycleId);
            // Filter to ensure it matches the requested employee (security/correctness check)
            $skills = array_filter($skills, function($s) use ($employeeId) {
                return $s->employeeId() === $employeeId;
            });
        } else {
            $skills = $this->personSkillRepository->findByEmployeeId($employeeId);
        }

        $data = array_map(function ($skill) {
            return [
                'id' => $skill->id(),
                'employee_id' => $skill->employeeId(),
                'skill_id' => $skill->skillId(),
                'self_rating' => $skill->selfRating(),
                'manager_rating' => $skill->managerRating(),
                'effective_date' => $skill->effectiveDate()->format('Y-m-d'),
                'created_at' => $skill->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $skills);

        return new WP_REST_Response($data, 200);
    }

    public function rateEmployeeSkill(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['skill_id'])) {
            return new WP_REST_Response(['error' => 'Missing skill_id'], 400);
        }

        $command = new RateEmployeeSkillCommand(
            $employeeId,
            (int) $params['skill_id'],
            (int) ($params['self_rating'] ?? 0),
            (int) ($params['manager_rating'] ?? 0),
            $params['effective_date'] ?? 'now',
            isset($params['review_cycle_id']) ? (int) $params['review_cycle_id'] : null
        );

        try {
            $this->rateEmployeeSkillHandler->handle($command);
            return new WP_REST_Response(['message' => 'Skill rating recorded successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }
}
