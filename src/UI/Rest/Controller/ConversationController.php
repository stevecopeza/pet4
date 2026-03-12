<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Conversation\Command\CreateConversationCommand;
use Pet\Application\Conversation\Command\CreateConversationHandler;
use Pet\Application\Conversation\Command\PostMessageCommand;
use Pet\Application\Conversation\Command\PostMessageHandler;
use Pet\Application\Conversation\Command\RequestDecisionCommand;
use Pet\Application\Conversation\Command\RequestDecisionHandler;
use Pet\Application\Conversation\Command\ResolveConversationCommand;
use Pet\Application\Conversation\Command\ResolveConversationHandler;
use Pet\Application\Conversation\Command\ReopenConversationCommand;
use Pet\Application\Conversation\Command\ReopenConversationHandler;
use Pet\Application\Conversation\Command\RespondToDecisionCommand;
use Pet\Application\Conversation\Command\RespondToDecisionHandler;
use Pet\Application\Conversation\Command\AddReactionCommand;
use Pet\Application\Conversation\Command\AddReactionHandler;
use Pet\Application\Conversation\Command\RemoveReactionCommand;
use Pet\Application\Conversation\Command\RemoveReactionHandler;
use Pet\Application\Conversation\Command\AddParticipantCommand;
use Pet\Application\Conversation\Command\AddParticipantHandler;
use Pet\Application\Conversation\Command\RemoveParticipantCommand;
use Pet\Application\Conversation\Command\RemoveParticipantHandler;
use Pet\Domain\Conversation\Service\ConversationAccessControl;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Conversation\ValueObject\ApprovalPolicy;
use WP_REST_Request;
use WP_REST_Response;

class ConversationController
{
    private ConversationRepository $conversationRepository;
    private DecisionRepository $decisionRepository;
    private ConversationAccessControl $conversationAccessControl;
    private CreateConversationHandler $createConversationHandler;
    private PostMessageHandler $postMessageHandler;
    private RequestDecisionHandler $requestDecisionHandler;
    private RespondToDecisionHandler $respondToDecisionHandler;
    private ResolveConversationHandler $resolveConversationHandler;
    private ReopenConversationHandler $reopenConversationHandler;
    private AddReactionHandler $addReactionHandler;
    private RemoveReactionHandler $removeReactionHandler;
    private AddParticipantHandler $addParticipantHandler;
    private RemoveParticipantHandler $removeParticipantHandler;

