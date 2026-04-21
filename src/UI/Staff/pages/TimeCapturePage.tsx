/**
 * TimeCapturePage — [pet_log_time] shortcode component
 *
 * Lets staff log time against tickets from mobile.
 * Uses GET/POST /pet/v1/staff/time-capture/entries + /context.
 */
import React, { useState, useEffect, useCallback } from 'react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface TicketSuggestion {
  id: number;
  subject: string;
  status: string;
  lifecycleOwner: string;
  isBillableDefault: boolean;
  isRollup: boolean;
}

interface Employee {
  id: number;
  wpUserId: number;
  displayName: string;
}

interface Context {
  employee: Employee;
  ticketSuggestions: TicketSuggestion[];
}

interface TimeEntry {
  id: number;
  ticketId: number;
  start: string;   // 'YYYY-MM-DD HH:MM:SS'
  end: string;
  duration: number; // minutes
  description: string;
  billable: boolean;
}

// ── API helpers ───────────────────────────────────────────────────────────────

declare const petStaffConfig: { apiUrl: string; nonce: string; userId: number };

function apiBase(): string {
  return (typeof petStaffConfig !== 'undefined' ? petStaffConfig.apiUrl : '/wp-json/pet/v1').replace(/\/$/, '');
}

function apiHeaders(): HeadersInit {
  const nonce = typeof petStaffConfig !== 'undefined' ? petStaffConfig.nonce : '';
  return { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce };
}

