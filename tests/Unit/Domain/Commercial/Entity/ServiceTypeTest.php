<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial\Entity;

use Pet\Domain\Commercial\Entity\ServiceType;
use PHPUnit\Framework\TestCase;

class ServiceTypeTest extends TestCase
{
    // ── Construction ──

    public function testConstructionSetsFields(): void
    {
        $st = new ServiceType('Managed Service', 'Ongoing support');
        $this->assertNull($st->id());
        $this->assertSame('Managed Service', $st->name());
        $this->assertSame('Ongoing support', $st->description());
        $this->assertSame('active', $st->status());
        $this->assertInstanceOf(\DateTimeImmutable::class, $st->createdAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $st->updatedAt());
    }

    public function testConstructionWithNullDescription(): void
    {
        $st = new ServiceType('Project Work');
        $this->assertNull($st->description());
    }

    public function testConstructionRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name cannot be empty');
        new ServiceType('   ');
    }

    public function testConstructionRejectsInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ServiceType status');
        new ServiceType('Test', null, 'bogus');
    }

    // ── update() ──

    public function testUpdateChangesNameAndDescription(): void
    {
        $st = new ServiceType('Old', 'Old desc');
        $st->update('New', 'New desc');
        $this->assertSame('New', $st->name());
        $this->assertSame('New desc', $st->description());
    }

    public function testUpdateRejectsEmptyName(): void
    {
        $st = new ServiceType('Valid');
        $this->expectException(\InvalidArgumentException::class);
        $st->update('', null);
    }

    public function testUpdateOnArchivedThrows(): void
    {
        $st = new ServiceType('Valid');
        $st->archive();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot update an archived');
        $st->update('Changed', null);
    }

    // ── archive() ──

    public function testArchiveChangesStatus(): void
    {
        $st = new ServiceType('Valid');
        $st->archive();
        $this->assertSame('archived', $st->status());
    }

    public function testArchiveAlreadyArchivedThrows(): void
    {
        $st = new ServiceType('Valid');
        $st->archive();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already archived');
        $st->archive();
    }
}
