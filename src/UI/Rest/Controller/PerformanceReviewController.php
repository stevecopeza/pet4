<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Work\Command\CreatePerformanceReviewCommand;
use Pet\Application\Work\Command\CreatePerformanceReviewHandler;
use Pet\Application\Work\Command\UpdatePerformanceReviewCommand;
use Pet\Application\Work\Command\UpdatePerformanceReviewHandler;
use Pet\Domain\Work\Repository\PerformanceReviewRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class PerformanceReviewController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'performance-reviews';

    private $repository;
    private $createHandler;
    private $updateHandler;

    public function __construct(
        PerformanceReviewRepository $repository,
        CreatePerformanceReviewHandler $createHandler,
        UpdatePerformanceReviewHandler $updateHandler
    ) {
        $this->repository = $repository;
        $this->createHandler = $createHandler;
        $this->updateHandler = $updateHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getReviews'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createReview'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getReview'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateReview'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getReviews(WP_REST_Request $request): WP_REST_Response
    {
        $employeeId = $request->get_param('employee_id');
        $reviewerId = $request->get_param('reviewer_id');

        if ($employeeId) {
            $reviews = $this->repository->findByEmployeeId((int) $employeeId);
        } elseif ($reviewerId) {
            $reviews = $this->repository->findByReviewerId((int) $reviewerId);
        } else {
            return new WP_REST_Response([], 200); // Or fetch all? Let's return empty if no filter to avoid dumping everything
        }

        $data = array_map([$this, 'serialize'], $reviews);

        return new WP_REST_Response($data, 200);
    }

    public function getReview(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $review = $this->repository->findById($id);

        if (!$review) {
            return new WP_REST_Response(['error' => 'Review not found'], 404);
        }

        return new WP_REST_Response($this->serialize($review), 200);
    }

    public function createReview(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['employee_id']) || empty($params['period_start']) || empty($params['period_end'])) {
            return new WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        $reviewerId = get_current_user_id(); // Default to current user

        $command = new CreatePerformanceReviewCommand(
            (int) $params['employee_id'],
            $reviewerId,
            $params['period_start'],
            $params['period_end']
        );

        try {
            $id = $this->createHandler->handle($command);
            return new WP_REST_Response(['id' => $id, 'message' => 'Review created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function updateReview(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $command = new UpdatePerformanceReviewCommand(
            $id,
            $params['content'] ?? [],
            $params['status'] ?? null
        );

        try {
            $this->updateHandler->handle($command);
            return new WP_REST_Response(['message' => 'Review updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    private function serialize($review): array
    {
        return [
            'id' => $review->id(),
            'employee_id' => $review->employeeId(),
            'reviewer_id' => $review->reviewerId(),
            'period_start' => $review->periodStart()->format('Y-m-d'),
            'period_end' => $review->periodEnd()->format('Y-m-d'),
            'status' => $review->status(),
            'content' => $review->content(),
            'created_at' => $review->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $review->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