async function apiFetch<T>(path: string, opts: RequestInit = {}): Promise<T> {
  const res = await fetch(`${apiBase()}${path}`, {
    ...opts,
    headers: { ...apiHeaders(), ...(opts.headers ?? {}) },
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error((body as any).error ?? (body as any).message ?? `API error ${res.status}`);
  }
  return res.json() as Promise<T>;
}

// ── Date/time utilities ────────────────────────────────────────────────────────

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

/** Round a Date to the nearest N minutes */
function roundToMinutes(d: Date, mins: number): Date {
  const ms = mins * 60 * 1000;
  return new Date(Math.round(d.getTime() / ms) * ms);
}

/** Format Date as HH:MM for <input type="time"> */
function toTimeInput(d: Date): string {
  return d.toTimeString().slice(0, 5);
}

/** Build ISO datetime string from date (YYYY-MM-DD) + HH:MM */
function toIsoDateTime(date: string, time: string): string {
  return `${date}T${time}:00`;
}

/** Format a count of minutes as "1h 30m" */
function fmtDuration(minutes: number): string {
  if (minutes <= 0) return '0m';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m}m`;
  if (m === 0) return `${h}h`;
  return `${h}h ${m}m`;
}

/** Compute duration in minutes between two HH:MM strings on the same date */
function calcDurationMinutes(start: string, end: string): number {
  const [sh, sm] = start.split(':').map(Number);
  const [eh, em] = end.split(':').map(Number);
  return Math.max(0, (eh * 60 + em) - (sh * 60 + sm));
}

/** Format 'YYYY-MM-DD HH:MM:SS' entry time as 'HH:MM' */
function entryTimeLabel(dt: string): string {
  return dt.substring(11, 16);
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function TimeCapturePage() {
  const now     = new Date();
  const rounded = roundToMinutes(now, 15);

  const [context, setContext]       = useState<Context | null>(null);
  const [entries, setEntries]       = useState<TimeEntry[]>([]);
  const [loading, setLoading]       = useState(true);
  const [saving, setSaving]         = useState(false);
  const [error, setError]           = useState<string | null>(null);
  const [saveError, setSaveError]   = useState<string | null>(null);
  const [saveOk, setSaveOk]         = useState(false);

  // Form state
  const [ticketId, setTicketId]       = useState<number | ''>('');
  const [startTime, setStartTime]     = useState(toTimeInput(rounded));
  const [endTime, setEndTime]         = useState(toTimeInput(now));
  const [isBillable, setIsBillable]   = useState(true);
  const [description, setDescription] = useState('');

  const today = todayIso();

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [ctx, allEntries] = await Promise.all([
        apiFetch<Context>('/staff/time-capture/context'),
        apiFetch<TimeEntry[]>('/staff/time-capture/entries'),
      ]);
      setContext(ctx);
      setEntries(allEntries.filter(e => e.start.startsWith(today)));
    } catch (e: any) {
      setError(e.message ?? 'Failed to load');
    } finally {
      setLoading(false);
    }
  }, [today]);

  useEffect(() => { load(); }, [load]);

  // When a ticket is selected, default billable to its isBillableDefault
  const handleTicketChange = (id: number | '') => {
    setTicketId(id);
    if (id !== '' && context) {
      const t = context.ticketSuggestions.find(s => s.id === id);
      if (t) setIsBillable(t.isBillableDefault);
    }
  };

  const handleStartNow = () => {
    setStartTime(toTimeInput(new Date()));
  };

  const durationMinutes = calcDurationMinutes(startTime, endTime);

  const handleSave = async () => {
    if (ticketId === '') { setSaveError('Please select a ticket.'); return; }
    if (durationMinutes <= 0) { setSaveError('End time must be after start time.'); return; }
    if (!description.trim()) { setSaveError('Please add a brief description.'); return; }

    try {
      setSaving(true);
      setSaveError(null);
      setSaveOk(false);
      await apiFetch('/staff/time-capture/entries', {
        method: 'POST',
        body: JSON.stringify({
          ticketId,
          start: toIsoDateTime(today, startTime),
          end: toIsoDateTime(today, endTime),
          isBillable,
          description: description.trim(),
        }),
      });
      setSaveOk(true);
      setDescription('');
      setTicketId('');
      setStartTime(toTimeInput(roundToMinutes(new Date(), 15)));
      setEndTime(toTimeInput(new Date()));
      await load();
    } catch (e: any) {
      setSaveError(e.message ?? 'Failed to save');
    } finally {
      setSaving(false);
    }
  };

  const todayTotal = entries.reduce((sum, e) => sum + e.duration, 0);

  if (loading) {
    return (
      <div className="staff-page">
        <div className="staff-loading">Loading…</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="staff-page">
        <div className="staff-error-box">{error}</div>
      </div>
    );
  }

  const tickets = context?.ticketSuggestions ?? [];

  return (
    <div className="staff-page">
      <div className="staff-header">
        <span className="staff-header__title">Log Time</span>
        <span className="staff-header__date">
          {new Date().toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}
        </span>
      </div>

      {/* ── Form ── */}
      <div className="staff-card">
        <div className="staff-field">
          <label className="staff-label">Ticket</label>
          <select
            className="staff-select"
            value={ticketId}
            onChange={e => handleTicketChange(e.target.value === '' ? '' : Number(e.target.value))}
          >
            <option value="">— Select ticket —</option>
            {tickets.map(t => (
              <option key={t.id} value={t.id}>
                #{t.id} — {t.subject}
              </option>
            ))}
          </select>
        </div>

        <div className="staff-row">
          <div className="staff-field staff-field--half">
            <label className="staff-label">Start</label>
            <div className="staff-time-row">
              <input
                type="time"
                className="staff-input"
                value={startTime}
                onChange={e => setStartTime(e.target.value)}
              />
              <button className="staff-btn-ghost" onClick={handleStartNow} title="Set to now">Now</button>
            </div>
          </div>
          <div className="staff-field staff-field--half">
            <label className="staff-label">End</label>
            <input
              type="time"
              className="staff-input"
              value={endTime}
              onChange={e => setEndTime(e.target.value)}
            />
          </div>
        </div>

        {durationMinutes > 0 && (
          <div className="staff-duration-badge">{fmtDuration(durationMinutes)}</div>
        )}

        <div className="staff-field">
          <label className="staff-label">Notes</label>
          <textarea
            className="staff-textarea"
            placeholder="Brief description of work done…"
            rows={2}
            value={description}
            onChange={e => setDescription(e.target.value)}
          />
        </div>

        <div className="staff-field staff-billable-row">
          <label className="staff-checkbox-label">
            <input
              type="checkbox"
              checked={isBillable}
              onChange={e => setIsBillable(e.target.checked)}
            />
            Billable
          </label>
        </div>

        {saveError && <div className="staff-error-box staff-error-box--inline">{saveError}</div>}
        {saveOk && <div className="staff-success-box">Time saved ✓</div>}

        <button
          className="staff-btn-primary"
          onClick={handleSave}
          disabled={saving}
        >
          {saving ? 'Saving…' : 'Save'}
        </button>
      </div>

      {/* ── Today's entries ── */}
      <div className="staff-section-title">
        Today's entries
        {todayTotal > 0 && (
          <span className="staff-total-badge">{fmtDuration(todayTotal)} total</span>
        )}
      </div>

      {entries.length === 0 ? (
        <div className="staff-empty">No entries logged today.</div>
      ) : (
        <div className="staff-entries">
          {entries.map(entry => {
            const ticket = tickets.find(t => t.id === entry.ticketId);
            return (
              <div key={entry.id} className="staff-entry">
                <div className="staff-entry__ticket">
                  #{entry.ticketId}
                  {ticket && <span className="staff-entry__subject"> — {ticket.subject}</span>}
                </div>
                <div className="staff-entry__meta">
                  <span>{entryTimeLabel(entry.start)}–{entryTimeLabel(entry.end)}</span>
                  <span className="staff-entry__dur">{fmtDuration(entry.duration)}</span>
                  {entry.billable && <span className="staff-pill staff-pill--blue">Billable</span>}
                </div>
                {entry.description && (
                  <div className="staff-entry__desc">{entry.description}</div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
