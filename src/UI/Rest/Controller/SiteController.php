<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Identity\Repository\SiteRepository;
use Pet\Application\Identity\Command\CreateSiteCommand;
use Pet\Application\Identity\Command\CreateSiteHandler;
use Pet\Application\Identity\Command\UpdateSiteCommand;
use Pet\Application\Identity\Command\UpdateSiteHandler;
use Pet\Application\Identity\Command\ArchiveSiteCommand;
use Pet\Application\Identity\Command\ArchiveSiteHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SiteController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'sites';

    private SiteRepository $siteRepository;
    private CreateSiteHandler $createSiteHandler;
    private UpdateSiteHandler $updateSiteHandler;
    private ArchiveSiteHandler $archiveSiteHandler;

    public function __construct(
        SiteRepository $siteRepository,
        CreateSiteHandler $createSiteHandler,
        UpdateSiteHandler $updateSiteHandler,
        ArchiveSiteHandler $archiveSiteHandler
    ) {
        $this->siteRepository = $siteRepository;
        $this->createSiteHandler = $createSiteHandler;
        $this->updateSiteHandler = $updateSiteHandler;
        $this->archiveSiteHandler = $archiveSiteHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSites'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createSite'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateSite'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveSite'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getSites(WP_REST_Request $request): WP_REST_Response
    {
        $customerId = $request->get_param('customer_id');
        
        if ($customerId) {
            $sites = $this->siteRepository->findByCustomerId((int) $customerId);
        } else {
            $sites = $this->siteRepository->findAll();
        }

        $data = array_map(function ($site) {
            return [
                'id' => $site->id(),
                'customerId' => $site->customerId(),
                'name' => $site->name(),
                'addressLines' => $site->addressLines(),
                'city' => $site->city(),
                'state' => $site->state(),
                'postalCode' => $site->postalCode(),
                'country' => $site->country(),
                'status' => $site->status(),
                'malleableData' => $site->malleableData(),
                'createdAt' => $site->createdAt()->format('Y-m-d H:i:s'),
                'archivedAt' => $site->archivedAt() ? $site->archivedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $sites);

        return new WP_REST_Response($data, 200);
    }

    public function createSite(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['customerId'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $command = new CreateSiteCommand(
                (int) $params['customerId'],
                $params['name'],
                $params['addressLines'] ?? null,
                $params['city'] ?? null,
                $params['state'] ?? null,
                $params['postalCode'] ?? null,
                $params['country'] ?? null,
                $params['status'] ?? 'active',
                $params['malleableData'] ?? []
            );

            $this->createSiteHandler->handle($command);

            return new WP_REST_Response(['message' => 'Site created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    public function updateSite(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['customerId'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $command = new UpdateSiteCommand(
                $id,
                (int) $params['customerId'],
                $params['name'],
                $params['addressLines'] ?? null,
                $params['city'] ?? null,
                $params['state'] ?? null,
                $params['postalCode'] ?? null,
                $params['country'] ?? null,
                $params['status'] ?? 'active',
                $params['malleableData'] ?? []
            );

            $this->updateSiteHandler->handle($command);

            return new WP_REST_Response(['message' => 'Site updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    public function archiveSite(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new ArchiveSiteCommand($id);
            $this->archiveSiteHandler->handle($command);

            return new WP_REST_Response(['message' => 'Site archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }
}
