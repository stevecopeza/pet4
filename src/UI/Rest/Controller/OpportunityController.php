<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\CloseOpportunityCommand;
use Pet\Application\Commercial\Command\CloseOpportunityHandler;
use Pet\Application\Commercial\Command\ConvertOpportunityToQuoteCommand;
use Pet\Application\Commercial\Command\ConvertOpportunityToQuoteHandler;
use Pet\Application\Commercial\Command\CreateOpportunityCommand;
use Pet\Application\Commercial\Command\CreateOpportunityHandler;
use Pet\Application\Commercial\Command\UpdateOpportunityCommand;
use Pet\Application\Commercial\Command\UpdateOpportunityHandler;
use Pet\Domain\Commercial\Repository\OpportunityRepository;
use Pet\UI\Rest\Support\PortalPermissionHelper;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class OpportunityController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE  = 'opportunities';

    public function __construct(
        private OpportunityRepository $opportunityRepository,
        private CreateOpportunityHandler $createHandler,
        private UpdateOpportunityHandler $updateHandler,
        private CloseOpportunityHandler $closeHandler,
        private ConvertOpportunityToQuoteHandler $convertHandler
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'list'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>[a-zA-Z0-9\-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>[a-zA-Z0-9\-]+)/convert-quote', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'convertToQuote'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>[a-zA-Z0-9\-]+)/close', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'close'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return PortalPermissionHelper::check('pet_sales', 'pet_manager');
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $stage      = $request->get_param('stage');
        $customerId = $request->get_param('customer_id');

        if ($customerId) {
            $rows = $this->opportunityRepository->findByCustomerId((int)$customerId);
        } elseif ($stage) {
            $rows = $this->opportunityRepository->findByStage((string)$stage);
        } else {
            $rows = $this->opportunityRepository->findAllEnriched();
        }

        $data = array_map(fn($row) => $this->serialize($row), $rows);
        return new WP_REST_Response(array_values($data), 200);
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        $opp = $this->opportunityRepository->findById((string)$request->get_param('id'));
        if (!$opp) {
            return new WP_REST_Response(['message' => 'Not Found'], 404);
        }
        return new WP_REST_Response($this->serialize($opp), 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        try {
            $opp = $this->createHandler->handle(new CreateOpportunityCommand(
                (int)($params['customerId'] ?? 0),
                (string)($params['name'] ?? ''),
                (string)($params['stage'] ?? 'discovery'),
                (float)($params['estimatedValue'] ?? 0),
                (int)(get_current_user_id()),
                isset($params['leadId']) ? (int)$params['leadId'] : null,
                $params['currency'] ?? 'ZAR',
                $params['expectedCloseDate'] ?? null,
                $params['qualification'] ?? [],
                $params['notes'] ?? null
            ));
            return new WP_REST_Response($this->serialize($opp), 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 422);
        }
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        try {
            $this->updateHandler->handle(new UpdateOpportunityCommand(
                (string)$request->get_param('id'),
                (string)($params['name'] ?? ''),
                (string)($params['stage'] ?? 'discovery'),
                (float)($params['estimatedValue'] ?? 0),
                isset($params['ownerId']) ? (int)$params['ownerId'] : (int)get_current_user_id(),
                $params['currency'] ?? 'ZAR',
                $params['expectedCloseDate'] ?? null,
                $params['qualification'] ?? [],
                $params['notes'] ?? null
            ));
            $opp = $this->opportunityRepository->findById((string)$request->get_param('id'));
            return new WP_REST_Response($opp ? $this->serialize($opp) : [], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 422);
        }
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $id  = (string)$request->get_param('id');
        $opp = $this->opportunityRepository->findById($id);
        if (!$opp) {
            return new WP_REST_Response(['message' => 'Not Found'], 404);
        }
        $this->opportunityRepository->delete($id);
        return new WP_REST_Response(['deleted' => true], 200);
    }

    public function convertToQuote(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $quoteId = $this->convertHandler->handle(new ConvertOpportunityToQuoteCommand(
                (string)$request->get_param('id'),
                (int)get_current_user_id()
            ));
            return new WP_REST_Response(['quoteId' => $quoteId], 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 422);
        }
    }

    public function close(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        try {
            $this->closeHandler->handle(new CloseOpportunityCommand(
                (string)$request->get_param('id'),
                (string)($params['stage'] ?? 'closed_lost')
            ));
            $opp = $this->opportunityRepository->findById((string)$request->get_param('id'));
            return new WP_REST_Response($opp ? $this->serialize($opp) : [], 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 422);
        }
    }

    private function serialize($opp): array
    {
        // Handles both Opportunity entity and enriched stdClass rows
        if ($opp instanceof \Pet\Domain\Commercial\Entity\Opportunity) {
            return [
                'id'                => $opp->id(),
                'customerId'        => $opp->customerId(),
                'customerName'      => null,
                'leadId'            => $opp->leadId(),
                'name'              => $opp->name(),
                'stage'             => $opp->stage(),
                'estimatedValue'    => $opp->estimatedValue(),
                'currency'          => $opp->currency(),
                'expectedCloseDate' => $opp->expectedCloseDate()?->format('Y-m-d'),
                'ownerId'           => $opp->ownerId(),
                'qualification'     => $opp->qualification(),
                'notes'             => $opp->notes(),
                'quoteId'           => $opp->quoteId(),
                'isOpen'            => $opp->isOpen(),
                'createdAt'         => $opp->createdAt()->format('Y-m-d H:i:s'),
                'updatedAt'         => $opp->updatedAt()?->format('Y-m-d H:i:s'),
                'closedAt'          => $opp->closedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        // Enriched row (stdClass from findAllEnriched)
        return [
            'id'                => $opp->id,
            'customerId'        => (int)$opp->customer_id,
            'customerName'      => $opp->customer_name ?? null,
            'leadId'            => isset($opp->lead_id) ? (int)$opp->lead_id : null,
            'name'              => $opp->name,
            'stage'             => $opp->stage,
            'estimatedValue'    => (float)$opp->estimated_value,
            'currency'          => $opp->currency ?? 'ZAR',
            'expectedCloseDate' => $opp->expected_close_date ?? null,
            'ownerId'           => (int)$opp->owner_id,
            'qualification'     => !empty($opp->qualification_json)
                                    ? (json_decode($opp->qualification_json, true) ?: []) : [],
            'notes'             => $opp->notes ?? null,
            'quoteId'           => isset($opp->quote_id) && $opp->quote_id ? (int)$opp->quote_id : null,
            'isOpen'            => in_array($opp->stage, ['discovery','proposal','negotiation'], true),
            'createdAt'         => $opp->created_at,
            'updatedAt'         => $opp->updated_at ?? null,
            'closedAt'          => $opp->closed_at ?? null,
        ];
    }
}
