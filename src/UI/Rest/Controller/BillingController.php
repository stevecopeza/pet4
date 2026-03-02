<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Finance\Command\CreateBillingExportCommand;
use Pet\Application\Finance\Command\CreateBillingExportHandler;
use Pet\Application\Finance\Command\AddBillingExportItemCommand;
use Pet\Application\Finance\Command\AddBillingExportItemHandler;
use Pet\Application\Finance\Command\QueueBillingExportForQuickBooksCommand;
use Pet\Application\Finance\Command\QueueBillingExportForQuickBooksHandler;
use Pet\Domain\Finance\Repository\BillingExportRepository;
use Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class BillingController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'billing';

    public function __construct(
        private BillingExportRepository $repository,
        private CreateBillingExportHandler $createHandler,
        private AddBillingExportItemHandler $addItemHandler,
        private QueueBillingExportForQuickBooksHandler $queueHandler,
        private SqlOutboxRepository $outbox
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/exports', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listExports'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createExport'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/exports/(?P<id>\\d+)/items', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listItems'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/exports/(?P<id>\\d+)/queue', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'queueExport'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/exports/(?P<id>\\d+)/totals', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'totals'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/exports/(?P<id>\\d+)/dispatch-log', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'dispatchLog'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function listExports(WP_REST_Request $request): WP_REST_Response
    {
        $exports = $this->repository->findAll(50);
        $mapped = array_map(function ($e) {
            return [
                'id' => $e->id(),
                'uuid' => $e->uuid(),
                'customerId' => $e->customerId(),
                'periodStart' => $e->periodStart()->format('Y-m-d'),
                'periodEnd' => $e->periodEnd()->format('Y-m-d'),
                'status' => $e->status(),
                'createdByEmployeeId' => $e->createdByEmployeeId(),
                'createdAt' => $e->createdAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $e->updatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $exports);
        return new WP_REST_Response($mapped, 200);
    }

    public function createExport(WP_REST_Request $request): WP_REST_Response
    {
        $customerId = (int)$request->get_param('customerId');
        $periodStart = new \DateTimeImmutable((string)$request->get_param('periodStart'));
        $periodEnd = new \DateTimeImmutable((string)$request->get_param('periodEnd'));
        $createdByEmployeeId = (int)$request->get_param('createdByEmployeeId');

        $id = $this->createHandler->handle(
            new CreateBillingExportCommand($customerId, $periodStart, $periodEnd, $createdByEmployeeId)
        );
        return new WP_REST_Response(['id' => $id], 201);
    }

    public function addItem(WP_REST_Request $request): WP_REST_Response
    {
        $exportId = (int)$request->get_param('id');
        $sourceType = (string)$request->get_param('sourceType');
        $sourceId = (int)$request->get_param('sourceId');
        $quantity = (float)$request->get_param('quantity');
        $unitPrice = (float)$request->get_param('unitPrice');
        $description = (string)$request->get_param('description');
        $qbItemRef = $request->get_param('qbItemRef');

        $itemId = $this->addItemHandler->handle(
            new AddBillingExportItemCommand($exportId, $sourceType, $sourceId, $quantity, $unitPrice, $description, $qbItemRef ? (string)$qbItemRef : null)
        );
        return new WP_REST_Response(['id' => $itemId], 201);
    }

    public function queueExport(WP_REST_Request $request): WP_REST_Response
    {
        $exportId = (int)$request->get_param('id');
        $this->queueHandler->handle(new QueueBillingExportForQuickBooksCommand($exportId));
        return new WP_REST_Response(['status' => 'queued'], 200);
    }

    public function listItems(WP_REST_Request $request): WP_REST_Response
    {
        $exportId = (int)$request->get_param('id');
        $items = $this->repository->findItems($exportId);
        $mapped = array_map(function ($i) {
            return [
                'id' => $i->id(),
                'exportId' => $i->exportId(),
                'sourceType' => $i->sourceType(),
                'sourceId' => $i->sourceId(),
                'quantity' => $i->quantity(),
                'unitPrice' => $i->unitPrice(),
                'amount' => $i->amount(),
                'description' => $i->description(),
                'qbItemRef' => $i->qbItemRef(),
                'status' => $i->status(),
                'createdAt' => $i->createdAt()->format('Y-m-d H:i:s'),
            ];
        }, $items);
        return new WP_REST_Response($mapped, 200);
    }

    public function totals(WP_REST_Request $request): WP_REST_Response
    {
        $exportId = (int)$request->get_param('id');
        $total = $this->repository->sumItemsTotal($exportId);
        return new WP_REST_Response(['totalAmount' => $total], 200);
    }

    public function dispatchLog(WP_REST_Request $request): WP_REST_Response
    {
        $exportId = (int)$request->get_param('id');
        $rows = $this->outbox->findByEventIdAndDestination($exportId, 'quickbooks');
        return new WP_REST_Response(array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'status' => $r['status'],
                'attemptCount' => (int)$r['attempt_count'],
                'nextAttemptAt' => $r['next_attempt_at'],
                'lastError' => $r['last_error'],
                'createdAt' => $r['created_at'],
                'updatedAt' => $r['updated_at'],
            ];
        }, $rows), 200);
    }
}
