<?php

declare(strict_types=1);

/**
 * PET Test Bootstrap
 *
 * Loads Composer autoloader and stubs WordPress functions/constants
 * so domain-layer unit tests can run without a WordPress environment.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists('wpdb')) {
    class wpdb
    {
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params = [];
        private array $jsonParams = [];

        public function __construct(string $method = 'GET', string $route = '')
        {
        }

        public function get_param(string $key)
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, $value): void
        {
            $this->params[$key] = $value;
        }

        public function get_json_params(): array
        {
            return $this->jsonParams;
        }

        public function set_json_params(array $params): void
        {
            $this->jsonParams = $params;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(private $data = null, private int $status = 200)
        {
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
        public const EDITABLE = 'PUT,PATCH';
        public const DELETABLE = 'DELETE';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args): bool
    {
        return true;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim($value);
    }
}

if (!function_exists('sanitize_url')) {
    function sanitize_url(string $value): string
    {
        return trim($value);
    }
}

// ── WordPress constant stubs (only define if not already defined) ──
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wp-stub/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('OBJECT_K')) {
    define('OBJECT_K', 'OBJECT_K');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

// ── WordPress function stubs used by domain/application code ──
if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return 1;
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($gmt ? 'UTC' : 'UTC')))->format(
            $type === 'mysql' ? 'Y-m-d H:i:s' : 'U'
        );
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!isset($GLOBALS['pet_test_transient_store']) || !is_array($GLOBALS['pet_test_transient_store'])) {
    $GLOBALS['pet_test_transient_store'] = [];
}

if (!function_exists('get_transient')) {
    function get_transient(string $transient)
    {
        return array_key_exists($transient, $GLOBALS['pet_test_transient_store'])
            ? $GLOBALS['pet_test_transient_store'][$transient]
            : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, $value, int $expiration = 0): bool
    {
        $GLOBALS['pet_test_transient_store'][$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        if (!array_key_exists($transient, $GLOBALS['pet_test_transient_store'])) {
            return false;
        }
        unset($GLOBALS['pet_test_transient_store'][$transient]);
        return true;
    }
}
