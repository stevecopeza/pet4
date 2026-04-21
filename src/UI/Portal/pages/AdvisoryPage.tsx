import React, { useCallback, useEffect, useState } from 'react';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface Customer {
  id: number;
  name: string;
  archivedAt: string | null;
}

interface Snapshot {
  customer_id?: number;
  tickets?: { by_status?: Record<string, number>; overdue?: number };
  projects?: { by_state?: Record<string, number> };
  delivery_tickets?: { by_status?: Record<string, number>; open?: number; closed?: number };
  signals?: { total_active?: number; by_severity?: Record<string, number>; by_type?: Record<string, number> };
  generated_at_utc?: string;
}

interface ReportContent {
  snapshot?: Snapshot;
}

interface Report {
  id: string;
  report_type: string;
  scope_type: string;
  scope_id: number;
  status: string;
  generated_at: string;
  summary?: string;
  content?: ReportContent;
}

function timeLabel(iso: string): string {
  return new Date(iso).toLocaleDateString('en-ZA', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function reportTypeLabel(type: string): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function metricTag(label: string, value: number | string, color?: string): React.ReactNode {
  return (
    <span key={label} style={{
      display: 'inline-flex', alignItems: 'center', gap: 4,
      background: '#f1f5f9', borderRadius: 6, padding: '3px 9px', fontSize: 12,
    }}>
      <span style={{ color: '#64748b' }}>{label}</span>
      <strong style={{ color: color ?? '#1e293b' }}>{value}</strong>
    </span>
  );
}

const SnapshotPanel: React.FC<{ snapshot?: Snapshot }> = ({ snapshot }) => {
  if (!snapshot) return <div style={{ color: '#94a3b8', fontSize: 13 }}>No snapshot data available.</div>;

  const tickets = snapshot.tickets ?? {};
  const projects = snapshot.projects ?? {};
  const delivery = snapshot.delivery_tickets ?? {};
  const signals = snapshot.signals ?? {};

  const ticketStatuses = Object.entries(tickets.by_status ?? {});
  const projectStates  = Object.entries(projects.by_state ?? {});
  const signalSeverity = Object.entries(signals.by_severity ?? {});
  const signalTypes    = Object.entries(signals.by_type ?? {});

  const sectionStyle: React.CSSProperties = { marginBottom: 14 };
  const sectionHead: React.CSSProperties = {
    fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em',
    color: '#94a3b8', marginBottom: 6,
  };
  const tagsRow: React.CSSProperties = { display: 'flex', flexWrap: 'wrap', gap: 6 };

  return (
    <div style={{ borderTop: '1px solid #f1f5f9', paddingTop: 12 }}>

      {/* Support Tickets */}
      {(ticketStatuses.length > 0 || tickets.overdue !== undefined) && (
        <div style={sectionStyle}>
          <div style={sectionHead}>Support Tickets</div>
          <div style={tagsRow}>
            {ticketStatuses.map(([s, c]) => metricTag(reportTypeLabel(s), c))}
            {(tickets.overdue ?? 0) > 0 && metricTag('Overdue', tickets.overdue!, '#dc2626')}
          </div>
        </div>
      )}

      {/* Projects */}
      {projectStates.length > 0 && (
        <div style={sectionStyle}>
          <div style={sectionHead}>Projects</div>
          <div style={tagsRow}>
            {projectStates.map(([s, c]) => metricTag(reportTypeLabel(s), c))}
          </div>
        </div>
      )}

      {/* Delivery Tickets */}
      {(delivery.open !== undefined || delivery.closed !== undefined) && (
        <div style={sectionStyle}>
          <div style={sectionHead}>Delivery Tickets</div>
          <div style={tagsRow}>
            {delivery.open !== undefined && metricTag('Open', delivery.open, delivery.open > 0 ? '#c2410c' : undefined)}
            {delivery.closed !== undefined && metricTag('Closed', delivery.closed, delivery.closed > 0 ? '#16a34a' : undefined)}
          </div>
        </div>
      )}

      {/* Signals */}
      {(signals.total_active !== undefined || signalSeverity.length > 0) && (
        <div style={{ ...sectionStyle, marginBottom: 0 }}>
          <div style={sectionHead}>Advisory Signals</div>
          <div style={tagsRow}>
            {signals.total_active !== undefined && metricTag('Active', signals.total_active, signals.total_active > 0 ? '#7c3aed' : undefined)}
            {signalSeverity.map(([sev, c]) => metricTag(reportTypeLabel(sev), c,
              sev === 'critical' ? '#dc2626' : sev === 'high' ? '#c2410c' : sev === 'medium' ? '#b45309' : undefined
            ))}
            {signalTypes.map(([t, c]) => metricTag(reportTypeLabel(t), c))}
          </div>
        </div>
      )}

      {ticketStatuses.length === 0 && projectStates.length === 0 && signals.total_active === undefined && (
        <div style={{ color: '#94a3b8', fontSize: 13 }}>No metrics recorded in this snapshot.</div>
      )}
    </div>
  );
};

const AdvisoryPage: React.FC = () => {
  const [customers, setCustomers]     = useState<Customer[]>([]);
  const [selectedCustId, setSelectedCustId] = useState<number | null>(null);
  const [reports, setReports]         = useState<Report[]>([]);
  const [expanded, setExpanded]       = useState<string | null>(null);
  const [custLoading, setCustLoading] = useState(true);
  const [repLoading, setRepLoading]   = useState(false);
  const [error, setError]             = useState<string | null>(null);

  // Load customers on mount
  useEffect(() => {
    (async () => {
      setCustLoading(true);
      try {
        const res = await fetch(`${apiUrl()}/customers`, { headers: hdrs() });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const all: Customer[] = await res.json();
        setCustomers(all.filter(c => !c.archivedAt));
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Failed to load customers');
      } finally {
        setCustLoading(false);
      }
    })();
  }, []);

  // Load reports when customer changes
  const loadReports = useCallback(async (custId: number) => {
    setRepLoading(true);
    setError(null);
    setReports([]);
    try {
      const res = await fetch(`${apiUrl()}/advisory/reports?customer_id=${custId}`, { headers: hdrs() });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setReports(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load reports');
    } finally {
      setRepLoading(false);
    }
  }, []);

  const selectCustomer = (id: number) => {
    setSelectedCustId(id);
    setExpanded(null);
    loadReports(id);
  };

  const toggleReport = async (report: Report) => {
    if (expanded === report.id) {
      setExpanded(null);
      return;
    }
    // Fetch full detail if not already loaded
    if (!report.content) {
      try {
        const res = await fetch(`${apiUrl()}/advisory/reports/${report.id}`, { headers: hdrs() });
        if (res.ok) {
          const detail = await res.json();
          setReports(prev => prev.map(r => r.id === report.id ? { ...r, ...detail } : r));
        }
      } catch { /* noop */ }
    }
    setExpanded(report.id);
  };

  const selectedCustomer = customers.find(c => c.id === selectedCustId);

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ marginBottom: 24 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: '0 0 4px' }}>Advisory</h1>
        <p style={{ margin: 0, color: '#64748b', fontSize: 13 }}>View AI-generated advisory reports and QBR packs for customers.</p>
      </div>

      {/* Customer picker */}
      <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12, padding: '20px 24px', marginBottom: 20 }}>
        <label style={{ display: 'block', fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: '#64748b', marginBottom: 8 }}>
          Select Customer
        </label>
        <select
          value={selectedCustId ?? ''}
          onChange={e => e.target.value ? selectCustomer(Number(e.target.value)) : setSelectedCustId(null)}
          disabled={custLoading}
          style={{ width: '100%', maxWidth: 400, padding: '9px 12px', border: '1px solid #cbd5e1', borderRadius: 8, fontSize: 14, color: '#1e293b', background: '#fff' }}
        >
          <option value="">— select customer —</option>
          {customers.map(c => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
      </div>

      {error && <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 12 }}>{error}</div>}

      {!selectedCustId && !custLoading && (
        <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8', fontSize: 14 }}>
          Select a customer above to view their advisory reports.
        </div>
      )}

      {repLoading && <div style={{ padding: '40px 0', textAlign: 'center', color: '#94a3b8' }}>Loading reports…</div>}

      {selectedCustId && !repLoading && reports.length === 0 && !error && (
        <div style={{ padding: '40px 0', textAlign: 'center', color: '#94a3b8', fontSize: 14 }}>
          No advisory reports found for {selectedCustomer?.name ?? 'this customer'}.
        </div>
      )}

      {!repLoading && reports.length > 0 && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <div style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: '#64748b', marginBottom: 4 }}>
            {reports.length} report{reports.length !== 1 ? 's' : ''} for {selectedCustomer?.name}
          </div>
          {reports.map(report => {
            const isOpen = expanded === report.id;
            return (
              <div key={report.id} style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, overflow: 'hidden' }}>
                <button
                  onClick={() => toggleReport(report)}
                  style={{ width: '100%', textAlign: 'left', padding: '14px 18px', background: '#f8fafc', border: 'none', cursor: 'pointer', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: isOpen ? '1px solid #e2e8f0' : 'none' }}
                >
                  <div>
                    <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a', marginBottom: 2 }}>
                      {reportTypeLabel(report.report_type)}
                    </div>
                    <div style={{ fontSize: 12, color: '#64748b' }}>{timeLabel(report.generated_at)}</div>
                  </div>
                  <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                    <span style={{
                      fontSize: 11, fontWeight: 700, padding: '2px 10px', borderRadius: 20,
                      background: report.status === 'published' ? '#f0fdf4' : '#f1f5f9',
                      color: report.status === 'published' ? '#16a34a' : '#64748b',
                    }}>
                      {report.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                    </span>
                    <span style={{ fontSize: 11, color: '#94a3b8' }}>{isOpen ? '▲' : '▼'}</span>
                  </div>
                </button>

                {isOpen && (
                  <div style={{ padding: '16px 18px' }}>
                    {report.summary && (
                      <div style={{ fontSize: 13, color: '#475569', marginBottom: 14, lineHeight: 1.5 }}>{report.summary}</div>
                    )}
                    {!report.content ? (
                      <div style={{ color: '#94a3b8', fontSize: 13 }}>Loading report content…</div>
                    ) : (
                      <SnapshotPanel snapshot={report.content.snapshot} />
                    )}
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

export default AdvisoryPage;
