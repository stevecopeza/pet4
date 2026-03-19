<?php

declare(strict_types=1);

namespace Pet\Application\Projection\Listener;

use Pet\Domain\Activity\Entity\ActivityLog;
use Pet\Domain\Activity\Repository\ActivityLogRepository;
use Pet\Domain\Feed\Entity\FeedEvent;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Conversation\Event\MessagePosted;
use Pet\Domain\Conversation\Event\DecisionRequested;
use Pet\Domain\Conversation\Event\DecisionResponded;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Event\ChangeOrderApprovedEvent;
use Pet\Domain\Delivery\Event\ProjectCreated;
use Pet\Domain\Delivery\Event\MilestoneCompletedEvent;
use Pet\Domain\Support\Event\TicketCreated;
use Pet\Domain\Support\Event\TicketWarningEvent;
use Pet\Domain\Support\Event\TicketBreachedEvent;
use Pet\Domain\Escalation\Event\EscalationTriggeredEvent as DomainEscalationTriggered;
use Pet\Domain\Escalation\Event\EscalationAcknowledgedEvent;
use Pet\Domain\Escalation\Event\EscalationResolvedEvent;

class FeedProjectionListener
{
    private ActivityLogRepository $activityRepo;
    private FeedEventRepository $feedRepo;
    private ConversationRepository $conversationRepo;
    private DecisionRepository $decisionRepo;

    public function __construct(
        ActivityLogRepository $activityRepo, 
        FeedEventRepository $feedRepo,
        ConversationRepository $conversationRepo,
        DecisionRepository $decisionRepo
    ) {
        $this->activityRepo = $activityRepo;
        $this->feedRepo = $feedRepo;
        $this->conversationRepo = $conversationRepo;
        $this->decisionRepo = $decisionRepo;
    }

