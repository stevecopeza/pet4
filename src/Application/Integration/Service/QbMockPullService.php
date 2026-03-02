<?php

declare(strict_types=1);

namespace Pet\Application\Integration\Service;

use Pet\Infrastructure\Persistence\Repository\SqlQbInvoiceRepository;
use Pet\Infrastructure\Persistence\Repository\SqlQbPaymentRepository;

final class QbMockPullService
{
    public function __construct(
        private SqlQbInvoiceRepository $invoices,
        private SqlQbPaymentRepository $payments
    ) {}

    public function pullInvoices(int $customerId): void
    {
        $payload = [
            'qb_invoice_id' => 'QB-PULL-' . $customerId,
            'doc_number' => 'PULL-' . $customerId,
            'currency' => 'ZAR',
            'total_amount' => 1234.56,
            'lines' => [
                ['description' => 'Pulled line', 'quantity' => 1, 'unit_price' => 1234.56, 'amount' => 1234.56, 'qb_item_ref' => 'GEN'],
            ],
        ];
        $this->invoices->recordInvoiceSnapshot($customerId, $payload);
    }

    public function pullPayments(int $customerId): void
    {
        $this->payments->upsertPayment(
            $customerId,
            'QB-PMT-' . $customerId,
            (new \DateTimeImmutable())->format('Y-m-d'),
            500.00,
            'ZAR',
            [['qb_invoice_id' => 'QB-PULL-' . $customerId, 'amount' => 500.00]]
        );
    }
}
