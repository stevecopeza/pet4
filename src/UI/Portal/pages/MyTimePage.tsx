import React, { useCallback, useEffect, useMemo, useState } from 'react';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface Entry {
  id: number;
  ticketId: number;
  ticketRef: string;
  start: string;
  end: string;
  duration: number;
  description: string;
  isBillable: boolean;
}

function minsToLabel(mins: number): string {
  if (!mins) return '0m';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h > 0 ? `${h}h ${m > 0 ? m + 'm' : ''}`.trim() : `${m}m`;
}

function startOfWeek(d: Date): Date {
  const copy = new Date(d);
  const day = copy.getDay(); // 0=Sun
  const diff = day === 0 ? -6 : 1 - day; // Monday-start week
  copy.setDate(copy.getDate() + diff);
  copy.setHours(0, 0, 0, 0);
  return copy;
}

function addDays(d: Date, n: number): Date {
  const copy = new Date(d);
  copy.setDate(copy.getDate() + n);
  return copy;
}

function isoDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

function formatDate(iso: string): string {
  const d = new Date(iso + 'T00:00:00');
  return d.toLocaleDateString('en-ZA', { weekday: 'short', month: 'short', day: 'numeric' });
}

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const MyTimePage: React.FC = () => {
  const [entries, setEntries]   = useState<Entry[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState<string | null>(null);
  const [weekStart, setWeekStart] = useState<Date>(() => startOfWeek(new Date()));
  const [notEnabled, setNotEnabled] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${apiUrl()}/staff/time-capture/entries`, { headers: hdrs() });
      if (res.status === 404 || res.status === 403) {
        setNotEnabled(true);
        return;
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setEntries(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const weekDays = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(weekStart, i)), [weekStart]);

  const entriesByDay = useMemo(() => {
    const map = new Map<string, Entry[]>();
    for (const e of entries) {
      const day = e.start.slice(0, 10);
      const existing = map.get(day) ?? [];
      existing.push(e);
      map.set(day, existing);
    }
    return map;
  }, [entries]);

  const weekEntries = useMemo(() =>
    weekDays.flatMap(d => entriesByDay.get(isoDate(d)) ?? []),
    [weekDays, entriesByDay]
  );

  const weekTotal       = weekEntries.reduce((s, e) => s + (e.duration ?? 0), 0);
  const weekBillable    = weekEntries.filter(e => e.isBillable).reduce((s, e) => s + (e.duration ?? 0), 0);
  const weekNonBillable = weekTotal - weekBillable;

  const totalAll       = entries.reduce((s, e) => s + (e.duration ?? 0), 0);
  const billableAll    = entries.filter(e => e.isBillable).reduce((s, e) => s + (e.duration ?? 0), 0);

  const goWeek = (delta: number) => setWeekStart(prev => addDays(prev, delta * 7));
  const goToday = () => setWeekStart(startOfWeek(new Date()));

  const thisWeekStart = isoDate(startOfWeek(new Date()));
  const isCurrentWeek = isoDate(weekStart) === thisWeekStart;

  if (notEnabled) {
    return (
      <div style={{ maxWidth: 700, margin: '0 auto', padding: '60px 20px', textAlign: 'center', color: '#94a3b8' }}>
        <div style={{ fontSize: 18, fontWeight: 600, marginBottom: 8, color: '#64748b' }}>Time capture not available</div>
        <div style={{ fontSize: 13 }}>The time capture feature is not enabled for this site.</div>
      </div>
    );
  }

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '24px 20px' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>Time History</h1>
        <div style={{ display: 'flex', gap: 8 }}>
          <a href="#log-time" style={{ padding: '7px 14px', background: '#2563eb', color: '#fff', border: 'none', borderRadius: 8, fontSize: 12, fontWeight: 600, cursor: 'pointer', textDecoration: 'none' }}>
            + Log Time
          </a>
          <button onClick={load} style={{ padding: '7px 12px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#f8fafc', fontSize: 12, fontWeight: 600, color: '#475569', cursor: 'pointer' }}>
            ↻
          </button>
        </div>
      </div>

      {/* Summary KPIs */}
      {!loading && !error && (
        <div style={{ display: 'flex', gap: 12, marginBottom: 20 }}>
          {[
            { label: 'All-time logged', value: minsToLabel(totalAll) },
            { label: 'Billable (total)', value: minsToLabel(billableAll) },
            { label: 'Non-billable (total)', value: minsToLabel(totalAll - billableAll) },
            { label: 'Entries', value: String(entries.length) },
          ].map(({ label, value }) => (
            <div key={label} style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: '12px 16px', flex: 1 }}>
              <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', color: '#64748b', marginBottom: 4 }}>{label}</div>
              <div style={{ fontSize: 18, fontWeight: 700, color: '#0f172a' }}>{value}</div>
            </div>
          ))}
        </div>
      )}

      {/* Week navigator */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16 }}>
        <button onClick={() => goWeek(-1)} style={{ padding: '6px 12px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#fff', cursor: 'pointer', fontSize: 14, color: '#475569' }}>‹</button>
        <span style={{ fontSize: 14, fontWeight: 600, color: '#0f172a', minWidth: 220, textAlign: 'center' }}>
          {formatDate(isoDate(weekDays[0]))} – {formatDate(isoDate(weekDays[6]))}
        </span>
        <button onClick={() => goWeek(1)} style={{ padding: '6px 12px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#fff', cursor: 'pointer', fontSize: 14, color: '#475569' }}>›</button>
        {!isCurrentWeek && (
          <button onClick={goToday} style={{ marginLeft: 4, padding: '6px 12px', border: '1px solid #2563eb', borderRadius: 8, background: '#eff6ff', fontSize: 12, fontWeight: 600, color: '#2563eb', cursor: 'pointer' }}>
            This week
          </button>
        )}
        {weekTotal > 0 && (
          <div style={{ marginLeft: 'auto', display: 'flex', gap: 10, fontSize: 13, color: '#64748b' }}>
            <span>Week total: <strong style={{ color: '#0f172a' }}>{minsToLabel(weekTotal)}</strong></span>
            {weekBillable > 0 && <span style={{ color: '#16a34a' }}>Billable: {minsToLabel(weekBillable)}</span>}
            {weekNonBillable > 0 && <span style={{ color: '#64748b' }}>Non-bill: {minsToLabel(weekNonBillable)}</span>}
          </div>
        )}
      </div>

      {error && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 16 }}>
          {error}
        </div>
      )}

      {loading && <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8' }}>Loading…</div>}

      {/* Day rows */}
      {!loading && !error && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {weekDays.map((day, i) => {
            const iso = isoDate(day);
            const dayEntries = entriesByDay.get(iso) ?? [];
            const dayTotal = dayEntries.reduce((s, e) => s + (e.duration ?? 0), 0);
            const isToday = iso === isoDate(new Date());

            return (
              <div key={iso} style={{
                background: '#fff',
                border: `1px solid ${isToday ? '#bfdbfe' : '#e2e8f0'}`,
                borderRadius: 10,
                overflow: 'hidden',
              }}>
                {/* Day header */}
                <div style={{
                  display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                  padding: '10px 16px',
                  background: isToday ? '#eff6ff' : dayEntries.length === 0 ? '#fafafa' : '#fff',
                }}>
                  <span style={{ fontSize: 13, fontWeight: 600, color: isToday ? '#2563eb' : '#1e293b' }}>
                    {DAYS[i]} · {formatDate(iso)}
                    {isToday && <span style={{ fontSize: 11, marginLeft: 8, color: '#2563eb', fontWeight: 700 }}>TODAY</span>}
                  </span>
                  {dayTotal > 0 && (
                    <span style={{ fontSize: 13, fontWeight: 700, color: '#0369a1' }}>{minsToLabel(dayTotal)}</span>
                  )}
                  {dayEntries.length === 0 && (
                    <span style={{ fontSize: 12, color: '#cbd5e1' }}>No entries</span>
                  )}
                </div>

                {/* Entries */}
                {dayEntries.length > 0 && (
                  <div style={{ borderTop: '1px solid #f1f5f9' }}>
                    {dayEntries.map(e => {
                      const startTime = e.start.slice(11, 16);
                      const endTime   = e.end.slice(11, 16);
                      return (
                        <div key={e.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 16px', borderBottom: '1px solid #f8fafc', fontSize: 13 }}>
                          <span style={{ fontWeight: 600, color: '#475569', minWidth: 90, fontSize: 12 }}>{startTime}–{endTime}</span>
                          <span style={{ fontWeight: 700, color: '#0369a1', minWidth: 50 }}>{minsToLabel(e.duration)}</span>
                          <span style={{ color: '#64748b', fontSize: 12, minWidth: 80 }}>{e.ticketRef}</span>
                          {e.description && <span style={{ color: '#94a3b8', fontSize: 12, flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{e.description}</span>}
                          {e.isBillable && <span style={{ fontSize: 11, fontWeight: 700, color: '#16a34a', marginLeft: 'auto', flexShrink: 0 }}>Billable</span>}
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default MyTimePage;
