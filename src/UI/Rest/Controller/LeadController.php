<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\CreateLeadCommand;
use Pet\Application\Commercial\Command\CreateLeadHandler;
use Pet\Application\Commercial\Command\UpdateLeadCommand;
use Pet\Application\Commercial\Command\UpdateLeadHandler;
use Pet\Application\Commercial\Command\DeleteLeadCommand;
use Pet\Application\Commercial\Command\DeleteLeadHandler;
use Pet\Domain\Commercial\Repository\LeadRepository;
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

    public function __construct(
        LeadRepository $leadRepository,
        CreateLeadHandler $createLeadHandler,
        UpdateLeadHandler $updateLeadHandler,
        DeleteLeadHandler $deleteLeadHandler
    ) {
        $this->leadRepository = $leadRepository;
        $this->createLeadHandler = $createLeadHandler;
        $this->updateLeadHandler = $updateLeadHandler;
        $this->deleteLeadHandler = $deleteLeadHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getLeads'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createLead'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateLead'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteLead'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getLeads(WP_REST_Request $request): WP_REST_Response
    {
        $customerId = $request->get_param('customer_id');
        
        if ($customerId) {
            $leads = $this->leadRepository->findByCustomerId((int) $customerId);
        } else {
            $leads = $this->leadRepository->findAll();
        }

        $data = array_map(function ($lead) {
            return [
                'id' => $lead->id(),
                'customerId' => $lead->customerId(),
                'subject' => $lead->subject(),
                'description' => $lead->description(),
                'status' => $lead->status(),
                'source' => $lead->source(),
                'estimatedValue' => $lead->estimatedValue(),
                'malleableData' => $lead->malleableData(),
                'createdAt' => $lead->createdAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $lead->updatedAt() ? $lead->updatedAt()->format('Y-m-d H:i:s') : null,
                'convertedAt' => $lead->convertedAt() ? $lead->convertedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $leads);

        return new WP_REST_Response($data, 200);
    }

    public function createLead(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateLeadCommand(
                (int) $params['customerId'],
                $params['subject'],
                $params['description'],
                $params['source'] ?? null,
                isset($params['estimatedValue']) ? (float) $params['estimatedValue'] : null,
                $params['malleableData'] ?? []
            );

            $this->createLeadHandler->handle($command);

            return new WP_REST_Response(['message' => 'Lead created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
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
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
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
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
