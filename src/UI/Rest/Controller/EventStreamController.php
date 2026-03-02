<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Event\Repository\EventStreamRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EventStreamController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'event-stream';

    private EventStreamRepository $repo;

    public function __construct(EventStreamRepository $repo)
    {
        $this->repo = $repo;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listEvents'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function listEvents(WP_REST_Request $request): WP_REST_Response
    {
        $limit = $request->get_param('limit') ? (int)$request->get_param('limit') : 100;
        $aggregateType = $request->get_param('aggregate_type');
        $aggregateId = $request->get_param('aggregate_id') ? (int)$request->get_param('aggregate_id') : null;
        $eventType = $request->get_param('event_type');

        $events = $this->repo->findLatest($limit, $aggregateType ?: null, $aggregateId, $eventType ?: null);

        $data = array_map(function ($e) {
            return [
                'id' => $e->id,
                'eventUuid' => $e->eventUuid,
                'occurredAt' => $e->occurredAt,
                'recordedAt' => $e->recordedAt,
                'aggregateType' => $e->aggregateType,
                'aggregateId' => $e->aggregateId,
                'aggregateVersion' => $e->aggregateVersion,
                'eventType' => $e->eventType,
                'eventSchemaVersion' => $e->eventSchemaVersion,
                'actorType' => $e->actorType,
                'actorId' => $e->actorId,
                'correlationId' => $e->correlationId,
                'causationId' => $e->causationId,
                'payloadJson' => $e->payloadJson,
                'metadataJson' => $e->metadataJson,
            ];
        }, $events);

        return new WP_REST_Response($data, 200);
    }
}
