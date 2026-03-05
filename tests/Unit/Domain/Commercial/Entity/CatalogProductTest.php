<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial\Entity;

use Pet\Domain\Commercial\Entity\CatalogProduct;
use PHPUnit\Framework\TestCase;

class CatalogProductTest extends TestCase
{
    private function makeProduct(): CatalogProduct
    {
        return new CatalogProduct('SKU-001', 'Widget', 100.0, 60.0, 'A widget', 'Hardware');
    }

    // ── Construction ──

    public function testConstructionSetsFields(): void
    {
        $p = $this->makeProduct();
        $this->assertNull($p->id());
        $this->assertSame('SKU-001', $p->sku());
        $this->assertSame('Widget', $p->name());
        $this->assertSame('A widget', $p->description());
        $this->assertSame('Hardware', $p->category());
        $this->assertSame(100.0, $p->unitPrice());
        $this->assertSame(60.0, $p->unitCost());
        $this->assertSame('active', $p->status());
    }

    public function testConstructionWithNullOptionals(): void
    {
        $p = new CatalogProduct('SKU-002', 'Gadget', 50.0, 30.0);
        $this->assertNull($p->description());
        $this->assertNull($p->category());
    }

    public function testConstructionRejectsNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unit price cannot be negative');
        new CatalogProduct('SKU-X', 'Bad', -1.0, 0.0);
    }

    public function testConstructionRejectsNegativeCost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unit cost cannot be negative');
        new CatalogProduct('SKU-X', 'Bad', 10.0, -5.0);
    }

    public function testConstructionRejectsEmptySku(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU cannot be empty');
        new CatalogProduct('  ', 'Name', 10.0, 5.0);
    }

    public function testConstructionRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name cannot be empty');
        new CatalogProduct('SKU-X', '  ', 10.0, 5.0);
    }

    // ── update() ──

    public function testUpdateChangesFields(): void
    {
        $p = $this->makeProduct();
        $p->update('Widget Pro', 120.0, 70.0, 'Upgraded', 'Premium');
        $this->assertSame('Widget Pro', $p->name());
        $this->assertSame(120.0, $p->unitPrice());
        $this->assertSame(70.0, $p->unitCost());
        $this->assertSame('Upgraded', $p->description());
        $this->assertSame('Premium', $p->category());
    }

    public function testUpdateOnArchivedThrows(): void
    {
        $p = $this->makeProduct();
        $p->archive();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot update an archived');
        $p->update('X', 10.0, 5.0, null, null);
    }

    public function testUpdateRejectsNegativePrice(): void
    {
        $p = $this->makeProduct();
        $this->expectException(\InvalidArgumentException::class);
        $p->update('X', -1.0, 5.0, null, null);
    }

    public function testUpdateRejectsNegativeCost(): void
    {
        $p = $this->makeProduct();
        $this->expectException(\InvalidArgumentException::class);
        $p->update('X', 10.0, -1.0, null, null);
    }

    // ── archive() ──

    public function testArchiveChangesStatus(): void
    {
        $p = $this->makeProduct();
        $p->archive();
        $this->assertSame('archived', $p->status());
    }

    public function testArchiveAlreadyArchivedThrows(): void
    {
        $p = $this->makeProduct();
        $p->archive();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already archived');
        $p->archive();
    }
}
