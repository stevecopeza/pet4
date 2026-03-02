<?php

declare(strict_types=1);

namespace Pet\Domain\Support\ValueObject;

class SlaState
{
    public const ACTIVE = 'active';
    public const WARNING = 'warning';
    public const BREACHED = 'breached';
    public const PAUSED = 'paused';
    public const NONE = 'none';
}
