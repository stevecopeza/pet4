<?php

declare(strict_types=1);

namespace Pet\UI\Standalone;

use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Dashboard\Service\DashboardAccessPolicy;

/**
 * Serves the Dashboards React app at /pet-dashboards/ — completely outside WP admin.
 *
 * Requires the user to be logged-in with manage_options capability.
 * Reads the same Vite manifest used by AdminPageRegistry to load JS/CSS.
 */
class StandaloneDashboardPage
{
    private string $pluginPath;
    private string $pluginUrl;
    private FeatureFlagService $featureFlags;
    private DashboardAccessPolicy $accessPolicy;

    public function __construct(string $pluginPath, string $pluginUrl, FeatureFlagService $featureFlags, DashboardAccessPolicy $accessPolicy)
    {
        $this->pluginPath = rtrim($pluginPath, '/');
        $this->pluginUrl = rtrim($pluginUrl, '/');
        $this->featureFlags = $featureFlags;
        $this->accessPolicy = $accessPolicy;
    }

    public function register(): void
    {
        if (!$this->featureFlags->isDashboardsEnabled()) {
            return;
        }

        add_action('init', [$this, 'addRewriteRule']);
        add_filter('query_vars', [$this, 'addQueryVar']);
        add_action('template_redirect', [$this, 'render'], 1);
        add_filter('redirect_canonical', [$this, 'bypassCanonicalRedirect'], 10, 2);
    }

    public function addRewriteRule(): void
    {
        add_rewrite_rule(
            '^pet-dashboards/?$',
            'index.php?pet_standalone_dashboard=1',
            'top'
        );
    }

    /** @param string[] $vars */
    public function addQueryVar(array $vars): array
    {
        $vars[] = 'pet_standalone_dashboard';
        return $vars;
    }

    /**
     * Prevent WordPress from redirecting our custom endpoint to a 404 or similar.
     * @param string|false $redirect
     * @return string|false
     */
    public function bypassCanonicalRedirect($redirect)
    {
        if (get_query_var('pet_standalone_dashboard')) {
            return false;
        }
        return $redirect;
    }

    public function render(): void
    {
        if (!get_query_var('pet_standalone_dashboard')) {
            return;
        }

        if (!$this->featureFlags->isDashboardsEnabled()) {
            status_header(404);
            exit;
        }

        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/pet-dashboards/')));
            exit;
        }

        $wpUserId = (int)get_current_user_id();
        $isAdmin = current_user_can('manage_options');
        $scopes = $this->accessPolicy->listVisibleTeamScopes($wpUserId, $isAdmin);
        if (empty($scopes)) {
            wp_die('Forbidden', 'Forbidden', ['response' => 403]);
        }

        $manifestPath = $this->pluginPath . '/dist/.vite/manifest.json';
        if (!file_exists($manifestPath)) {
            wp_die('PET Plugin Error: Build manifest not found. Please run <code>npm run build</code>.', 'PET Error', ['response' => 500]);
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $entryKey = 'src/UI/Admin/main.tsx';

        if (!isset($manifest[$entryKey])) {
            wp_die('PET Plugin Error: Entry point not found in manifest.', 'PET Error', ['response' => 500]);
        }

        $jsFile = $this->pluginUrl . '/dist/' . $manifest[$entryKey]['file'];
        $cssFiles = array_map(
            fn(string $f) => $this->pluginUrl . '/dist/' . $f,
            $manifest[$entryKey]['css'] ?? []
        );

        $cacheBust = '1.0.2.' . time();
        $nonce = wp_create_nonce('wp_rest');
        $apiUrl = rest_url('pet/v1');
        $currentUserId = $wpUserId;

        // Output a minimal, standalone HTML page
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PET — Dashboards</title>
    <?php foreach ($cssFiles as $css): ?>
    <link rel="stylesheet" href="<?php echo esc_url($css . '?v=' . $cacheBust); ?>" />
    <?php endforeach; ?>
    <style>
        /* Reset any inherited WP front-end styles */
        html, body { margin: 0; padding: 0; background: #f0f2f5; }
    </style>
</head>
<body>
    <div id="pet-admin-root"></div>
    <script>
        window.petSettings = {
            apiUrl:        <?php echo wp_json_encode($apiUrl); ?>,
            nonce:         <?php echo wp_json_encode($nonce); ?>,
            currentPage:   "pet-dashboards",
            currentUserId: <?php echo (int) $currentUserId; ?>
        };
    </script>
    <script src="<?php echo esc_url($jsFile . '?v=' . $cacheBust); ?>"></script>
</body>
</html>
        <?php
        exit;
    }
}
