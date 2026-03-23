<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

use Pet\Application\Finance\Command\AddBillingExportItemHandler;
use Pet\Application\Finance\Command\ConfirmBillingExportHandler;
use Pet\Application\Finance\Command\CreateBillingExportHandler;
use Pet\Application\Finance\Command\QueueBillingExportForQuickBooksHandler;
use Pet\Domain\Finance\Repository\BillingExportRepository;
use Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository;
use Pet\UI\Rest\Controller\BillingController;
use PHPUnit\Framework\TestCase;

final class BillingControllerInputValidationTest extends TestCase
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function instanceWithoutConstructor(string $className): object
    {
        $reflector = new \ReflectionClass($className);
        /** @var T $instance */
        $instance = $reflector->newInstanceWithoutConstructor();
        return $instance;
    }
    public function testCreateExportReturns400ForMalformedDates(): void
    {
        $repository = $this->createMock(BillingExportRepository::class);
        $create = $this->instanceWithoutConstructor(CreateBillingExportHandler::class);
        $addItem = $this->instanceWithoutConstructor(AddBillingExportItemHandler::class);
        $queue = $this->instanceWithoutConstructor(QueueBillingExportForQuickBooksHandler::class);
        $confirm = $this->instanceWithoutConstructor(ConfirmBillingExportHandler::class);
        $outbox = $this->instanceWithoutConstructor(SqlOutboxRepository::class);

        $controller = new BillingController($repository, $create, $addItem, $queue, $confirm, $outbox);

        $request = new \WP_REST_Request('POST', '/pet/v1/billing/exports');
        $request->set_param('customerId', 1);
        $request->set_param('createdByEmployeeId', 1);
        $request->set_param('periodStart', 'bad-date');
        $request->set_param('periodEnd', '2026-03-31');

        $response = $controller->createExport($request);
        self::assertSame(400, $response->get_status());
    }

    public function testAddItemReturns400ForNonNumericFields(): void
    {
        $repository = $this->createMock(BillingExportRepository::class);
        $create = $this->instanceWithoutConstructor(CreateBillingExportHandler::class);
        $addItem = $this->instanceWithoutConstructor(AddBillingExportItemHandler::class);
        $queue = $this->instanceWithoutConstructor(QueueBillingExportForQuickBooksHandler::class);
        $confirm = $this->instanceWithoutConstructor(ConfirmBillingExportHandler::class);
        $outbox = $this->instanceWithoutConstructor(SqlOutboxRepository::class);

        $controller = new BillingController($repository, $create, $addItem, $queue, $confirm, $outbox);

        $request = new \WP_REST_Request('POST', '/pet/v1/billing/exports/1/items');
        $request->set_param('id', 1);
        $request->set_param('sourceType', 'ticket');
        $request->set_param('sourceId', 'abc');
        $request->set_param('quantity', '1.25');
        $request->set_param('unitPrice', 'oops');
        $request->set_param('description', 'Service');

        $response = $controller->addItem($request);
        self::assertSame(400, $response->get_status());
    }
}
