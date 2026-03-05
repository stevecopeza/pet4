<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Application\Commercial\Service\RateCardResolver;

use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\CatalogItemRepository;
use Pet\Domain\Work\Repository\RoleRepository;
use Pet\Domain\Sla\Repository\SlaRepository;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteMilestone;
use Pet\Domain\Commercial\Entity\Component\QuoteTask;
use Pet\Domain\Commercial\Entity\Component\RecurringServiceComponent;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;

class AddComponentHandler
{
    private TransactionManager $transactionManager;
    private QuoteRepository $quoteRepository;
    private CatalogItemRepository $catalogItemRepository;
    private SlaRepository $slaRepository;
    private RateCardResolver $rateCardResolver;
    private RoleRepository $roleRepository;

    public function __construct(
        TransactionManager $transactionManager, 
        QuoteRepository $quoteRepository,
        CatalogItemRepository $catalogItemRepository,
        SlaRepository $slaRepository,
        RateCardResolver $rateCardResolver,
        RoleRepository $roleRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->quoteRepository = $quoteRepository;
        $this->catalogItemRepository = $catalogItemRepository;
        $this->slaRepository = $slaRepository;
        $this->rateCardResolver = $rateCardResolver;
        $this->roleRepository = $roleRepository;
    }

    public function handle(AddComponentCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $quote = $this->quoteRepository->findById($command->quoteId());
        if (!$quote) {
            throw new \DomainException("Quote not found: {$command->quoteId()}");
        }

        $type = $command->type();
        $data = $command->data();
        $description = $data['description'] ?? '';
        $section = $data['section'] ?? 'General';

        if ($type === 'catalog') {
            $items = [];
            foreach ($data['items'] ?? [] as $itemData) {
                $catalogItemId = isset($itemData['catalog_item_id']) ? (int) $itemData['catalog_item_id'] : null;
                $wbsSnapshot = [];
                
                if ($catalogItemId) {
                    $catalogItem = $this->catalogItemRepository->findById($catalogItemId);
                    if ($catalogItem) {
                        $wbsSnapshot = $catalogItem->wbsTemplate();
                    }
                }
                
                // Override wbs_snapshot if provided in command data (e.g. from UI)
                if (isset($itemData['wbs_snapshot']) && is_array($itemData['wbs_snapshot'])) {
                    $wbsSnapshot = $itemData['wbs_snapshot'];
                }

                $type = $itemData['type'] ?? 'service';
                $sku = $itemData['sku'] ?? null;
                if ($catalogItemId && !$sku) {
                    $catalogItem = $this->catalogItemRepository->findById($catalogItemId);
                    if ($catalogItem) {
                        $sku = $catalogItem->sku();
                        $type = $catalogItem->type();
                    }
                }
                $items[] = new QuoteCatalogItem(
                    $itemData['description'],
                    (float) $itemData['quantity'],
                    (float) $itemData['unit_sell_price'],
                    (float) ($itemData['unit_internal_cost'] ?? 0.0),
                    null,
                    $catalogItemId,
                    $wbsSnapshot,
                    $type,
                    $sku,
                    $itemData['role_id'] ?? null
                );
            }
            $component = new CatalogComponent($items, $description, null, $section);

        } elseif ($type === 'implementation') {
            $milestones = [];
            foreach ($data['milestones'] ?? [] as $mData) {
                $tasks = [];
                foreach ($mData['tasks'] ?? [] as $tData) {
                    // New model: resolve rates via RateCardResolver when role_id + service_type_id provided
                    if (isset($tData['role_id']) && isset($tData['service_type_id'])) {
                        $roleId = (int) $tData['role_id'];
                        $serviceTypeId = (int) $tData['service_type_id'];
                        $effectiveDate = new \DateTimeImmutable();

                        $rateCard = $this->rateCardResolver->resolve(
                            $roleId,
                            $serviceTypeId,
                            $quote->contractId(),
                            $effectiveDate
                        );

                        $role = $this->roleRepository->findById($roleId);
                        if (!$role) {
                            throw new \DomainException("Role not found: {$roleId}");
                        }

                        $tasks[] = new QuoteTask(
                            $tData['description'],
                            (float) $tData['duration_hours'],
                            $roleId,
                            $role->baseInternalRate() ?? 0.0,
                            $rateCard->sellRate(),
                            null,
                            null,
                            $serviceTypeId,
                            $rateCard->id()
                        );
                    } else {
                        // Legacy path: hardcoded rates (backward compat for seed Q1-Q7)
                        $tasks[] = new QuoteTask(
                            $tData['description'],
                            (float) $tData['duration_hours'],
                            (int) $tData['complexity'],
                            (float) ($tData['internal_cost'] ?? 0.0),
                            (float) $tData['sell_rate']
                        );
                    }
                }
                $milestones[] = new QuoteMilestone($mData['description'], $tasks);
            }
            $component = new ImplementationComponent($milestones, $description, null, $section);

        } elseif ($type === 'recurring') {
            $slaSnapshot = $data['sla_snapshot'] ?? [];

            if (isset($data['sla_definition_id'])) {
                $sla = $this->slaRepository->findById((int) $data['sla_definition_id']);
                if ($sla) {
                    $snapshot = $sla->createSnapshot(null);
                    $slaSnapshot = [
                        'sla_original_id' => $snapshot->slaOriginalId(),
                        'sla_version_at_binding' => $snapshot->slaVersionAtBinding(),
                        'sla_name_at_binding' => $snapshot->slaNameAtBinding(),
                        'response_target_minutes' => $snapshot->responseTargetMinutes(),
                        'resolution_target_minutes' => $snapshot->resolutionTargetMinutes(),
                        'calendar_snapshot' => $snapshot->calendarSnapshot(),
                        'bound_at' => $snapshot->boundAt()->format('c'),
                        'uuid' => $snapshot->uuid(),
                    ];
                }
            }

            $component = new RecurringServiceComponent(
                $data['service_name'],
                $slaSnapshot,
                $data['cadence'],
                (int) $data['term_months'],
                $data['renewal_model'],
                (float) $data['sell_price_per_period'],
                (float) ($data['internal_cost_per_period'] ?? 0.0),
                $description,
                null,
                $section
            );

        } elseif ($type === 'once_off_service') {
            $topology = $data['topology'] ?? OnceOffServiceComponent::TOPOLOGY_SIMPLE;

            if ($topology === OnceOffServiceComponent::TOPOLOGY_SIMPLE) {
                $units = [];
                foreach ($data['units'] ?? [] as $unitData) {
                    $units[] = new SimpleUnit(
                        $unitData['title'],
                        (float) $unitData['quantity'],
                        (float) $unitData['unit_sell_price'],
                        (float) ($unitData['unit_internal_cost'] ?? 0.0),
                        $unitData['description'] ?? null
                    );
                }
                if (empty($units)) {
                    throw new \InvalidArgumentException('Once-off service component requires at least one unit.');
                }

                $component = new OnceOffServiceComponent(
                    $topology,
                    [],
                    $units,
                    $description,
                    null,
                    $section
                );
            } else {
                $phases = [];
                foreach ($data['phases'] ?? [] as $phaseData) {
                    $phaseUnits = [];
                    foreach ($phaseData['units'] ?? [] as $unitData) {
                        $phaseUnits[] = new SimpleUnit(
                            $unitData['title'],
                            (float) $unitData['quantity'],
                            (float) $unitData['unit_sell_price'],
                            (float) ($unitData['unit_internal_cost'] ?? 0.0),
                            $unitData['description'] ?? null
                        );
                    }
                    $phases[] = new \Pet\Domain\Commercial\Entity\Component\Phase(
                        $phaseData['name'],
                        $phaseUnits,
                        $phaseData['description'] ?? null
                    );
                }
                if (empty($phases)) {
                    throw new \InvalidArgumentException('Complex once-off service component requires at least one phase.');
                }

                $component = new OnceOffServiceComponent(
                    $topology,
                    $phases,
                    [],
                    $description,
                    null,
                    $section
                );
            }

        } else {
            throw new \InvalidArgumentException("Invalid component type: {$type}");
        }

        $quote->addComponent($component);
        $this->quoteRepository->save($quote);
    
        });
    }
}
