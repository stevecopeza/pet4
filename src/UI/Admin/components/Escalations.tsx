import React, { useEffect, useMemo, useState } from 'react';
import { DataTable, Column } from './DataTable';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import Dialog from './foundation/Dialog';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';
import useToast from './foundation/useToast';
import LoadingState from './foundation/states/LoadingState';
import ErrorState from './foundation/states/ErrorState';

interface EscalationItem {
  id: number;
  escalation_id: string;
  source_entity_type: string;
  source_entity_id: number;
  severity: string;
  status: string;
  reason: string;
  summary?: string;
  metadata: Record<string, unknown>;
  created_by: number | null;
  acknowledged_by: number | null;
  resolved_by: number | null;
  created_at: string;
  opened_at?: string;
  acknowledged_at: string | null;
  resolved_at: string | null;
  resolution_note?: string | null;
}

interface EscalationTransition {
  id: number;
  from_status: string | null;
  to_status: string;
  transitioned_by: number | null;
  transitioned_at: string;
  reason: string | null;
}

interface EscalationDetail extends EscalationItem {
  transitions: EscalationTransition[];
}

interface ListResponse {
  items: EscalationItem[];
  total: number;
  page: number;
  per_page: number;
}

type SeverityFilter = 'ALL' | 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
type StatusFilter = 'all' | 'open';

const fmtDate = (iso: string | null | undefined): string => {
  if (!iso) return '—';
  return new Date(iso).toLocaleString();
};

const normalizeTone = (value: string | null | undefined): string => (
  String(value || 'unknown').toLowerCase().replace(/[^a-z0-9]+/g, '-')
);

const toneLabel = (value: string | null | undefined): string => (
  String(value || 'UNKNOWN').toUpperCase()
);

