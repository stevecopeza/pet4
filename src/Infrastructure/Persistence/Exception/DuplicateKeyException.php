<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Exception;

/**
 * Thrown when a database insert fails due to a duplicate unique key violation.
 */
class DuplicateKeyException extends \RuntimeException
{
}
