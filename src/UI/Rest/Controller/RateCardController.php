<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\CreateRateCardCommand;
use Pet\Application\Commercial\Command\CreateRateCardHandler;
use Pet\Application\Commercial\Command\ArchiveRateCardCommand;
use Pet\Application\Commercial\Command\ArchiveRateCardHandler;
use Pet\Application\Commercial\Command\ResolveRateCardQuery;
use Pet\Application\Commercial\Command\ResolveRateCardHandler;
use Pet\Domain\Commercial\Repository\RateCardRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RateCardController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'rate-cards';

    private RateCardRepository $repository;
    private CreateRateCardHandler $createHandler;
    private ArchiveRateCardHandler $archiveHandler;
    private ResolveRateCardHandler $resolveHandler;

    public function __construct(
        RateCardRepository $repository,
        CreateRateCardHandler $createHandler,
        ArchiveRateCardHandler $archiveHandler,
        ResolveRateCardHandler $resolveHandler
    ) {
        $this->repository = $repository;
        $this->createHandler = $createHandler;
        $this->archiveHandler = $archiveHandler;
        $this->resolveHandler = $resolveHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list'], 'permission_callback' => [$this, 'checkPermission']],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create'], 'permission_callback' => [$this, 'checkPermission']],
        ]);
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/archive', [
            ['methods' => 'POST', 'callback' => [$this, 'archive'], 'permission_callback' => [$this, 'checkPermission']],
        ]);
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/resolve', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'resolve'], 'permission_callback' => [$this, 'checkPermission']],
        ]);
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $filters = [];
        foreach (['role_id', 'service_type_id', 'contract_id', 'status'] as $key) {
            $val = $request->get_param($key);
            if ($val !== null) {
                $filters[$key] = $val;
            }
        }
        $items = $this->repository->findAll($filters);
        $data = array_map(fn($rc) => $this->serialize($rc), $items);
        return new WP_REST_Response($data, 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        try {
            $id = $this->createHandler->handle(new CreateRateCardCommand(
                (int)($params['role_id'] ?? 0),
                (int)($params['service_type_id'] ?? 0),
                (float)($params['sell_rate'] ?? 0),
                isset($params['contract_id']) ? (int)$params['contract_id'] : null,
                isset($params['valid_from']) ? new \DateTimeImmutable($params['valid_from']) : null,
                isset($params['valid_to']) ? new \DateTimeImmutable($params['valid_to']) : null
            ));
            return new WP_REST_Response(['id' => $id, 'status' => 'created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function archive(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $this->archiveHandler->handle(new ArchiveRateCardCommand((int)$request->get_param('id')));
            return new WP_REST_Response(['status' => 'archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function resolve(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $card = $this->resolveHandler->handle(new ResolveRateCardQuery(
                (int)$request->get_param('role_id'),
                (int)$request->get_param('service_type_id'),
                $request->get_param('contract_id') ? (int)$request->get_param('contract_id') : null,
                new \DateTimeImmutable($request->get_param('effective_date') ?? 'now')
            ));
            return new WP_REST_Response($this->serialize($card), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], $e instanceof \DomainException ? 404 : 400);
        }
    }

    private function serialize($rc): array
    {
        return [
            'id' => $rc->id(),
            'role_id' => $rc->roleId(),
            'service_type_id' => $rc->serviceTypeId(),
            'sell_rate' => $rc->sellRate(),
            'contract_id' => $rc->contractId(),
            'valid_from' => $rc->validFrom() ? $rc->validFrom()->format('Y-m-d') : null,
            'valid_to' => $rc->validTo() ? $rc->validTo()->format('Y-m-d') : null,
            'status' => $rc->status(),
            'created_at' => $rc->createdAt()->format('c'),
            'updated_at' => $rc->updatedAt()->format('c'),
        ];
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
