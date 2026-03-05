<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Service;

use Pet\Application\Commercial\Service\RateCardResolver;
use Pet\Domain\Commercial\Entity\RateCard;
use Pet\Domain\Commercial\Repository\RateCardRepository;
use PHPUnit\Framework\TestCase;

class RateCardResolverTest extends TestCase
{
    private RateCardRepository $repo;
    private RateCardResolver $resolver;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(RateCardRepository::class);
        $this->resolver = new RateCardResolver($this->repo);
    }

    private function makeCard(float $rate = 150.0, ?int $contractId = null): RateCard
    {
        return new RateCard(1, 2, $rate, $contractId);
    }

    // ── Contract-specific match ──

    public function testResolvesContractSpecificFirst(): void
    {
        $contractCard = $this->makeCard(200.0, 5);
        $this->repo->method('findForResolution')
            ->willReturnCallback(function (int $roleId, int $stId, ?int $cId, \DateTimeImmutable $date) use ($contractCard) {
                return $cId === 5 ? $contractCard : null;
            });

        $result = $this->resolver->resolve(1, 2, 5, new \DateTimeImmutable());
        $this->assertSame(200.0, $result->sellRate());
        $this->assertSame(5, $result->contractId());
    }

    // ── Global fallback ──

    public function testFallsBackToGlobalWhenNoContractMatch(): void
    {
        $globalCard = $this->makeCard(100.0, null);
        $this->repo->method('findForResolution')
            ->willReturnCallback(function (int $roleId, int $stId, ?int $cId, \DateTimeImmutable $date) use ($globalCard) {
                return $cId === null ? $globalCard : null;
            });

        $result = $this->resolver->resolve(1, 2, 5, new \DateTimeImmutable());
        $this->assertSame(100.0, $result->sellRate());
        $this->assertNull($result->contractId());
    }

    // ── Global with no contract ──

    public function testResolvesGlobalWhenContractIdNull(): void
    {
        $globalCard = $this->makeCard(80.0, null);
        $this->repo->method('findForResolution')
            ->willReturnCallback(function (int $roleId, int $stId, ?int $cId, \DateTimeImmutable $date) use ($globalCard) {
                return $cId === null ? $globalCard : null;
            });

        $result = $this->resolver->resolve(1, 2, null, new \DateTimeImmutable());
        $this->assertSame(80.0, $result->sellRate());
    }

    // ── No match throws ──

    public function testThrowsWhenNoCardFound(): void
    {
        $this->repo->method('findForResolution')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No valid rate card');
        $this->resolver->resolve(1, 2, null, new \DateTimeImmutable('2026-06-15'));
    }

    public function testThrowsWhenNoContractOrGlobalFound(): void
    {
        $this->repo->method('findForResolution')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('contract 5 or global');
        $this->resolver->resolve(1, 2, 5, new \DateTimeImmutable('2026-06-15'));
    }
}
