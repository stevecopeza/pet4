import React, { useCallback, useEffect, useState } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs    = () => ({ 'X-WP-Nonce': nonce() });
const jsonHdrs = () => ({ 'X-WP-Nonce': nonce(), 'Content-Type': 'application/json' });

interface TicketSuggestion {
  ticketId: number;
  referenceCode: string;
  subject: string;
  isBillableDefault: boolean;
}

interface TimeEntry {
  id: number;
  ticketId: number;
  ticketRef: string;
  start: string;
  end: string;
  duration: number;
  description: string;
  isBillable: boolean;
}

function nowTime(): string {
  const d = new Date();
  return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
}

function todayDate(): string {
  return new Date().toISOString().slice(0, 10);
}

function durationLabel(start: string, end: string): string | null {
  const [sh, sm] = start.split(':').map(Number);
  const [eh, em] = end.split(':').map(Number);
  if (isNaN(sh) || isNaN(eh)) return null;
  const mins = (eh * 60 + em) - (sh * 60 + sm);
  if (mins <= 0) return null;
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h > 0 ? `${h}h ${m > 0 ? m + 'm' : ''}`.trim() : `${m}m`;
}

function minsToLabel(mins: number): string {
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h > 0 ? `${h}h ${m > 0 ? m + 'm' : ''}`.trim() : `${m}m`;
}

