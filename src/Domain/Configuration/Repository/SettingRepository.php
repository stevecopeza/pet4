<?php

declare(strict_types=1);

namespace Pet\Domain\Configuration\Repository;

use Pet\Domain\Configuration\Entity\Setting;

interface SettingRepository
{
    public function save(Setting $setting): void;
    public function findByKey(string $key): ?Setting;
    public function findAll(): array;
}
