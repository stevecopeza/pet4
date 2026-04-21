<?php

declare(strict_types=1);

namespace Pet\Application\Advisory\Command;

use DateTimeImmutable;
use Pet\Application\Advisory\Service\CustomerAdvisorySnapshotQuery;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Domain\Advisory\Entity\AdvisoryReport;
use Pet\Domain\Advisory\Repository\AdvisoryReportRepository;

class GenerateAdvisoryReportHandler
{
    public const SCOPE_TYPE_CUSTOMER = 'customer';

    public function __construct(
        private AdvisoryReportRepository $reports,
        private CustomerAdvisorySnapshotQuery $snapshotQuery,
        private FeatureFlagService $featureFlags
    ) {
    }

    public function handle(GenerateAdvisoryReportCommand $command): AdvisoryReport
    {
        if (!$this->featureFlags->isAdvisoryReportsEnabled()) {
            throw new \DomainException('Advisory reports are disabled');
        }

        $customerId = $command->customerId();
        $reportType = $command->reportType();

        $version = $this->reports->findNextVersionNumber($reportType, self::SCOPE_TYPE_CUSTOMER, $customerId);
        $snapshot = $this->snapshotQuery->snapshotForCustomer($customerId);

        $title = "Advisory Report — Customer {$customerId}";
        $summary = $this->summarizeSnapshot($snapshot);

        $report = new AdvisoryReport(
            wp_generate_uuid4(),
            $reportType,
            self::SCOPE_TYPE_CUSTOMER,
            $customerId,
            $version,
            $title,
            $summary,
            'GENERATED',
            new DateTimeImmutable(),
            $command->generatedByUserId(),
            [
                'snapshot' => $snapshot,
            ],
            [
                'generator' => 'CustomerAdvisorySnapshotQuery',
                'generated_at_utc' => gmdate('c'),
            ]
        );

        $this->reports->save($report);

        return $report;
    }

    private function summarizeSnapshot(array $snapshot): string
    {
        $ticketsOverdue = (int)($snapshot['tickets']['overdue'] ?? 0);
        $signalsActive = (int)($snapshot['signals']['total_active'] ?? 0);
        $openDeliveryTickets = (int)($snapshot['delivery_tickets']['open'] ?? 0);

        return "Active signals: {$signalsActive}; Ticket overdue: {$ticketsOverdue}; Open delivery tickets: {$openDeliveryTickets}";
    }
}

