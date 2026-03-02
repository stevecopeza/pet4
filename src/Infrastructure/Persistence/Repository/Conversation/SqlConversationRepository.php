<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository\Conversation;

use Pet\Domain\Conversation\Entity\Conversation;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Event\ConversationEvent;

use Pet\Domain\Conversation\Event\ParticipantAdded;
use Pet\Domain\Conversation\Event\ParticipantRemoved;
use Pet\Domain\Conversation\Event\ContactParticipantAdded;
use Pet\Domain\Conversation\Event\ContactParticipantRemoved;
use Pet\Domain\Conversation\Event\TeamParticipantAdded;
use Pet\Domain\Conversation\Event\TeamParticipantRemoved;

class SqlConversationRepository implements ConversationRepository
{
    public function save(Conversation $conversation): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_conversations';
        $eventsTable = $wpdb->prefix . 'pet_conversation_events';
        $participantsTable = $wpdb->prefix . 'pet_conversation_participants';

        $data = [
            'uuid' => $conversation->uuid(),
            'context_type' => $conversation->contextType(),
            'context_id' => $conversation->contextId(),
            'context_version' => $conversation->contextVersion(),
            'subject' => $conversation->subject(),
            'subject_key' => $conversation->subjectKey(),
            'state' => $conversation->state(),
            'created_at' => $conversation->createdAt()->format('Y-m-d H:i:s'),
        ];

        if ($conversation->id() === null) {
            $wpdb->insert($table, $data);
            $conversationId = $wpdb->insert_id;
            $this->setId($conversation, (int)$conversationId);
        } else {
            $conversationId = $conversation->id();
            $wpdb->update($table, ['state' => $conversation->state()], ['id' => $conversationId]);
        }

