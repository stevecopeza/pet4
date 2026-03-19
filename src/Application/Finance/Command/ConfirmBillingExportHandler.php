<?php

declare(strict_types=1);

namespace Pet\Application\Finance\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Finance\Repository\BillingExportRepository;
use Pet\Infrastructure\Persistence\Repository\SqlExternalMappingRepository;
use Pet\Infrastructure\Persistence\Transaction\SqlTransaction;

final class ConfirmBillingExportHandler
{
    private TransactionManager $transactionManager;

    public function __construct(
        TransactionManager $transactionManager,
        private BillingExportRepository $repository,
        private SqlExternalMappingRepository $mappings,
        private SqlTransaction $tx
    ) {
        $this->transactionManager = $transactionManager;
    }

    public function handle(ConfirmBillingExportCommand $command): string
    {
        return $this->transactionManager->transactional(function () use ($command) {
            $export = $this->repository->findById($command->exportId());
            if (!$export) {
                throw new \DomainException('Export not found');
            }

            if ($export->status() === 'confirmed') {
                return 'confirmed';
            }

            if ($export->status() !== 'sent') {
                throw new \DomainException('Only sent exports can be confirmed');
            }

            if (!$this->mappings->exists('quickbooks', 'invoice', $export->id())) {
                throw new \DomainException('Export cannot be confirmed without reconciliation evidence');
            }

            $export->confirm();

            $this->tx->begin();
            try {
                $this->repository->save($export);
                $this->tx->commit();
                return $export->status();
            } catch (\Throwable $e) {
                $this->tx->rollback();
                throw $e;
            }
        });
    }
}
