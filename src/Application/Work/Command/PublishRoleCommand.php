<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class PublishRoleCommand
{
    private int $roleId;

    public function __construct(int $roleId)
    {
        $this->roleId = $roleId;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }
}
