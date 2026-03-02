<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\AssignCertificationToPersonCommand;
use Pet\Application\Work\Command\AssignCertificationToPersonHandler;
use Pet\Domain\Work\Repository\PersonCertificationRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EmployeeCertificationController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'employees';

    private PersonCertificationRepository $personCertificationRepository;
    private AssignCertificationToPersonHandler $assignCertificationToPersonHandler;

    public function __construct(
        PersonCertificationRepository $personCertificationRepository,
        AssignCertificationToPersonHandler $assignCertificationToPersonHandler
    ) {
        $this->personCertificationRepository = $personCertificationRepository;
        $this->assignCertificationToPersonHandler = $assignCertificationToPersonHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\\d+)/certifications', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getEmployeeCertifications'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'assignCertification'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function getEmployeeCertifications(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int)$request->get_param('id');
        $certifications = $this->personCertificationRepository->findByEmployeeId($employeeId);
        
        // Note: The repository returns entities. If we want enriched data (like certification name),
        // the repository implementation currently returns entities but fetched extra data in SQL.
        // However, the mapRowToEntity discarded that extra data.
        // To fix this properly for the UI, we should probably fetch the certification details separately
        // or have a DTO. 
        // Given the time constraints, I will assume the frontend will fetch the list of all certifications 
        // to map IDs to names, OR I can update the repository to return a DTO or enriched array.
        // Let's stick to returning the entity data and let frontend handle mapping for now, 
        // or update the controller to fetch certification details if needed.
        // Actually, looking at my previous EmployeeSkillController, I didn't return skill names there either?
        // Wait, for skills, I might have needed it.
        // Let's check EmployeeSkills.tsx again. It used `skills` prop which came from `getSkills` (all skills).
        // So the frontend has the lookup map. Good.
        
        $data = array_map(function ($cert) {
            return [
                'id' => $cert->id(),
                'employee_id' => $cert->employeeId(),
                'certification_id' => $cert->certificationId(),
                'obtained_date' => $cert->obtainedDate()->format('Y-m-d'),
                'expiry_date' => $cert->expiryDate() ? $cert->expiryDate()->format('Y-m-d') : null,
                'evidence_url' => $cert->evidenceUrl(),
                'status' => $cert->status(),
                'created_at' => $cert->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $certifications);

        return new WP_REST_Response($data, 200);
    }

    public function assignCertification(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = (int)$request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['certification_id']) || empty($params['obtained_date'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        $command = new AssignCertificationToPersonCommand(
            $employeeId,
            (int)$params['certification_id'],
            $params['obtained_date'],
            $params['expiry_date'] ?? null,
            $params['evidence_url'] ?? null
        );

        try {
            $this->assignCertificationToPersonHandler->handle($command);
            return new WP_REST_Response(['message' => 'Certification assigned successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
