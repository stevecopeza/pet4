<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Domain\Feed\Repository\AnnouncementRepository;
use Pet\Domain\Feed\Repository\AnnouncementAcknowledgementRepository;
use Pet\Domain\Feed\Repository\FeedReactionRepository;
use Pet\Domain\Feed\Entity\Announcement;
use Pet\Domain\Feed\Entity\AnnouncementAcknowledgement;
use Pet\Domain\Feed\Entity\FeedReaction;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Work\Repository\AssignmentRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use DateTimeImmutable;

class FeedController implements RestController
{
    private const NAMESPACE = 'pet/v1';

    private EmployeeRepository $employeeRepository;
    private FeedEventRepository $feedEventRepository;
    private AnnouncementRepository $announcementRepository;
    private AnnouncementAcknowledgementRepository $ackRepository;
    private FeedReactionRepository $reactionRepository;
    private AssignmentRepository $assignmentRepository;

    public function __construct(
        EmployeeRepository $employeeRepository,
        FeedEventRepository $feedEventRepository,
        AnnouncementRepository $announcementRepository,
        AnnouncementAcknowledgementRepository $ackRepository,
        FeedReactionRepository $reactionRepository,
        AssignmentRepository $assignmentRepository
    ) {
        $this->employeeRepository = $employeeRepository;
        $this->feedEventRepository = $feedEventRepository;
        $this->announcementRepository = $announcementRepository;
        $this->ackRepository = $ackRepository;
        $this->reactionRepository = $reactionRepository;
        $this->assignmentRepository = $assignmentRepository;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/feed', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getFeed'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/announcements', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAnnouncements'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createAnnouncement'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/announcements/(?P<id>[^/]+)/ack', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'acknowledgeAnnouncement'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/feed/(?P<id>[^/]+)/react', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'reactToFeedEvent'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getFeed(WP_REST_Request $request): WP_REST_Response
    {
        $wpUserId = get_current_user_id();
        $employee = $this->employeeRepository->findByWpUserId((int)$wpUserId);
        $userId = (string)$wpUserId;
        $departmentIds = $employee ? array_map('strval', $employee->teamIds()) : [];
        $roleIds = [];
        if ($employee && $employee->id() !== null) {
            $assignments = $this->assignmentRepository->findByEmployeeId((int)$employee->id());
            foreach ($assignments as $assignment) {
                if ($assignment->status() === 'active' && ($assignment->endDate() === null || $assignment->endDate() > new DateTimeImmutable())) {
                    $roleIds[] = (string)$assignment->roleId();
                }
            }
        }

        $events = $this->feedEventRepository->findRelevantForUser($userId, $departmentIds, $roleIds, 50);
        $data = array_map([$this, 'serializeFeedEvent'], $events);
        return new WP_REST_Response($data, 200);
    }

    public function getAnnouncements(WP_REST_Request $request): WP_REST_Response
    {
        $wpUserId = get_current_user_id();
        $employee = $this->employeeRepository->findByWpUserId((int)$wpUserId);
        $userId = (string)$wpUserId;
        $departmentIds = $employee ? array_map('strval', $employee->teamIds()) : [];
        $roleIds = [];
        if ($employee && $employee->id() !== null) {
            $assignments = $this->assignmentRepository->findByEmployeeId((int)$employee->id());
            foreach ($assignments as $assignment) {
                if ($assignment->status() === 'active' && ($assignment->endDate() === null || $assignment->endDate() > new DateTimeImmutable())) {
                    $roleIds[] = (string)$assignment->roleId();
                }
            }
        }

        $announcements = $this->announcementRepository->findRelevantForUser($userId, $departmentIds, $roleIds);
        $data = array_map([$this, 'serializeAnnouncement'], $announcements);
        return new WP_REST_Response($data, 200);
    }

    public function createAnnouncement(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        try {
            $id = $this->generateUuid();
            $title = (string)($params['title'] ?? '');
            $body = (string)($params['body'] ?? '');
            $priority = (string)($params['priorityLevel'] ?? 'normal');
            $pinned = (bool)($params['pinned'] ?? false);
            $ackRequired = (bool)($params['acknowledgementRequired'] ?? false);
            $gpsRequired = (bool)($params['gpsRequired'] ?? false);
            $ackDeadline = !empty($params['acknowledgementDeadline']) ? new DateTimeImmutable($params['acknowledgementDeadline']) : null;
            $audienceScope = (string)($params['audienceScope'] ?? 'global');
            $audienceRef = isset($params['audienceReferenceId']) ? (string)$params['audienceReferenceId'] : null;
            $authorUserId = (string)get_current_user_id();
            $expiresAt = !empty($params['expiresAt']) ? new DateTimeImmutable($params['expiresAt']) : null;

            $announcement = Announcement::create(
                $id,
                $title,
                $body,
                $priority,
                $pinned,
                $ackRequired,
                $gpsRequired,
                $ackDeadline,
                $audienceScope,
                $audienceRef,
                $authorUserId,
                $expiresAt
            );

            $this->announcementRepository->save($announcement);
            return new WP_REST_Response($this->serializeAnnouncement($announcement), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function acknowledgeAnnouncement(WP_REST_Request $request): WP_REST_Response
    {
        $id = (string)$request->get_param('id');
        $params = $request->get_json_params();

        try {
            $userId = (string)get_current_user_id();
            $existing = $this->ackRepository->findByAnnouncementAndUser($id, $userId);
            if ($existing) {
                return new WP_REST_Response(['message' => 'Already acknowledged'], 200);
            }

            $ack = AnnouncementAcknowledgement::create(
                $this->generateUuid(),
                $id,
                $userId,
                isset($params['deviceInfo']) ? (string)$params['deviceInfo'] : null,
                isset($params['gpsLat']) ? (float)$params['gpsLat'] : null,
                isset($params['gpsLng']) ? (float)$params['gpsLng'] : null
            );
            $this->ackRepository->save($ack);

            return new WP_REST_Response([
                'id' => $ack->getId(),
                'announcementId' => $ack->getAnnouncementId(),
                'userId' => $ack->getUserId(),
                'acknowledgedAt' => $ack->getAcknowledgedAt()->format(DateTimeImmutable::ATOM),
            ], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function reactToFeedEvent(WP_REST_Request $request): WP_REST_Response
    {
        $id = (string)$request->get_param('id');
        $params = $request->get_json_params();

        try {
            $userId = (string)get_current_user_id();
            $type = (string)($params['reactionType'] ?? 'acknowledged');

            $existing = $this->reactionRepository->findByEventAndUser($id, $userId);
            if ($existing) {
                return new WP_REST_Response([
                    'id' => $existing->getId(),
                    'feedEventId' => $existing->getFeedEventId(),
                    'userId' => $existing->getUserId(),
                    'reactionType' => $existing->getReactionType(),
                    'createdAt' => $existing->getCreatedAt()->format(DateTimeImmutable::ATOM),
                ], 200);
            }

            $reaction = FeedReaction::create(
                $this->generateUuid(),
                $id,
                $userId,
                $type
            );
            $this->reactionRepository->save($reaction);

            return new WP_REST_Response([
                'id' => $reaction->getId(),
                'feedEventId' => $reaction->getFeedEventId(),
                'userId' => $reaction->getUserId(),
                'reactionType' => $reaction->getReactionType(),
                'createdAt' => $reaction->getCreatedAt()->format(DateTimeImmutable::ATOM),
            ], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    private function serializeFeedEvent( \Pet\Domain\Feed\Entity\FeedEvent $event): array
    {
        return [
            'id' => $event->getId(),
            'eventType' => $event->getEventType(),
            'sourceEngine' => $event->getSourceEngine(),
            'sourceEntityId' => $event->getSourceEntityId(),
            'classification' => $event->getClassification(),
            'title' => $event->getTitle(),
            'summary' => $event->getSummary(),
            'metadata' => $event->getMetadata(),
            'audienceScope' => $event->getAudienceScope(),
            'audienceReferenceId' => $event->getAudienceReferenceId(),
            'pinned' => $event->isPinned(),
            'expiresAt' => $event->getExpiresAt() ? $event->getExpiresAt()->format(DateTimeImmutable::ATOM) : null,
            'createdAt' => $event->getCreatedAt()->format(DateTimeImmutable::ATOM),
        ];
    }

    private function serializeAnnouncement( \Pet\Domain\Feed\Entity\Announcement $a): array
    {
        return [
            'id' => $a->getId(),
            'title' => $a->getTitle(),
            'body' => $a->getBody(),
            'priorityLevel' => $a->getPriorityLevel(),
            'pinned' => $a->isPinned(),
            'acknowledgementRequired' => $a->isAcknowledgementRequired(),
            'gpsRequired' => $a->isGpsRequired(),
            'acknowledgementDeadline' => $a->getAcknowledgementDeadline() ? $a->getAcknowledgementDeadline()->format(DateTimeImmutable::ATOM) : null,
            'audienceScope' => $a->getAudienceScope(),
            'audienceReferenceId' => $a->getAudienceReferenceId(),
            'authorUserId' => $a->getAuthorUserId(),
            'expiresAt' => $a->getExpiresAt() ? $a->getExpiresAt()->format(DateTimeImmutable::ATOM) : null,
            'createdAt' => $a->getCreatedAt()->format(DateTimeImmutable::ATOM),
        ];
    }

    private function generateUuid(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
