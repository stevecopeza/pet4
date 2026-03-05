<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Work\Entity;

use Pet\Domain\Work\Entity\Role;
use PHPUnit\Framework\TestCase;

class RoleExtendedTest extends TestCase
{
    private function makeRole(?float $rate = null): Role
    {
        return new Role(
            'Engineer',
            'senior',
            'Senior engineer',
            'Deliver quality work',
            null,      // id
            1,         // version
            'draft',   // status
            [],        // requiredSkills
            null,      // createdAt
            null,      // publishedAt
            $rate      // baseInternalRate
        );
    }

    public function testBaseInternalRateStoredOnConstruction(): void
    {
        $role = $this->makeRole(75.50);
        $this->assertSame(75.50, $role->baseInternalRate());
    }

    public function testBaseInternalRateDefaultsToNull(): void
    {
        $role = $this->makeRole();
        $this->assertNull($role->baseInternalRate());
    }

    public function testPublishWithRateSucceeds(): void
    {
        $role = $this->makeRole(80.0);
        $role->publish();
        $this->assertSame('published', $role->status());
        $this->assertNotNull($role->publishedAt());
    }

    public function testPublishWithoutRateThrows(): void
    {
        $role = $this->makeRole();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('base internal rate');
        $role->publish();
    }

    public function testPublishAlreadyPublishedThrows(): void
    {
        $role = $this->makeRole(80.0);
        $role->publish();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already published');
        $role->publish();
    }

    public function testUpdateSetsBaseInternalRate(): void
    {
        $role = $this->makeRole();
        $role->update('Engineer', 'senior', 'Updated desc', 'New criteria', [], 90.0);
        $this->assertSame(90.0, $role->baseInternalRate());
    }

    public function testUpdateClearsBaseInternalRate(): void
    {
        $role = $this->makeRole(80.0);
        $role->update('Engineer', 'senior', 'Desc', 'Criteria', [], null);
        $this->assertNull($role->baseInternalRate());
    }

    public function testUpdateOnPublishedRoleThrows(): void
    {
        $role = $this->makeRole(80.0);
        $role->publish();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot update a published role');
        $role->update('New', 'senior', 'D', 'C', []);
    }
}
