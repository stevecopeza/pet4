<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\QuoteComponent;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\Phase;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;
use Pet\Domain\Commercial\Entity\Component\RecurringServiceComponent;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteMilestone;
use Pet\Domain\Commercial\Entity\Component\QuoteTask;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;

use Pet\Domain\Commercial\Repository\CostAdjustmentRepository;

class SqlQuoteRepository implements QuoteRepository
{
    private $wpdb;
    private $quotesTable;
    private $componentsTable;
    private $milestonesTable;
    private $tasksTable;
    private $recurringTable;
    private $catalogTable;
    private $paymentScheduleTable;
    private $onceOffPhasesTable;
    private $onceOffUnitsTable;
    private $costAdjustmentRepository;

    public function __construct(\wpdb $wpdb, ?CostAdjustmentRepository $costAdjustmentRepository = null)
    {
        $this->wpdb = $wpdb;
        $this->quotesTable = $wpdb->prefix . 'pet_quotes';
        $this->componentsTable = $wpdb->prefix . 'pet_quote_components';
        $this->milestonesTable = $wpdb->prefix . 'pet_quote_milestones';
        $this->tasksTable = $wpdb->prefix . 'pet_quote_tasks';
        $this->recurringTable = $wpdb->prefix . 'pet_quote_recurring_services';
        $this->catalogTable = $wpdb->prefix . 'pet_quote_catalog_items';
        $this->paymentScheduleTable = $wpdb->prefix . 'pet_quote_payment_schedule';
        $this->onceOffPhasesTable = $wpdb->prefix . 'pet_quote_onceoff_phases';
        $this->onceOffUnitsTable = $wpdb->prefix . 'pet_quote_onceoff_units';
        $this->costAdjustmentRepository = $costAdjustmentRepository ?? new SqlCostAdjustmentRepository($wpdb);
    }

    public function save(Quote $quote): void
    {
        $data = [
            'customer_id'              => $quote->customerId(),
            'lead_id'                  => $quote->leadId(),
            'opportunity_id'           => $quote->opportunityId(),
            'contract_id'              => $quote->contractId(),
            'title'                    => $quote->title(),
            'description'              => $quote->description(),
            'state'                    => $quote->state()->toString(),
            'version'                  => $quote->version(),
            'total_value'              => $quote->totalValue(),
            'total_internal_cost'      => $quote->totalInternalCost(),
            'currency'                 => $quote->currency(),
            'accepted_at'              => $quote->acceptedAt() ? $quote->acceptedAt()->format('Y-m-d H:i:s') : null,
            'malleable_data'           => json_encode($quote->malleableData()),
            'created_at'               => $this->formatDate($quote->createdAt()),
            'updated_at'               => $this->formatDate($quote->updatedAt()),
            'archived_at'              => $this->formatDate($quote->archivedAt()),
            // Approval fields
            'rejection_note'           => $quote->rejectionNote(),
            'submitted_for_approval_at'=> $this->formatDate($quote->submittedForApprovalAt()),
            'approved_at'              => $this->formatDate($quote->approvedAt()),
            'approved_by_user_id'      => $quote->approvedByUserId(),
        ];

        $format = ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'];

        if ($quote->id()) {
            $this->wpdb->update(
                $this->quotesTable,
                $data,
                ['id' => $quote->id()],
                $format,
                ['%d']
            );
            $quoteId = $quote->id();
        } else {
            $this->wpdb->insert(
                $this->quotesTable,
                $data,
                $format
            );
            $quoteId = $this->wpdb->insert_id;
            
            // Use reflection to set the ID on the private property
            $reflection = new \ReflectionClass($quote);
            $property = $reflection->getProperty('id');
            $property->setAccessible(true);
            $property->setValue($quote, (int)$quoteId);
        }

        if ($quoteId) {
            if ($this->shouldPersistComponents((int)$quoteId, $quote->components())) {
                $this->saveComponents((int)$quoteId, $quote->components());
            }
            $this->savePaymentSchedule((int)$quoteId, $quote->paymentSchedule());
            
            // Save adjustments
            foreach ($quote->costAdjustments() as $adjustment) {
                // Ensure quoteId is set on adjustment if it's new
                // Since adjustment is immutable but constructed with quoteId, 
                // we assume it's correct or we might need to recreate it?
                // Actually CostAdjustment constructor takes quoteId.
                // If the quote is new, the adjustment might have been created with a placeholder ID?
                // No, usually adjustments are added to an existing quote.
                // But if created together, we might have an issue.
                // However, in this system, adjustments are likely added via a separate command 
                // or after quote creation.
                // If we support adding adjustments during quote creation, we need to handle this.
                // For now, let's assume they have the ID or we update it.
                // But CostAdjustment is immutable.
                // If quoteId was 0/null, we can't change it easily without recreating.
                // Let's assume for now adjustments are added to an existing quote.
                
                // However, we should just save them.
                $this->costAdjustmentRepository->save($adjustment);
            }
        }
    }

