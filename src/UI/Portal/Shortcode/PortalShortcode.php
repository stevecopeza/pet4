<?php

declare(strict_types=1);

namespace Pet\UI\Portal\Shortcode;

/**
 * PortalShortcode
 *
 * Registers the [pet_portal] shortcode.
 * Renders a React SPA mount point and injects window.petSettings
 * with the portal bundle — same shape as the admin panel injection.
 *
 * Usage: Create a WP page with slug /portal, body: [pet_portal]
 */
class PortalShortcode
{
    private string $pluginPath;
    private string $pluginUrl;

    public function __construct(string $pluginPath, string $pluginUrl)
    {
        $this->pluginPath = rtrim($pluginPath, '/');
        $this->pluginUrl  = rtrim($pluginUrl, '/');
    }

    public function register(): void
    {
        add_action('init', function () {
            add_shortcode('pet_portal', [$this, 'render']);
        });
    }

    public function render(array $atts = [], ?string $content = null): string
    {
        // Must be logged in
        if (!is_user_logged_in()) {
            $loginUrl = wp_login_url(get_permalink());
            return '<p style="padding:40px;text-align:center;">'
                . '<a href="' . esc_url($loginUrl) . '" style="color:#2563eb;font-weight:600;">Sign in to access the staff portal →</a>'
                . '</p>';
        }

        $this->enqueueAssets();

        // Body class for CSS targeting
        add_filter('body_class', function (array $classes): array {
            $classes[] = 'portal-page';
            return $classes;
        });

        // Suppress WP theme chrome so the portal is a full-page SPA.
        // Hides the theme's site header, navigation, page title, sidebar, and footer.
        // Works with Twenty Twenty-Five and most block/classic themes.
        add_action('wp_head', function (): void {
            echo '<style id="pet-portal-chrome-suppress">
                /* Hide WP theme chrome on portal page */
                body.portal-page .site-header,
                body.portal-page header.wp-block-template-part,
                body.portal-page .wp-block-template-part[class*="header"],
                body.portal-page footer.wp-block-template-part,
                body.portal-page .wp-block-template-part[class*="footer"],
                body.portal-page .site-footer,
                body.portal-page .entry-title,
                body.portal-page h1.wp-block-post-title,
                body.portal-page .wp-block-post-title,
                body.portal-page nav.main-navigation,
                body.portal-page .navigation-branding,
                body.portal-page .widget-area { display: none !important; }

                /* Remove all padding/margin from WP content containers */
                body.portal-page,
                body.portal-page .wp-site-blocks,
                body.portal-page .site,
                body.portal-page .site-content,
                body.portal-page main.wp-block-group,
                body.portal-page .wp-block-group,
                body.portal-page .entry-content,
                body.portal-page article,
                body.portal-page .hentry,
                body.portal-page .page-content {
                    max-width: none !important;
                    padding: 0 !important;
                    margin: 0 !important;
                    width: 100% !important;
                }

                /* Ensure the portal root fills the page */
                body.portal-page #pet-portal-root {
                    display: block;
                    width: 100%;
                    min-height: 100vh;
                }
            </style>';
        });

        return '<div id="pet-portal-root"></div>';
    }

    private function enqueueAssets(): void
    {
        $manifestPath = $this->pluginPath . '/dist/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            // Build hasn't run yet — show helpful message
            add_action('wp_footer', function () {
                echo '<div style="padding:40px;text-align:center;color:#b32d2e;">'
                    . 'PET Portal: build not found. Run <code>npm run build</code> in the plugin directory.'
                    . '</div>';
            });
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $entryKey = 'src/UI/Portal/main.tsx';

        if (!isset($manifest[$entryKey])) {
            add_action('wp_footer', function () {
                echo '<div style="padding:40px;text-align:center;color:#b32d2e;">'
                    . 'PET Portal: portal entry not found in build manifest. Run <code>npm run build</code>.'
                    . '</div>';
            });
            return;
        }

        $jsFile  = $manifest[$entryKey]['file'];
        $cssFiles = $manifest[$entryKey]['css'] ?? [];

        // Enqueue portal JS (Vite outputs ES modules — must be loaded as type="module")
        wp_enqueue_script(
            'pet-portal-app',
            $this->pluginUrl . '/dist/' . $jsFile,
            [],
            null,
            true // load in footer
        );

        // WordPress does not add type="module" by default. Filter the tag to inject it.
        // The browser module system then handles all relative chunk imports automatically.
        add_filter('script_loader_tag', static function (string $tag, string $handle): string {
            if ($handle === 'pet-portal-app') {
                $tag = str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);

        // Enqueue portal CSS
        foreach ($cssFiles as $index => $cssFile) {
            wp_enqueue_style(
                'pet-portal-style-' . $index,
                $this->pluginUrl . '/dist/' . $cssFile,
                [],
                null
            );
        }

        // Inject window.petSettings — same shape as admin panel
        $currentUser = wp_get_current_user();
        $caps        = $this->getUserPortalCaps($currentUser);

        wp_localize_script('pet-portal-app', 'petSettings', [
            'apiUrl'                  => rest_url('pet/v1'),
            'nonce'                   => wp_create_nonce('wp_rest'),
            'currentUserId'           => get_current_user_id(),
            'currentUserDisplayName'  => $currentUser->display_name,
            'currentUserEmail'        => $currentUser->user_email,
            'currentUserCaps'         => $caps,
            'logoutUrl'               => wp_logout_url(home_url('/portal')),
        ]);
    }

    /**
     * Returns an array of capability strings held by the user.
     * Includes portal caps and manage_options so the React app
     * can derive role labels and access gates cleanly.
     */
    private function getUserPortalCaps(\WP_User $user): array
    {
        $portalCaps = ['pet_sales', 'pet_hr', 'pet_manager', 'pet_staff', 'manage_options'];
        $held = [];
        foreach ($portalCaps as $cap) {
            if (user_can($user, $cap)) {
                $held[] = $cap;
            }
        }
        return $held;
    }
}
