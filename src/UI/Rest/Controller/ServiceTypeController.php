<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\CreateServiceTypeCommand;
use Pet\Application\Commercial\Command\CreateServiceTypeHandler;
use Pet\Application\Commercial\Command\UpdateServiceTypeCommand;
use Pet\Application\Commercial\Command\UpdateServiceTypeHandler;
use Pet\Application\Commercial\Command\ArchiveServiceTypeCommand;
use Pet\Application\Commercial\Command\ArchiveServiceTypeHandler;
use Pet\Domain\Commercial\Repository\ServiceTypeRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ServiceTypeController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'service-types';

    private ServiceTypeRepository $repository;
    private CreateServiceTypeHandler $createHandler;
    private UpdateServiceTypeHandler $updateHandler;
    private ArchiveServiceTypeHandler $archiveHandler;

    public function __construct(
        ServiceTypeRepository $repository,
        CreateServiceTypeHandler $createHandler,
        UpdateServiceTypeHandler $updateHandler,
        ArchiveServiceTypeHandler $archiveHandler
    ) {
        $this->repository = $repository;
        $this->createHandler = $createHandler;
        $this->updateHandler = $updateHandler;
        $this->archiveHandler = $archiveHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list'], 'permission_callback' => [$this, 'checkPermission']],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create'], 'permission_callback' => [$this, 'checkPermission']],
        ]);
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            ['methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => [$this, 'checkPermission']],
            ['methods' => 'POST', 'callback' => [$this, 'archive'], 'permission_callback' => [$this, 'checkPermission']],
        ]);
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $items = $this->repository->findAll();
        $data = array_map(fn($st) => [
            'id' => $st->id(),
            'name' => $st->name(),
            'description' => $st->description(),
            'status' => $st->status(),
            'created_at' => $st->createdAt()->format('c'),
            'updated_at' => $st->updatedAt()->format('c'),
        ], $items);
        return new WP_REST_Response($data, 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        try {
            $id = $this->createHandler->handle(new CreateServiceTypeCommand(
                $params['name'] ?? '',
                $params['description'] ?? null
            ));
            return new WP_REST_Response(['id' => $id, 'status' => 'created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        try {
            $this->updateHandler->handle(new UpdateServiceTypeCommand(
                (int)$request->get_param('id'),
                $params['name'] ?? '',
                $params['description'] ?? null
            ));
            return new WP_REST_Response(['status' => 'updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function archive(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $this->archiveHandler->handle(new ArchiveServiceTypeCommand((int)$request->get_param('id')));
            return new WP_REST_Response(['status' => 'archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
