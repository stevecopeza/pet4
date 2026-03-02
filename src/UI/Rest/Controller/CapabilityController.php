<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Work\Repository\CapabilityRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class CapabilityController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'capabilities';

    private $capabilityRepository;

    public function __construct(CapabilityRepository $capabilityRepository)
    {
        $this->capabilityRepository = $capabilityRepository;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCapabilities'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getCapabilities(WP_REST_Request $request): WP_REST_Response
    {
        $capabilities = $this->capabilityRepository->findAll();

        $data = array_map(function ($capability) {
            return [
                'id' => $capability->id(),
                'name' => $capability->name(),
                'description' => $capability->description(),
            ];
        }, $capabilities);

        return new WP_REST_Response($data, 200);
    }
}
