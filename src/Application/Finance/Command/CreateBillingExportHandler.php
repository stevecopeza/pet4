<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Finance\Entity\BillingExport;
use Pet\Domain\Finance\Repository\BillingExportRepository;
use Pet\Domain\Event\Repository\EventStreamRepository;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;
use Pet\Domain\Finance\Event\BillingExportCreated;

final class CreateBillingExportHandler
{
    private TransactionManager $transactionManager;
    public function __construct(TransactionManager $transactionManager, 
        private BillingExportRepository $repository,
        private EventStreamRepository $events,
        private SqlTransaction $tx
    )
    {
        $this->transactionManager = $transactionManager;
    }

    public function handle(CreateBillingExportCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command) {
        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16));
        $export = BillingExport::draft(
            $uuid,
            $command->customerId(),
            $command->periodStart(),
            $command->periodEnd(),
            $command->createdByEmployeeId()
        );
        $this->tx->begin();
        try {
            $this->repository->save($export);
            $version = $this->events->nextVersion('billing_export', $export->id());
            $payload = json_encode([
                'uuid' => $uuid,
                'export_id' => $export->id(),
                'customer_id' => $command->customerId(),
                'period_start' => $command->periodStart()->format('Y-m-d'),
                'period_end' => $command->periodEnd()->format('Y-m-d'),
                'created_by_employee_id' => $command->createdByEmployeeId(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->events->append('billing_export', $export->id(), $version, 'BillingExportCreated', $payload);
            $this->tx->commit();
            return $export->id();
        } catch (\Throwable $e) {
            $this->tx->rollback();
            throw $e;
        }
    
        });
    }
}
