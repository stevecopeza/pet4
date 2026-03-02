<?php
declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration;

final class MigrationManifest
{
    public static function getAll(): array
    {
        return MigrationRegistry::all();
    }
}
