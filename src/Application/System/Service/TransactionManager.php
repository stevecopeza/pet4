<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

interface TransactionManager
{
    /**
     * Executes the given operation within a database transaction.
     *
     * @param callable $operation
     * @return mixed The return value of the operation
     * @throws \Throwable If the operation fails
     */
    public function transactional(callable $operation);
}
