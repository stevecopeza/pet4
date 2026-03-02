<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

/**
 * Writes structured audit entries to the admin audit log table.
 * Covers: quote accept/reject, contract creation, ticket deletion,
 * setting changes, demo seed/purge.
 */
class AdminAuditLogger
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    /**
     * Log an administrative action.
     *
     * @param string $action  Short action key (e.g. 'quote_accepted', 'ticket_deleted')
     * @param array  $context Arbitrary evidence payload (JSON-serializable)
     * @param string $mode    Operational mode context (default 'live')
     */
    public function log(string $action, array $context = [], string $mode = 'live'): void
    {
        $table = $this->wpdb->prefix . 'pet_admin_audit_log';

        // Guard: table may not exist yet during early bootstrap/migration
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );
        if (!$exists) {
            return;
        }

        $userId = function_exists('get_current_user_id') ? get_current_user_id() : 0;

        $this->wpdb->insert($table, [
            'user_id' => $userId,
            'action' => $action,
            'mode' => $mode,
            'evidence_json' => json_encode($context, JSON_UNESCAPED_SLASHES),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
