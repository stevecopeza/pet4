import React, { useEffect, useState, useRef } from 'react';
import { Conversation, Decision } from '../types';

interface ConversationPanelProps {
  uuid?: string;
  contextType?: string;
  contextId?: string;
  contextVersion?: string;
  defaultSubject?: string;
  subjectKey?: string;
}

interface PetSettings {
  apiUrl: string;
  nonce: string;
  currentUserId: number;
}

const getSettings = (): PetSettings => (window as any).petSettings;

interface ProcessedMessage {
  id: number;
  kind: 'message';
  occurred_at: string;
  payload: {
    body: string;
    mentions: string[];
    attachments: any[];
    reply_to_message_id?: number | null;
  };
  actor_id: number;
  reactions: Record<string, number[]>; // type -> actorIds
  replyTo?: ProcessedMessage; // Resolved parent message
}

type TimelineItemType = 
  | ProcessedMessage 
  | { id: string; kind: 'event'; occurred_at: string; type: string; payload: any; actor_id: number }
  | { id: string; kind: 'decision_req'; occurred_at: string; payload: Decision }
  | { id: string; kind: 'decision_res'; occurred_at: string; payload: Decision };

const ConversationPanel: React.FC<ConversationPanelProps> = ({ 
  uuid,
  contextType, 
  contextId, 
  contextVersion,
  defaultSubject, 
  subjectKey 
}) => {
  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [timelineItems, setTimelineItems] = useState<TimelineItemType[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [newMessage, setNewMessage] = useState('');
  const [isPosting, setIsPosting] = useState(false);
  const [replyToId, setReplyToId] = useState<number | null>(null);
  const [hasMore, setHasMore] = useState(false);
  
  const inputRef = useRef<HTMLInputElement>(null);
  const timelineRef = useRef<HTMLDivElement>(null);

  const fetchConversation = async (loadMore = false) => {
    try {
      if (loadMore) {
        setLoadingMore(true);
      } else {
        setLoading(true);
      }

      let url = `${getSettings().apiUrl}/conversations?limit=20`;
      
      if (uuid) {
        url += `&uuid=${uuid}`;
      } else if (contextType && contextId) {
        url += `&context_type=${contextType}&context_id=${contextId}`;
        if (contextVersion) url += `&context_version=${contextVersion}`;
        if (subjectKey) url += `&subject_key=${subjectKey}`;
      } else {
         // Should not happen if props are correct, but handle safely
         setLoading(false);
         return;
      }

      // If loading more, use the ID of the oldest event we have
      if (loadMore && conversation && conversation.timeline.length > 0) {
        // Find oldest event ID. Events are sorted by ID usually, but let's be safe.
        // The API returns newest first.
        // Our local state might have aggregated timeline.
        // We should use conversation.timeline which is raw events.
        // Sort by ID to find oldest.
        const sortedEvents = [...conversation.timeline].sort((a, b) => a.id - b.id);
        const oldestId = sortedEvents[0].id;
        url += `&before_event_id=${oldestId}`;
      }

      const response = await fetch(url, {
        headers: {
          'X-WP-Nonce': getSettings().nonce,
        },
      });

      if (response.status === 404) {
        if (!loadMore) setConversation(null);
        setLoading(false);
        setLoadingMore(false);
        return;
      }

      if (!response.ok) {
        throw new Error('Failed to fetch conversation');
      }

      const data = await response.json();
      
      if (loadMore) {
        // Append new events to existing timeline
        // data.timeline contains OLDER events.
        if (data.timeline.length === 0) {
            setHasMore(false);
        } else {
            setConversation(prev => {
                if (!prev) return data;
                return {
                    ...prev,
                    timeline: [...prev.timeline, ...data.timeline] // We just dump them in, we'll sort later
                };
            });
            // If we got full page, assume more
            setHasMore(data.timeline.length >= 20);
        }
      } else {
        setConversation(data);
        setHasMore(data.timeline.length >= 20);
        
        // Mark as read (using newest event ID)
        if (data.timeline && data.timeline.length > 0) {
          const newestEvent = [...data.timeline].sort((a: any, b: any) => b.id - a.id)[0];
          markAsRead(data.uuid, newestEvent.id);
        }
      }
      
      setError(null);

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
      setLoadingMore(false);
    }
  };

  const markAsRead = async (uuid: string, lastEventId: number) => {
    try {
      await fetch(`${getSettings().apiUrl}/conversations/${uuid}/read`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': getSettings().nonce,
        },
        body: JSON.stringify({ last_seen_event_id: lastEventId }),
      });
    } catch (e) {
      console.error('Failed to mark as read', e);
    }
  };

  useEffect(() => {
    fetchConversation();
  }, [uuid, contextType, contextId, contextVersion, subjectKey]);

  // Process timeline whenever conversation changes
  useEffect(() => {
    if (!conversation) {
        setTimelineItems([]);
        return;
    }
    setTimelineItems(processTimeline(conversation));
  }, [conversation]);

  const processTimeline = (conv: Conversation): TimelineItemType[] => {
    const events = [...conv.timeline].sort((a, b) => a.id - b.id); // Sort by ID (chronological)
    
    const messages: Record<number, ProcessedMessage> = {};
    const items: TimelineItemType[] = [];

    // First pass: Create message objects and handle reactions
    events.forEach(e => {
        if (e.type === 'MessagePosted') {
            const msg: ProcessedMessage = {
                id: e.id,
                kind: 'message',
                occurred_at: e.occurred_at,
                payload: e.payload,
                actor_id: e.actor_id,
                reactions: {},
            };
            messages[e.id] = msg;
            items.push(msg);
        } else if (e.type === 'ReactionAdded') {
            const msgId = e.payload.message_id;
            const type = e.payload.reaction_type;
            const actorId = e.actor_id; // Event actor is the reactor
            
            if (messages[msgId]) {
                if (!messages[msgId].reactions[type]) messages[msgId].reactions[type] = [];
                if (!messages[msgId].reactions[type].includes(actorId)) {
                    messages[msgId].reactions[type].push(actorId);
                }
            }
        } else if (e.type === 'ReactionRemoved') {
            const msgId = e.payload.message_id;
            const type = e.payload.reaction_type;
            const actorId = e.actor_id;
            
            if (messages[msgId] && messages[msgId].reactions[type]) {
                messages[msgId].reactions[type] = messages[msgId].reactions[type].filter(id => id !== actorId);
                if (messages[msgId].reactions[type].length === 0) delete messages[msgId].reactions[type];
            }
        } else {
            // Other events
            items.push({
                id: `event-${e.id}`,
                kind: 'event',
                occurred_at: e.occurred_at,
                type: e.type,
                payload: e.payload,
                actor_id: e.actor_id
            });
        }
    });

    // Second pass: Resolve reply parents
    Object.values(messages).forEach(msg => {
        if (msg.payload.reply_to_message_id && messages[msg.payload.reply_to_message_id]) {
            msg.replyTo = messages[msg.payload.reply_to_message_id];
        }
    });

    // Add decisions
    const decisionReqs = conv.decisions.map(d => ({
        id: `decision-req-${d.uuid}`,
        kind: 'decision_req' as const,
        occurred_at: d.requested_at,
        payload: d
    }));

    const decisionRes = conv.decisions.filter(d => d.finalized_at).map(d => ({
        id: `decision-res-${d.uuid}`,
        kind: 'decision_res' as const,
        occurred_at: d.finalized_at!,
        payload: d
    }));

    // Merge and sort
    const finalItems = [...items, ...decisionReqs, ...decisionRes].sort((a, b) => 
        new Date(a.occurred_at).getTime() - new Date(b.occurred_at).getTime()
    );

    return finalItems;
  };

  const handleCreateConversation = async () => {
    try {
      setIsPosting(true);
      const response = await fetch(`${getSettings().apiUrl}/conversations`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': getSettings().nonce,
        },
        body: JSON.stringify({
          context_type: contextType,
          context_id: contextId,
          context_version: contextVersion,
          subject: defaultSubject,
          subject_key: subjectKey,
        }),
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.error || 'Failed to create conversation');
      }

      const data = await response.json();
      // After creating, post the message if there is one
      if (newMessage.trim()) {
        await postMessage(data.uuid, newMessage);
      } else {
        await fetchConversation();
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error creating conversation');
      setIsPosting(false);
    }
  };

  const postMessage = async (uuid: string, body: string) => {
    try {
      const payload: any = { body };
      if (replyToId) {
        payload.reply_to_message_id = replyToId;
      }

      const response = await fetch(`${getSettings().apiUrl}/conversations/${uuid}/messages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': getSettings().nonce,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.error || 'Failed to post message');
      }

      setNewMessage('');
      setReplyToId(null);
      await fetchConversation();
      
      // Scroll to bottom
      if (timelineRef.current) {
        setTimeout(() => {
            timelineRef.current!.scrollTop = timelineRef.current!.scrollHeight;
        }, 100);
      }

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error posting message');
    } finally {
      setIsPosting(false);
    }
  };

  const handleSend = async () => {
    if (!newMessage.trim()) return;

    if (!conversation) {
      await handleCreateConversation();
    } else {
      setIsPosting(true);
      await postMessage(conversation.uuid, newMessage);
    }
  };

  const handleDecisionResponse = async (uuid: string, response: string, comment: string) => {
    try {
      const res = await fetch(`${getSettings().apiUrl}/decisions/${uuid}/respond`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': getSettings().nonce,
        },
        body: JSON.stringify({ response, comment }),
      });

      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.error || 'Failed to respond');
      }

      await fetchConversation();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error responding');
    }
  };

  const handleReaction = async (messageId: number, type: string, isRemoval: boolean) => {
      if (!conversation) return;
      
      // Optimistic update could go here, but let's stick to refresh for safety
      try {
          const url = `${getSettings().apiUrl}/conversations/${conversation.uuid}/messages/${messageId}/reactions${isRemoval ? '/' + type : ''}`;
          const method = isRemoval ? 'DELETE' : 'POST';
          const body = isRemoval ? undefined : JSON.stringify({ reaction_type: type });
          
          const res = await fetch(url, {
              method,
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': getSettings().nonce,
              },
              body
          });
          
          if (!res.ok) throw new Error('Failed to update reaction');
          
          await fetchConversation();
      } catch (e) {
          console.error(e);
      }
  };

  const handleReplyClick = (messageId: number) => {
      setReplyToId(messageId);
      inputRef.current?.focus();
  };

  if (loading && !conversation) return <div>Loading conversation...</div>;

  const replyToMessage = replyToId && timelineItems.find(i => i.kind === 'message' && i.id === replyToId) as ProcessedMessage | undefined;

  return (
    <div className="pet-conversation-panel" style={{ border: '1px solid #ddd', padding: '15px', borderRadius: '4px', background: '#f9f9f9', display: 'flex', flexDirection: 'column', height: '600px' }}>
      <h3 style={{ marginTop: 0, flexShrink: 0 }}>Conversation: {conversation ? conversation.subject : defaultSubject}</h3>
      
      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}

      <div 
        ref={timelineRef}
        className="pet-timeline" 
        style={{ flex: 1, overflowY: 'auto', marginBottom: '15px', display: 'flex', flexDirection: 'column', gap: '10px', paddingRight: '5px' }}
      >
        {!conversation && <div style={{ color: '#666', fontStyle: 'italic' }}>No conversation started yet.</div>}
        
        {hasMore && (
            <button 
                onClick={() => fetchConversation(true)} 
                disabled={loadingMore}
                style={{ alignSelf: 'center', padding: '5px 10px', fontSize: '12px', cursor: 'pointer', marginBottom: '10px' }}
            >
                {loadingMore ? 'Loading...' : 'Load earlier messages'}
            </button>
        )}

        {timelineItems.map((item: any) => (
          <TimelineItem 
            key={item.id} 
            item={item} 
            onRespond={handleDecisionResponse}
            onReact={handleReaction}
            onReply={handleReplyClick}
            currentUserId={getSettings().currentUserId}
          />
        ))}
      </div>

      <div className="pet-compose" style={{ flexShrink: 0 }}>
        {replyToMessage && (
            <div style={{ background: '#f0f0f0', padding: '5px 10px', fontSize: '12px', borderLeft: '3px solid #007cba', marginBottom: '5px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span>Replying to User {replyToMessage.actor_id}: "{replyToMessage.payload.body.substring(0, 50)}..."</span>
                <button onClick={() => setReplyToId(null)} style={{ border: 'none', background: 'none', cursor: 'pointer', fontSize: '14px' }}>&times;</button>
            </div>
        )}
        <div style={{ display: 'flex', gap: '10px' }}>
            <input 
            ref={inputRef}
            type="text" 
            value={newMessage}
            onChange={(e) => setNewMessage(e.target.value)}
            placeholder="Type a message..."
            style={{ flex: 1, padding: '8px' }}
            disabled={isPosting}
            onKeyDown={(e) => e.key === 'Enter' && handleSend()}
            />
            <button 
            onClick={handleSend}
            disabled={isPosting || !newMessage.trim()}
            style={{ padding: '8px 16px', background: '#007cba', color: 'white', border: 'none', borderRadius: '3px', cursor: 'pointer' }}
            >
            {isPosting ? 'Sending...' : 'Send'}
            </button>
        </div>
      </div>
    </div>
  );
};

const TimelineItem: React.FC<{ 
  item: any; 
  onRespond: (uuid: string, response: string, comment: string) => void;
  onReact: (msgId: number, type: string, isRemoval: boolean) => void;
  onReply: (msgId: number) => void;
  currentUserId: number;
}> = ({ item, onRespond, onReact, onReply, currentUserId }) => {
  const style: React.CSSProperties = {
    padding: '10px',
    background: 'white',
    borderRadius: '4px',
    border: '1px solid #eee',
    fontSize: '13px',
    position: 'relative'
  };

  const REACTION_TYPES = ['👍', '👎', '❤️', '👀', '✅'];

  if (item.kind === 'message') {
    const msg = item as ProcessedMessage;
    const isMe = msg.actor_id === currentUserId;
    
    return (
      <div style={{ ...style, alignSelf: isMe ? 'flex-end' : 'flex-start', maxWidth: '85%', background: isMe ? '#eef' : 'white' }}>
        {msg.replyTo && (
            <div style={{ fontSize: '11px', color: '#666', borderLeft: '2px solid #ccc', paddingLeft: '5px', marginBottom: '5px' }}>
                Replying to User {msg.replyTo.actor_id}: {msg.replyTo.payload.body.substring(0, 30)}...
            </div>
        )}
        
        <div style={{ fontWeight: 'bold', marginBottom: '4px', display: 'flex', justifyContent: 'space-between' }}>
            <span>User {item.actor_id}</span>
            <span style={{ fontWeight: 'normal', color: '#999', fontSize: '11px', marginLeft: '10px' }}>
                {new Date(item.occurred_at).toLocaleString()}
            </span>
        </div>
        
        <div style={{ whiteSpace: 'pre-wrap' }}>{item.payload.body}</div>

        <div style={{ marginTop: '8px', display: 'flex', gap: '5px', flexWrap: 'wrap', alignItems: 'center' }}>
            {/* Reactions Display */}
            {Object.entries(msg.reactions || {}).map(([type, actors]) => {
                const hasReacted = actors.includes(currentUserId);
                return (
                    <button 
                        key={type}
                        onClick={() => onReact(msg.id, type, hasReacted)}
                        style={{ 
                            background: hasReacted ? '#dbeafe' : '#f3f4f6', 
                            border: hasReacted ? '1px solid #bfdbfe' : '1px solid #e5e7eb',
                            borderRadius: '12px',
                            padding: '2px 6px',
                            fontSize: '11px',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '3px'
                        }}
                        title={`Reacted by: ${actors.join(', ')}`}
                    >
                        <span>{type}</span>
                        <span style={{ fontWeight: 'bold' }}>{actors.length}</span>
                    </button>
                );
            })}

            {/* Add Reaction Button */}
            <div className="pet-reaction-picker" style={{ position: 'relative', display: 'inline-block' }}>
                <button 
                    className="pet-add-reaction-btn"
                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#999', fontSize: '14px' }}
                    title="Add reaction"
                    onClick={(e) => {
                        const picker = e.currentTarget.nextElementSibling as HTMLElement;
                        picker.style.display = picker.style.display === 'none' ? 'flex' : 'none';
                    }}
                >
                    ☺+
                </button>
                <div 
                    style={{ 
                        display: 'none', 
                        position: 'absolute', 
                        bottom: '100%', 
                        left: 0, 
                        background: 'white', 
                        border: '1px solid #ccc', 
                        borderRadius: '4px', 
                        padding: '5px', 
                        gap: '5px', 
                        boxShadow: '0 2px 5px rgba(0,0,0,0.1)',
                        zIndex: 10
                    }}
                    onMouseLeave={(e) => e.currentTarget.style.display = 'none'}
                >
                    {REACTION_TYPES.map(type => (
                        <button 
                            key={type}
                            onClick={(e) => {
                                onReact(msg.id, type, false);
                                e.currentTarget.parentElement!.style.display = 'none';
                            }}
                            style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: '16px' }}
                        >
                            {type}
                        </button>
                    ))}
                </div>
            </div>

            {/* Reply Button */}
            <button 
                onClick={() => onReply(msg.id)}
                style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#666', fontSize: '11px', marginLeft: 'auto' }}
            >
                Reply
            </button>
        </div>
      </div>
    );
  }

  if (item.kind === 'event') {
    return (
        <div style={{ ...style, color: '#666', textAlign: 'center', background: 'none', border: 'none' }}>
          <span style={{ fontSize: '11px' }}>
            [{item.type}] {new Date(item.occurred_at).toLocaleString()}
          </span>
        </div>
    );
  }

  if (item.kind === 'decision_req') {
      const decision = item.payload as Decision;
      return (
        <div style={{ ...style, borderLeft: '3px solid orange' }}>
            <div style={{ fontWeight: 'bold', color: 'orange' }}>Decision Requested: {decision.decision_type}</div>
            <div style={{ fontSize: '11px', color: '#999' }}>{new Date(item.occurred_at).toLocaleString()}</div>
            <div style={{ marginTop: '5px' }}>
                Status: <strong>{decision.state.toUpperCase()}</strong>
                {decision.state === 'pending' && (
                    <div style={{ marginTop: '10px', display: 'flex', gap: '5px' }}>
                        <button onClick={() => onRespond(decision.uuid, 'approved', '')} style={{ background: '#46b450', color: 'white', border: 'none', padding: '5px 10px', borderRadius: '3px', cursor: 'pointer' }}>Approve</button>
                        <button onClick={() => onRespond(decision.uuid, 'rejected', '')} style={{ background: '#dc3232', color: 'white', border: 'none', padding: '5px 10px', borderRadius: '3px', cursor: 'pointer' }}>Reject</button>
                    </div>
                )}
            </div>
        </div>
      );
  }

  if (item.kind === 'decision_res') {
      return (
        <div style={{ ...style, borderLeft: '3px solid green' }}>
            <div style={{ fontWeight: 'bold', color: 'green' }}>Decision Finalized</div>
            <div style={{ fontSize: '11px', color: '#999' }}>{new Date(item.occurred_at).toLocaleString()}</div>
            <div>Outcome: {item.payload.outcome}</div>
        </div>
      );
  }

  return null;
};

export default ConversationPanel;