import React, { useCallback, useEffect, useState } from 'react';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface ActivityItem {
  id: string;
  occurred_at: string;
  actor_display_name: string | null;
  actor_avatar_url: string | null;
  event_type: string;
  severity: string | null;
  reference_type: string | null;
  reference_id: string | null;
  customer_name: string | null;
  headline: string;
  subline: string | null;
  tags: string[];
}

const FILTERS = [
  { label: 'All',        value: '' },
  { label: 'Support',    value: 'ticket' },
  { label: 'Project',    value: 'project' },
  { label: 'Commercial', value: 'quote' },
];

const severityColor: Record<string, string> = {
  critical: '#dc2626',
  warning:  '#f59e0b',
  info:     '#3b82f6',
};

function relativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  return `${Math.floor(h / 24)}d ago`;
}

function dayLabel(iso: string): string {
  const d = new Date(iso);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);
  if (d.toDateString() === today.toDateString()) return 'Today';
  if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
}

function initials(name: string | null): string {
  if (!name) return '?';
  const w = name.trim().split(/\s+/);
  return w.length >= 2 ? (w[0][0] + w[w.length - 1][0]).toUpperCase() : name.slice(0, 2).toUpperCase();
}

const ActivityPage: React.FC = () => {
  const [items, setItems]       = useState<ActivityItem[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState<string | null>(null);
  const [filter, setFilter]     = useState('');
  const [limit, setLimit]       = useState(50);

  const load = useCallback(async (lim: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${apiUrl()}/activities?limit=${lim}`, { headers: hdrs() });
      if (!res.ok) throw new Error(`Failed to load activity (${res.status})`);
      const data = await res.json();
      setItems(data.items ?? data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(limit); }, [load, limit]);

  const filtered = filter
    ? items.filter(i => (i.reference_type ?? '').includes(filter))
    : items;

  // Group by day
  const groups: { day: string; items: ActivityItem[] }[] = [];
  for (const item of filtered) {
    const day = dayLabel(item.occurred_at);
    const last = groups[groups.length - 1];
    if (last && last.day === day) {
      last.items.push(item);
    } else {
      groups.push({ day, items: [item] });
    }
  }

  return (
    <div style={{ maxWidth: 760, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 20 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>Activity</h1>
        <button onClick={() => load(limit)} style={{ background: 'none', border: '1px solid #cbd5e1', borderRadius: 6, padding: '4px 12px', fontSize: 13, color: '#64748b', cursor: 'pointer' }}>↻ Refresh</button>
      </div>

      {/* filter tabs */}
      <div style={{ display: 'flex', gap: 4, marginBottom: 20, borderBottom: '2px solid #e2e8f0' }}>
        {FILTERS.map(f => (
          <button
            key={f.value}
            onClick={() => setFilter(f.value)}
            style={{
              padding: '7px 14px', background: 'none', border: 'none', cursor: 'pointer',
              fontSize: 13, fontWeight: 600,
              color: filter === f.value ? '#2563eb' : '#64748b',
              borderBottom: filter === f.value ? '2px solid #2563eb' : '2px solid transparent',
              marginBottom: -2,
            }}
          >
            {f.label}
          </button>
        ))}
      </div>

      {error && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 16 }}>
          {error}
        </div>
      )}

      {loading && <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b', fontSize: 14 }}>Loading…</div>}

      {!loading && !error && filtered.length === 0 && (
        <div style={{ textAlign: 'center', padding: '60px 0', color: '#94a3b8', fontSize: 14 }}>No recent activity.</div>
      )}

      {/* grouped list */}
      {!loading && groups.map(group => (
        <div key={group.day} style={{ marginBottom: 24 }}>
          <div style={{ fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 8, paddingBottom: 4, borderBottom: '1px solid #f1f5f9' }}>
            {group.day}
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            {group.items.map(item => (
              <div key={item.id} style={{ display: 'flex', gap: 12, padding: '10px 0', borderBottom: '1px solid #f8fafc', alignItems: 'flex-start' }}>
                {/* avatar */}
                <div style={{
                  width: 34, height: 34, borderRadius: '50%', background: '#e0e7ff', color: '#3730a3',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 12, fontWeight: 700, flexShrink: 0,
                }}>
                  {item.actor_avatar_url
                    ? <img src={item.actor_avatar_url} alt="" style={{ width: 34, height: 34, borderRadius: '50%' }} />
                    : initials(item.actor_display_name)
                  }
                </div>

                {/* text */}
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', alignItems: 'baseline', gap: 6, marginBottom: 2 }}>
                    {item.severity && (
                      <span style={{ width: 7, height: 7, borderRadius: '50%', background: severityColor[item.severity] ?? '#94a3b8', display: 'inline-block', flexShrink: 0, marginTop: 1 }} />
                    )}
                    <span style={{ fontSize: 14, color: '#1e293b', fontWeight: 500 }}>{item.headline}</span>
                  </div>
                  {item.subline && (
                    <div style={{ fontSize: 12, color: '#64748b' }}>{item.subline}</div>
                  )}
                  {item.customer_name && (
                    <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>{item.customer_name}</div>
                  )}
                </div>

                {/* time */}
                <div style={{ fontSize: 11, color: '#94a3b8', whiteSpace: 'nowrap', flexShrink: 0 }}>
                  {relativeTime(item.occurred_at)}
                </div>
              </div>
            ))}
          </div>
        </div>
      ))}

      {/* load more */}
      {!loading && filtered.length >= limit && (
        <div style={{ textAlign: 'center', paddingTop: 16 }}>
          <button
            onClick={() => setLimit(l => l + 50)}
            style={{ padding: '8px 24px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13, fontWeight: 600, color: '#475569', cursor: 'pointer' }}
          >
            Load more
          </button>
        </div>
      )}
    </div>
  );
};

export default ActivityPage;
