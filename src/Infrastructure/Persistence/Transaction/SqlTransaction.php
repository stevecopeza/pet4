<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Transaction;

use Pet\Application\System\Service\TransactionManager;

class SqlTransaction implements TransactionManager
{
    private $wpdb;
    private int $depth = 0;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function begin(): void
    {
        if ($this->depth === 0) {
            $this->wpdb->query('START TRANSACTION');
        }
        $this->depth++;
    }

    public function commit(): void
    {
        $this->depth--;
        if ($this->depth === 0) {
            $this->wpdb->query('COMMIT');
        }
    }

    public function rollback(): void
    {
        // If an inner transaction rolls back, we must ensure the outer one knows.
        // However, standard pattern is to rethrow exception.
        // So we rollback the whole thing if we are at top level.
        // If we are nested, we can't rollback just the inner part (unless savepoints).
        // For now, we assume failure = total failure.
        // We only rollback if we started it (depth 0 or 1?).
        // Actually, if we are in a transaction, we should rollback.
        // But if depth > 0, and we rollback, the outer code might try to commit later?
        // If outer code catches exception, it should also rollback.
        
        // Let's keep it simple: if depth > 0, we do nothing here (rely on exception propagation).
        // Wait, if inner code calls rollback explicitly (without exception), then we are in trouble.
        // But transactional() pattern uses try-catch.
        
        // If someone calls rollback(), they usually intend to abort.
        // If we are nested, we can't abort just this level without savepoints.
        // So we abort everything.
        
        if ($this->depth > 0) {
            // We are nested. We cannot safely rollback just this level.
            // We must force a rollback of the root transaction.
            // But we can't easily tell the root to rollback without throwing.
            // So we rollback the actual DB transaction.
            $this->wpdb->query('ROLLBACK');
            // And reset depth to 0 because the DB transaction is gone.
            $this->depth = 0;
            return;
        }

        // If depth is 0 (should not happen if begin was called), or we are just cleaning up.
        $this->wpdb->query('ROLLBACK');
        $this->depth = 0;
    }

    public function transactional(callable $operation)
    {
        $this->begin();
        try {
            $result = $operation();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
