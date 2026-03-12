import React, { useEffect, useState } from 'react';

interface EscalationItem {
  id: number;
  escalation_id: string;
  source_entity_type: string;
  source_entity_id: number;
  severity: string;
  status: string;
  reason: string;
  metadata: Record<string, unknown>;
  created_by: number | null;
  acknowledged_by: number | null;
  resolved_by: number | null;
  created_at: string;
  acknowledged_at: string | null;
  resolved_at: string | null;
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

const Escalations = () => {
  const [data, setData] = useState<ListResponse | null>(null);
  const [filter, setFilter] = useState<'all' | 'open'>('open');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);

  const fetchData = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '20' });
      if (filter === 'open') params.set('status', 'open');
      const res = await fetch(`${window.petSettings.apiUrl}/escalations?${params}`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      });
      if (res.ok) {
        setData(await res.json());
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchData(); }, [filter, page]);

  const doAction = async (id: number, action: 'acknowledge' | 'resolve') => {
    const body = action === 'resolve' ? JSON.stringify({ resolution_note: prompt('Resolution note (optional):') || null }) : undefined;
    await fetch(`${window.petSettings.apiUrl}/escalations/${id}/${action}`, {
      method: 'POST',
      headers: { 'X-WP-Nonce': window.petSettings.nonce, 'Content-Type': 'application/json' },
      body,
    });
    fetchData();
  };

  const Badge = ({ label, color }: { label: string; color: string }) => (
    <span style={{
      display: 'inline-block', padding: '2px 8px', borderRadius: 3,
      background: color, color: '#fff', fontSize: 12, fontWeight: 600,
    }}>{label}</span>
  );

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', gap: 8 }}>
        <button className={`button ${filter === 'open' ? 'button-primary' : ''}`} onClick={() => { setFilter('open'); setPage(1); }}>Open / Acknowledged</button>
        <button className={`button ${filter === 'all' ? 'button-primary' : ''}`} onClick={() => { setFilter('all'); setPage(1); }}>All</button>
        <button className="button" onClick={fetchData} disabled={loading}>↻ Refresh</button>
      </div>

      {loading && <p>Loading…</p>}

      {data && (
        <>
          <p style={{ color: '#666' }}>{data.total} escalation{data.total !== 1 ? 's' : ''}</p>
          <table className="widefat striped" style={{ tableLayout: 'auto' }}>
            <thead>
              <tr>
                <th>ID</th>
                <th>Source</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Reason</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {data.items.map((e) => (
                <tr key={e.id}>
                  <td>{e.id}</td>
                  <td>{e.source_entity_type} #{e.source_entity_id}</td>
                  <td><Badge label={e.severity} color={severityColors[e.severity] || '#999'} /></td>
                  <td><Badge label={e.status} color={statusColors[e.status] || '#999'} /></td>
                  <td style={{ maxWidth: 300, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{e.reason}</td>
                  <td>{new Date(e.created_at).toLocaleString()}</td>
                  <td>
                    {e.status === 'OPEN' && (
                      <button className="button button-small" onClick={() => doAction(e.id, 'acknowledge')}>Acknowledge</button>
                    )}
                    {(e.status === 'OPEN' || e.status === 'ACKED') && (
                      <button className="button button-small" style={{ marginLeft: 4 }} onClick={() => doAction(e.id, 'resolve')}>Resolve</button>
                    )}
                  </td>
                </tr>
              ))}
              {data.items.length === 0 && (
                <tr><td colSpan={7} style={{ textAlign: 'center', padding: 20, color: '#999' }}>No escalations found.</td></tr>
              )}
            </tbody>
          </table>

          {data.total > data.per_page && (
            <div style={{ marginTop: 12, display: 'flex', gap: 8, alignItems: 'center' }}>
              <button className="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Prev</button>
              <span>Page {data.page} of {Math.ceil(data.total / data.per_page)}</span>
              <button className="button" disabled={page * data.per_page >= data.total} onClick={() => setPage(page + 1)}>Next →</button>
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default Escalations;
