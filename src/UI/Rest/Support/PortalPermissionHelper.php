<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Support;

/**
 * Shared permission helper for portal-accessible REST endpoints.
 *
 * Usage in a REST controller:
 *
 *   use Pet\UI\Rest\Support\PortalPermissionHelper;
 *
 *   public function checkPortalPermission(): bool
 *   {
 *       return PortalPermissionHelper::check('pet_sales', 'pet_hr', 'pet_manager');
 *   }
 *
 * Rules:
 * - `manage_options` always passes (admin panel users unaffected).
 * - User must be logged in.
 * - At least one of the supplied capabilities must be held.
 *
 * Available portal capabilities (registered on plugin activation):
 *   pet_sales   — Sales staff: customers, leads, quotes
 *   pet_hr      — HR staff: employees, catalog
 *   pet_manager — Managers: everything including approval queue
 */
class PortalPermissionHelper
{
    public static function check(string ...$caps): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        foreach ($caps as $cap) {
            if (current_user_can($cap)) {
                return true;
            }
        }

        return false;
    }
}