    public function findById(int $id, bool $lock = false): ?Quote
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->quotesTable} WHERE id = %d LIMIT 1",
            $id
        );

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCustomerId(int $customerId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->quotesTable} WHERE customer_id = %d AND archived_at IS NULL ORDER BY created_at DESC",
            $customerId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->quotesTable} WHERE archived_at IS NULL ORDER BY created_at DESC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findPendingApproval(): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->quotesTable}
             WHERE state = %s AND archived_at IS NULL
             ORDER BY submitted_for_approval_at ASC",
            QuoteState::PENDING_APPROVAL
        );
        $results = $this->wpdb->get_results($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function delete(int $id): void
    {
        $this->wpdb->update(
            $this->quotesTable,
            ['archived_at' => current_time('mysql')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    public function countPending(): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->quotesTable} WHERE state = %s",
            QuoteState::DRAFT
        );
        
        return (int) $this->wpdb->get_var($sql);
    }

    public function sumRevenue(\DateTimeImmutable $start, \DateTimeImmutable $end): float
    {
        // Sum total_value for Accepted quotes updated within the range
        $sql = $this->wpdb->prepare(
            "SELECT SUM(total_value) 
             FROM {$this->quotesTable}
             WHERE state = %s
             AND updated_at >= %s
             AND updated_at <= %s",
            QuoteState::ACCEPTED,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        return (float) $this->wpdb->get_var($sql);
    }

    public function findByLeadId(int $leadId): ?Quote
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->quotesTable} WHERE lead_id = %d AND archived_at IS NULL ORDER BY id DESC LIMIT 1",
            $leadId
        );
        $row = $this->wpdb->get_row($sql);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Count quotes grouped by state (for sales KPIs).
     * @return array<string, int>
     */
    public function countByState(): array
    {
        $sql = "SELECT state, COUNT(*) as cnt FROM {$this->quotesTable} WHERE archived_at IS NULL GROUP BY state";
        $rows = $this->wpdb->get_results($sql);
        $map = [];
        foreach ($rows as $row) {
            $map[$row->state] = (int) $row->cnt;
        }
        return $map;
    }

    /**
     * Sum total_value for quotes in given states.
     */
    public function sumValueByStates(array $states): float
    {
        if (empty($states)) return 0.0;
        $placeholders = implode(',', array_fill(0, count($states), '%s'));
        $sql = $this->wpdb->prepare(
            "SELECT COALESCE(SUM(total_value), 0) FROM {$this->quotesTable} WHERE state IN ($placeholders) AND archived_at IS NULL",
            ...$states
        );
        return (float) $this->wpdb->get_var($sql);
    }

    /**
     * Average total_value for accepted quotes.
     */
    public function avgAcceptedValue(): float
    {
        $sql = $this->wpdb->prepare(
            "SELECT COALESCE(AVG(total_value), 0) FROM {$this->quotesTable} WHERE state = %s AND archived_at IS NULL",
            QuoteState::ACCEPTED
        );
        return (float) $this->wpdb->get_var($sql);
    }

    private function saveComponents(int $quoteId, array $components): void
    {
        // For simplicity in this iteration, we'll wipe and recreate components
        // In a production system with partial updates, we'd want smart syncing
        $this->deleteComponents($quoteId);

        foreach ($components as $component) {
            $this->wpdb->insert(
                $this->componentsTable,
                [
                    'quote_id' => $quoteId,
                    'type' => $component->type(),
                    'section' => $component->section(),
                    'description' => $component->description(),
                ],
                ['%d', '%s', '%s', '%s']
            );
            $componentId = $this->wpdb->insert_id;

            if ($component instanceof ImplementationComponent) {
                $this->saveMilestones($componentId, $component->milestones());
            } elseif ($component instanceof RecurringServiceComponent) {
                $this->saveRecurringService($componentId, $component);
            } elseif ($component instanceof CatalogComponent) {
                $this->saveCatalogItems($componentId, $component->items());
            } elseif ($component instanceof OnceOffServiceComponent) {
                $this->saveOnceOffService($componentId, $component);
            }
        }
    }

    private function shouldPersistComponents(int $quoteId, array $components): bool
    {
        $existingIds = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM {$this->componentsTable} WHERE quote_id = %d ORDER BY id ASC",
            $quoteId
        ));
        $existingIds = array_map('intval', is_array($existingIds) ? $existingIds : []);

        if (empty($components)) {
            return !empty($existingIds);
        }

        $componentIds = [];
        foreach ($components as $component) {
            if (!$component instanceof QuoteComponent) {
                return true;
            }

            $componentId = (int)$component->id();
            if ($componentId <= 0) {
                return true;
            }

            if (!$this->hasPersistedComponentChildren($component)) {
                return true;
            }

            $componentIds[] = $componentId;
        }

        sort($existingIds);
        sort($componentIds);

        if (count($existingIds) !== count($componentIds)) {
            return true;
        }

        return $existingIds !== $componentIds;
    }

    private function hasPersistedComponentChildren(QuoteComponent $component): bool
    {
        if ($component instanceof ImplementationComponent) {
            foreach ($component->milestones() as $milestone) {
                if ((int)$milestone->id() <= 0) {
                    return false;
                }

                foreach ($milestone->tasks() as $task) {
                    if ((int)$task->id() <= 0) {
                        return false;
                    }
                }
            }

            return true;
        }

        if ($component instanceof CatalogComponent) {
            foreach ($component->items() as $item) {
                if ((int)$item->id() <= 0) {
                    return false;
                }
            }

            return true;
        }

        if ($component instanceof OnceOffServiceComponent) {
            if ($component->topology() === OnceOffServiceComponent::TOPOLOGY_SIMPLE) {
                foreach ($component->units() as $unit) {
                    if ((int)$unit->id() <= 0) {
                        return false;
                    }
                }

                return true;
            }

            foreach ($component->phases() as $phase) {
                if ((int)$phase->id() <= 0) {
                    return false;
                }

                foreach ($phase->units() as $unit) {
                    if ((int)$unit->id() <= 0) {
                        return false;
                    }
                }
            }

            return true;
        }

        return true;
    }

    private function deleteComponents(int $quoteId): void
    {
        // Get component IDs
        $sql = $this->wpdb->prepare("SELECT id, type FROM {$this->componentsTable} WHERE quote_id = %d", $quoteId);
        $rows = $this->wpdb->get_results($sql);

        foreach ($rows as $row) {
            $id = (int) $row->id;
            // Delete child data first
            if ($row->type === 'implementation') {
                // Find milestones to delete tasks
                $mSql = $this->wpdb->prepare("SELECT id FROM {$this->milestonesTable} WHERE component_id = %d", $id);
                $mIds = $this->wpdb->get_col($mSql);
                if (!empty($mIds)) {
                    $mIdsStr = implode(',', array_map('intval', $mIds));
                    $this->wpdb->query("DELETE FROM {$this->tasksTable} WHERE milestone_id IN ($mIdsStr)");
                }
            }
            $this->wpdb->delete($this->milestonesTable, ['component_id' => $id], ['%d']);
            
            $this->wpdb->delete($this->recurringTable, ['component_id' => $id], ['%d']);
            $this->wpdb->delete($this->catalogTable, ['component_id' => $id], ['%d']);
            
            // Delete component
            $this->wpdb->delete($this->componentsTable, ['id' => $id], ['%d']);
        }
    }

    private function saveMilestones(int $componentId, array $milestones): void
    {
        foreach ($milestones as $milestone) {
            $this->wpdb->insert(
                $this->milestonesTable,
                [
                    'component_id' => $componentId,
                    'title' => $milestone->title(),
                    'description' => $milestone->description(),
                ],
                ['%d', '%s', '%s']
            );
            $milestoneId = $this->wpdb->insert_id;
            
            $this->saveTasks($milestoneId, $milestone->tasks());
        }
    }

    private function saveTasks(int $milestoneId, array $tasks): void
    {
        foreach ($tasks as $task) {
            $this->wpdb->insert(
                $this->tasksTable,
                [
                    'milestone_id' => $milestoneId,
                    'title' => $task->title(),
                    'description' => $task->description(),
                    'duration_hours' => $task->durationHours(),
                    'role_id' => $task->roleId(),
                    'base_internal_rate' => $task->baseInternalRate(),
                    'sell_rate' => $task->sellRate(),
                    'service_type_id' => $task->serviceTypeId(),
                    'rate_card_id' => $task->rateCardId(),
                ],
                ['%d', '%s', '%s', '%f', '%d', '%f', '%f', '%d', '%d']
            );
        }
    }

    private function saveRecurringService(int $componentId, RecurringServiceComponent $component): void
    {
        $this->wpdb->insert(
            $this->recurringTable,
            [
                'component_id' => $componentId,
                'service_name' => $component->serviceName(),
                'sla_snapshot' => json_encode($component->slaSnapshot()),
                'cadence' => $component->cadence(),
                'term_months' => $component->termMonths(),
                'renewal_model' => $component->renewalModel(),
                'sell_price_per_period' => $component->sellPricePerPeriod(),
                'internal_cost_per_period' => $component->internalCostPerPeriod(),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%f', '%f']
        );
    }

    private function saveCatalogItems(int $componentId, array $items): void
    {
        foreach ($items as $item) {
            $this->wpdb->insert(
                $this->catalogTable,
                [
                    'component_id' => $componentId,
                    'type' => $item->type(),
                    'description' => $item->description(),
                    'sku' => $item->sku(),
                    'role_id' => $item->roleId(),
                    'quantity' => $item->quantity(),
                    'unit_sell_price' => $item->unitSellPrice(),
                    'unit_internal_cost' => $item->unitInternalCost(),
                    'catalog_item_id' => $item->catalogItemId(),
                    'wbs_snapshot' => json_encode($item->wbsSnapshot()),
                ],
                ['%d', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%d', '%s']
            );
        }
    }

    private function savePaymentSchedule(int $quoteId, array $schedule): void
    {
        $this->wpdb->delete($this->paymentScheduleTable, ['quote_id' => $quoteId], ['%d']);

        foreach ($schedule as $milestone) {
            $this->wpdb->insert(
                $this->paymentScheduleTable,
                [
                    'quote_id' => $quoteId,
                    'title' => $milestone->title(),
                    'amount' => $milestone->amount(),
                    'due_date' => $milestone->dueDate() ? $milestone->dueDate()->format('Y-m-d H:i:s') : null,
                    'paid_flag' => $milestone->isPaid() ? 1 : 0,
                ],
                ['%d', '%s', '%f', '%s', '%d']
            );
        }
    }

    private function loadPaymentSchedule(int $quoteId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->paymentScheduleTable} WHERE quote_id = %d ORDER BY id ASC",
            $quoteId
        );
        $rows = $this->wpdb->get_results($sql);
        $schedule = [];

        foreach ($rows as $row) {
            $schedule[] = new PaymentMilestone(
                $row->title,
                (float)$row->amount,
                $row->due_date ? new \DateTimeImmutable($row->due_date) : null,
                (bool)$row->paid_flag,
                (int)$row->id
            );
        }

        return $schedule;
    }

    private function hydrate(object $row): Quote
    {
        $components = $this->loadComponents((int)$row->id);
        $costAdjustments = $this->costAdjustmentRepository->findByQuoteId((int)$row->id);
        $paymentSchedule = $this->loadPaymentSchedule((int)$row->id);

        return new Quote(
            (int)$row->customer_id,
            $row->title ?? '',
            $row->description ?? null,
            QuoteState::fromString($row->state),
            (int)$row->version,
            isset($row->total_value) ? (float)$row->total_value : 0.00,
            isset($row->total_internal_cost) ? (float)$row->total_internal_cost : 0.00,
            isset($row->currency) ? $row->currency : 'USD',
            !empty($row->accepted_at) ? new \DateTimeImmutable($row->accepted_at) : null,
            (int)$row->id,
            $row->created_at ? new \DateTimeImmutable($row->created_at) : null,
            $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null,
            $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null,
            $components,
            isset($row->malleable_data) ? json_decode($row->malleable_data, true) : [],
            $costAdjustments,
            $paymentSchedule,
            isset($row->lead_id) && $row->lead_id ? (int)$row->lead_id : null,
            $row->opportunity_id ?? null,
            isset($row->contract_id) && $row->contract_id ? (int)$row->contract_id : null,
            // Approval fields
            $row->rejection_note ?? null,
            !empty($row->submitted_for_approval_at) ? new \DateTimeImmutable($row->submitted_for_approval_at) : null,
            !empty($row->approved_at) ? new \DateTimeImmutable($row->approved_at) : null,
            isset($row->approved_by_user_id) && $row->approved_by_user_id ? (int)$row->approved_by_user_id : null
        );
    }

    private function loadComponents(int $quoteId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->componentsTable} WHERE quote_id = %d ORDER BY id ASC",
            $quoteId
        );
        $rows = $this->wpdb->get_results($sql);
        $components = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $type = $row->type;
            $description = $row->description;
            $section = $row->section ?? 'General';

            if ($type === 'implementation') {
                $milestones = $this->loadMilestones($id);
                $components[] = new ImplementationComponent($milestones, $description, $id, $section);
            } elseif ($type === 'recurring') {
                $serviceData = $this->loadRecurringServiceData($id);
                if ($serviceData) {
                    $components[] = new RecurringServiceComponent(
                        $serviceData->service_name,
                        json_decode($serviceData->sla_snapshot, true) ?? [],
                        $serviceData->cadence,
                        (int)$serviceData->term_months,
                        $serviceData->renewal_model,
                        (float)$serviceData->sell_price_per_period,
                        (float)$serviceData->internal_cost_per_period,
                        $description,
                        $id,
                        $section
                    );
                }
            } elseif ($type === 'catalog') {
                $items = $this->loadCatalogItems($id);
                $components[] = new CatalogComponent($items, $description, $id, $section);
            } elseif ($type === 'once_off_service') {
                $components[] = $this->loadOnceOffServiceComponent($id, $description, $section);
            }
        }

        return $components;
    }

    private function saveOnceOffService(int $componentId, OnceOffServiceComponent $component): void
    {
        if ($component->topology() === OnceOffServiceComponent::TOPOLOGY_SIMPLE) {
            foreach ($component->units() as $unit) {
                $this->saveOnceOffUnit($componentId, null, $unit);
            }
        } else {
            foreach ($component->phases() as $phase) {
                $this->wpdb->insert(
                    $this->onceOffPhasesTable,
                    [
                        'component_id' => $componentId,
                        'name' => $phase->name(),
                        'description' => $phase->description(),
                    ],
                    ['%d', '%s', '%s']
                );
                $phaseId = $this->wpdb->insert_id;

                foreach ($phase->units() as $unit) {
                    $this->saveOnceOffUnit($componentId, $phaseId, $unit);
                }
            }
        }
    }

    private function saveOnceOffUnit(int $componentId, ?int $phaseId, SimpleUnit $unit): void
    {
        $this->wpdb->insert(
            $this->onceOffUnitsTable,
            [
                'component_id' => $componentId,
                'phase_id' => $phaseId,
                'title' => $unit->title(),
                'description' => $unit->description(),
                'quantity' => $unit->quantity(),
                'unit_sell_price' => $unit->unitSellPrice(),
                'unit_internal_cost' => $unit->unitInternalCost(),
            ],
            ['%d', '%d', '%s', '%s', '%f', '%f', '%f']
        );
    }

    private function loadOnceOffServiceComponent(int $componentId, ?string $description, string $section): OnceOffServiceComponent
    {
        $phases = $this->loadOnceOffPhases($componentId);
        $topology = empty($phases)
            ? OnceOffServiceComponent::TOPOLOGY_SIMPLE
            : OnceOffServiceComponent::TOPOLOGY_COMPLEX;

        if ($topology === OnceOffServiceComponent::TOPOLOGY_SIMPLE) {
            $units = $this->loadOnceOffUnitsWithoutPhase($componentId);

            return new OnceOffServiceComponent(
                $topology,
                [],
                $units,
                $description,
                $componentId,
                $section
            );
        }

        return new OnceOffServiceComponent(
            $topology,
            $phases,
            [],
            $description,
            $componentId,
            $section
        );
    }

    private function loadOnceOffPhases(int $componentId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->onceOffPhasesTable} WHERE component_id = %d ORDER BY id ASC",
            $componentId
        );
        $rows = $this->wpdb->get_results($sql);
        $phases = [];

        foreach ($rows as $row) {
            $units = $this->loadOnceOffUnitsForPhase((int)$row->id);
            $phases[] = new Phase(
                $row->name,
                $units,
                $row->description,
                (int)$row->id
            );
        }

        return $phases;
    }

    private function loadOnceOffUnitsForPhase(int $phaseId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->onceOffUnitsTable} WHERE phase_id = %d ORDER BY id ASC",
            $phaseId
        );
        $rows = $this->wpdb->get_results($sql);
        $units = [];

        foreach ($rows as $row) {
            $units[] = new SimpleUnit(
                $row->title,
                (float)$row->quantity,
                (float)$row->unit_sell_price,
                (float)$row->unit_internal_cost,
                $row->description,
                (int)$row->id
            );
        }

        return $units;
    }

    private function loadOnceOffUnitsWithoutPhase(int $componentId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->onceOffUnitsTable} WHERE component_id = %d AND phase_id IS NULL ORDER BY id ASC",
            $componentId
        );
        $rows = $this->wpdb->get_results($sql);
        $units = [];

        foreach ($rows as $row) {
            $units[] = new SimpleUnit(
                $row->title,
                (float)$row->quantity,
                (float)$row->unit_sell_price,
                (float)$row->unit_internal_cost,
                $row->description,
                (int)$row->id
            );
        }

        return $units;
    }

    private function loadMilestones(int $componentId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->milestonesTable} WHERE component_id = %d ORDER BY id ASC",
            $componentId
        );
        $rows = $this->wpdb->get_results($sql);
        $milestones = [];

        foreach ($rows as $row) {
            $tasks = $this->loadTasks((int)$row->id);
            $milestones[] = new QuoteMilestone(
                $row->title,
                $tasks,
                $row->description,
                (int)$row->id
            );
        }

        return $milestones;
    }

    private function loadTasks(int $milestoneId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tasksTable} WHERE milestone_id = %d ORDER BY id ASC",
            $milestoneId
        );
        $rows = $this->wpdb->get_results($sql);
        $tasks = [];

        foreach ($rows as $row) {
            $tasks[] = new QuoteTask(
                $row->title,
                (float)$row->duration_hours,
                (int)$row->role_id,
                (float)$row->base_internal_rate,
                (float)$row->sell_rate,
                $row->description,
                (int)$row->id,
                isset($row->service_type_id) && $row->service_type_id ? (int)$row->service_type_id : null,
                isset($row->rate_card_id) && $row->rate_card_id ? (int)$row->rate_card_id : null
            );
        }

        return $tasks;
    }

    private function loadRecurringServiceData(int $componentId): ?object
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->recurringTable} WHERE component_id = %d LIMIT 1",
            $componentId
        );
        return $this->wpdb->get_row($sql);
    }

    private function loadCatalogItems(int $componentId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->catalogTable} WHERE component_id = %d ORDER BY id ASC",
            $componentId
        );
        $rows = $this->wpdb->get_results($sql);
        $items = [];

        foreach ($rows as $row) {
            $items[] = new QuoteCatalogItem(
                $row->description,
                (float)$row->quantity,
                (float)$row->unit_sell_price,
                (float)$row->unit_internal_cost,
                (int)$row->id,
                isset($row->catalog_item_id) ? (int)$row->catalog_item_id : null,
                isset($row->wbs_snapshot) ? json_decode($row->wbs_snapshot, true) : [],
                isset($row->type) ? $row->type : 'service',
                isset($row->sku) ? $row->sku : null,
                isset($row->role_id) ? (int)$row->role_id : null
            );
        }

        return $items;
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }

    private function findLinesByQuoteId(int $quoteId): array
    {
        // Deprecated, but keeping method signature if needed or removing it.
        // It's private, so safe to remove.
        return [];
    }
}
