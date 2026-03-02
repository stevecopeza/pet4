<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Activity\Dto\ActivityEvent;
use Pet\Application\Activity\Service\ActivityEventTransformer;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ActivityController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'activity';

    private FeedEventRepository $feedEventRepository;
    private ActivityEventTransformer $transformer;

    public function __construct(
        FeedEventRepository $feedEventRepository,
        ActivityEventTransformer $transformer
    ) {
        $this->feedEventRepository = $feedEventRepository;
        $this->transformer = $transformer;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getActivityLogs'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return is_user_logged_in();
    }

    public function getActivityLogs(WP_REST_Request $request): WP_REST_Response
    {
        $limitParam = $request->get_param('limit');
        $limit = is_numeric($limitParam) ? (int) $limitParam : 50;
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $range = $request->get_param('range');
        $fromParam = $request->get_param('from');
        $toParam = $request->get_param('to');

        $from = null;
        $to = null;

        if (is_string($fromParam) && $fromParam !== '') {
            try {
                $from = new \DateTimeImmutable($fromParam);
            } catch (\Throwable $e) {
                $from = null;
            }
        }

        if (is_string($toParam) && $toParam !== '') {
            try {
                $to = new \DateTimeImmutable($toParam);
            } catch (\Throwable $e) {
                $to = null;
            }
        }

        if ($from === null && $range) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            if ($range === '24h') {
                $from = $now->modify('-24 hours');
            } elseif ($range === '30d') {
                $from = $now->modify('-30 days');
            } else {
                $from = $now->modify('-7 days');
                $range = '7d';
            }
        }

        $wpUserId = get_current_user_id();

        $items = [];

        try {
            $events = $this->feedEventRepository->findRelevantForUser((string) $wpUserId, [], [], $limit);

            $filtered = array_filter($events, function ($event) use ($from, $to, $request) {
                if ($from instanceof \DateTimeImmutable && $event->getCreatedAt() < $from) {
                    return false;
                }
                if ($to instanceof \DateTimeImmutable && $event->getCreatedAt() > $to) {
                    return false;
                }

                $eventTypeFilters = $request->get_param('event_type');
                if (is_array($eventTypeFilters) && !empty($eventTypeFilters)) {
                    $mappedType = $this->transformer->fromFeedEvent($event)->eventType;
                    $allowed = array_map('strval', $eventTypeFilters);
                    if (!in_array($mappedType, $allowed, true)) {
                        return false;
                    }
                }

                $severityFilters = $request->get_param('severity');
                if (is_array($severityFilters) && !empty($severityFilters)) {
                    $mappedSeverity = $this->transformer->fromFeedEvent($event)->severity;
                    $allowed = array_map('strval', $severityFilters);
                    if (!in_array($mappedSeverity, $allowed, true)) {
                        return false;
                    }
                }

                $referenceTypeFilters = $request->get_param('reference_type');
                if (is_array($referenceTypeFilters) && !empty($referenceTypeFilters)) {
                    $mappedReferenceType = $this->transformer->fromFeedEvent($event)->referenceType;
                    $allowed = array_map('strval', $referenceTypeFilters);
                    if (!in_array($mappedReferenceType, $allowed, true)) {
                        return false;
                    }
                }

                $actorFilters = $request->get_param('actor_id');
                if (is_array($actorFilters) && !empty($actorFilters)) {
                    $mapped = $this->transformer->fromFeedEvent($event)->actorId;
                    $allowed = array_map('strval', $actorFilters);
                    if ($mapped === null || !in_array($mapped, $allowed, true)) {
                        return false;
                    }
                }

                $customerFilters = $request->get_param('customer_id');
                if (is_array($customerFilters) && !empty($customerFilters)) {
                    $mapped = $this->transformer->fromFeedEvent($event)->customerId;
                    $allowed = array_map('strval', $customerFilters);
                    if ($mapped === null || !in_array($mapped, $allowed, true)) {
                        return false;
                    }
                }

                $q = $request->get_param('q');
                if (is_string($q) && $q !== '') {
                    $haystack = $event->getTitle() . ' ' . $event->getSummary();
                    if (stripos($haystack, $q) === false) {
                        return false;
                    }
                }

                return true;
            });

            $items = array_map(function ($event) {
                $dto = $this->transformer->fromFeedEvent($event);

                return [
                    'id' => $dto->id,
                    'occurred_at' => $dto->occurredAt,
                    'actor_type' => $dto->actorType,
                    'actor_id' => $dto->actorId,
                    'actor_display_name' => $dto->actorDisplayName,
                    'actor_avatar_url' => $dto->actorAvatarUrl,
                    'event_type' => $dto->eventType,
                    'severity' => $dto->severity,
                    'reference_type' => $dto->referenceType,
                    'reference_id' => $dto->referenceId,
                    'reference_url' => $dto->referenceUrl,
                    'customer_id' => $dto->customerId,
                    'customer_name' => $dto->customerName,
                    'company_logo_url' => $dto->companyLogoUrl,
                    'headline' => $dto->headline,
                    'subline' => $dto->subline,
                    'tags' => $dto->tags,
                    'sla' => $dto->sla,
                    'meta' => $dto->meta,
                ];
            }, $filtered);
        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'items' => [],
                'next_page' => null,
                'meta' => [
                    'range' => $range ?: null,
                    'limit' => $limit,
                    'error' => 'activity_load_failed',
                ],
            ], 200);
        }

        $response = [
            'items' => array_values($items),
            'next_page' => null,
            'meta' => [
                'range' => $range ?: null,
                'limit' => $limit,
            ],
        ];

        return new WP_REST_Response($response, 200);
    }
}
