<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial\Entity;

use Pet\Domain\Commercial\Entity\RateCard;
use PHPUnit\Framework\TestCase;

class RateCardTest extends TestCase
{
    private function makeCard(
        float $sellRate = 150.0,
        ?int $contractId = null,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null
    ): RateCard {
        return new RateCard(1, 2, $sellRate, $contractId, $validFrom, $validTo);
    }

    // ── Construction ──

    public function testConstructionSetsFields(): void
    {
        $from = new \DateTimeImmutable('2026-01-01');
        $to = new \DateTimeImmutable('2026-12-31');
        $rc = new RateCard(3, 5, 250.0, 10, $from, $to);

        $this->assertNull($rc->id());
        $this->assertSame(3, $rc->roleId());
        $this->assertSame(5, $rc->serviceTypeId());
        $this->assertSame(250.0, $rc->sellRate());
        $this->assertSame(10, $rc->contractId());
        $this->assertEquals($from, $rc->validFrom());
        $this->assertEquals($to, $rc->validTo());
        $this->assertSame('active', $rc->status());
    }

    public function testConstructionNullableDateRange(): void
    {
        $rc = $this->makeCard();
        $this->assertNull($rc->validFrom());
        $this->assertNull($rc->validTo());
        $this->assertNull($rc->contractId());
    }

    public function testConstructionRejectsZeroRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sell rate must be greater than 0');
        new RateCard(1, 2, 0.0);
    }

    public function testConstructionRejectsNegativeRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateCard(1, 2, -10.0);
    }

    public function testConstructionRejectsFromAfterTo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom must be <= validTo');
        new RateCard(1, 2, 100.0, null, new \DateTimeImmutable('2026-12-31'), new \DateTimeImmutable('2026-01-01'));
    }

    public function testConstructionRejectsInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateCard(1, 2, 100.0, null, null, null, 'bogus');
    }

    // ── isEffectiveAt() ──

    public function testEffectiveWithNullDates(): void
    {
        $rc = $this->makeCard();
        $this->assertTrue($rc->isEffectiveAt(new \DateTimeImmutable('2020-01-01')));
        $this->assertTrue($rc->isEffectiveAt(new \DateTimeImmutable('2099-12-31')));
    }

    public function testEffectiveWithFromOnly(): void
    {
        $rc = $this->makeCard(150.0, null, new \DateTimeImmutable('2026-06-01'));
        $this->assertFalse($rc->isEffectiveAt(new \DateTimeImmutable('2026-05-31')));
        $this->assertTrue($rc->isEffectiveAt(new \DateTimeImmutable('2026-06-01')));
        $this->assertTrue($rc->isEffectiveAt(new \DateTimeImmutable('2099-01-01')));
    }

    public function testEffectiveWithToOnly(): void
    {
        $rc = $this->makeCard(150.0, null, null, new \DateTimeImmutable('2026-06-30'));
        $this->assertTrue($rc->isEffectiveAt(new \DateTimeImmutable('2020-01-01')));
        $this->assertTrue($rc->isEffectiveAt(new \DateTimeImmutable('2026-06-30')));
        $this->assertFalse($rc->isEffectiveAt(new \DateTimeImmutable('2026-07-01')));
    }

    public function testEffectiveWithBothDates(): void
    {
        $rc = $this->makeCard(150.0, null, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'));
        $this->assertFalse($rc->isEffectiveAt(new \DateTimeImmutable('2025-12-31')));
        $this->assertTrue($rc->isEffectiveAt(new \DateTimeImmutable('2026-06-15')));
        $this->assertFalse($rc->isEffectiveAt(new \DateTimeImmutable('2027-01-01')));
    }

    public function testArchivedCardNeverEffective(): void
    {
        $rc = $this->makeCard();
        $rc->archive();
        $this->assertFalse($rc->isEffectiveAt(new \DateTimeImmutable()));
    }

    // ── archive() ──

    public function testArchiveChangesStatus(): void
    {
        $rc = $this->makeCard();
        $rc->archive();
        $this->assertSame('archived', $rc->status());
    }

    public function testArchiveAlreadyArchivedThrows(): void
    {
        $rc = $this->makeCard();
        $rc->archive();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already archived');
        $rc->archive();
    }
}
