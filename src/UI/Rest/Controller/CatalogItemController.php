<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\CreateCatalogItemCommand;
use Pet\Application\Commercial\Command\CreateCatalogItemHandler;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class CatalogItemController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'catalog-items';

    private CatalogItemRepository $repository;
    private CreateCatalogItemHandler $createHandler;

    public function __construct(
        CatalogItemRepository $repository,
        CreateCatalogItemHandler $createHandler
    ) {
        $this->repository = $repository;
        $this->createHandler = $createHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getItems'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function getItems(WP_REST_Request $request): WP_REST_Response
    {
        $items = $this->repository->findAll();
        
        $data = array_map(function ($item) {
            return [
                'id' => $item->id(),
                'sku' => $item->sku(),
                'name' => $item->name(),
                'type' => $item->type(),
                'description' => $item->description(),
                'category' => $item->category(),
                'wbs_template' => $item->wbsTemplate(),
                'unit_price' => $item->unitPrice(),
                'unit_cost' => $item->unitCost(),
            ];
        }, $items);

        return new WP_REST_Response($data, 200);
    }

    public function createItem(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        try {
            $command = new CreateCatalogItemCommand(
                $params['name'],
                (float) $params['unit_price'],
                (float) ($params['unit_cost'] ?? 0.0),
                $params['sku'] ?? null,
                $params['description'] ?? null,
                $params['category'] ?? null,
                $params['type'] ?? 'product',
                $params['wbs_template'] ?? []
            );

            $this->createHandler->handle($command);

            return new WP_REST_Response(['status' => 'created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
