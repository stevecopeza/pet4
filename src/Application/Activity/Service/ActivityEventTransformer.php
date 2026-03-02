<?php

declare(strict_types=1);

namespace Pet\Application\Activity\Service;

use Pet\Application\Activity\Dto\ActivityEvent;
use Pet\Domain\Feed\Entity\FeedEvent;

final class ActivityEventTransformer
{
    public function fromFeedEvent(FeedEvent $event): ActivityEvent
    {
        $metadata = $event->getMetadata();

        $actorType = isset($metadata['actor_type']) && is_string($metadata['actor_type']) ? $metadata['actor_type'] : 'system';
        $actorId = isset($metadata['actor_id']) && is_string($metadata['actor_id']) ? $metadata['actor_id'] : null;
        $actorName = isset($metadata['actor_name']) && is_string($metadata['actor_name']) ? $metadata['actor_name'] : null;
        $actorDisplayName = $actorName && $actorName !== '' ? $actorName : 'System';
        $actorAvatarUrl = isset($metadata['actor_avatar_url']) && is_string($metadata['actor_avatar_url']) ? $metadata['actor_avatar_url'] : null;

        $eventType = $this->mapEventType($event->getEventType());
        $severity = $this->mapSeverity($eventType, $event->getClassification());

        $referenceType = $this->mapReferenceType($eventType, $event->getSourceEngine(), $metadata);
        $referenceId = isset($metadata['context_id']) ? '#' . $metadata['context_id'] : '#' . $event->getSourceEntityId();
        $referenceUrl = isset($metadata['reference_url']) && is_string($metadata['reference_url']) ? $metadata['reference_url'] : null;

        $customerId = isset($metadata['customer_id']) && is_string($metadata['customer_id']) ? $metadata['customer_id'] : null;
        $customerName = isset($metadata['customer_name']) && is_string($metadata['customer_name']) ? $metadata['customer_name'] : null;
        $companyLogoUrl = isset($metadata['company_logo_url']) && is_string($metadata['company_logo_url']) ? $metadata['company_logo_url'] : null;

        $headline = $event->getTitle();
        $subline = $event->getSummary() !== '' ? $event->getSummary() : null;

        $tags = $this->buildTags($eventType, $metadata);

        $sla = null;
        if ($referenceType === 'ticket' && isset($metadata['sla']) && is_array($metadata['sla'])) {
            $slaMeta = $metadata['sla'];
            $sla = [
                'clock_state' => isset($slaMeta['clock_state']) && is_string($slaMeta['clock_state']) ? $slaMeta['clock_state'] : 'active',
                'target_at' => isset($slaMeta['target_at']) && is_string($slaMeta['target_at']) ? $slaMeta['target_at'] : null,
                'breach_at' => isset($slaMeta['breach_at']) && is_string($slaMeta['breach_at']) ? $slaMeta['breach_at'] : null,
                'seconds_remaining' => isset($slaMeta['seconds_remaining']) && is_int($slaMeta['seconds_remaining']) ? $slaMeta['seconds_remaining'] : null,
                'kind' => isset($slaMeta['kind']) && is_string($slaMeta['kind']) ? $slaMeta['kind'] : null,
                'policy_name' => isset($slaMeta['policy_name']) && is_string($slaMeta['policy_name']) ? $slaMeta['policy_name'] : null,
            ];
        }

        $occurredAt = $event->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'))->format('c');

        $meta = $metadata;

        unset(
            $meta['actor_type'],
            $meta['actor_id'],
            $meta['actor_name'],
            $meta['actor_avatar_url'],
            $meta['reference_url'],
            $meta['customer_id'],
            $meta['customer_name'],
            $meta['company_logo_url'],
            $meta['sla']
        );

        return new ActivityEvent(
            $event->getId(),
            $occurredAt,
            $actorType,
            $actorId,
            $actorDisplayName,
            $actorAvatarUrl,
            $eventType,
            $severity,
            $referenceType,
            $referenceId,
            $referenceUrl,
            $customerId,
            $customerName,
            $companyLogoUrl,
            $headline,
            $subline,
            $tags,
            $sla,
            $meta
        );
    }

    private function mapEventType(string $domainEventType): string
    {
        $map = [
            'support.ticket_warning' => 'SLA_RISK_DETECTED',
            'support.ticket_breached' => 'SLA_BREACH_RECORDED',
            'support.escalation_triggered' => 'ESCALATION_TRIGGERED',
            'commercial.quote_accepted' => 'QUOTE_ACCEPTED',
            'commercial.change_order_approved' => 'CHANGE_ORDER_APPROVED',
            'delivery.milestone_completed' => 'MILESTONE_COMPLETED',
            'support.ticket_created' => 'TICKET_CREATED',
            'conversation.message_posted' => 'CONVERSATION_MESSAGE',
            'conversation.decision_requested' => 'DECISION_REQUESTED',
            'conversation.decision_recorded' => 'DECISION_RESPONDED',
        ];

        if (isset($map[$domainEventType])) {
            return $map[$domainEventType];
        }

        return strtoupper(str_replace('.', '_', $domainEventType));
    }

