<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

final class SqlQbPaymentRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function upsertPayment(int $customerId, string $qbPaymentId, string $receivedDate, float $amount, string $currency, array $appliedInvoices): void
    {
        $table = $this->wpdb->prefix . 'pet_qb_payments';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $appliedJson = json_encode($appliedInvoices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $rawJson = json_encode([
            'qb_payment_id' => $qbPaymentId,
            'received_date' => $receivedDate,
            'amount' => round($amount, 2),
            'currency' => $currency,
            'applied_invoices' => $appliedInvoices,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sql = "
            INSERT INTO $table (customer_id, qb_payment_id, received_date, amount, currency, applied_invoices_json, raw_json, last_synced_at)
            VALUES (%d, %s, %s, %f, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount), currency = VALUES(currency), applied_invoices_json = VALUES(applied_invoices_json), raw_json = VALUES(raw_json), last_synced_at = VALUES(last_synced_at)
        ";
        $prepared = $this->wpdb->prepare($sql, [$customerId, $qbPaymentId, $receivedDate, $amount, $currency, $appliedJson, $rawJson, $now]);
        $this->wpdb->query($prepared);
    }
}
