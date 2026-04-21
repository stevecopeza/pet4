import React, { useCallback, useEffect, useRef, useState } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs    = () => ({ 'X-WP-Nonce': nonce() });
const jsonHdrs = () => ({ 'X-WP-Nonce': nonce(), 'Content-Type': 'application/json' });

/* ─── types ──────────────────────────────────────────────── */
interface Conversation {
  uuid: string;
  context_type: string;
  context_id: string;
  subject: string;
  state: string;
  created_at: string;
}

interface Message {
  id: number;
  body: string;
  author_id: number;
  author_name: string | null;
  created_at: string;
  reactions?: Record<string, number>;
}

interface Decision {
  uuid: string;
  decision_type: string;
  conversation_id: string;
  state: string;
  payload: Record<string, unknown>;
  requested_at: string;
  requester_id: number;
}

interface Props {
  activeUuid: string | null;
  onUnreadChange: (count: number) => void;
}

/* ─── helpers ────────────────────────────────────────────── */
function relativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'just now';
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  return `${Math.floor(h / 24)}d ago`;
}

function contextLabel(type: string): string {
  const map: Record<string, string> = { ticket: 'Ticket', project: 'Project', quote: 'Quote', lead: 'Lead' };
  return map[type] ?? type;
}

function contextColor(type: string): { bg: string; fg: string } {
  const map: Record<string, { bg: string; fg: string }> = {
    ticket:  { bg: '#eff6ff', fg: '#1d4ed8' },
    project: { bg: '#f0fdf4', fg: '#15803d' },
    quote:   { bg: '#faf5ff', fg: '#6d28d9' },
    lead:    { bg: '#fff7ed', fg: '#c2410c' },
  };
  return map[type] ?? { bg: '#f1f5f9', fg: '#475569' };
}

function initials(name: string | null): string {
  if (!name) return '?';
  const w = name.trim().split(/\s+/);
  return w.length >= 2 ? (w[0][0] + w[w.length - 1][0]).toUpperCase() : name.slice(0, 2).toUpperCase();
}

