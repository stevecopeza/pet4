<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\CreateLeadCommand;
use Pet\Application\Commercial\Command\CreateLeadHandler;
use Pet\Application\Commercial\Command\UpdateLeadCommand;
use Pet\Application\Commercial\Command\UpdateLeadHandler;
use Pet\Application\Commercial\Command\DeleteLeadCommand;
use Pet\Application\Commercial\Command\DeleteLeadHandler;
use Pet\Application\Commercial\Command\ConvertLeadToQuoteCommand;
use Pet\Application\Commercial\Command\ConvertLeadToQuoteHandler;
use Pet\Domain\Commercial\Repository\LeadRepository;
use Pet\UI\Rest\Support\PortalPermissionHelper;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class LeadController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'leads';

    private LeadRepository $leadRepository;
    private CreateLeadHandler $createLeadHandler;
    private UpdateLeadHandler $updateLeadHandler;
    private DeleteLeadHandler $deleteLeadHandler;
    private ConvertLeadToQuoteHandler $convertLeadToQuoteHandler;

    public function __construct(
        LeadRepository $leadRepository,
        CreateLeadHandler $createLeadHandler,
        UpdateLeadHandler $updateLeadHandler,
        DeleteLeadHandler $deleteLeadHandler,
        ConvertLeadToQuoteHandler $convertLeadToQuoteHandler
    ) {
        $this->leadRepository = $leadRepository;
        $this->createLeadHandler = $createLeadHandler;
        $this->updateLeadHandler = $updateLeadHandler;
        $this->deleteLeadHandler = $deleteLeadHandler;
        $this->convertLeadToQuoteHandler = $convertLeadToQuoteHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getLeads'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createLead'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateLead'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteLead'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/convert', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'convertLead'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function checkPortalPermission(): bool
    {
        return PortalPermissionHelper::check('pet_sales', 'pet_manager');
    }

    public function getLeads(WP_REST_Request $request): WP_REST_Response
    {
        $customerId = $request->get_param('customer_id');

        if ($customerId) {
            // Filtered by customer — enrich manually
            $leads = $this->leadRepository->findByCustomerId((int) $customerId);
            $data  = array_map(function ($lead) {
                return $this->serializeLead($lead);
            }, $leads);
        } else {
            // Full list — use enriched query that joins customer name in one go
            $rows = $this->leadRepository->findAllEnriched();
            $data = array_map(function ($row) {
                return [
                    'id'             => (int) $row->id,
                    'customerId'     => $row->customer_id !== null ? (int) $row->customer_id : null,
                    'customerName'   => $row->customer_name ?? null,
                    'subject'        => $row->subject,
                    'description'    => $row->description,
                    'status'         => $row->status,
                    'source'         => $row->source,
                    'estimatedValue' => $row->estimated_value !== null ? (float) $row->estimated_value : null,
                    'malleableData'  => isset($row->malleable_data) ? (json_decode($row->malleable_data, true) ?: []) : [],
                    'createdAt'      => $row->created_at,
                    'updatedAt'      => $row->updated_at,
                    'convertedAt'    => $row->converted_at,
                ];
            }, $rows);
        }

        return new WP_REST_Response($data, 200);
    }

    private function serializeLead(\Pet\Domain\Commercial\Entity\Lead $lead, ?string $customerName = null): array
    {
        return [
            'id'             => $lead->id(),
            'customerId'     => $lead->customerId(),
            'customerName'   => $customerName,
            'subject'        => $lead->subject(),
            'description'    => $lead->description(),
            'status'         => $lead->status(),
            'source'         => $lead->source(),
            'estimatedValue' => $lead->estimatedValue(),
            'malleableData'  => $lead->malleableData(),
            'createdAt'      => $lead->createdAt()->format('Y-m-d H:i:s'),
            'updatedAt'      => $lead->updatedAt() ? $lead->updatedAt()->format('Y-m-d H:i:s') : null,
            'convertedAt'    => $lead->convertedAt() ? $lead->convertedAt()->format('Y-m-d H:i:s') : null,
        ];
    }

    public function createLead(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateLeadCommand(
                isset($params['customerId']) ? (int) $params['customerId'] : null,
                $params['subject'],
                $params['description'],
                $params['source'] ?? null,
                isset($params['estimatedValue']) ? (float) $params['estimatedValue'] : null,
                $params['malleableData'] ?? []
            );

            $this->createLeadHandler->handle($command);

            return new WP_REST_Response(['message' => 'Lead created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function updateLead(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new UpdateLeadCommand(
                $id,
                $params['subject'],
                $params['description'],
                $params['status'],
                $params['source'] ?? null,
                isset($params['estimatedValue']) ? (float) $params['estimatedValue'] : null,
                $params['malleableData'] ?? []
            );

            $this->updateLeadHandler->handle($command);

            return new WP_REST_Response(['message' => 'Lead updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function deleteLead(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new DeleteLeadCommand($id);
            $this->deleteLeadHandler->handle($command);

            return new WP_REST_Response(['message' => 'Lead deleted'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function convertLead(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new ConvertLeadToQuoteCommand(
                $id,
                $params['title'] ?? '',
                $params['description'] ?? null,
                $params['currency'] ?? 'USD'
            );

            $quoteId = $this->convertLeadToQuoteHandler->handle($command);

            return new WP_REST_Response([
                'message' => 'Lead converted to quote',
                'quoteId' => $quoteId,
            ], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }
}
