<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Infrastructure\Persistence\Repository\SqlQbInvoiceRepository;
use Pet\Infrastructure\Persistence\Repository\SqlQbPaymentRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class QuickBooksController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'finance';

    public function __construct() {}

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/qb/invoices', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listInvoices'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/qb/payments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listPayments'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function listInvoices(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_qb_invoices';
        $rows = $wpdb->get_results("SELECT id, customer_id, qb_invoice_id, doc_number, status, issue_date, due_date, currency, total, balance, last_synced_at FROM $table ORDER BY id DESC LIMIT 100", ARRAY_A);
        return new WP_REST_Response($rows ?: [], 200);
    }

    public function listPayments(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_qb_payments';
        $rows = $wpdb->get_results("SELECT id, customer_id, qb_payment_id, received_date, amount, currency, last_synced_at FROM $table ORDER BY id DESC LIMIT 100", ARRAY_A);
        return new WP_REST_Response($rows ?: [], 200);
    }
}
