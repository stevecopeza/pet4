<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository\Conversation;

use Pet\Domain\Conversation\Entity\Decision;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Conversation\ValueObject\ApprovalPolicy;

class SqlDecisionRepository implements DecisionRepository
{
    public function save(Decision $decision): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_decisions';
        $eventsTable = $wpdb->prefix . 'pet_decision_events';

        $data = [
            'uuid' => $decision->uuid(),
            'conversation_id' => $decision->conversationId(),
            'decision_type' => $decision->decisionType(),
            'state' => $decision->state(),
            'payload' => json_encode($decision->payload()),
            'policy_snapshot' => json_encode($decision->policy()->jsonSerialize()),
            'requested_at' => $decision->requestedAt()->format('Y-m-d H:i:s'),
            'requester_id' => $decision->requesterId(),
            'finalized_at' => $decision->finalizedAt() ? $decision->finalizedAt()->format('Y-m-d H:i:s') : null,
            'finalizer_id' => $decision->finalizerId(),
            'outcome' => $decision->outcome(),
            'outcome_comment' => $decision->outcomeComment(),
        ];

        if ($decision->id() === null) {
            $wpdb->insert($table, $data);
            $decisionId = $wpdb->insert_id;
            $this->setId($decision, (int)$decisionId);
        } else {
            $decisionId = $decision->id();
            $wpdb->update($table, [
                'state' => $decision->state(),
                'finalized_at' => $decision->finalizedAt() ? $decision->finalizedAt()->format('Y-m-d H:i:s') : null,
                'finalizer_id' => $decision->finalizerId(),
                'outcome' => $decision->outcome(),
                'outcome_comment' => $decision->outcomeComment(),
            ], ['id' => $decisionId]);
        }

        foreach ($decision->releaseEvents() as $event) {
            $wpdb->insert($eventsTable, [
                'decision_id' => $decisionId,
                'event_type' => $event->eventType(),
                'payload' => json_encode($event->payload()),
                'occurred_at' => $event->occurredAt()->format('Y-m-d H:i:s'),
                'actor_id' => $event->actorId(),
            ]);
        }
    }

    public function findById(int $id): ?Decision
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_decisions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByUuid(string $uuid): ?Decision
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_decisions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE uuid = %s", $uuid));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByConversationId(int $conversationId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_decisions';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE conversation_id = %d", $conversationId));

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByUuidForUpdate(string $uuid): ?Decision
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_decisions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE uuid = %s FOR UPDATE", $uuid));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findPendingByUserId(int $userId): array
    {
        global $wpdb;
        $decisionsTable = $wpdb->prefix . 'pet_decisions';
        $participantsTable = $wpdb->prefix . 'pet_conversation_participants';

        $sql = $wpdb->prepare("
            SELECT d.* 
            FROM $decisionsTable d
            JOIN $participantsTable p ON p.conversation_id = d.conversation_id
            WHERE d.state = 'pending' 
            AND p.user_id = %d
            ORDER BY d.requested_at ASC
        ", $userId);

        $rows = $wpdb->get_results($sql);

        $decisions = [];
        foreach ($rows as $row) {
            $decision = $this->hydrate($row);
            
            if (!$decision->policy()->isEligible($userId)) {
                continue;
            }

            if ($decision->hasUserResponded($userId)) {
                continue;
            }

            $decisions[] = $decision;
        }

        return $decisions;
    }

    private function hydrate(object $row): Decision
    {
        global $wpdb;
        $decision = new Decision(
            (int)$row->id,
            $row->uuid,
            (int)$row->conversation_id,
            $row->decision_type,
            $row->state,
            json_decode($row->payload, true),
            ApprovalPolicy::fromArray(json_decode($row->policy_snapshot, true)),
            new \DateTimeImmutable($row->requested_at),
            (int)$row->requester_id,
            $row->finalized_at ? new \DateTimeImmutable($row->finalized_at) : null,
            $row->finalizer_id ? (int)$row->finalizer_id : null,
            $row->outcome,
            $row->outcome_comment
        );

        // Hydrate existing responses
        $eventsTable = $wpdb->prefix . 'pet_decision_events';
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $eventsTable WHERE decision_id = %d AND event_type = 'DecisionResponded'",
            $row->id
        ));

        $responses = [];
        foreach ($events as $event) {
            $payload = json_decode($event->payload, true);
            $responses[(int)$event->actor_id] = $payload['response'];
        }

        $decision->setExistingResponses($responses);

        return $decision;
    }

    private function setId(Decision $decision, int $id): void
    {
        $reflection = new \ReflectionClass($decision);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($decision, $id);
    }
}
