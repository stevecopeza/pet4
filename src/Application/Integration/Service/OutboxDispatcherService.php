<?php

declare(strict_types=1);

namespace Pet\Application\Integration\Service;

use Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository;
use Pet\Domain\Finance\Repository\BillingExportRepository;
use Pet\Infrastructure\Persistence\Repository\SqlQbInvoiceRepository;
use Pet\Application\System\Service\TransactionManager;

final class OutboxDispatcherService
{
    public function __construct(
        private SqlOutboxRepository $outbox,
        private BillingExportRepository $exports,
        private SqlQbInvoiceRepository $qbInvoices,
        private \Pet\Infrastructure\Persistence\Repository\SqlExternalMappingRepository $mappings,
        private \Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository $events,
        private TransactionManager $transactionManager
    ) {
    }

    public function dispatchQuickBooks(): void
    {
        $due = $this->transactionManager->transactional(function () {
            $items = $this->outbox->findDue('quickbooks', 25);
            if (!empty($items)) {
                $ids = array_column($items, 'id');
                // Claim for 5 minutes to allow processing without concurrency issues
                $this->outbox->claim($ids, new \DateTimeImmutable('+5 minutes'));
            }
            return $items;
        });

        foreach ($due as $row) {
            $outboxId = (int)$row['id'];
            $eventId = (int)$row['event_id'];
            $currentExportId = 0;
            try {
                $event = $this->events->findById($eventId);
                if (!$event) {
                    throw new \RuntimeException('Event not found for outbox row ' . $outboxId);
                }
                $payload = json_decode($event->payloadJson, true) ?: [];
                $exportId = isset($payload['export_id']) ? (int)$payload['export_id'] : 0;
                $currentExportId = $exportId;
                $export = $this->exports->findById($exportId);
                if (!$export) {
                    throw new \RuntimeException('Export not found for outbox row ' . $outboxId);
                }
                $items = $this->exports->findItems($exportId);
                $envelope = $this->buildEnvelope($exportId, $items);
                $qbInvoiceId = $this->deterministicInvoiceId($exportId);
                $docNumber = $this->deterministicDocNumber($exportId);
                $envelope['qb_invoice_id'] = $qbInvoiceId;
                $envelope['doc_number'] = $docNumber;
                $this->simulateQuickBooksSend($envelope);

                // Wrap all post-dispatch bookkeeping in a single transaction.
                // The external call (above) is intentionally outside this transaction —
                // it cannot be rolled back. What we protect here is truth drift: if the
                // process crashes after the external call but before marking the row
                // sent, the next cron run would re-dispatch. The transaction ensures
                // all internal "I did this" records are written atomically.
                $exportCapture = $export;
                $this->transactionManager->transactional(
                    function () use ($exportCapture, $envelope, $qbInvoiceId, $exportId, $outboxId) {
                        $this->qbInvoices->recordInvoiceSnapshot($exportCapture->customerId(), $envelope);
                        $this->mappings->upsert('quickbooks', 'invoice', $exportId, $qbInvoiceId, null);
                        $this->outbox->markSent($outboxId);
                        $exportCapture->markSent();
                        $this->exports->save($exportCapture);
                    }
                );
            } catch (\Throwable $e) {
                $attempt = ((int)$row['attempt_count']) + 1;
                if ($attempt >= 6) {
                    $this->outbox->markDead($outboxId, $e->getMessage());
                    if ($currentExportId > 0) {
                        $export = $this->exports->findById($currentExportId);
                        if ($export) {
                            $export->markFailed();
                            $this->exports->save($export);
                        }
                    }
                    continue;
                }
                $backoff = $this->backoffAt($attempt);
                $this->outbox->markFailed($outboxId, $attempt, $backoff, $e->getMessage());
            }
        }
    }

    private function buildEnvelope(int $exportId, array $items): array
    {
        $total = 0.0;
        $lines = [];
        foreach ($items as $i) {
            $lines[] = [
                'source_type' => $i->sourceType(),
                'source_id' => $i->sourceId(),
                'description' => $i->description(),
                'quantity' => round($i->quantity(), 2),
                'unit_price' => round($i->unitPrice(), 2),
                'amount' => round($i->amount(), 2),
                'qb_item_ref' => $i->qbItemRef() ?? 'GEN-SERVICE',
            ];
            $total += $i->amount();
        }
        $total = round($total, 2);
        return [
            'export_id' => $exportId,
            'schema_version' => 1,
            'lines' => $lines,
            'total_amount' => $total,
        ];
    }

    private function simulateQuickBooksSend(array $payload): void
    {
        if (empty($payload['lines'])) {
            throw new \RuntimeException('No line items to send');
        }
        // Simulate success
    }

    private function deterministicInvoiceId(int $exportId): string
    {
        return 'QB-INV-' . $exportId;
    }

    private function deterministicDocNumber(int $exportId): string
    {
        return 'INV-' . $exportId;
    }

    private function backoffAt(int $attempt): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        $map = [
            1 => '+1 minutes',
            2 => '+5 minutes',
            3 => '+30 minutes',
            4 => '+2 hours',
            5 => '+6 hours',
            6 => '+24 hours',
        ];
        $spec = $map[$attempt] ?? '+60 minutes';
        return $now->modify($spec);
    }
}