const Escalations = () => {
  const toast = useToast();
  const [data, setData] = useState<ListResponse | null>(null);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<EscalationDetail | null>(null);
  const [filter, setFilter] = useState<StatusFilter>('open');
  const [severityFilter, setSeverityFilter] = useState<SeverityFilter>('ALL');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [actionBusy, setActionBusy] = useState(false);
  const [pendingAcknowledgeId, setPendingAcknowledgeId] = useState<number | null>(null);
  const [pendingResolveId, setPendingResolveId] = useState<number | null>(null);
  const [resolutionNote, setResolutionNote] = useState('');
  const [resolveError, setResolveError] = useState<string | null>(null);

  const fetchData = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '20' });
      if (filter === 'open') params.set('status', 'open');
      const res = await fetch(`${window.petSettings.apiUrl}/escalations?${params}`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      });
      if (!res.ok) {
        throw new Error('Failed to fetch escalations');
      }
      setData(await res.json());
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch escalations');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchData(); }, [filter, page]);

  const filteredItems = useMemo(
    () => data?.items.filter((item) => severityFilter === 'ALL' || item.severity === severityFilter) ?? [],
    [data?.items, severityFilter]
  );

  const summary = useMemo(() => {
    const open = filteredItems.filter((item) => item.status === 'OPEN').length;
    const acked = filteredItems.filter((item) => item.status === 'ACKED').length;
    const resolved = filteredItems.filter((item) => item.status === 'RESOLVED').length;
    return {
      visible: filteredItems.length,
      open,
      acked,
      resolved,
    };
  }, [filteredItems]);

  const totalPages = useMemo(
    () => (data ? Math.max(1, Math.ceil(data.total / data.per_page)) : 1),
    [data]
  );

  const fetchDetail = async (id: number) => {
    setSelectedId(id);
    setDetailLoading(true);
    setDetailError(null);
    try {
      const res = await fetch(`${window.petSettings.apiUrl}/escalations/${id}`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      });
      if (!res.ok) {
        throw new Error(`Failed to load escalation #${id}`);
      }
      setDetail(await res.json());
    } catch (err) {
      setDetailError(err instanceof Error ? err.message : 'Failed to load escalation detail');
    } finally {
      setDetailLoading(false);
    }
  };

  const acknowledgeEscalation = async (id: number) => {
    setActionBusy(true);
    try {
      const res = await fetch(`${window.petSettings.apiUrl}/escalations/${id}/acknowledge`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.petSettings.nonce, 'Content-Type': 'application/json' },
      });
      if (!res.ok) {
        throw new Error('Failed to acknowledge escalation');
      }
      toast.success('Escalation acknowledged.');
      await fetchData();
      if (selectedId === id) {
        await fetchDetail(id);
      }
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Escalation action failed');
    } finally {
      setActionBusy(false);
      setPendingAcknowledgeId(null);
    }
  };

  const resolveEscalation = async () => {
    if (pendingResolveId === null) {
      return;
    }
    setActionBusy(true);
    setResolveError(null);
    try {
      const res = await fetch(`${window.petSettings.apiUrl}/escalations/${pendingResolveId}/resolve`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.petSettings.nonce, 'Content-Type': 'application/json' },
        body: JSON.stringify({ resolution_note: resolutionNote.trim() || null }),
      });
      if (!res.ok) {
        throw new Error('Failed to resolve escalation');
      }
      toast.success('Escalation resolved.');
      await fetchData();
      if (selectedId === pendingResolveId) {
        await fetchDetail(pendingResolveId);
      }
      setPendingResolveId(null);
      setResolutionNote('');
    } catch (err) {
      setResolveError(err instanceof Error ? err.message : 'Escalation action failed');
    } finally {
      setActionBusy(false);
    }
  };

  const Badge = ({ label, toneType }: { label: string; toneType: 'severity' | 'status' }) => (
    <span className={`pet-escalations-badge pet-escalations-badge--${toneType}-${normalizeTone(label)}`}>
      {toneLabel(label)}
    </span>
  );

  const columns: Column<EscalationItem>[] = [
    {
      key: 'id',
      header: 'ID',
      render: (val, item) => (
        <button
          type="button"
          className="button-link pet-escalations-id-link"
          onClick={() => fetchDetail(item.id)}
        >
          {String(val)}
        </button>
      )
    },
    {
      key: 'source_entity_type',
      header: 'Source',
      render: (val, item) => `${String(val)} #${item.source_entity_id}`
    },
    {
      key: 'severity',
      header: 'Severity',
      render: (val) => <Badge label={String(val)} toneType="severity" />
    },
    {
      key: 'status',
      header: 'Status',
      render: (val) => <Badge label={String(val)} toneType="status" />
    },
    { key: 'summary', header: 'Summary', render: (val) => String(val || '') },
    { key: 'reason', header: 'Reason', render: (val) => String(val) },
    { key: 'opened_at', header: 'Opened', render: (_val, item) => fmtDate(item.opened_at || item.created_at) },
    {
      key: 'escalation_id',
      header: 'Actions',
      render: (_val, item) => (
        <div className="pet-escalations-row-actions">
          {item.status === 'OPEN' && (
            <button
              type="button"
              className="button button-small"
              onClick={() => setPendingAcknowledgeId(item.id)}
              disabled={actionBusy}
            >
              Acknowledge
            </button>
          )}
          {(item.status === 'OPEN' || item.status === 'ACKED') && (
            <button
              type="button"
              className="button button-small"
              onClick={() => {
                setPendingResolveId(item.id);
                setResolutionNote('');
                setResolveError(null);
              }}
              disabled={actionBusy}
            >
              Resolve
            </button>
          )}
        </div>
      )
    },
  ];

  const detailTone = detail ? normalizeTone(detail.severity) : 'unknown';

  return (
    <PageShell
      className="pet-escalations-page"
      title="Escalations"
      subtitle="Monitor, acknowledge, and resolve operational escalations."
      actions={(
        <div className="pet-escalations-shell-actions">
          <a className="button button-secondary" href="/wp-admin/admin.php?page=pet-escalation-rules">
            Rules
          </a>
          <button type="button" className="button button-secondary" onClick={fetchData} disabled={loading}>
            {loading ? 'Refreshing…' : 'Refresh'}
          </button>
        </div>
      )}
    >
      <Panel className="pet-escalations-toolbar-panel">
        <div className="pet-escalations-toolbar">
          <div className="pet-escalations-segmented">
            <button
              type="button"
              className={`button pet-escalations-segmented-btn ${filter === 'open' ? 'button-primary is-active' : ''}`}
              onClick={() => { setFilter('open'); setPage(1); }}
            >
              Open / Acknowledged
            </button>
            <button
              type="button"
              className={`button pet-escalations-segmented-btn ${filter === 'all' ? 'button-primary is-active' : ''}`}
              onClick={() => { setFilter('all'); setPage(1); }}
            >
              All
            </button>
          </div>
          <label className="pet-escalations-severity-filter">
            <span>Severity</span>
            <select value={severityFilter} onChange={(event) => setSeverityFilter(event.target.value as SeverityFilter)}>
              <option value="ALL">All Severities</option>
              <option value="LOW">Low</option>
              <option value="MEDIUM">Medium</option>
              <option value="HIGH">High</option>
              <option value="CRITICAL">Critical</option>
            </select>
          </label>
        </div>
        <div className="pet-escalations-summary-grid">
          <div className="pet-escalations-summary-item">
            <span className="pet-escalations-summary-label">Visible</span>
            <strong className="pet-escalations-summary-value">{summary.visible}</strong>
          </div>
          <div className="pet-escalations-summary-item">
            <span className="pet-escalations-summary-label">Open</span>
            <strong className="pet-escalations-summary-value">{summary.open}</strong>
          </div>
          <div className="pet-escalations-summary-item">
            <span className="pet-escalations-summary-label">Acknowledged</span>
            <strong className="pet-escalations-summary-value">{summary.acked}</strong>
          </div>
          <div className="pet-escalations-summary-item">
            <span className="pet-escalations-summary-label">Resolved</span>
            <strong className="pet-escalations-summary-value">{summary.resolved}</strong>
          </div>
        </div>
      </Panel>

      <Panel className="pet-escalations-table-panel">
        <div className="pet-escalations-section-header">
          <h3>Escalation queue</h3>
          {data && (
            <p>
              {filteredItems.length} of {data.total} escalation{data.total !== 1 ? 's' : ''}
              {severityFilter !== 'ALL' ? ` (${severityFilter})` : ''}
            </p>
          )}
        </div>

        {loading && !data && <LoadingState />}
        {error && !data && <ErrorState message={error} onRetry={fetchData} />}

        {data && !error && (
          <>
            <DataTable
              columns={columns}
              data={filteredItems}
              loading={loading}
              error={error}
              onRetry={fetchData}
              emptyMessage="No escalations found."
              compatibilityMode="wp"
              rowClassName={(item) => item.status === 'OPEN' ? 'pet-escalations-row--requires-ack' : ''}
            />

            {data.total > data.per_page && (
              <div className="pet-escalations-pagination">
                <button type="button" className="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>
                  ← Prev
                </button>
                <span>Page {data.page} of {totalPages}</span>
                <button
                  type="button"
                  className="button"
                  disabled={page * data.per_page >= data.total}
                  onClick={() => setPage(page + 1)}
                >
                  Next →
                </button>
              </div>
            )}
          </>
        )}
      </Panel>

      {(selectedId !== null || detail || detailError || detailLoading) && (
        <Panel className={`pet-escalations-detail-panel pet-escalations-detail-panel--${detailTone}`}>
          <div className="pet-escalations-detail-header">
            <div>
              <h3>Escalation detail</h3>
              {selectedId !== null && <p>Escalation #{selectedId}</p>}
            </div>
            <button
              type="button"
              className="button"
              onClick={() => {
                setSelectedId(null);
                setDetail(null);
                setDetailError(null);
              }}
            >
              Close
            </button>
          </div>

          {detailLoading && selectedId !== null && <LoadingState label={`Loading escalation #${selectedId}…`} />}
          {detailError && !detailLoading && (
            <ErrorState
              message={detailError}
              onRetry={() => {
                if (selectedId !== null) {
                  fetchDetail(selectedId);
                }
              }}
            />
          )}

          {detail && !detailLoading && !detailError && (
            <>
              <div className="pet-escalations-detail-grid">
                <div className="pet-escalations-detail-field">
                  <span>Source</span>
                  <div>{detail.source_entity_type} #{detail.source_entity_id}</div>
                </div>
                <div className="pet-escalations-detail-field">
                  <span>Severity</span>
                  <div><Badge label={detail.severity} toneType="severity" /></div>
                </div>
                <div className="pet-escalations-detail-field">
                  <span>Status</span>
                  <div><Badge label={detail.status} toneType="status" /></div>
                </div>
                <div className="pet-escalations-detail-field">
                  <span>Opened</span>
                  <div>{fmtDate(detail.opened_at || detail.created_at)}</div>
                </div>
                {detail.acknowledged_at && (
                  <div className="pet-escalations-detail-field">
                    <span>Acknowledged</span>
                    <div>{fmtDate(detail.acknowledged_at)}</div>
                  </div>
                )}
                {detail.resolved_at && (
                  <div className="pet-escalations-detail-field">
                    <span>Resolved</span>
                    <div>{fmtDate(detail.resolved_at)}</div>
                  </div>
                )}
                <div className="pet-escalations-detail-field pet-escalations-detail-field--wide">
                  <span>Summary</span>
                  <div>{detail.summary || '—'}</div>
                </div>
                <div className="pet-escalations-detail-field pet-escalations-detail-field--wide">
                  <span>Reason</span>
                  <div>{detail.reason}</div>
                </div>
                <div className="pet-escalations-detail-field pet-escalations-detail-field--wide">
                  <span>Resolution Note</span>
                  <div>{detail.resolution_note || '—'}</div>
                </div>
              </div>

              <div className="pet-escalations-timeline">
                <div className="pet-escalations-timeline-title">Timeline</div>
                <table className="widefat striped pet-escalations-timeline-table">
                  <thead>
                    <tr>
                      <th>From</th>
                      <th>To</th>
                      <th>By</th>
                      <th>At</th>
                      <th>Reason</th>
                    </tr>
                  </thead>
                  <tbody>
                    {detail.transitions.map((transition) => (
                      <tr key={transition.id}>
                        <td>{transition.from_status || '—'}</td>
                        <td>{transition.to_status}</td>
                        <td>{transition.transitioned_by ?? '—'}</td>
                        <td>{fmtDate(transition.transitioned_at)}</td>
                        <td>{transition.reason || '—'}</td>
                      </tr>
                    ))}
                    {detail.transitions.length === 0 && (
                      <tr>
                        <td colSpan={5} className="pet-escalations-timeline-empty">No transitions found.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </Panel>
      )}

      {error && data && (
        <div className="pet-escalations-warning">{error}</div>
      )}

      <ConfirmationDialog
        open={pendingAcknowledgeId !== null}
        title="Acknowledge escalation?"
        description="This action will acknowledge the selected escalation."
        confirmLabel="Acknowledge"
        busy={actionBusy}
        onCancel={() => setPendingAcknowledgeId(null)}
        onConfirm={() => {
          if (pendingAcknowledgeId !== null) {
            acknowledgeEscalation(pendingAcknowledgeId);
          }
        }}
      />
      <Dialog
        open={pendingResolveId !== null}
        title="Resolve escalation"
        description="Provide an optional resolution note before resolving this escalation."
        onClose={() => {
          if (!actionBusy) {
            setPendingResolveId(null);
            setResolutionNote('');
            setResolveError(null);
          }
        }}
      >
        <div className="pet-escalations-resolve-form">
          <label className="pet-escalations-resolve-label">
            <span>Resolution note (optional)</span>
            <textarea
              rows={4}
              value={resolutionNote}
              onChange={(event) => setResolutionNote(event.target.value)}
              placeholder="Add context for how this escalation was resolved."
              disabled={actionBusy}
            />
          </label>
          {resolveError && <div className="pet-escalations-resolve-error">{resolveError}</div>}
          <div className="pet-dialog-actions">
            <button
              type="button"
              className="button"
              onClick={() => {
                setPendingResolveId(null);
                setResolutionNote('');
                setResolveError(null);
              }}
              disabled={actionBusy}
            >
              Cancel
            </button>
            <button type="button" className="button button-primary" onClick={resolveEscalation} disabled={actionBusy}>
              {actionBusy ? 'Working…' : 'Resolve'}
            </button>
          </div>
        </div>
      </Dialog>
    </PageShell>
  );
};

export default Escalations;
