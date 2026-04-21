<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\CreateCatalogItemCommand;
use Pet\Application\Commercial\Command\CreateCatalogItemHandler;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;
use Pet\UI\Rest\Support\PortalPermissionHelper;
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
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createItem'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateItem'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteItem'],
                'permission_callback' => [$this, 'checkPortalPermission'],
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

        // Duplicate SKU check
        $sku = $params['sku'] ?? null;
        if ($sku !== null && $sku !== '') {
            $existing = $this->repository->findBySku($sku);
            if ($existing !== null) {
                return new WP_REST_Response(
                    ['error' => "A catalog item with SKU \"$sku\" already exists."],
                    409
                );
            }
        }

        try {
            $command = new CreateCatalogItemCommand(
                $params['name'],
                (float) $params['unit_price'],
                (float) ($params['unit_cost'] ?? 0.0),
                $sku,
                $params['description'] ?? null,
                $params['category'] ?? null,
                $params['type'] ?? 'product',
                $params['wbs_template'] ?? []
            );

            $this->createHandler->handle($command);

            // Return the newly created item so callers can immediately use it
            global $wpdb;
            $newId = (int) $wpdb->insert_id;
            if ($newId > 0) {
                $created = $this->repository->findById($newId);
                if ($created) {
                    return new WP_REST_Response([
                        'id'         => $created->id(),
                        'name'       => $created->name(),
                        'unit_price' => $created->unitPrice(),
                        'unit_cost'  => $created->unitCost(),
                        'type'       => $created->type(),
                    ], 201);
                }
            }

            return new WP_REST_Response(['status' => 'created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function updateItem(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $existing = $this->repository->findById($id);
            if (!$existing) {
                return new WP_REST_Response(['error' => 'Catalog item not found.'], 404);
            }

            // Duplicate SKU check (exclude self)
            $sku = $params['sku'] ?? $existing->sku();
            if ($sku !== null && $sku !== '') {
                $skuMatch = $this->repository->findBySku($sku);
                if ($skuMatch !== null && $skuMatch->id() !== $id) {
                    return new WP_REST_Response(
                        ['error' => "A catalog item with SKU \"$sku\" already exists."],
                        409
                    );
                }
            }

            $item = new \Pet\Domain\Commercial\Entity\CatalogItem(
                $params['name'] ?? $existing->name(),
                (float) ($params['unit_price'] ?? $existing->unitPrice()),
                (float) ($params['unit_cost'] ?? $existing->unitCost()),
                $params['type'] ?? $existing->type(),
                $sku,
                array_key_exists('description', $params) ? $params['description'] : $existing->description(),
                array_key_exists('category', $params) ? $params['category'] : $existing->category(),
                $params['wbs_template'] ?? $existing->wbsTemplate(),
                $id,
                $existing->createdAt()
            );

            $this->repository->save($item);

            return new WP_REST_Response([
                'id' => $item->id(),
                'sku' => $item->sku(),
                'name' => $item->name(),
                'type' => $item->type(),
                'description' => $item->description(),
                'category' => $item->category(),
                'wbs_template' => $item->wbsTemplate(),
                'unit_price' => $item->unitPrice(),
                'unit_cost' => $item->unitCost(),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function deleteItem(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $existing = $this->repository->findById($id);
            if (!$existing) {
                return new WP_REST_Response(['error' => 'Catalog item not found.'], 404);
            }

            $this->repository->delete($id);

            return new WP_REST_Response(['status' => 'deleted'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function checkPortalPermission(): bool
    {
        return PortalPermissionHelper::check('pet_sales', 'pet_hr', 'pet_manager');
    }
}