    public function __construct(
        ConversationRepository $conversationRepository,
        DecisionRepository $decisionRepository,
        ConversationAccessControl $conversationAccessControl,
        CreateConversationHandler $createConversationHandler,
        PostMessageHandler $postMessageHandler,
        RequestDecisionHandler $requestDecisionHandler,
        RespondToDecisionHandler $respondToDecisionHandler,
        ResolveConversationHandler $resolveConversationHandler,
        ReopenConversationHandler $reopenConversationHandler,
        AddReactionHandler $addReactionHandler,
        RemoveReactionHandler $removeReactionHandler,
        AddParticipantHandler $addParticipantHandler,
        RemoveParticipantHandler $removeParticipantHandler
    ) {
        $this->conversationRepository = $conversationRepository;
        $this->decisionRepository = $decisionRepository;
        $this->conversationAccessControl = $conversationAccessControl;
        $this->createConversationHandler = $createConversationHandler;
        $this->postMessageHandler = $postMessageHandler;
        $this->requestDecisionHandler = $requestDecisionHandler;
        $this->respondToDecisionHandler = $respondToDecisionHandler;
        $this->resolveConversationHandler = $resolveConversationHandler;
        $this->reopenConversationHandler = $reopenConversationHandler;
        $this->addReactionHandler = $addReactionHandler;
        $this->removeReactionHandler = $removeReactionHandler;
        $this->addParticipantHandler = $addParticipantHandler;
        $this->removeParticipantHandler = $removeParticipantHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route('pet/v1', '/conversations', [
            'methods' => 'POST',
            'callback' => [$this, 'createConversation'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/messages', [
            'methods' => 'POST',
            'callback' => [$this, 'postMessage'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/decisions', [
            'methods' => 'POST',
            'callback' => [$this, 'requestDecision'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/decisions/(?P<uuid>[a-zA-Z0-9-]+)/respond', [
            'methods' => 'POST',
            'callback' => [$this, 'respondToDecision'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/resolve', [
            'methods' => 'POST',
            'callback' => [$this, 'resolveConversation'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/reopen', [
            'methods' => 'POST',
            'callback' => [$this, 'reopenConversation'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/read', [
            'methods' => 'POST',
            'callback' => [$this, 'markAsRead'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/messages/(?P<message_id>\d+)/reactions', [
            'methods' => 'POST',
            'callback' => [$this, 'addReaction'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/messages/(?P<message_id>\d+)/reactions/(?P<reaction_type>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'removeReaction'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/participants/add', [
            'methods' => 'POST',
            'callback' => [$this, 'addParticipant'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/participants/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'removeParticipant'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/unread-counts', [
            'methods' => 'GET',
            'callback' => [$this, 'getUnreadCounts'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/active-subjects', [
            'methods' => 'GET',
            'callback' => [$this, 'getActiveSubjects'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/conversations/me', [
            'methods' => 'GET',
            'callback' => [$this, 'getMyConversations'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('pet/v1', '/decisions/pending', [
            'methods' => 'GET',
            'callback' => [$this, 'getMyPendingDecisions'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
        
        register_rest_route('pet/v1', '/conversations/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'getSummary'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // GET endpoints
        register_rest_route('pet/v1', '/conversations', [
            'methods' => 'GET',
            'callback' => [$this, 'getConversation'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('edit_posts');
    }

    public function getUnreadCounts(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $counts = $this->conversationRepository->getUnreadCounts($userId);
        return new WP_REST_Response($counts, 200);
    }

    public function getActiveSubjects(WP_REST_Request $request): WP_REST_Response
    {
        $contextType = $request->get_param('context_type');
        $contextId = $request->get_param('context_id');

        if (!$contextType || !$contextId) {
            return new WP_REST_Response(['error' => 'context_type and context_id are required'], 400);
        }

        $subjectKeys = $this->conversationRepository->findOpenSubjectKeysByContext($contextType, $contextId);

        return new WP_REST_Response($subjectKeys, 200);
    }

    public function getMyConversations(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $limit = (int)($request->get_param('limit') ?? 10);
        
        $conversations = $this->conversationRepository->findRecentByUserId($userId, $limit);
        
        $data = array_map(function($conversation) {
            return [
                'uuid' => $conversation->uuid(),
                'context_type' => $conversation->contextType(),
                'context_id' => $conversation->contextId(),
                'subject' => $conversation->subject(),
                'state' => $conversation->state(),
                'created_at' => $conversation->createdAt()->format('c'),
            ];
        }, $conversations);

        return new WP_REST_Response($data, 200);
    }

    public function getMyPendingDecisions(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $decisions = $this->decisionRepository->findPendingByUserId($userId);
        
        $data = array_map(function($decision) {
            return [
                'uuid' => $decision->uuid(),
                'decision_type' => $decision->decisionType(),
                'conversation_id' => $decision->conversationId(),
                'state' => $decision->state(),
                'payload' => $decision->payload(),
                'requested_at' => $decision->requestedAt()->format('c'),
                'requester_id' => $decision->requesterId(),
            ];
        }, $decisions);

        return new WP_REST_Response($data, 200);
    }

    public function markAsRead(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');
            $params = $request->get_json_params();
            $lastEventId = (int)($params['last_seen_event_id'] ?? 0);
            $userId = get_current_user_id();

            $conversation = $this->conversationRepository->findByUuid($uuid);
            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            // Security: Check participation or context access
            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $userId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $userId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $this->conversationRepository->markAsRead((int)$conversation->id(), $userId, $lastEventId);

            return new WP_REST_Response(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addReaction(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');
            $messageId = (int)$request->get_param('message_id');
            $params = $request->get_json_params();
            $reactionType = $params['reaction_type'];
            $actorId = get_current_user_id();

            // Security: Check participation or context access
            $conversation = $this->conversationRepository->findByUuid($uuid);
            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $actorId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $actorId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }
            
            $command = new AddReactionCommand(
                $uuid,
                $messageId,
                $reactionType,
                $actorId
            );

            $this->addReactionHandler->handle($command);

            return new WP_REST_Response(['status' => 'success'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function removeReaction(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');
            $messageId = (int)$request->get_param('message_id');
            $reactionType = $request->get_param('reaction_type');
            $actorId = get_current_user_id();

            // Security: Check participation or context access
            $conversation = $this->conversationRepository->findByUuid($uuid);
            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $actorId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $actorId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $command = new RemoveReactionCommand(
                $uuid,
                $messageId,
                $reactionType,
                $actorId
            );

            $this->removeReactionHandler->handle($command);

            return new WP_REST_Response(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addParticipant(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');
            $params = $request->get_json_params();
            $actorId = get_current_user_id();

            // Security: Check participation or context access
            $conversation = $this->conversationRepository->findByUuid($uuid);
            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $actorId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $actorId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $command = new AddParticipantCommand(
                $uuid,
                $params['participant_type'],
                (int)$params['participant_id'],
                $actorId
            );

            $this->addParticipantHandler->handle($command);

            return new WP_REST_Response(['status' => 'success'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function removeParticipant(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');
            $params = $request->get_json_params();
            $actorId = get_current_user_id();

            // Security: Check participation or context access
            $conversation = $this->conversationRepository->findByUuid($uuid);
            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $actorId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $actorId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $command = new RemoveParticipantCommand(
                $uuid,
                $params['participant_type'],
                (int)$params['participant_id'],
                $actorId
            );

            $this->removeParticipantHandler->handle($command);

            return new WP_REST_Response(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }


    public function createConversation(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params = $request->get_json_params();
            $actorId = get_current_user_id();

            // Security: Check access to context
            $contextType = $params['context_type'];
            $contextId = (string)$params['context_id'];

            if (!$this->conversationAccessControl->check($contextType, $contextId, $actorId)) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $command = new CreateConversationCommand(
                $contextType,
                $contextId,
                $params['subject'],
                $params['subject_key'],
                $actorId,
                $params['context_version'] ?? null
            );

            $uuid = $this->createConversationHandler->handle($command);

            return new WP_REST_Response(['uuid' => $uuid], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function postMessage(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');

            // Security: Check participation or context access
            $conversation = $this->conversationRepository->findByUuid($uuid);
            $userId = get_current_user_id();

            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $userId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $userId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $params = $request->get_json_params();
            $actorId = get_current_user_id();

            $command = new PostMessageCommand(
                $uuid,
                $params['body'],
                $params['mentions'] ?? [],
                $params['attachments'] ?? [],
                $actorId,
                isset($params['reply_to_message_id']) ? (int)$params['reply_to_message_id'] : null
            );

            $this->postMessageHandler->handle($command);

            return new WP_REST_Response(['status' => 'success'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function requestDecision(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');

            // Security: Check participation or context access
            $conversation = $this->conversationRepository->findByUuid($uuid);
            $userId = get_current_user_id();

            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $userId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $userId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $params = $request->get_json_params();
            $actorId = get_current_user_id();

            $policyData = $params['policy'];
            $policy = new ApprovalPolicy($policyData['mode'], $policyData['eligible_user_ids']);

            $command = new RequestDecisionCommand(
                $uuid,
                $params['decision_type'],
                $params['payload'],
                $policy,
                $actorId
            );

            $decisionUuid = $this->requestDecisionHandler->handle($command);

            return new WP_REST_Response(['uuid' => $decisionUuid], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function respondToDecision(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');

            // Security: Check participation via decision
            $decision = $this->decisionRepository->findByUuid($uuid);
            if (!$decision) {
                return new WP_REST_Response(['error' => 'Decision not found', 'code' => 'DECISION_NOT_FOUND'], 404);
            }

            $conversation = $this->conversationRepository->findById($decision->conversationId());
            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $userId = get_current_user_id();
            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $userId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $userId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Decision not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $params = $request->get_json_params();
            $actorId = get_current_user_id();

            $command = new RespondToDecisionCommand(
                $uuid,
                $params['response'],
                $params['comment'] ?? null,
                $actorId
            );

            $this->respondToDecisionHandler->handle($command);

            return new WP_REST_Response(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function resolveConversation(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');

            // Security: Check participation or context access
            $conversation = $this->conversationRepository->findByUuid($uuid);
            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $userId = get_current_user_id();
            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $userId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $userId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $actorId = get_current_user_id();

            $command = new ResolveConversationCommand($uuid, $actorId);
            $this->resolveConversationHandler->handle($command);

            return new WP_REST_Response(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function reopenConversation(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $uuid = $request->get_param('uuid');

            // Security: Check participation or context access
            $conversation = $this->conversationRepository->findByUuid($uuid);
            if (!$conversation) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $userId = get_current_user_id();
            $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $userId);
            $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $userId);

            if (!$hasAccess && !$isParticipant) {
                return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_FORBIDDEN'], 404);
            }

            $actorId = get_current_user_id();

            $command = new ReopenConversationCommand($uuid, $actorId);
            $this->reopenConversationHandler->handle($command);

            return new WP_REST_Response(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function getSummary(WP_REST_Request $request): WP_REST_Response
    {
        $contextType = $request->get_param('context_type');
        $contextIdsParam = $request->get_param('context_ids');

        if (!$contextType || !$contextIdsParam) {
            return new WP_REST_Response(['error' => 'context_type and context_ids are required'], 400);
        }

        $contextIds = array_filter(array_map('trim', explode(',', $contextIdsParam)));

        if (empty($contextIds)) {
            return new WP_REST_Response([], 200);
        }

        $userId = get_current_user_id();
        $summary = $this->conversationRepository->getSummaryForContexts($contextType, $contextIds, $userId);

        return new WP_REST_Response($summary, 200);
    }

    public function getConversation(WP_REST_Request $request): WP_REST_Response
    {
        $contextType = $request->get_param('context_type');
        $contextId = $request->get_param('context_id');
        $contextVersion = $request->get_param('context_version');
        $subjectKey = $request->get_param('subject_key');
        $uuid = $request->get_param('uuid');

        if ($uuid) {
            $conversation = $this->conversationRepository->findByUuid($uuid);
        } elseif ($contextType && $contextId) {
            $conversation = $this->conversationRepository->findByContext($contextType, $contextId, $contextVersion, $subjectKey);
        } else {
            return new WP_REST_Response(['error' => 'Missing context or uuid'], 400);
        }

        if (!$conversation) {
            return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
        }

        // Security: Check participation or context access
        $userId = get_current_user_id();
        $hasAccess = $this->conversationAccessControl->check($conversation->contextType(), $conversation->contextId(), $userId);
        $isParticipant = $this->conversationRepository->isParticipant((int)$conversation->id(), $userId);

        if (!$hasAccess && !$isParticipant) {
            return new WP_REST_Response(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
        }

        // We need to return full conversation data including events and decisions
        // This likely requires more queries or a "View" model.
        // For now, let's just return basic info + decisions.
        
        $limit = (int)($request->get_param('limit') ?? 50);
        $beforeEventId = $request->get_param('before_event_id') ? (int)$request->get_param('before_event_id') : null;

        $decisions = $this->decisionRepository->findByConversationId((int)$conversation->id());
        $timeline = $this->conversationRepository->getTimelineData((int)$conversation->id(), $limit, $beforeEventId);
        $participants = $this->conversationRepository->getParticipants((int)$conversation->id());
        
        $data = [
            'uuid' => $conversation->uuid(),
            'context_type' => $conversation->contextType(),
            'context_id' => $conversation->contextId(),
            'subject' => $conversation->subject(),
            'state' => $conversation->state(),
            'created_at' => $conversation->createdAt()->format('c'),
            'participants' => $participants,
            'decisions' => array_map(function($d) {
                return [
                    'uuid' => $d->uuid(),
                    'decision_type' => $d->decisionType(),
                    'state' => $d->state(),
                    'payload' => $d->payload(),
                    'outcome' => $d->outcome(),
                    'requested_at' => $d->requestedAt()->format('c'),
                    'finalized_at' => $d->finalizedAt() ? $d->finalizedAt()->format('c') : null,
                ];
            }, $decisions),
            'timeline' => $timeline,
        ];

        return new WP_REST_Response($data, 200);
    }
}
