<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

final class SqlQbInvoiceRepository
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function recordInvoiceSnapshot(int $customerId, array $payload): void
    {
        $table = $this->wpdb->prefix . 'pet_qb_invoices';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $qbInvoiceId = isset($payload['qb_invoice_id']) ? (string)$payload['qb_invoice_id'] : $this->generateId();
        $docNumber = isset($payload['doc_number']) ? (string)$payload['doc_number'] : null;
        $currency = isset($payload['currency']) ? (string)$payload['currency'] : 'ZAR';
        $total = isset($payload['total_amount']) ? (float)$payload['total_amount'] : 0.0;
        $rawJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sql = "
            INSERT INTO $table (customer_id, qb_invoice_id, doc_number, status, issue_date, due_date, currency, total, balance, raw_json, last_synced_at)
            VALUES (%d, %s, %s, %s, %s, %s, %s, %f, %f, %s, %s)
            ON DUPLICATE KEY UPDATE doc_number = VALUES(doc_number), status = VALUES(status), currency = VALUES(currency), total = VALUES(total), balance = VALUES(balance), raw_json = VALUES(raw_json), last_synced_at = VALUES(last_synced_at)
        ";
        $prepared = $this->wpdb->prepare($sql, [
            $customerId,
            $qbInvoiceId,
            $docNumber,
            'Open',
            (new \DateTimeImmutable())->format('Y-m-d'),
            null,
            $currency,
            round($total, 2),
            round($total, 2),
            $rawJson,
            $now,
        ]);
        $this->wpdb->query($prepared);
    }

    private function generateId(): string
    {
        $hex = function ($len) {
            $str = '';
            for ($i = 0; $i < $len; $i++) {
                $str .= dechex(random_int(0, 15));
            }
            return $str;
        };
        return 'QB-' . $hex(8) . '-' . $hex(4) . '-' . $hex(4);
    }
}
