<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Service;

use Pet\Application\Commercial\Service\QuoteBlockCostSnapshotEnricher;
use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Entity\CatalogItem;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;
use Pet\Domain\Work\Entity\Role;
use Pet\Domain\Work\Repository\RoleRepository;
use PHPUnit\Framework\TestCase;

class QuoteBlockCostSnapshotEnricherTest extends TestCase
{
    private CatalogItemRepository $catalogItemRepository;
    private RoleRepository $roleRepository;
    private QuoteBlockCostSnapshotEnricher $enricher;

    protected function setUp(): void
    {
        $this->catalogItemRepository = $this->createMock(CatalogItemRepository::class);
        $this->roleRepository = $this->createMock(RoleRepository::class);
        $this->enricher = new QuoteBlockCostSnapshotEnricher(
            $this->catalogItemRepository,
            $this->roleRepository
        );
    }

    public function testEnrichesSimpleServiceCostFromCatalogItem(): void
    {
        $catalogItem = new CatalogItem('Managed Service', 120.0, 42.0, 'service', null, null, null, [], 5);
        $this->catalogItemRepository
            ->method('findById')
            ->with(5)
            ->willReturn($catalogItem);

        $payload = [
            'description' => 'Service line',
            'quantity' => 3,
            'catalogItemId' => 5,
        ];

        $result = $this->enricher->enrichPayload(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $payload);

        $this->assertSame(42.0, $result['unitCost']);
        $this->assertSame(126.0, $result['totalCost']);
    }

    public function testEnrichesSimpleServiceCostFromRoleBaseRate(): void
    {
        $role = new Role('Engineer', 'L2', 'Role description', 'Success criteria', 7, 1, 'draft', [], null, null, 90.0);
        $this->roleRepository
            ->method('findById')
            ->with(7)
            ->willReturn($role);

        $payload = [
            'description' => 'Service line',
            'quantity' => 2,
            'roleId' => 7,
        ];

        $result = $this->enricher->enrichPayload(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $payload);

        $this->assertSame(90.0, $result['unitCost']);
        $this->assertSame(180.0, $result['totalCost']);
    }

    public function testDoesNotInventCostWhenNoAuthoritativeSourceExists(): void
    {
        $payload = [
            'description' => 'No references',
            'quantity' => 2,
        ];

        $result = $this->enricher->enrichPayload(QuoteBlock::TYPE_ONCE_OFF_SIMPLE_SERVICE, $payload);

        $this->assertArrayNotHasKey('unitCost', $result);
        $this->assertArrayNotHasKey('totalCost', $result);
    }

    public function testEnrichesRecurringTotalCostFromPersistedCadenceData(): void
    {
        $payload = [
            'internalCostPerPeriod' => 120.0,
            'cadence' => 'quarterly',
            'termMonths' => 12,
        ];

        $result = $this->enricher->enrichPayload(QuoteBlock::TYPE_REPEAT_SERVICE, $payload);

        $this->assertSame(480.0, $result['totalCost']);
    }

    public function testEnrichesProjectUnitPhaseAndProjectCostsWhenAllUnitCostsAreResolvable(): void
    {
        $catalogItem = new CatalogItem('Service Item', 150.0, 40.0, 'service', null, null, null, [], 11);
        $role = new Role('Analyst', 'L1', 'Role description', 'Success criteria', 22, 1, 'draft', [], null, null, 60.0);

        $this->catalogItemRepository
            ->method('findById')
            ->willReturnCallback(static function (int $id) use ($catalogItem): ?CatalogItem {
                return $id === 11 ? $catalogItem : null;
            });
        $this->roleRepository
            ->method('findById')
            ->willReturnCallback(static function (int $id) use ($role): ?Role {
                return $id === 22 ? $role : null;
            });

        $payload = [
            'phases' => [
                [
                    'name' => 'Phase A',
                    'units' => [
                        ['description' => 'Catalog-backed', 'quantity' => 2, 'catalogItemId' => 11],
                        ['description' => 'Role-backed', 'quantity' => 1, 'roleId' => 22],
                    ],
                ],
            ],
        ];

        $result = $this->enricher->enrichPayload(QuoteBlock::TYPE_ONCE_OFF_PROJECT, $payload);

        $this->assertSame(40.0, $result['phases'][0]['units'][0]['unitCost']);
        $this->assertSame(80.0, $result['phases'][0]['units'][0]['totalCost']);
        $this->assertSame(60.0, $result['phases'][0]['units'][1]['unitCost']);
        $this->assertSame(60.0, $result['phases'][0]['units'][1]['totalCost']);
        $this->assertSame(140.0, $result['phases'][0]['phaseTotalCost']);
        $this->assertSame(140.0, $result['totalCost']);
    }
}

