<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Configuration\Entity\Setting;
use Pet\Domain\Configuration\Repository\SettingRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SettingsController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'settings';

    private SettingRepository $settingRepository;

    public function __construct(SettingRepository $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE, // Using POST for updating settings (or PUT if supported, but WP REST often uses POST)
                'callback' => [$this, 'updateSetting'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getSettings(WP_REST_Request $request): WP_REST_Response
    {
        $settings = $this->settingRepository->findAll();

        $data = array_map(function ($setting) {
            return [
                'key' => $setting->key(),
                'value' => $setting->value(),
                'type' => $setting->type(),
                'description' => $setting->description(),
                'updatedAt' => $setting->updatedAt() ? $setting->updatedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $settings);

        return new WP_REST_Response($data, 200);
    }

    public function updateSetting(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PET Settings] Update request: ' . json_encode($params));
        }

        if (!isset($params['key']) || !isset($params['value'])) {
            return new WP_REST_Response(['error' => 'Key and value are required'], 400);
        }

        try {
            // Find existing setting to preserve description/type if not provided
            $existing = $this->settingRepository->findByKey($params['key']);
            
            $setting = new Setting(
                $params['key'],
                $params['value'],
                $params['type'] ?? ($existing ? $existing->type() : 'string'),
                $params['description'] ?? ($existing ? $existing->description() : '')
            );

            $this->settingRepository->save($setting);

            return new WP_REST_Response(['message' => 'Setting updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
