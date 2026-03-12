<?php

declare(strict_types=1);

namespace Pet\Tests\Stub;

use Pet\Application\System\Service\TransactionManager;

class FakeTransactionManager implements TransactionManager
{
    public function transactional(callable $operation)
    {
        return $operation();
    }
}