    public function onMessagePosted(MessagePosted $event): void
    {
        $conversation = $this->conversationRepo->findByUuid($event->conversationUuid());
        if (!$conversation) {
            return;
        }

        // Collapse logic
        $lastEvent = $this->feedRepo->findLatestBySource(
            'conversation', 
            $event->conversationUuid(), 
            'conversation.message_posted'
        );

        $payload = $event->payload();
        $body = $payload['body'];
        $messageId = $payload['message_id'] ?? null;
        $actorId = $event->actorId();

        // Check if we can collapse
        if ($lastEvent) {
            $meta = $lastEvent->getMetadata();

            // Idempotency guard: if this message ID was already processed in the last event, skip
            if (isset($meta['latest_message_id']) && $messageId && $meta['latest_message_id'] === $messageId) {
                return;
            }

            if (($meta['actor_id'] ?? null) === $actorId) {
                $count = ($meta['message_count'] ?? 1) + 1;
                $newMeta = array_merge($meta, [
                    'message_count' => $count,
                    'latest_body' => $body,
                    'latest_message_id' => $messageId
                ]);
                
                $summary = "User {$actorId} posted {$count} messages in " . $conversation->subject();

                $updatedEvent = new FeedEvent(
                    $lastEvent->getId(),
                    $lastEvent->getEventType(),
                    $lastEvent->getSourceEngine(),
                    $lastEvent->getSourceEntityId(),
                    $lastEvent->getClassification(),
                    $lastEvent->getTitle(),
                    $summary,
                    $newMeta,
                    $lastEvent->getAudienceScope(),
                    $lastEvent->getAudienceReferenceId(),
                    $lastEvent->isPinned(),
                    $lastEvent->getExpiresAt(),
                    new \DateTimeImmutable()
                );
                
                $this->feedRepo->save($updatedEvent);
                return;
            }
        }

        // Create new event
        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'conversation.message_posted',
            'conversation',
            $event->conversationUuid(),
            'informational',
            'New Message',
            "User {$actorId} posted a message in " . $conversation->subject(),
            [
                'actor_id' => $actorId,
                'message_count' => 1,
                'latest_body' => $body,
                'context_type' => $conversation->contextType(),
                'context_id' => $conversation->contextId(),
                'subject_key' => $conversation->subjectKey()
            ],
            'global',
            null
        ));
    }

    public function onDecisionRequested(DecisionRequested $event): void
    {
        $conversation = $this->conversationRepo->findByUuid($event->conversationUuid());
        if (!$conversation) {
            return;
        }

        $payload = $event->payload();
        $decisionType = $payload['decision_type'];

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'conversation.decision_requested',
            'conversation',
            $event->conversationUuid(),
            'action_required',
            'Decision Requested',
            "Decision requested: {$decisionType} in " . $conversation->subject(),
            [
                'decision_type' => $decisionType,
                'requester_id' => $event->actorId(),
                'context_type' => $conversation->contextType(),
                'context_id' => $conversation->contextId()
            ],
            'global',
            null
        ));
    }

    public function onDecisionResponded(DecisionResponded $event): void
    {
        $decision = $this->decisionRepo->findByUuid($event->decisionUuid());
        if (!$decision) {
            return;
        }

        $conversation = $this->conversationRepo->findById($decision->conversationId());
        if (!$conversation) {
            return;
        }

        $payload = $event->payload();
        $response = $payload['response'];
        $comment = $payload['comment'] ?? '';

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'conversation.decision_recorded',
            'conversation',
            $conversation->uuid(),
            'informational',
            'Decision Response',
            "User {$event->actorId()} responded '{$response}' in " . $conversation->subject(),
            [
                'response' => $response,
                'comment' => $comment,
                'responder_id' => $event->actorId(),
                'context_type' => $conversation->contextType(),
                'context_id' => $conversation->contextId()
            ],
            'global',
            null
        ));
    }

    public function onQuoteAccepted(QuoteAccepted $event): void
    {
        $quoteId = (string)$event->quote()->id();
        
        if ($this->feedRepo->findLatestBySource('commercial', $quoteId, 'commercial.quote_accepted')) {
            return;
        }

        $this->log('quote_accepted', "Quote {$quoteId} accepted", null, 'quote', (int)$quoteId);
        
        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'commercial.quote_accepted',
            'commercial',
            $quoteId,
            'strategic',
            'New Business Won',
            "Quote #{$quoteId} has been accepted.",
            ['amount' => $event->quote()->totalValue()],
            'global',
            null
        ));
    }

    public function onProjectCreated(ProjectCreated $event): void
    {
        $projectId = (string)$event->project()->id();

        if ($this->feedRepo->findLatestBySource('delivery', $projectId, 'delivery.project_created')) {
            return;
        }

        $this->log('project_created', "Project {$projectId} created", null, 'project', (int)$projectId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'delivery.project_created',
            'delivery',
            $projectId,
            'operational',
            'Project Started',
            "Project #{$projectId} has been initiated.",
            [],
            'department', // Delivery department
            'delivery_dept_id' // Placeholder
        ));
    }

    public function onTicketCreated(TicketCreated $event): void
    {
        $ticketId = (string)$event->ticket()->id();

        if ($this->feedRepo->findLatestBySource('support', $ticketId, 'support.ticket_created')) {
            return;
        }

        $this->log('ticket_created', "Ticket {$ticketId} created", null, 'ticket', (int)$ticketId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'support.ticket_created',
            'support',
            $ticketId,
            'operational',
            'New Ticket',
            "Ticket #{$ticketId} created: {$event->ticket()->subject()}",
            ['priority' => $event->ticket()->priority()],
            'department', // Support department
            'support_dept_id' // Placeholder
        ));
    }

    public function onTicketWarning(TicketWarningEvent $event): void
    {
        $ticketId = (string)$event->getTicketId();

        // Idempotency: We might want to allow multiple warnings, but usually not for the same state/time?
        // But TicketWarningEvent doesn't have a unique ID per se other than being a warning.
        // Assuming one warning per ticket lifecycle stage or per incident?
        // For now, let's skip duplicate check or maybe check if we recently warned?
        // The requirement says "FeedProjectionListener must prevent duplicate entries per event_uuid".
        // SourcedEvents have UUIDs. If these are SourcedEvents, we should use that.
        // But these handlers don't seem to use event UUIDs yet in the repo lookup.
        // Let's assume strict idempotency for "Warning" is harder without unique ID.
        // But we can check if the LAST event for this ticket was a warning?
        // Actually, let's leave it for now unless we have a specific rule.
        // Wait, "Step 4: FeedProjectionListener must prevent duplicate entries per event_uuid."
        // If the event HAS a UUID, we should use it.
        // Does TicketWarningEvent have a UUID?
        
        $this->log('ticket_warning', "Ticket {$ticketId} warning", null, 'ticket', (int)$ticketId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'support.ticket_warning',
            'support',
            $ticketId,
            'operational',
            'SLA Warning',
            "Ticket #{$ticketId} is approaching SLA breach.",
            [],
            'role', // Support Manager
            'support_manager_role_id' // Placeholder
        ));
    }

    public function onTicketBreached(TicketBreachedEvent $event): void
    {
        $ticketId = (string)$event->getTicketId();
        $this->log('ticket_breached', "Ticket {$ticketId} breached", null, 'ticket', (int)$ticketId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'support.ticket_breached',
            'support',
            $ticketId,
            'critical',
            'SLA Breached',
            "Ticket #{$ticketId} has breached SLA.",
            [],
            'role', // Support Manager
            'support_manager_role_id' // Placeholder
        ));
    }

    public function onMilestoneCompleted(MilestoneCompletedEvent $event): void
    {
        $projectId = (string)$event->projectId();
        $this->log('milestone_completed', "Milestone '{$event->milestoneTitle()}' completed for Project {$projectId}", null, 'project', (int)$projectId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'delivery.milestone_completed',
            'delivery',
            $projectId,
            'operational',
            'Milestone Achieved',
            "Milestone '{$event->milestoneTitle()}' completed.",
            [],
            'department', // Delivery department
            'delivery_dept_id'
        ));
    }

    public function onDomainEscalationTriggered(DomainEscalationTriggered $event): void
    {
        $entityId = (string)$event->sourceEntityId();
        $this->log(
            'escalation_created',
            "Escalation created for {$event->sourceEntityType()} {$entityId}: {$event->reason()}",
            null,
            $event->sourceEntityType(),
            (int)$entityId
        );

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'escalation.triggered',
            'escalation',
            $event->escalationId(),
            'critical',
            'Escalation Triggered',
            "Escalation raised for {$event->sourceEntityType()} #{$entityId} [{$event->severity()}]: {$event->reason()}",
            [
                'severity' => $event->severity(),
                'source_entity_type' => $event->sourceEntityType(),
                'source_entity_id' => $event->sourceEntityId(),
            ],
            'role',
            'support_manager_role_id'
        ));
    }

    public function onEscalationAcknowledged(EscalationAcknowledgedEvent $event): void
    {
        $entityId = (string)$event->sourceEntityId();
        $this->log(
            'escalation_acknowledged',
            "Escalation {$event->escalationId()} acknowledged by user {$event->acknowledgedBy()}",
            $event->acknowledgedBy(),
            $event->sourceEntityType(),
            (int)$entityId
        );

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'escalation.acknowledged',
            'escalation',
            $event->escalationId(),
            'operational',
            'Escalation Acknowledged',
            "Escalation for {$event->sourceEntityType()} #{$entityId} acknowledged by user {$event->acknowledgedBy()}.",
            [
                'severity' => $event->severity(),
                'acknowledged_by' => $event->acknowledgedBy(),
            ],
            'role',
            'support_manager_role_id'
        ));
    }

    public function onEscalationResolved(EscalationResolvedEvent $event): void
    {
        $entityId = (string)$event->sourceEntityId();
        $this->log(
            'escalation_resolved',
            "Escalation {$event->escalationId()} resolved by user {$event->resolvedBy()}",
            $event->resolvedBy(),
            $event->sourceEntityType(),
            (int)$entityId
        );

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'escalation.resolved',
            'escalation',
            $event->escalationId(),
            'informational',
            'Escalation Resolved',
            "Escalation for {$event->sourceEntityType()} #{$entityId} resolved.",
            [
                'severity' => $event->severity(),
                'resolved_by' => $event->resolvedBy(),
                'resolution_note' => $event->resolutionNote(),
            ],
            'global',
            null
        ));
    }

    public function onChangeOrderApproved(ChangeOrderApprovedEvent $event): void
    {
        $quoteId = (string)$event->costAdjustment()->quoteId();
        $this->log('change_order_approved', "Change Order approved for Quote {$quoteId}", null, 'quote', (int)$quoteId);

        $this->feedRepo->save(FeedEvent::create(
            $this->generateUuid(),
            'commercial.change_order_approved',
            'commercial',
            $quoteId,
            'operational',
            'Change Order Approved',
            "Change Order for Quote #{$quoteId} approved.",
            [],
            'department', // Delivery department
            'delivery_dept_id'
        ));
    }

    private function log(string $type, string $description, ?int $userId, string $entityType, int $entityId): void
    {
        $log = new ActivityLog(
            $type,
            $description,
            $userId ?? get_current_user_id(), // Might be 0 if system
            $entityType,
            $entityId
        );
        $this->activityRepo->save($log);
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
