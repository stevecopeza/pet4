<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Persistence\Migration;

use Pet\Infrastructure\Persistence\Migration\MigrationRegistry;
use PHPUnit\Framework\TestCase;

final class MigrationRegistryTest extends TestCase
{
    public function testRegistryIncludesDemoSeedRegistryColumnExtensionMigration(): void
    {
        $migrations = MigrationRegistry::all();

        $this->assertContains(
            \Pet\Infrastructure\Persistence\Migration\Definition\AlterDemoSeedRegistryTableAddColumns::class,
            $migrations
        );
        $createIndex = array_search(
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateDemoSeedRegistryTable::class,
            $migrations,
            true
        );
        $alterIndex = array_search(
            \Pet\Infrastructure\Persistence\Migration\Definition\AlterDemoSeedRegistryTableAddColumns::class,
            $migrations,
            true
        );
        $this->assertNotFalse($createIndex);
        $this->assertNotFalse($alterIndex);
        $this->assertGreaterThan($createIndex, $alterIndex);
    }
}

