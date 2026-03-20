<?php

declare(strict_types=1);

namespace Pet\UI\Standalone;

use Pet\Application\Identity\Service\StaffEmployeeResolver;
use Pet\Application\System\Service\FeatureFlagService;

/**
 * Serves the Staff Time Capture React app at /pet-time-capture/ outside wp-admin.
 */
class StandaloneStaffTimeCapturePage
{
    public function __construct(
        private string $pluginPath,
        private string $pluginUrl,
        private FeatureFlagService $featureFlags,
        private StaffEmployeeResolver $staffEmployeeResolver
    ) {
        $this->pluginPath = rtrim($this->pluginPath, '/');
        $this->pluginUrl = rtrim($this->pluginUrl, '/');
    }

    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRule']);
        add_filter('query_vars', [$this, 'addQueryVar']);
        add_action('template_redirect', [$this, 'render'], 1);
        add_filter('redirect_canonical', [$this, 'bypassCanonicalRedirect'], 10, 2);
    }

    public function addRewriteRule(): void
    {
        add_rewrite_rule(
            '^pet-time-capture/?$',
            'index.php?pet_standalone_staff_time_capture=1',
            'top'
        );
    }

    /**
     * @param string[] $vars
     */
    public function addQueryVar(array $vars): array
    {
        $vars[] = 'pet_standalone_staff_time_capture';
        return $vars;
    }

    /**
     * @param string|false $redirect
     * @return string|false
     */
    public function bypassCanonicalRedirect($redirect)
    {
        if ($this->isStandaloneRequest()) {
            return false;
        }
        return $redirect;
    }

    public function render(): void
    {
        if (!$this->isStandaloneRequest()) {
            return;
        }
        $isAdmin = current_user_can('manage_options');
        if (!$this->featureFlags->isStaffTimeCaptureEnabled() && !$isAdmin) {
            status_header(404);
            exit;
        }

        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/pet-time-capture/')));
            exit;
        }

        $wpUserId = (int) get_current_user_id();
        if (!$isAdmin) {
            $resolved = $this->staffEmployeeResolver->resolve($wpUserId);
            if (!$resolved['ok']) {
                wp_die(
                    esc_html((string) $resolved['message']),
                    'Forbidden',
                    ['response' => 403]
                );
            }
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
            fn(string $file): string => $this->pluginUrl . '/dist/' . $file,
            $manifest[$entryKey]['css'] ?? []
        );

        $cacheBust = '1.0.2.' . time();
        $nonce = wp_create_nonce('wp_rest');
        $apiUrl = rest_url('pet/v1');
        status_header(200);
        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->is_404 = false;
        }

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PET — Staff Time Capture</title>
    <?php foreach ($cssFiles as $css): ?>
    <link rel="stylesheet" href="<?php echo esc_url($css . '?v=' . $cacheBust); ?>" />
    <?php endforeach; ?>
    <style>
        html, body { margin: 0; padding: 0; background: #f0f2f5; }
    </style>
</head>
<body>
    <div id="pet-admin-root"></div>
    <script>
        window.petSettings = {
            apiUrl:        <?php echo wp_json_encode($apiUrl); ?>,
            nonce:         <?php echo wp_json_encode($nonce); ?>,
            currentPage:   "pet-time-capture",
            currentUserId: <?php echo (int) $wpUserId; ?>
        };
    </script>
    <script src="<?php echo esc_url($jsFile . '?v=' . $cacheBust); ?>"></script>
</body>
</html>
        <?php
        exit;
    }

    private function isStandaloneRequest(): bool
    {
        if ((bool) get_query_var('pet_standalone_staff_time_capture')) {
            return true;
        }

        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($requestUri === '') {
            return false;
        }

        $path = (string) wp_parse_url($requestUri, PHP_URL_PATH);
        $normalized = trim($path, '/');

        return $normalized === 'pet-time-capture';
    }
}
