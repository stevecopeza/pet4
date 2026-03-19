import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';
import LoadingState from './foundation/states/LoadingState';
import ErrorState from './foundation/states/ErrorState';
import EmptyState from './foundation/states/EmptyState';

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

const severityColors: Record<string, string> = {
  LOW: '#28a745',
  MEDIUM: '#ffc107',
  HIGH: '#fd7e14',
  CRITICAL: '#dc3545',
};

const statusColors: Record<string, string> = {
  OPEN: '#dc3545',
  ACKED: '#ffc107',
  RESOLVED: '#28a745',
};

type SeverityFilter = 'ALL' | 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';

const fmtDate = (iso: string | null | undefined): string => {
  if (!iso) return '\u2014';
  return new Date(iso).toLocaleString();
};

const Escalations = () => {
  const toast = useToast();
  const [data, setData] = useState<ListResponse | null>(null);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<EscalationDetail | null>(null);
  const [filter, setFilter] = useState<'all' | 'open'>('open');
  const [severityFilter, setSeverityFilter] = useState<SeverityFilter>('ALL');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [actionBusy, setActionBusy] = useState(false);
  const [pendingAction, setPendingAction] = useState<{ id: number; action: 'acknowledge' | 'resolve' } | null>(null);

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

  const filteredItems = data?.items.filter((e) => severityFilter === 'ALL' || e.severity === severityFilter) ?? [];

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

  const doAction = async (id: number, action: 'acknowledge' | 'resolve') => {
    setActionBusy(true);
    try {
      const body = action === 'resolve' ? JSON.stringify({ resolution_note: prompt('Resolution note (optional):') || null }) : undefined;
      const res = await fetch(`${window.petSettings.apiUrl}/escalations/${id}/${action}`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.petSettings.nonce, 'Content-Type': 'application/json' },
        body,
      });
      if (!res.ok) {
        throw new Error(`Failed to ${action} escalation`);
      }
      toast.success(action === 'acknowledge' ? 'Escalation acknowledged.' : 'Escalation resolved.');
      fetchData();
      if (selectedId === id) {
        fetchDetail(id);
      }
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Escalation action failed');
    } finally {
      setActionBusy(false);
      setPendingAction(null);
    }
  };

  const Badge = ({ label, color }: { label: string; color: string }) => (
    <span style={{
      display: 'inline-block', padding: '2px 8px', borderRadius: 3,
      background: color, color: '#fff', fontSize: 12, fontWeight: 600,
    }}>{label}</span>
  );

  const columns: Column<EscalationItem>[] = [
    {
      key: 'id',
      header: 'ID',
      render: (val, item) => (
        <button
          type="button"
          className="button-link"
          onClick={() => fetchDetail(item.id)}
          style={{ padding: 0 }}
        >
          {String(val)}
        </button>
      )
    },
    { key: 'source_entity_type', header: 'Source', render: (val, item) => `${String(val)} #${item.source_entity_id}` },
    { key: 'severity', header: 'Severity', render: (val) => <Badge label={String(val)} color={severityColors[String(val)] || '#999'} /> },
    { key: 'status', header: 'Status', render: (val) => <Badge label={String(val)} color={statusColors[String(val)] || '#999'} /> },
    { key: 'summary', header: 'Summary', render: (val) => String(val || '') },
    { key: 'reason', header: 'Reason', render: (val) => String(val) },
    { key: 'opened_at', header: 'Opened', render: (_val, item) => fmtDate(item.opened_at || item.created_at) },
    {
      key: 'escalation_id',
      header: 'Actions',
      render: (_val, item) => (
        <>
          {item.status === 'OPEN' && (
            <button className="button button-small" onClick={() => setPendingAction({ id: item.id, action: 'acknowledge' })}>Acknowledge</button>
          )}
          {(item.status === 'OPEN' || item.status === 'ACKED') && (
            <button className="button button-small" style={{ marginLeft: 4 }} onClick={() => setPendingAction({ id: item.id, action: 'resolve' })}>Resolve</button>
          )}
        </>
      )
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
        <button className={`button ${filter === 'open' ? 'button-primary' : ''}`} onClick={() => { setFilter('open'); setPage(1); }}>Open / Acknowledged</button>
        <button className={`button ${filter === 'all' ? 'button-primary' : ''}`} onClick={() => { setFilter('all'); setPage(1); }}>All</button>
        <select
          value={severityFilter}
          onChange={(e) => setSeverityFilter(e.target.value as SeverityFilter)}
          style={{ height: 30 }}
        >
          <option value="ALL">All Severities</option>
          <option value="LOW">Low</option>
          <option value="MEDIUM">Medium</option>
          <option value="HIGH">High</option>
          <option value="CRITICAL">Critical</option>
        </select>
        <button className="button" onClick={fetchData} disabled={loading}>\u21BB Refresh</button>
      </div>

      {loading && !data && <LoadingState />}
      {error && !data && <ErrorState message={error} onRetry={fetchData} />}

      {data && !error && (
        <>
          <p style={{ color: '#666' }}>{filteredItems.length} of {data.total} escalation{data.total !== 1 ? 's' : ''}{severityFilter !== 'ALL' ? ` (${severityFilter})` : ''}</p>
          <DataTable
            columns={columns}
            data={filteredItems}
            loading={loading}
            error={error}
            onRetry={fetchData}
            emptyMessage="No escalations found."
            compatibilityMode="wp"
          />

          {data.total > data.per_page && (
            <div style={{ marginTop: 12, display: 'flex', gap: 8, alignItems: 'center' }}>
              <button className="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Prev</button>
              <span>Page {data.page} of {Math.ceil(data.total / data.per_page)}</span>
              <button className="button" disabled={page * data.per_page >= data.total} onClick={() => setPage(page + 1)}>Next →</button>
            </div>
          )}

          <div style={{ marginTop: 20 }}>
            {detailLoading && selectedId !== null && <LoadingState label={`Loading escalation #${selectedId}…`} />}
            {detailError && !detailLoading && <ErrorState message={detailError} onRetry={() => {
              if (selectedId !== null) {
                fetchDetail(selectedId);
              }
            }} />}
            {detail && (
              <div className="pd-card" style={{ padding: 20, borderTop: `4px solid ${severityColors[detail.severity] || '#e0e0e0'}` }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                  <div className="pd-card-title" style={{ fontSize: '1.1rem' }}>Escalation #{detail.id}</div>
                  <button type="button" className="button" onClick={() => { setSelectedId(null); setDetail(null); }}>Close</button>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '160px 1fr', gap: '8px 16px', marginBottom: 16 }}>
                  <div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Source</div>
                  <div>{detail.source_entity_type} #{detail.source_entity_id}</div>

                  <div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Severity</div>
                  <div><Badge label={detail.severity} color={severityColors[detail.severity] || '#999'} /></div>

                  <div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Status</div>
                  <div><Badge label={detail.status} color={statusColors[detail.status] || '#999'} /></div>

                  <div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Opened</div>
                  <div>{fmtDate(detail.opened_at || detail.created_at)}</div>

                  {detail.acknowledged_at && (
                    <><div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Acknowledged</div>
                    <div>{fmtDate(detail.acknowledged_at)}</div></>
                  )}

                  {detail.resolved_at && (
                    <><div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Resolved</div>
                    <div>{fmtDate(detail.resolved_at)}</div></>
                  )}

                  <div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Summary</div>
                  <div>{detail.summary || '\u2014'}</div>

                  <div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Reason</div>
                  <div>{detail.reason}</div>

                  <div style={{ fontSize: '0.78rem', color: '#888', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Resolution Note</div>
                  <div>{detail.resolution_note || '\u2014'}</div>
                </div>

                <div className="pd-card-title" style={{ fontSize: '0.9rem', marginBottom: 8 }}>Timeline</div>
                <table className="widefat striped">
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
                    {detail.transitions.map((t) => (
                      <tr key={t.id}>
                        <td>{t.from_status || '\u2014'}</td>
                        <td>{t.to_status}</td>
                        <td>{t.transitioned_by ?? '\u2014'}</td>
                        <td>{fmtDate(t.transitioned_at)}</td>
                        <td>{t.reason || '\u2014'}</td>
                      </tr>
                    ))}
                    {detail.transitions.length === 0 && (
                      <tr><td colSpan={5} style={{ textAlign: 'center', padding: 12 }}><EmptyState message="No transitions found." /></td></tr>
                    )}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      <ConfirmationDialog
        open={pendingAction !== null}
        title={pendingAction?.action === 'acknowledge' ? 'Acknowledge escalation?' : 'Resolve escalation?'}
        description={pendingAction?.action === 'acknowledge'
          ? 'This action will acknowledge the selected escalation.'
          : 'This action will resolve the selected escalation.'}
        confirmLabel={pendingAction?.action === 'acknowledge' ? 'Acknowledge' : 'Resolve'}
        busy={actionBusy}
        onCancel={() => setPendingAction(null)}
        onConfirm={() => {
          if (pendingAction) {
            doAction(pendingAction.id, pendingAction.action);
          }
        }}
      />
    </div>
  );
};

export default Escalations;
