<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Directory;

interface UserDirectory
{
    public function getDisplayName(int $userId): ?string;

    public function getAvatarUrl(int $userId): ?string;
}
