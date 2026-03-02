<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Application\Identity\Command\CreateCustomerCommand;
use Pet\Application\Identity\Command\CreateCustomerHandler;
use Pet\Application\Identity\Command\UpdateCustomerCommand;
use Pet\Application\Identity\Command\UpdateCustomerHandler;
use Pet\Application\Identity\Command\ArchiveCustomerCommand;
use Pet\Application\Identity\Command\ArchiveCustomerHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class CustomerController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'customers';

    private CustomerRepository $customerRepository;
    private CreateCustomerHandler $createCustomerHandler;
    private UpdateCustomerHandler $updateCustomerHandler;
    private ArchiveCustomerHandler $archiveCustomerHandler;

    public function __construct(
        CustomerRepository $customerRepository,
        CreateCustomerHandler $createCustomerHandler,
        UpdateCustomerHandler $updateCustomerHandler,
        ArchiveCustomerHandler $archiveCustomerHandler
    ) {
        $this->customerRepository = $customerRepository;
        $this->createCustomerHandler = $createCustomerHandler;
        $this->updateCustomerHandler = $updateCustomerHandler;
        $this->archiveCustomerHandler = $archiveCustomerHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCustomers'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createCustomer'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateCustomer'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveCustomer'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getCustomers(WP_REST_Request $request): WP_REST_Response
    {
        $customers = $this->customerRepository->findAll();

        $data = array_map(function ($customer) {
            return [
                'id' => $customer->id(),
                'name' => $customer->name(),
                'legalName' => $customer->legalName(),
                'contactEmail' => $customer->contactEmail(),
                'status' => $customer->status(),
                'malleableData' => $customer->malleableData(),
                'createdAt' => $customer->createdAt()->format('Y-m-d H:i:s'),
                'archivedAt' => $customer->archivedAt() ? $customer->archivedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $customers);

        return new WP_REST_Response($data, 200);
    }

    public function createCustomer(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['contactEmail'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $malleableData = $params['malleableData'] ?? [];

            $command = new CreateCustomerCommand(
                $params['name'],
                $params['contactEmail'],
                $params['legalName'] ?? null,
                $params['status'] ?? 'active',
                $malleableData
            );

            $this->createCustomerHandler->handle($command);

            return new WP_REST_Response(['message' => 'Customer created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function updateCustomer(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['contactEmail'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $malleableData = $params['malleableData'] ?? [];

            $command = new UpdateCustomerCommand(
                $id,
                $params['name'],
                $params['contactEmail'],
                $params['legalName'] ?? null,
                $params['status'] ?? 'active',
                $malleableData
            );

            $this->updateCustomerHandler->handle($command);

            return new WP_REST_Response(['message' => 'Customer updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function archiveCustomer(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new ArchiveCustomerCommand($id);
            $this->archiveCustomerHandler->handle($command);

            return new WP_REST_Response(['message' => 'Customer archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }
}