/* ─── thread panel ───────────────────────────────────────── */
const ThreadPanel: React.FC<{
  conv: Conversation;
  currentUserId: number;
  onBack: () => void;
  onResolved: () => void;
}> = ({ conv, currentUserId, onBack, onResolved }) => {
  const [messages, setMessages]   = useState<Message[]>([]);
  const [decisions, setDecisions] = useState<Decision[]>([]);
  const [loading, setLoading]     = useState(true);
  const [reply, setReply]         = useState('');
  const [sending, setSending]     = useState(false);
  const [actioning, setActioning] = useState(false);
  const [error, setError]         = useState<string | null>(null);
  const bottomRef = useRef<HTMLDivElement>(null);

  const loadThread = useCallback(async () => {
    setLoading(true);
    try {
      const [msgRes, decRes] = await Promise.all([
        fetch(`${apiUrl()}/conversations/${conv.uuid}/messages`, { headers: hdrs() }),
        fetch(`${apiUrl()}/decisions/pending`, { headers: hdrs() }),
      ]);
      if (msgRes.ok) setMessages(await msgRes.json());
      if (decRes.ok) {
        const all: Decision[] = await decRes.json();
        setDecisions(all.filter(d => d.conversation_id === conv.uuid));
      }
      // mark as read
      fetch(`${apiUrl()}/conversations/${conv.uuid}/read`, {
        method: 'POST', headers: jsonHdrs(),
        body: JSON.stringify({ last_seen_event_id: 0 }),
      }).catch(() => {});
    } catch { /* noop */ }
    setLoading(false);
  }, [conv.uuid]);

  useEffect(() => {
    loadThread();
  }, [loadThread]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const sendReply = async () => {
    const body = reply.trim();
    if (!body) return;
    setSending(true);
    setError(null);
    try {
      const res = await fetch(`${apiUrl()}/conversations/${conv.uuid}/messages`, {
        method: 'POST', headers: jsonHdrs(), body: JSON.stringify({ body }),
      });
      if (!res.ok) throw new Error(await res.text());
      setReply('');
      await loadThread();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Send failed');
    } finally {
      setSending(false);
    }
  };

  const resolveConv = async () => {
    setActioning(true);
    try {
      await fetch(`${apiUrl()}/conversations/${conv.uuid}/resolve`, { method: 'POST', headers: jsonHdrs() });
      onResolved();
    } catch { /* noop */ }
    setActioning(false);
  };

  const reopenConv = async () => {
    setActioning(true);
    try {
      await fetch(`${apiUrl()}/conversations/${conv.uuid}/reopen`, { method: 'POST', headers: jsonHdrs() });
      await loadThread();
    } catch { /* noop */ }
    setActioning(false);
  };

  const respondDecision = async (uuid: string, response: string) => {
    setActioning(true);
    try {
      await fetch(`${apiUrl()}/decisions/${uuid}/respond`, {
        method: 'POST', headers: jsonHdrs(), body: JSON.stringify({ response }),
      });
      await loadThread();
    } catch { /* noop */ }
    setActioning(false);
  };

  const isResolved = conv.state === 'resolved';

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      {/* thread header */}
      <div style={{ padding: '14px 20px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12, flexShrink: 0 }}>
        <div>
          <button onClick={onBack} style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 13, color: '#64748b', padding: 0, marginBottom: 6 }}>← Back</button>
          <div style={{ fontSize: 16, fontWeight: 700, color: '#0f172a' }}>{conv.subject || 'Conversation'}</div>
          <div style={{ display: 'flex', gap: 8, marginTop: 4, alignItems: 'center' }}>
            <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 8, ...contextColor(conv.context_type) }}>
              {contextLabel(conv.context_type)} #{conv.context_id}
            </span>
            <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 8, background: isResolved ? '#dcfce7' : '#dbeafe', color: isResolved ? '#15803d' : '#1d4ed8' }}>
              {conv.state}
            </span>
          </div>
        </div>
        <div>
          {!isResolved ? (
            <button onClick={resolveConv} disabled={actioning} style={{ padding: '7px 14px', background: 'none', border: '1px solid #cbd5e1', borderRadius: 8, fontSize: 13, fontWeight: 600, color: '#475569', cursor: actioning ? 'not-allowed' : 'pointer' }}>
              Resolve
            </button>
          ) : (
            <button onClick={reopenConv} disabled={actioning} style={{ padding: '7px 14px', background: 'none', border: '1px solid #cbd5e1', borderRadius: 8, fontSize: 13, fontWeight: 600, color: '#475569', cursor: actioning ? 'not-allowed' : 'pointer' }}>
              Reopen
            </button>
          )}
        </div>
      </div>

      {/* pending decisions */}
      {decisions.length > 0 && (
        <div style={{ padding: '10px 20px', background: '#fffbeb', borderBottom: '1px solid #fde68a', flexShrink: 0 }}>
          {decisions.map(d => (
            <div key={d.uuid} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
              <div>
                <div style={{ fontSize: 13, fontWeight: 600, color: '#92400e' }}>Decision required: {d.decision_type}</div>
                <div style={{ fontSize: 12, color: '#78350f' }}>Requested {relativeTime(d.requested_at)}</div>
              </div>
              <div style={{ display: 'flex', gap: 8 }}>
                <button onClick={() => respondDecision(d.uuid, 'approve')} disabled={actioning} style={{ padding: '6px 14px', background: '#16a34a', color: '#fff', border: 'none', borderRadius: 8, fontSize: 13, fontWeight: 600, cursor: 'pointer' }}>Approve</button>
                <button onClick={() => respondDecision(d.uuid, 'reject')} disabled={actioning} style={{ padding: '6px 14px', background: 'none', border: '1px solid #fca5a5', color: '#dc2626', borderRadius: 8, fontSize: 13, fontWeight: 600, cursor: 'pointer' }}>Reject</button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* messages */}
      <div style={{ flex: 1, overflowY: 'auto', padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 14 }}>
        {loading && <div style={{ textAlign: 'center', color: '#64748b', fontSize: 14 }}>Loading…</div>}
        {!loading && messages.length === 0 && (
          <div style={{ textAlign: 'center', color: '#94a3b8', fontSize: 14, padding: '40px 0' }}>No messages yet. Start the conversation below.</div>
        )}
        {messages.map(msg => {
          const isOwn = msg.author_id === currentUserId;
          return (
            <div key={msg.id} style={{ display: 'flex', gap: 10, justifyContent: isOwn ? 'flex-end' : 'flex-start' }}>
              {!isOwn && (
                <div style={{ width: 32, height: 32, borderRadius: '50%', background: '#e0e7ff', color: '#3730a3', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12, fontWeight: 700, flexShrink: 0 }}>
                  {initials(msg.author_name)}
                </div>
              )}
              <div style={{ maxWidth: '72%' }}>
                {!isOwn && (
                  <div style={{ fontSize: 11, color: '#64748b', marginBottom: 3, fontWeight: 600 }}>{msg.author_name ?? 'Unknown'}</div>
                )}
                <div style={{
                  background: isOwn ? '#2563eb' : '#f1f5f9',
                  color: isOwn ? '#fff' : '#1e293b',
                  borderRadius: isOwn ? '12px 12px 4px 12px' : '12px 12px 12px 4px',
                  padding: '10px 14px', fontSize: 14, lineHeight: 1.5,
                  whiteSpace: 'pre-wrap', wordBreak: 'break-word',
                }}>
                  {msg.body}
                </div>
                <div style={{ fontSize: 10, color: '#94a3b8', marginTop: 3, textAlign: isOwn ? 'right' : 'left' }}>
                  {relativeTime(msg.created_at)}
                </div>
              </div>
            </div>
          );
        })}
        <div ref={bottomRef} />
      </div>

      {/* error */}
      {error && (
        <div style={{ padding: '6px 20px', background: '#fef2f2', color: '#dc2626', fontSize: 13, flexShrink: 0 }}>{error}</div>
      )}

      {/* reply box */}
      {!isResolved && (
        <div style={{ padding: '12px 20px', borderTop: '1px solid #e2e8f0', display: 'flex', gap: 10, alignItems: 'flex-end', flexShrink: 0 }}>
          <textarea
            value={reply}
            onChange={e => setReply(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); } }}
            placeholder="Write a message… (Enter to send, Shift+Enter for new line)"
            rows={2}
            style={{ flex: 1, padding: '9px 12px', border: '1px solid #cbd5e1', borderRadius: 8, fontSize: 14, fontFamily: 'inherit', resize: 'none', outline: 'none', color: '#1e293b' }}
          />
          <button
            onClick={sendReply}
            disabled={sending || !reply.trim()}
            style={{ padding: '9px 18px', background: '#2563eb', color: '#fff', border: 'none', borderRadius: 8, fontSize: 14, fontWeight: 600, cursor: (sending || !reply.trim()) ? 'not-allowed' : 'pointer', opacity: (sending || !reply.trim()) ? 0.5 : 1, fontFamily: 'inherit', whiteSpace: 'nowrap' }}
          >
            {sending ? '…' : 'Send'}
          </button>
        </div>
      )}
    </div>
  );
};