        foreach ($conversation->releaseEvents() as $event) {
            $wpdb->insert($eventsTable, [
                'conversation_id' => $conversationId,
                'event_type' => $event->eventType(),
                'payload' => json_encode($event->payload()),
                'occurred_at' => $event->occurredAt()->format('Y-m-d H:i:s'),
                'actor_id' => $event->actorId(),
            ]);

            // Project participants
            if ($event instanceof ParticipantAdded) {
                $payload = $event->payload();
                $wpdb->replace($participantsTable, [
                    'conversation_id' => $conversationId,
                    'user_id' => $payload['user_id'],
                    'added_at' => $event->occurredAt()->format('Y-m-d H:i:s'),
                ]);
            } elseif ($event instanceof ParticipantRemoved) {
                $payload = $event->payload();
                $wpdb->delete($participantsTable, [
                    'conversation_id' => $conversationId,
                    'user_id' => $payload['user_id'],
                ]);
            } elseif ($event instanceof ContactParticipantAdded) {
                $payload = $event->payload();
                $wpdb->replace($participantsTable, [
                    'conversation_id' => $conversationId,
                    'contact_id' => $payload['contact_id'],
                    'added_at' => $event->occurredAt()->format('Y-m-d H:i:s'),
                ]);
            } elseif ($event instanceof ContactParticipantRemoved) {
                $payload = $event->payload();
                $wpdb->delete($participantsTable, [
                    'conversation_id' => $conversationId,
                    'contact_id' => $payload['contact_id'],
                ]);
            } elseif ($event instanceof TeamParticipantAdded) {
                $payload = $event->payload();
                $wpdb->replace($participantsTable, [
                    'conversation_id' => $conversationId,
                    'team_id' => $payload['team_id'],
                    'added_at' => $event->occurredAt()->format('Y-m-d H:i:s'),
                ]);
            } elseif ($event instanceof TeamParticipantRemoved) {
                $payload = $event->payload();
                $wpdb->delete($participantsTable, [
                    'conversation_id' => $conversationId,
                    'team_id' => $payload['team_id'],
                ]);
            }
        }
    }

    public function findById(int $id): ?Conversation
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_conversations';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByUuid(string $uuid): ?Conversation
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_conversations';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE uuid = %s", $uuid));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByContext(string $contextType, string $contextId, ?string $contextVersion = null, ?string $subjectKey = null): ?Conversation
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_conversations';

        $where = ["context_type = %s", "context_id = %s"];
        $args = [$contextType, $contextId];

        if ($contextVersion !== null) {
            $where[] = "context_version = %s";
            $args[] = $contextVersion;
        }

        if ($subjectKey !== null) {
            $where[] = "subject_key = %s";
            $args[] = $subjectKey;
        }

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 1";

        $row = $wpdb->get_row($wpdb->prepare($sql, ...$args));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function markAsRead(int $conversationId, int $userId, int $lastEventId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_conversation_read_state';

        $wpdb->replace(
            $table,
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'last_seen_event_id' => $lastEventId,
            ],
            ['%d', '%d', '%d']
        );
    }

    public function getUnreadCounts(int $userId): array
    {
        global $wpdb;
        $conversations = $wpdb->prefix . 'pet_conversations';
        $participants = $wpdb->prefix . 'pet_conversation_participants';
        $readState = $wpdb->prefix . 'pet_conversation_read_state';
        $events = $wpdb->prefix . 'pet_conversation_events';
        $teamMembers = $wpdb->prefix . 'pet_team_members';
        $employees = $wpdb->prefix . 'pet_employees';

        // Count events that are newer than last_seen_event_id for conversations user participates in
        $sql = $wpdb->prepare("
            SELECT c.id as conversation_id, COUNT(DISTINCT e.id) as unread_count
            FROM $conversations c
            JOIN $participants p ON p.conversation_id = c.id
            LEFT JOIN $readState rs ON rs.conversation_id = c.id AND rs.user_id = %d
            JOIN $events e ON e.conversation_id = c.id
            WHERE e.id > COALESCE(rs.last_seen_event_id, 0)
            AND (
                p.user_id = %d
                OR p.team_id IN (
                    SELECT tm.team_id 
                    FROM $teamMembers tm
                    JOIN $employees emp ON tm.employee_id = emp.id
                    WHERE emp.wp_user_id = %d
                    AND tm.removed_at IS NULL
                )
            )
            GROUP BY c.id
        ", $userId, $userId, $userId);

        $results = $wpdb->get_results($sql);
        
        $counts = [];
        foreach ($results as $row) {
            $counts[(int)$row->conversation_id] = (int)$row->unread_count;
        }

        return $counts;
    }

    public function getTimelineData(int $conversationId, int $limit = 50, ?int $beforeEventId = null): array
    {
        global $wpdb;
        $events = $wpdb->prefix . 'pet_conversation_events';

        $sql = "SELECT * FROM $events WHERE conversation_id = %d";
        $args = [$conversationId];

        if ($beforeEventId !== null) {
            $sql .= " AND id < %d";
            $args[] = $beforeEventId;
        }

        // Recent-first strategy: Get newest events first
        $sql .= " ORDER BY occurred_at DESC, id DESC LIMIT %d";
        $args[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args));

        return array_map(function($row) {
            return [
                'id' => (int)$row->id,
                'type' => $row->event_type,
                'payload' => json_decode($row->payload, true),
                'occurred_at' => $row->occurred_at,
                'actor_id' => (int)$row->actor_id,
            ];
        }, $rows);
    }

    public function getParticipants(int $conversationId): array
    {
        global $wpdb;
        $participants = $wpdb->prefix . 'pet_conversation_participants';
        $employees = $wpdb->prefix . 'pet_employees';
        $contacts = $wpdb->prefix . 'pet_contacts';
        $teams = $wpdb->prefix . 'pet_teams';
        $users = $wpdb->prefix . 'users';

        $sql = $wpdb->prepare("
            SELECT 
                p.user_id, 
                p.contact_id, 
                p.team_id,
                p.added_at,
                u.display_name as user_display_name,
                c.first_name as contact_first_name, c.last_name as contact_last_name,
                t.name as team_name
            FROM $participants p
            LEFT JOIN $users u ON p.user_id = u.ID
            LEFT JOIN $contacts c ON p.contact_id = c.id
            LEFT JOIN $teams t ON p.team_id = t.id
            WHERE p.conversation_id = %d
        ", $conversationId);

        $rows = $wpdb->get_results($sql);

        return array_map(function($row) {
            if ($row->user_id) {
                return [
                    'type' => 'user',
                    'id' => (int)$row->user_id,
                    'name' => $row->user_display_name,
                    'added_at' => $row->added_at
                ];
            } elseif ($row->contact_id) {
                return [
                    'type' => 'contact',
                    'id' => (int)$row->contact_id,
                    'name' => trim(($row->contact_first_name ?? '') . ' ' . ($row->contact_last_name ?? '')),
                    'added_at' => $row->added_at
                ];
            } elseif ($row->team_id) {
                return [
                    'type' => 'team',
                    'id' => (int)$row->team_id,
                    'name' => $row->team_name,
                    'added_at' => $row->added_at
                ];
            }
            return null;
        }, $rows);
    }

    public function messageExistsInConversation(int $conversationId, int $messageId): bool
    {
        global $wpdb;
        $events = $wpdb->prefix . 'pet_conversation_events';
        // Check if event exists, belongs to conversation, and is a message
        // Event type is 'MessagePosted' (from MessagePosted::eventType())
        $query = $wpdb->prepare(
            "SELECT 1 FROM $events WHERE conversation_id = %d AND id = %d AND event_type = 'MessagePosted' LIMIT 1",
            $conversationId,
            $messageId
        );
        return (bool)$wpdb->get_var($query);
    }

    public function hasReaction(int $conversationId, int $messageId, int $actorId, string $type): bool
    {
        global $wpdb;
        $events = $wpdb->prefix . 'pet_conversation_events';
        
        // Look for latest reaction event for this user/message/type
        // Event types are 'ReactionAdded' and 'ReactionRemoved'
        $likePattern = '%"message_id":' . $messageId . '%"reaction_type":"' . $wpdb->esc_like($type) . '"%';
        
        $query = $wpdb->prepare(
            "SELECT event_type FROM $events 
             WHERE conversation_id = %d 
             AND actor_id = %d 
             AND event_type IN ('ReactionAdded', 'ReactionRemoved')
             AND payload LIKE %s
             ORDER BY id DESC LIMIT 1",
            $conversationId,
            $actorId,
            $likePattern
        );
        
        $lastEventType = $wpdb->get_var($query);
        
        return $lastEventType === 'ReactionAdded';
    }

    public function findRecentByUserId(int $userId, int $limit = 10): array
    {
        global $wpdb;
        $conversations = $wpdb->prefix . 'pet_conversations';
        $participants = $wpdb->prefix . 'pet_conversation_participants';
        $teamMembers = $wpdb->prefix . 'pet_team_members';
        $employees = $wpdb->prefix . 'pet_employees';

        $sql = $wpdb->prepare("
            SELECT DISTINCT c.*
            FROM $conversations c
            JOIN $participants p ON p.conversation_id = c.id
            WHERE (
                p.user_id = %d
                OR p.team_id IN (
                    SELECT tm.team_id 
                    FROM $teamMembers tm
                    JOIN $employees emp ON tm.employee_id = emp.id
                    WHERE emp.wp_user_id = %d
                    AND tm.removed_at IS NULL
                )
            )
            ORDER BY c.id DESC
            LIMIT %d
        ", $userId, $userId, $limit);

        $rows = $wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function isParticipant(int $conversationId, int $userId): bool
    {
        global $wpdb;
        $participants = $wpdb->prefix . 'pet_conversation_participants';
        $teamMembers = $wpdb->prefix . 'pet_team_members';
        $employees = $wpdb->prefix . 'pet_employees';

        $sql = $wpdb->prepare(
            "SELECT 1 FROM $participants p
             WHERE conversation_id = %d 
             AND (
                p.user_id = %d
                OR p.team_id IN (
                    SELECT tm.team_id 
                    FROM $teamMembers tm
                    JOIN $employees emp ON tm.employee_id = emp.id
                    WHERE emp.wp_user_id = %d
                    AND tm.removed_at IS NULL
                )
             )
             LIMIT 1",
            $conversationId,
            $userId,
            $userId
        );

        return (bool)$wpdb->get_var($sql);
    }

    public function getInternalParticipantCount(int $conversationId): int
    {
        global $wpdb;
        $participants = $wpdb->prefix . 'pet_conversation_participants';
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $participants 
             WHERE conversation_id = %d 
             AND ((user_id IS NOT NULL AND user_id > 0) OR (team_id IS NOT NULL AND team_id > 0))",
            $conversationId
        );
        
        $count = (int)$wpdb->get_var($sql);
        return $count;
    }

    private function hydrate(object $row): Conversation
    {
        return new Conversation(
            (int)$row->id,
            $row->uuid,
            $row->context_type,
            $row->context_id,
            $row->subject,
            $row->subject_key,
            $row->state,
            new \DateTimeImmutable($row->created_at),
            $row->context_version ?? null
        );
    }

    private function setId(Conversation $conversation, int $id): void
    {
        $reflection = new \ReflectionClass($conversation);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($conversation, $id);
    }
}