    private function mapSeverity(string $eventType, string $classification): string
    {
        $matrix = [
            'SLA_BREACH_RECORDED' => 'breach',
            'SLA_RISK_DETECTED' => 'risk',
            'ESCALATION_TRIGGERED' => 'attention',
            'QUOTE_ACCEPTED' => 'info',
            'CHANGE_ORDER_APPROVED' => 'commercial',
            'TIMESHEET_SUBMITTED' => 'info',
            'TIME_ENTRY_LOGGED' => 'info',
            'MILESTONE_COMPLETED' => 'info',
            'TICKET_STATUS_CHANGED' => 'info',
            'TICKET_COMMENT_ADDED' => 'info',
            'ANNOUNCEMENT_PUBLISHED' => 'info',
            'ANNOUNCEMENT_ACKNOWLEDGED' => 'info',
            'CONVERSATION_MESSAGE' => 'info',
            'DECISION_REQUESTED' => 'attention',
            'DECISION_RESPONDED' => 'info',
        ];

        if (isset($matrix[$eventType])) {
            return $matrix[$eventType];
        }

        if ($classification === 'critical') {
            return 'breach';
        }

        if ($classification === 'strategic') {
            return 'commercial';
        }

        return 'info';
    }

    private function mapReferenceType(string $eventType, string $sourceEngine, array $metadata = []): string
    {
        // First check metadata for explicit context type
        if (isset($metadata['context_type']) && is_string($metadata['context_type'])) {
            $contextType = $metadata['context_type'];
            if ($contextType === 'ticket') {
                return 'ticket';
            }
            if ($contextType === 'project') {
                return 'project';
            }
            if ($contextType === 'quote') {
                return 'quote';
            }
            if ($contextType === 'knowledge_article') {
                return 'knowledge';
            }
            if ($contextType === 'milestone') {
                return 'milestone';
            }
        }

        if (str_starts_with($eventType, 'TICKET_') || str_starts_with($eventType, 'SLA_') || $eventType === 'ESCALATION_TRIGGERED') {
            return 'ticket';
        }

        if ($eventType === 'MILESTONE_COMPLETED' || $eventType === 'MILESTONE_PLANNED') {
            return 'milestone';
        }

        if ($eventType === 'TASK_CREATED' || $eventType === 'TASK_STATUS_CHANGED') {
            return 'task';
        }

        if ($eventType === 'PROJECT_CREATED') {
            return 'project';
        }

        if ($eventType === 'QUOTE_SENT' || $eventType === 'QUOTE_ACCEPTED') {
            return 'quote';
        }

        if ($eventType === 'CHANGE_ORDER_SUBMITTED' || $eventType === 'CHANGE_ORDER_APPROVED') {
            return 'change_order';
        }

        if ($eventType === 'COMMERCIAL_ADJUSTMENT_CREATED' || $eventType === 'COMMERCIAL_ADJUSTMENT_APPROVED') {
            return 'commercial_adjustment';
        }

        if ($eventType === 'TIME_ENTRY_LOGGED') {
            return 'time_entry';
        }

        if ($eventType === 'TIMESHEET_SUBMITTED') {
            return 'timesheet';
        }

        if ($eventType === 'ANNOUNCEMENT_PUBLISHED' || $eventType === 'ANNOUNCEMENT_ACKNOWLEDGED') {
            return 'announcement';
        }

        if ($sourceEngine === 'support') {
            return 'ticket';
        }

        if ($sourceEngine === 'delivery') {
            return 'project';
        }

        if ($sourceEngine === 'commercial') {
            return 'quote';
        }

        if ($sourceEngine === 'conversation') {
             if (isset($metadata['context_type'])) {
                 if ($metadata['context_type'] === 'knowledge_article') {
                     return 'knowledge';
                 }
                 return $metadata['context_type'];
             }
             return 'conversation';
        }

        return 'knowledge';
    }

    private function buildTags(string $eventType, array $metadata): array
    {
        $defaultTags = [
            'SLA_BREACH_RECORDED' => 'SLA Breach',
            'SLA_RISK_DETECTED' => 'SLA Risk',
            'ESCALATION_TRIGGERED' => 'Escalation',
            'QUOTE_ACCEPTED' => 'Quote',
            'CHANGE_ORDER_APPROVED' => 'Commercial',
            'TIMESHEET_SUBMITTED' => 'Time',
            'TIME_ENTRY_LOGGED' => 'Time Entry',
            'MILESTONE_COMPLETED' => 'Milestone',
            'TICKET_STATUS_CHANGED' => 'Ticket',
            'TICKET_COMMENT_ADDED' => 'Comment',
            'ANNOUNCEMENT_PUBLISHED' => 'Announcement',
            'ANNOUNCEMENT_ACKNOWLEDGED' => 'Acknowledged',
            'CONVERSATION_MESSAGE' => 'Discussion',
            'DECISION_REQUESTED' => 'Decision',
            'DECISION_RESPONDED' => 'Decision',
        ];

        $tags = [];

        if (isset($defaultTags[$eventType])) {
            $tags[] = $defaultTags[$eventType];
        } else {
            $tags[] = 'Activity';
        }

        if (isset($metadata['tags']) && is_array($metadata['tags'])) {
            foreach ($metadata['tags'] as $tag) {
                if (is_string($tag) && $tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        return array_values(array_unique($tags));
    }
}

