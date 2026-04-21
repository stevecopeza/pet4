<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Support;

/**
 * Sanitises exception messages before they reach REST clients.
 *
 * Domain exceptions (DomainException subclasses) carry intentional,
 * user-facing messages describing business-rule violations — these are
 * safe to return verbatim.
 *
 * All other exceptions may carry internal details (table names, query
 * fragments, file paths) and must never be returned to clients. They are
 * logged server-side and replaced with a generic message.
 *
 * Usage in a controller catch block:
 *
 *   } catch (\Exception $e) {
 *       return new WP_REST_Response(
 *           ['error' => RestError::message($e)],
 *           $e instanceof \DomainException ? 422 : 500
 *       );
 *   }
 */
class RestError
{
    public static function message(\Throwable $e): string
    {
        if ($e instanceof \DomainException) {
            // Business-rule violations: message is intentionally user-facing.
            return $e->getMessage();
        }

        // Infrastructure / unexpected failure: log internally, return nothing useful.
        error_log(
            '[PET REST] Unhandled ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine()
        );

        return 'An error occurred. Please try again or contact your administrator.';
    }
}
