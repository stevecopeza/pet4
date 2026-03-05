<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\CreateCatalogProductCommand;
use Pet\Application\Commercial\Command\CreateCatalogProductHandler;
use Pet\Application\Commercial\Command\UpdateCatalogProductCommand;
use Pet\Application\Commercial\Command\UpdateCatalogProductHandler;
use Pet\Application\Commercial\Command\ArchiveCatalogProductCommand;
use Pet\Application\Commercial\Command\ArchiveCatalogProductHandler;
use Pet\Domain\Commercial\Repository\CatalogProductRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class CatalogProductController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'catalog-products';

    private CatalogProductRepository $repository;
    private CreateCatalogProductHandler $createHandler;
    private UpdateCatalogProductHandler $updateHandler;
    private ArchiveCatalogProductHandler $archiveHandler;

    public function __construct(
        CatalogProductRepository $repository,
        CreateCatalogProductHandler $createHandler,
        UpdateCatalogProductHandler $updateHandler,
        ArchiveCatalogProductHandler $archiveHandler
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
        ]);
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/archive', [
            ['methods' => 'POST', 'callback' => [$this, 'archive'], 'permission_callback' => [$this, 'checkPermission']],
        ]);
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $items = $this->repository->findActive();
        $data = array_map(fn($p) => [
            'id' => $p->id(),
            'sku' => $p->sku(),
            'name' => $p->name(),
            'description' => $p->description(),
            'category' => $p->category(),
            'unit_price' => $p->unitPrice(),
            'unit_cost' => $p->unitCost(),
            'status' => $p->status(),
            'created_at' => $p->createdAt()->format('c'),
            'updated_at' => $p->updatedAt()->format('c'),
        ], $items);
        return new WP_REST_Response($data, 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        try {
            $id = $this->createHandler->handle(new CreateCatalogProductCommand(
                $params['sku'] ?? '',
                $params['name'] ?? '',
                (float)($params['unit_price'] ?? 0),
                (float)($params['unit_cost'] ?? 0),
                $params['description'] ?? null,
                $params['category'] ?? null
            ));
            return new WP_REST_Response(['id' => $id, 'status' => 'created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        try {
            $this->updateHandler->handle(new UpdateCatalogProductCommand(
                (int)$request->get_param('id'),
                $params['name'] ?? '',
                (float)($params['unit_price'] ?? 0),
                (float)($params['unit_cost'] ?? 0),
                $params['description'] ?? null,
                $params['category'] ?? null
            ));
            return new WP_REST_Response(['status' => 'updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function archive(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $this->archiveHandler->handle(new ArchiveCatalogProductCommand((int)$request->get_param('id')));
            return new WP_REST_Response(['status' => 'archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