const LogTimePage: React.FC = () => {
  const user = usePortalUser();
  const [tickets, setTickets]   = useState<TicketSuggestion[]>([]);
  const [entries, setEntries]   = useState<TimeEntry[]>([]);
  const [loading, setLoading]   = useState(true);
  const [saving, setSaving]     = useState(false);
  const [error, setError]       = useState<string | null>(null);
  const [success, setSuccess]   = useState<string | null>(null);

  const [form, setForm] = useState({
    ticketId: '',
    date: todayDate(),
    start: nowTime(),
    end: nowTime(),
    isBillable: true,
    description: '',
  });

  const loadContext = useCallback(async () => {
    try {
      const res = await fetch(`${apiUrl()}/staff/time-capture/context`, { headers: hdrs() });
      if (res.ok) {
        const data = await res.json();
        setTickets(data.ticketSuggestions ?? []);
      }
    } catch { /* noop */ }
  }, []);

  const loadEntries = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(`${apiUrl()}/staff/time-capture/entries`, { headers: hdrs() });
      if (res.ok) {
        const data: TimeEntry[] = await res.json();
        const today = todayDate();
        setEntries(data.filter(e => e.start.startsWith(today)));
      }
    } catch { /* noop */ }
    setLoading(false);
  }, []);

  useEffect(() => {
    loadContext();
    loadEntries();
  }, [loadContext, loadEntries]);

  // Auto-set billable default when ticket changes
  useEffect(() => {
    const t = tickets.find(t => String(t.ticketId) === form.ticketId);
    if (t) setForm(f => ({ ...f, isBillable: t.isBillableDefault }));
  }, [form.ticketId, tickets]);

  const duration = durationLabel(form.start, form.end);

  const totalMins = entries.reduce((acc, e) => acc + (e.duration ?? 0), 0);

  const handleSave = async () => {
    if (!form.ticketId) { setError('Please select a ticket.'); return; }
    if (!form.start || !form.end) { setError('Start and end time are required.'); return; }
    if (!duration) { setError('End time must be after start time.'); return; }
    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      const start = `${form.date}T${form.start}:00`;
      const end   = `${form.date}T${form.end}:00`;
      const res = await fetch(`${apiUrl()}/staff/time-capture/entries`, {
        method: 'POST',
        headers: jsonHdrs(),
        body: JSON.stringify({ ticketId: Number(form.ticketId), start, end, isBillable: form.isBillable, description: form.description }),
      });
      if (!res.ok) {
        const msg = await res.text();
        throw new Error(msg || 'Save failed');
      }
      setSuccess('Time entry saved.');
      setForm(f => ({ ...f, description: '', start: nowTime(), end: nowTime() }));
      await loadEntries();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const labelStyle: React.CSSProperties = {
    display: 'block', fontSize: 11, fontWeight: 700, textTransform: 'uppercase',
    letterSpacing: '0.05em', color: '#64748b', marginBottom: 4,
  };
  const inputStyle: React.CSSProperties = {
    width: '100%', padding: '9px 12px', border: '1px solid #cbd5e1', borderRadius: 8,
    fontSize: 14, fontFamily: 'inherit', color: '#1e293b', background: '#fff', outline: 'none',
    boxSizing: 'border-box',
  };

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '24px 20px' }}>
      <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: '0 0 24px' }}>Log Time</h1>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, alignItems: 'start' }}>

        {/* ── form ── */}
        <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12, padding: 20, boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
          <div style={{ fontSize: 13, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 16 }}>New Entry</div>

          {error && <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '8px 12px', fontSize: 13, marginBottom: 12 }}>{error}</div>}
          {success && <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#16a34a', borderRadius: 8, padding: '8px 12px', fontSize: 13, marginBottom: 12 }}>{success}</div>}

          {/* ticket */}
          <div style={{ marginBottom: 14 }}>
            <label style={labelStyle}>Ticket</label>
            <select
              value={form.ticketId}
              onChange={e => setForm(f => ({ ...f, ticketId: e.target.value }))}
              style={{ ...inputStyle, appearance: 'none', backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E")`, backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', paddingRight: 32 }}
            >
              <option value="">— select ticket —</option>
              {tickets.map(t => (
                <option key={t.ticketId} value={String(t.ticketId)}>
                  {t.referenceCode} — {t.subject}
                </option>
              ))}
            </select>
          </div>

          {/* date + times */}
          <div style={{ marginBottom: 14 }}>
            <label style={labelStyle}>Date</label>
            <input type="date" value={form.date} onChange={e => setForm(f => ({ ...f, date: e.target.value }))} style={inputStyle} />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 14 }}>
            <div>
              <label style={labelStyle}>Start</label>
              <div style={{ display: 'flex', gap: 6 }}>
                <input type="time" value={form.start} onChange={e => setForm(f => ({ ...f, start: e.target.value }))} style={{ ...inputStyle, flex: 1 }} />
                <button onClick={() => setForm(f => ({ ...f, start: nowTime() }))} style={{ padding: '9px 10px', border: '1px solid #cbd5e1', borderRadius: 8, background: '#f8fafc', fontSize: 11, fontWeight: 700, color: '#475569', cursor: 'pointer', whiteSpace: 'nowrap' }}>Now</button>
              </div>
            </div>
            <div>
              <label style={labelStyle}>End</label>
              <div style={{ display: 'flex', gap: 6 }}>
                <input type="time" value={form.end} onChange={e => setForm(f => ({ ...f, end: e.target.value }))} style={{ ...inputStyle, flex: 1 }} />
                <button onClick={() => setForm(f => ({ ...f, end: nowTime() }))} style={{ padding: '9px 10px', border: '1px solid #cbd5e1', borderRadius: 8, background: '#f8fafc', fontSize: 11, fontWeight: 700, color: '#475569', cursor: 'pointer', whiteSpace: 'nowrap' }}>Now</button>
              </div>
            </div>
          </div>

          {/* duration badge */}
          {duration && (
            <div style={{ marginBottom: 14 }}>
              <span style={{ display: 'inline-block', background: '#eff6ff', color: '#1d4ed8', fontSize: 13, fontWeight: 700, padding: '4px 12px', borderRadius: 20 }}>
                {duration}
              </span>
            </div>
          )}

          {/* billable */}
          <div style={{ marginBottom: 16 }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, color: '#475569', cursor: 'pointer' }}>
              <input type="checkbox" checked={form.isBillable} onChange={e => setForm(f => ({ ...f, isBillable: e.target.checked }))} style={{ width: 17, height: 17, accentColor: '#3b82f6', cursor: 'pointer' }} />
              Billable
            </label>
          </div>

          {/* description */}
          <div style={{ marginBottom: 18 }}>
            <label style={labelStyle}>Notes</label>
            <textarea
              value={form.description}
              onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
              rows={3}
              placeholder="What did you work on?"
              style={{ ...inputStyle, resize: 'vertical', minHeight: 72 }}
            />
          </div>

          <button
            onClick={handleSave}
            disabled={saving}
            style={{ display: 'block', width: '100%', padding: '11px 0', background: '#2563eb', color: '#fff', border: 'none', borderRadius: 8, fontSize: 15, fontWeight: 600, cursor: saving ? 'not-allowed' : 'pointer', opacity: saving ? 0.6 : 1, fontFamily: 'inherit' }}
          >
            {saving ? 'Saving…' : 'Save Entry'}
          </button>
        </div>

        {/* ── today's entries ── */}
        <div>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 12 }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
              Today's entries
            </div>
            {totalMins > 0 && (
              <span style={{ fontSize: 12, fontWeight: 700, background: '#f0fdf4', color: '#16a34a', padding: '2px 10px', borderRadius: 10 }}>
                Total {minsToLabel(totalMins)}
              </span>
            )}
          </div>

          {loading && <div style={{ color: '#64748b', fontSize: 13 }}>Loading…</div>}

          {!loading && entries.length === 0 && (
            <div style={{ textAlign: 'center', padding: '40px 0', color: '#94a3b8', fontSize: 14 }}>No entries today yet.</div>
          )}

          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {entries.map(e => {
              const startTime = e.start.slice(11, 16);
              const endTime   = e.end.slice(11, 16);
              return (
                <div key={e.id} style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 8, padding: '10px 14px' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8, marginBottom: 4 }}>
                    <span style={{ fontSize: 13, fontWeight: 600, color: '#1e293b' }}>{e.ticketRef}</span>
                    <span style={{ fontSize: 13, fontWeight: 700, color: '#0369a1', whiteSpace: 'nowrap' }}>{minsToLabel(e.duration)}</span>
                  </div>
                  <div style={{ display: 'flex', gap: 10, fontSize: 12, color: '#64748b' }}>
                    <span>{startTime}–{endTime}</span>
                    {e.isBillable && <span style={{ color: '#16a34a', fontWeight: 600 }}>Billable</span>}
                  </div>
                  {e.description && (
                    <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 4, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{e.description}</div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
};

export default LogTimePage;