/* ─── main page ──────────────────────────────────────────── */
const ConversationsPage: React.FC<Props> = ({ activeUuid, onUnreadChange }) => {
  const user = usePortalUser();
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [loading, setLoading]             = useState(true);
  const [error, setError]                 = useState<string | null>(null);
  const [selected, setSelected]           = useState<Conversation | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${apiUrl()}/conversations/me?limit=50`, { headers: hdrs() });
      if (!res.ok) throw new Error(`Failed to load conversations (${res.status})`);
      const data: Conversation[] = await res.json();
      setConversations(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  // If a UUID was passed via hash (e.g. #conversations/uuid), select it
  useEffect(() => {
    if (activeUuid && conversations.length > 0) {
      const found = conversations.find(c => c.uuid === activeUuid);
      if (found) setSelected(found);
    }
  }, [activeUuid, conversations]);

  // Refresh unread count whenever conversations change
  useEffect(() => {
    fetch(`${apiUrl()}/conversations/unread-counts`, { headers: hdrs() })
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (data && typeof data === 'object') {
          const total = Object.values(data as Record<string, number>).reduce((a, b) => a + b, 0);
          onUnreadChange(total);
        }
      })
      .catch(() => {});
  }, [conversations, onUnreadChange]);

  if (selected) {
    return (
      <div style={{ height: 'calc(100vh - 60px)', display: 'flex', flexDirection: 'column' }}>
        <ThreadPanel
          conv={selected}
          currentUserId={user.id}
          onBack={() => { setSelected(null); load(); window.location.hash = '#conversations'; }}
          onResolved={() => { setSelected(null); load(); window.location.hash = '#conversations'; }}
        />
      </div>
    );
  }

  return (
    <div style={{ maxWidth: 760, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 20 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>Conversations</h1>
        <button onClick={load} style={{ background: 'none', border: '1px solid #cbd5e1', borderRadius: 6, padding: '4px 12px', fontSize: 13, color: '#64748b', cursor: 'pointer' }}>↻ Refresh</button>
      </div>

      {error && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 16 }}>{error}</div>
      )}

      {loading && <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b', fontSize: 14 }}>Loading…</div>}

      {!loading && conversations.length === 0 && !error && (
        <div style={{ textAlign: 'center', padding: '60px 0', color: '#94a3b8', fontSize: 14 }}>No conversations yet.</div>
      )}

      {!loading && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {conversations.map(conv => {
            const isResolved = conv.state === 'resolved';
            const cc = contextColor(conv.context_type);
            return (
              <button
                key={conv.uuid}
                onClick={() => { setSelected(conv); window.location.hash = `#conversations/${conv.uuid}`; }}
                style={{
                  background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: '14px 16px',
                  textAlign: 'left', cursor: 'pointer', width: '100%', display: 'flex', gap: 14, alignItems: 'center',
                  boxShadow: '0 1px 3px rgba(0,0,0,0.04)', transition: 'box-shadow 0.12s',
                  opacity: isResolved ? 0.7 : 1,
                }}
                onMouseEnter={e => (e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)')}
                onMouseLeave={e => (e.currentTarget.style.boxShadow = '0 1px 3px rgba(0,0,0,0.04)')}
              >
                {/* icon */}
                <div style={{ width: 40, height: 40, borderRadius: 10, background: cc.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <span style={{ fontSize: 18 }}>💬</span>
                </div>

                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8, marginBottom: 4 }}>
                    <div style={{ fontSize: 14, fontWeight: 600, color: '#1e293b', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {conv.subject || 'Conversation'}
                    </div>
                    <span style={{ fontSize: 11, color: '#94a3b8', whiteSpace: 'nowrap', flexShrink: 0 }}>{relativeTime(conv.created_at)}</span>
                  </div>
                  <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    <span style={{ fontSize: 11, fontWeight: 600, padding: '1px 7px', borderRadius: 8, background: cc.bg, color: cc.fg }}>
                      {contextLabel(conv.context_type)} #{conv.context_id}
                    </span>
                    <span style={{ fontSize: 11, fontWeight: 600, padding: '1px 7px', borderRadius: 8, background: isResolved ? '#dcfce7' : '#dbeafe', color: isResolved ? '#15803d' : '#1d4ed8' }}>
                      {conv.state}
                    </span>
                  </div>
                </div>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default ConversationsPage;
