import React, { useEffect, useMemo, useState } from 'react';
import { Ticket, TimeEntry } from '../types';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';
import useToast from './foundation/useToast';
import LoadingState from './foundation/states/LoadingState';
import ErrorState from './foundation/states/ErrorState';
import EmptyState from './foundation/states/EmptyState';

type TimelineBlock =
  | { type: 'entry'; id: string; entry: TimeEntry }
  | { type: 'gap'; id: string; start: Date; end: Date; minutes: number };

const MIN_GAP_MINUTES = 15;
const DAILY_TARGET_MINUTES = 8 * 60;

const formatDateInput = (date: Date) => {
  const pad = (value: number) => value.toString().padStart(2, '0');
  const year = date.getFullYear();
  const month = pad(date.getMonth() + 1);
  const day = pad(date.getDate());
  const hours = pad(date.getHours());
  const minutes = pad(date.getMinutes());
  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const formatMinutes = (minutes: number) => {
  const safeMinutes = Math.max(0, minutes);
  const hours = Math.floor(safeMinutes / 60);
  const remainder = safeMinutes % 60;
  if (hours > 0 && remainder > 0) {
    return `${hours}h ${remainder}m`;
  }
  if (hours > 0) {
    return `${hours}h`;
  }
  return `${remainder}m`;
};

const parseServerError = async (response: Response, fallback: string) => {
  try {
    const data = await response.json();
    if (typeof data?.error === 'string' && data.error.trim() !== '') {
      return data.error;
    }
    if (typeof data?.message === 'string' && data.message.trim() !== '') {
      return data.message;
    }
  } catch (_) {
    // Ignore parse errors and use fallback
  }
  return fallback;
};

const StaffTimeCapture: React.FC = () => {
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [entries, setEntries] = useState<TimeEntry[]>([]);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [resolvedEmployeeId, setResolvedEmployeeId] = useState<number | null>(null);
  const [isSheetOpen, setIsSheetOpen] = useState(false);
  const [sheetStep, setSheetStep] = useState<1 | 2>(1);
  const [ticketSearch, setTicketSearch] = useState('');
  const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);
  const [draftDescription, setDraftDescription] = useState('');
  const [draftIsBillable, setDraftIsBillable] = useState(true);
  const [draftStart, setDraftStart] = useState(formatDateInput(new Date()));
  const [selectedDurationMinutes, setSelectedDurationMinutes] = useState<number>(30);
  const [saving, setSaving] = useState(false);
  const [sheetError, setSheetError] = useState<string | null>(null);

  const fetchEntries = async () => {
    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;
    const response = await fetch(`${apiUrl}/staff/time-capture/entries`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    if (!response.ok) {
      throw new Error(await parseServerError(response, 'Failed to fetch time entries'));
    }
    const data = await response.json();
    setEntries(Array.isArray(data) ? data : []);
  };

  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const [contextRes, entriesRes] = await Promise.all([
        fetch(`${apiUrl}/staff/time-capture/context`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/staff/time-capture/entries`, { headers: { 'X-WP-Nonce': nonce } }),
      ]);

      if (!contextRes.ok) {
        throw new Error(await parseServerError(contextRes, 'Failed to fetch time capture context'));
      }
      if (!entriesRes.ok) {
        throw new Error(await parseServerError(entriesRes, 'Failed to fetch time entries'));
      }

      const contextData = await contextRes.json();
      const entriesData = await entriesRes.json();
      if (!contextData?.employee?.id) {
        setResolvedEmployeeId(null);
        setEntries([]);
        setError('Current user is not mapped to an active PET employee profile for Staff Time Capture.');
        return;
      }

      setResolvedEmployeeId(contextData.employee.id);
      setTickets(Array.isArray(contextData.ticketSuggestions) ? contextData.ticketSuggestions : []);
      setEntries(Array.isArray(entriesData) ? entriesData : []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load Staff Time Capture data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const todayKey = useMemo(() => {
    const now = new Date();
    return `${now.getFullYear()}-${(now.getMonth() + 1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')}`;
  }, []);

  const todayEntries = useMemo(() => {
    return entries
      .filter((entry) => {
        const startDate = new Date(entry.start);
        const key = `${startDate.getFullYear()}-${(startDate.getMonth() + 1).toString().padStart(2, '0')}-${startDate.getDate().toString().padStart(2, '0')}`;
        return key === todayKey;
      })
      .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime());
  }, [entries, todayKey]);

  const timelineBlocks = useMemo<TimelineBlock[]>(() => {
    const now = new Date();
    const workdayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 8, 0, 0, 0);
    const workdayEnd = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 17, 0, 0, 0);
    const blocks: TimelineBlock[] = [];
    let cursor = workdayStart;

    for (const entry of todayEntries) {
      const entryStart = new Date(entry.start);
      const entryEnd = new Date(entry.end);
      const gapMinutes = Math.floor((entryStart.getTime() - cursor.getTime()) / 60000);
      if (gapMinutes >= MIN_GAP_MINUTES) {
        blocks.push({
          type: 'gap',
          id: `gap-${cursor.toISOString()}-${entryStart.toISOString()}`,
          start: new Date(cursor),
          end: new Date(entryStart),
          minutes: gapMinutes,
        });
      }
      blocks.push({ type: 'entry', id: `entry-${entry.id}`, entry });
      if (entryEnd > cursor) {
        cursor = entryEnd;
      }
    }

    const endGapMinutes = Math.floor((workdayEnd.getTime() - cursor.getTime()) / 60000);
    if (endGapMinutes >= MIN_GAP_MINUTES) {
      blocks.push({
        type: 'gap',
        id: `gap-${cursor.toISOString()}-${workdayEnd.toISOString()}`,
        start: new Date(cursor),
        end: new Date(workdayEnd),
        minutes: endGapMinutes,
      });
    }

    return blocks;
  }, [todayEntries]);

  const summary = useMemo(() => {
    const totalMinutes = todayEntries.reduce((sum, entry) => sum + (entry.duration || 0), 0);
    const billableMinutes = todayEntries.reduce((sum, entry) => sum + (entry.billable ? (entry.duration || 0) : 0), 0);
    const billablePercent = totalMinutes > 0 ? Math.round((billableMinutes / totalMinutes) * 100) : 0;
    const completionPercent = Math.min(100, Math.round((totalMinutes / DAILY_TARGET_MINUTES) * 100));
    return {
      totalMinutes,
      billablePercent,
      entryCount: todayEntries.length,
      completionPercent,
    };
  }, [todayEntries]);

  const basicStreak = useMemo(() => {
    const dayKeys = new Set(
      entries.map((entry) => {
        const date = new Date(entry.start);
        return `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}`;
      })
    );

    let streak = 0;
    for (let i = 0; i < 14; i += 1) {
      const date = new Date();
      date.setDate(date.getDate() - i);
      const key = `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}`;
      if (!dayKeys.has(key)) {
        break;
      }
      streak += 1;
    }
    return streak;
  }, [entries]);

  const recentEntrySuggestions = useMemo(() => {
    const seen = new Set<number>();
    const suggestions: TimeEntry[] = [];
    const ordered = [...entries].sort((a, b) => new Date(b.start).getTime() - new Date(a.start).getTime());
    for (const entry of ordered) {
      if (seen.has(entry.ticketId)) {
        continue;
      }
      seen.add(entry.ticketId);
      suggestions.push(entry);
      if (suggestions.length >= 5) {
        break;
      }
    }
    return suggestions;
  }, [entries]);

  const filteredTickets = useMemo(() => {
    const normalizedSearch = ticketSearch.trim().toLowerCase();
    const likelyTimeLoggable = tickets.filter((ticket) => ticket.status !== 'closed' && !ticket.isRollup);
    if (!normalizedSearch) {
      return likelyTimeLoggable.slice(0, 8);
    }
    return likelyTimeLoggable
      .filter((ticket) => {
        const subject = ticket.subject?.toLowerCase() ?? '';
        return subject.includes(normalizedSearch) || String(ticket.id).includes(normalizedSearch);
      })
      .slice(0, 8);
  }, [ticketSearch, tickets]);

  const resetSheet = () => {
    setSheetStep(1);
    setTicketSearch('');
    setSelectedTicketId(null);
    setDraftDescription('');
    setDraftIsBillable(true);
    setDraftStart(formatDateInput(new Date()));
    setSelectedDurationMinutes(30);
    setSheetError(null);
    setIsSheetOpen(false);
  };

  const openAddEntry = (startDate?: Date, durationMinutes = 30) => {
    setSheetStep(1);
    setSheetError(null);
    setTicketSearch('');
    setSelectedTicketId(null);
    setDraftDescription('');
    setDraftIsBillable(true);
    setDraftStart(formatDateInput(startDate || new Date()));
    setSelectedDurationMinutes(durationMinutes);
    setIsSheetOpen(true);
  };

  const handleFillFirstGap = () => {
    const firstGap = timelineBlocks.find((block) => block.type === 'gap');
    if (firstGap && firstGap.type === 'gap') {
      openAddEntry(firstGap.start, Math.min(120, Math.max(15, firstGap.minutes)));
      return;
    }
    openAddEntry();
  };

  const handleSelectRecent = (entry: TimeEntry) => {
    setSelectedTicketId(entry.ticketId);
    setDraftDescription(entry.description || '');
    setDraftIsBillable(entry.billable);
    setSheetStep(2);
  };

  const handleSelectTicket = (ticket: Ticket) => {
    setSelectedTicketId(ticket.id);
    setDraftDescription(ticket.subject || '');
    setDraftIsBillable(ticket.isBillableDefault ?? true);
  };

  const handleCreateEntry = async () => {
    if (!resolvedEmployeeId) {
      setSheetError('Employee mapping is required before creating time entries.');
      return;
    }
    if (!selectedTicketId || !draftDescription.trim() || !draftStart || !selectedDurationMinutes) {
      setSheetError('Select a ticket, provide an activity description, and choose a duration.');
      return;
    }

    setSaving(true);
    setSheetError(null);

    try {
      const startDate = new Date(draftStart);
      const endDate = new Date(startDate.getTime() + selectedDurationMinutes * 60000);
      const payload = {
        ticketId: selectedTicketId,
        start: formatDateInput(startDate),
        end: formatDateInput(endDate),
        isBillable: draftIsBillable,
        description: draftDescription.trim(),
      };

      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const response = await fetch(`${apiUrl}/staff/time-capture/entries`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const message = await parseServerError(response, 'Failed to create time entry');
        setSheetError(message);
        toast.error(message);
        return;
      }

      const optimisticId = Date.now() * -1;
      setEntries((prev) => [
        ...prev,
        {
          id: optimisticId,
          employeeId: resolvedEmployeeId,
          ticketId: payload.ticketId,
          start: payload.start,
          end: payload.end,
          duration: selectedDurationMinutes,
          description: payload.description,
          billable: payload.isBillable,
          status: 'draft',
        },
      ]);

      toast.success('Time entry saved');
      resetSheet();
      await fetchEntries();
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to create time entry';
      setSheetError(message);
      toast.error(message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <PageShell
      title="Staff Time Capture"
      subtitle="Mobile-first daily time logging surface built on existing PET time-entry contracts."
      className="pet-staff-time-capture"
      testId="staff-time-capture-shell"
      actions={(
        <div className="pet-staff-time-capture-actions">
          <button type="button" className="button" onClick={handleFillFirstGap}>Fill Gap</button>
          <button type="button" className="button button-primary" onClick={() => openAddEntry()}>Add Entry</button>
        </div>
      )}
    >
      <Panel className="pet-staff-time-capture-honesty" testId="staff-time-capture-honesty">
        <p>
          This surface uses staff-scoped self-only endpoints and is available only when Staff Time Capture rollout is enabled.
        </p>
      </Panel>

      {loading ? <LoadingState label="Loading Staff Time Capture…" /> : null}
      {!loading && error ? <ErrorState message={error} onRetry={loadData} /> : null}

      {!loading && !error && (
        <>
          <Panel className="pet-staff-time-capture-today-header" testId="staff-time-capture-today-header">
            <div className="pet-staff-time-capture-today-title">
              <h3>Today</h3>
              <span>{formatMinutes(summary.totalMinutes)} / {formatMinutes(DAILY_TARGET_MINUTES)}</span>
            </div>
            <div className="pet-staff-time-capture-progress-track" aria-label="Day completion">
              <div className="pet-staff-time-capture-progress-fill" style={{ width: `${summary.completionPercent}%` }} />
            </div>
          </Panel>

          <Panel className="pet-staff-time-capture-summary" testId="staff-time-capture-summary">
            <div className="pet-staff-time-capture-summary-item">
              <span className="pet-staff-time-capture-summary-label">Total Logged</span>
              <strong>{formatMinutes(summary.totalMinutes)}</strong>
            </div>
            <div className="pet-staff-time-capture-summary-item">
              <span className="pet-staff-time-capture-summary-label">Billable %</span>
              <strong>{summary.billablePercent}%</strong>
            </div>
            <div className="pet-staff-time-capture-summary-item">
              <span className="pet-staff-time-capture-summary-label">Entry Count</span>
              <strong>{summary.entryCount}</strong>
            </div>
            <div className="pet-staff-time-capture-summary-item">
              <span className="pet-staff-time-capture-summary-label">Streak</span>
              <strong>{basicStreak}d</strong>
            </div>
          </Panel>

          <Panel className="pet-staff-time-capture-timeline" testId="staff-time-capture-timeline">
            <div className="pet-staff-time-capture-timeline-heading">
              <h3>Timeline</h3>
              <p>Tap Add Entry for quick capture. Gap cards show unlogged working-time windows.</p>
            </div>
            {timelineBlocks.length === 0 ? (
              <EmptyState message="No entries for today yet. Add your first time entry." />
            ) : (
              <div className="pet-staff-time-capture-timeline-list">
                {timelineBlocks.map((block) => {
                  if (block.type === 'gap') {
                    return (
                      <div key={block.id} className="pet-staff-time-capture-gap-card" data-testid="staff-time-capture-gap-card">
                        <div>
                          <strong>Gap</strong>
                          <p>{formatDateInput(block.start).slice(11)} - {formatDateInput(block.end).slice(11)}</p>
                        </div>
                        <span>{formatMinutes(block.minutes)}</span>
                      </div>
                    );
                  }

                  const entry = block.entry;
                  return (
                    <div key={block.id} className="pet-staff-time-capture-entry-card" data-testid="staff-time-capture-entry-card">
                      <div className="pet-staff-time-capture-entry-time">
                        <strong>{formatDateInput(new Date(entry.start)).slice(11)} - {formatDateInput(new Date(entry.end)).slice(11)}</strong>
                        <span>{formatMinutes(entry.duration || 0)}</span>
                      </div>
                      <p className="pet-staff-time-capture-entry-description">{entry.description || 'No activity description'}</p>
                      <div className="pet-staff-time-capture-entry-meta">
                        <span>Ticket #{entry.ticketId}</span>
                        <span className={entry.billable ? 'pet-badge-billable' : 'pet-badge-non-billable'}>
                          {entry.billable ? 'Billable' : 'Non-billable'}
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </Panel>
        </>
      )}

      {isSheetOpen && (
        <div className="pet-bottom-sheet-overlay" onClick={resetSheet}>
          <div
            className="pet-bottom-sheet pet-staff-time-capture-sheet"
            onClick={(event) => event.stopPropagation()}
            role="dialog"
            aria-modal="true"
            aria-labelledby="staff-time-capture-sheet-title"
          >
            <div className="pet-bottom-sheet-header">
              <h3 id="staff-time-capture-sheet-title">Add Entry</h3>
              <button type="button" className="button button-link-delete" onClick={resetSheet}>Close</button>
            </div>

            {sheetStep === 1 ? (
              <div className="pet-staff-time-capture-sheet-step">
                <label htmlFor="staff-time-capture-ticket-search">Search ticket</label>
                <input
                  id="staff-time-capture-ticket-search"
                  type="text"
                  value={ticketSearch}
                  onChange={(event) => setTicketSearch(event.target.value)}
                  placeholder="Search by ticket ID or subject"
                />

                {recentEntrySuggestions.length > 0 && (
                  <div className="pet-staff-time-capture-sheet-group">
                    <strong>Recent activities</strong>
                    <div className="pet-staff-time-capture-chip-grid">
                      {recentEntrySuggestions.map((entry) => (
                        <button
                          key={`recent-${entry.id}`}
                          type="button"
                          className="button"
                          onClick={() => handleSelectRecent(entry)}
                        >
                          #{entry.ticketId} · {entry.description || 'Reuse activity'}
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                <div className="pet-staff-time-capture-sheet-group">
                  <strong>Assigned/active tickets</strong>
                  <div className="pet-staff-time-capture-chip-grid">
                    {filteredTickets.length === 0 ? (
                      <span className="pet-staff-time-capture-muted">No ticket suggestions found.</span>
                    ) : (
                      filteredTickets.map((ticket) => (
                        <button
                          key={`ticket-${ticket.id}`}
                          type="button"
                          className={`button ${selectedTicketId === ticket.id ? 'button-primary' : ''}`.trim()}
                          onClick={() => handleSelectTicket(ticket)}
                        >
                          #{ticket.id} · {ticket.subject}
                        </button>
                      ))
                    )}
                  </div>
                </div>

                <label htmlFor="staff-time-capture-description">Activity</label>
                <textarea
                  id="staff-time-capture-description"
                  rows={3}
                  value={draftDescription}
                  onChange={(event) => setDraftDescription(event.target.value)}
                  placeholder="Describe the work performed"
                />

                <label className="pet-staff-time-capture-billable-toggle">
                  <input
                    type="checkbox"
                    checked={draftIsBillable}
                    onChange={(event) => setDraftIsBillable(event.target.checked)}
                  />
                  Billable
                </label>

                <div className="pet-staff-time-capture-sheet-actions">
                  <button type="button" className="button" onClick={resetSheet}>Cancel</button>
                  <button
                    type="button"
                    className="button button-primary"
                    onClick={() => setSheetStep(2)}
                    disabled={!selectedTicketId || !draftDescription.trim()}
                  >
                    Continue
                  </button>
                </div>
              </div>
            ) : (
              <div className="pet-staff-time-capture-sheet-step">
                <label htmlFor="staff-time-capture-start">Start time</label>
                <input
                  id="staff-time-capture-start"
                  type="datetime-local"
                  value={draftStart}
                  onChange={(event) => setDraftStart(event.target.value)}
                />

                <div className="pet-staff-time-capture-sheet-group">
                  <strong>Duration</strong>
                  <div className="pet-staff-time-capture-chip-grid">
                    {[15, 30, 60, 90, 120].map((duration) => (
                      <button
                        key={`duration-${duration}`}
                        type="button"
                        className={`button ${selectedDurationMinutes === duration ? 'button-primary' : ''}`.trim()}
                        onClick={() => setSelectedDurationMinutes(duration)}
                      >
                        {formatMinutes(duration)}
                      </button>
                    ))}
                  </div>
                </div>

                <label htmlFor="staff-time-capture-duration">Custom duration (minutes)</label>
                <input
                  id="staff-time-capture-duration"
                  type="number"
                  min={1}
                  value={selectedDurationMinutes}
                  onChange={(event) => setSelectedDurationMinutes(Number(event.target.value) || 0)}
                />

                {sheetError && <p className="pet-staff-time-capture-sheet-error">{sheetError}</p>}

                <div className="pet-staff-time-capture-sheet-actions">
                  <button type="button" className="button" onClick={() => setSheetStep(1)} disabled={saving}>Back</button>
                  <button
                    type="button"
                    className="button button-primary"
                    onClick={handleCreateEntry}
                    disabled={saving}
                  >
                    {saving ? 'Saving…' : 'Save Entry'}
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </PageShell>
  );
};

export default StaffTimeCapture;
