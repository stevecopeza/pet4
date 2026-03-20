import React, { useEffect, useState, useCallback } from 'react';
import '../dashboard-styles.css';

type Customer = { id: number; name: string };

type Signal = {
  id: string;
  signal_type: string;
  severity: string;
  status: string;
  title: string | null;
  summary: string | null;
  message: string | null;
  source_entity_type: string | null;
  source_entity_id: string | null;
  customer_id: number | null;
  created_at: string;
};

type ReportListRow = {
  id: string;
  report_type: string;
  scope_type: string;
  scope_id: number;
  version_number: number;
  title: string;
  summary: string | null;
  status: string;
  generated_at: string;
  generated_by: number | null;
};

type ReportDetail = ReportListRow & {
  content: any;
  source_snapshot_metadata: any;
};

type Tab = 'reports' | 'signals';

const severityClass = (sev: string): string => {
  const s = sev.toLowerCase();
  if (s === 'critical' || s === 'high') return 'severity-critical';
  if (s === 'medium' || s === 'warning') return 'severity-medium';
  return '';
};

const Advisory = () => {
  const [tab, setTab] = useState<Tab>('signals');
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [customerId, setCustomerId] = useState<string>('');
  const [reportType, setReportType] = useState<string>('customer_advisory_summary');
  const [reports, setReports] = useState<ReportListRow[]>([]);
  const [selected, setSelected] = useState<ReportDetail | null>(null);
  const [signals, setSignals] = useState<Signal[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // @ts-ignore
  const apiUrl: string = window.petSettings?.apiUrl ?? '';
  // @ts-ignore
  const nonce: string = window.petSettings?.nonce ?? '';
  const hdrs = () => ({ 'X-WP-Nonce': nonce });
  const jsonHdrs = () => ({ 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' });

  /* --- Fetch customers for selector --- */
  const fetchCustomers = useCallback(async () => {
    try {
      const res = await fetch(`${apiUrl}/customers`, { headers: hdrs() });
      if (res.ok) {
        const data = await res.json();
        setCustomers(Array.isArray(data) ? data : []);
      }
    } catch { /* non-critical */ }
  }, []);

  useEffect(() => { fetchCustomers(); }, []);

  /* --- Fetch signals --- */
  const fetchSignals = useCallback(async () => {
    setError(null);
    setLoading(true);
    try {
      const res = await fetch(`${apiUrl}/advisory/signals/recent?limit=50`, { headers: hdrs() });
      if (!res.ok) throw new Error(await res.text());
      setSignals(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to fetch signals');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { if (tab === 'signals') fetchSignals(); }, [tab]);

  /* --- Reports --- */
  const fetchReports = async () => {
    if (!customerId) return;
    setSelected(null);
    setError(null);
    setLoading(true);
    try {
      const url = `${apiUrl}/advisory/reports?customer_id=${encodeURIComponent(customerId)}&report_type=${encodeURIComponent(reportType)}`;
      const res = await fetch(url, { headers: hdrs() });
      if (!res.ok) throw new Error(await res.text());
      setReports(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to fetch reports');
    } finally {
      setLoading(false);
    }
  };

  const generateReport = async () => {
    if (!customerId) return;
    setError(null);
    setLoading(true);
    try {
      const res = await fetch(`${apiUrl}/advisory/reports/generate`, {
        method: 'POST', headers: jsonHdrs(),
        body: JSON.stringify({ customerId: Number(customerId), reportType }),
      });
      if (!res.ok) throw new Error(await res.text());
      await fetchReports();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to generate report');
    } finally {
      setLoading(false);
    }
  };

  const openReport = async (id: string) => {
    setError(null);
    setLoading(true);
    try {
      const res = await fetch(`${apiUrl}/advisory/reports/${encodeURIComponent(id)}`, { headers: hdrs() });
      if (!res.ok) throw new Error(await res.text());
      setSelected(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load report');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { setReports([]); setSelected(null); }, [reportType]);

  /* ============================================================
     Render
     ============================================================ */
  return (
    <div className="pet-dashboards-fullscreen">
      <div className="pd-header">
        <div>
          <h1>Advisory Layer</h1>
          <div className="pd-header-subtitle">Signals &amp; Reports</div>
        </div>
      </div>

      {/* Tabs */}
      <div className="pd-tabs">
        <button type="button" className={`pd-tab${tab === 'reports' ? ' active' : ''}`} onClick={() => setTab('reports')}>Reports</button>
        <button type="button" className={`pd-tab${tab === 'signals' ? ' active' : ''}`} onClick={() => setTab('signals')}>Signals</button>
      </div>

      <div className="pd-content">
        {error && <div className="pd-error" style={{ marginBottom: 16 }}>{error}</div>}

        {/* ---- Reports tab ---- */}
        {tab === 'reports' && (
          <>
            {/* Controls */}
            <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginBottom: 20, flexWrap: 'wrap' }}>
              <div>
                <div className="pd-kpi-label" style={{ marginBottom: 4 }}>Customer</div>
                <select
                  className="pd-assign-select"
                  value={customerId}
                  onChange={(e) => setCustomerId(e.target.value)}
                  style={{ minWidth: 220 }}
                >
                  <option value="">\u2014 select customer \u2014</option>
                  {customers.map((c) => (
                    <option key={c.id} value={String(c.id)}>{c.name} (#{c.id})</option>
                  ))}
                </select>
              </div>
              <div>
                <div className="pd-kpi-label" style={{ marginBottom: 4 }}>Report Type</div>
                <select className="pd-assign-select" value={reportType} onChange={(e) => setReportType(e.target.value)} style={{ minWidth: 260 }}>
                  <option value="customer_advisory_summary">Customer Advisory Summary</option>
                </select>
              </div>
              <div style={{ alignSelf: 'flex-end', display: 'flex', gap: 8 }}>
                <button type="button" className="pd-refresh-btn" style={{ background: 'rgba(13,110,253,0.1)', color: '#0d6efd', borderColor: '#b6d4fe' }} disabled={loading || !customerId} onClick={fetchReports}>
                  Load
                </button>
                <button type="button" className="pd-log-work-btn" disabled={loading || !customerId} onClick={generateReport}>
                  Generate Report
                </button>
              </div>
            </div>

            {loading && <div className="pd-loading"><div className="pd-spinner" /></div>}

            {/* Report list + detail */}
            <div style={{ display: 'grid', gridTemplateColumns: selected ? '1fr 1fr' : '1fr', gap: 16 }}>
              <div className="pd-card">
                <div className="pd-card-header">
                  <div className="pd-card-title">Reports</div>
                  <div className="pd-card-metric">{reports.length}</div>
                </div>
                {reports.length === 0 && <div className="pd-empty">No reports loaded.</div>}
                <div className="pd-list">
                  {reports.map((r) => (
                    <div
                      key={r.id}
                      className="pd-list-item pd-clickable"
                      onClick={() => openReport(r.id)}
                      style={{ cursor: 'pointer' }}
                    >
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                        <div className="pd-list-title">v{r.version_number} \u2014 {r.title}</div>
                        <span style={{ fontSize: '0.72rem', color: '#888' }}>{new Date(r.generated_at).toLocaleDateString()}</span>
                      </div>
                      {r.summary && <div className="pd-list-sub">{r.summary}</div>}
                      <div className="pd-list-sub" style={{ marginTop: 4 }}>
                        <span style={{ fontWeight: 600, textTransform: 'uppercase', fontSize: '0.68rem', letterSpacing: '0.04em', color: r.status === 'published' ? '#28a745' : '#888' }}>{r.status}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {selected && (
                <div className="pd-card pd-info">
                  <div className="pd-card-header">
                    <div className="pd-card-title">Report v{selected.version_number}</div>
                    <button type="button" className="pd-refresh-btn" onClick={() => setSelected(null)} style={{ color: '#666', borderColor: '#d0d5dd', fontSize: '0.72rem' }}>Close</button>
                  </div>
                  <div className="pd-breakdown">
                    <div className="pd-breakdown-row">
                      <div className="pd-breakdown-label">Title</div>
                      <div className="pd-breakdown-value">{selected.title}</div>
                    </div>
                    {selected.summary && (
                      <div className="pd-breakdown-row">
                        <div className="pd-breakdown-label">Summary</div>
                        <div className="pd-breakdown-value" style={{ color: '#333', fontWeight: 400 }}>{selected.summary}</div>
                      </div>
                    )}
                    <div className="pd-breakdown-row">
                      <div className="pd-breakdown-label">Generated</div>
                      <div className="pd-breakdown-value">{new Date(selected.generated_at).toLocaleString()}</div>
                    </div>
                    <div className="pd-breakdown-row">
                      <div className="pd-breakdown-label">Status</div>
                      <div className="pd-breakdown-value">{selected.status}</div>
                    </div>
                  </div>
                  {selected.content && (
                    <>
                      <div className="pd-card-title" style={{ fontSize: '0.82rem', marginBottom: 8 }}>Content</div>
                      <pre className="pd-card-pre" style={{ maxHeight: 400 }}>{JSON.stringify(selected.content, null, 2)}</pre>
                    </>
                  )}
                </div>
              )}
            </div>
          </>
        )}

        {/* ---- Signals tab ---- */}
        {tab === 'signals' && (
          <>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
              <div className="pd-section-title" style={{ margin: 0 }}>
                Recent Signals
                <span className="pd-badge" style={{ marginLeft: 8 }}>{signals.length}</span>
              </div>
              <button type="button" className="pd-refresh-btn" onClick={fetchSignals} disabled={loading} style={{ background: 'rgba(13,110,253,0.1)', color: '#0d6efd', borderColor: '#b6d4fe' }}>
                {loading ? 'Loading\u2026' : '\u21BB Refresh'}
              </button>
            </div>

            {signals.length === 0 && !loading && <div className="pd-empty">No active signals.</div>}

            <div className="pd-signal-list">
              {signals.map((s) => (
                <div key={s.id} className={`pd-signal-item ${severityClass(s.severity)}`}>
                  <div className="pd-signal-type">{s.signal_type.replace(/_/g, ' ')}</div>
                  <div className="pd-signal-message">
                    <strong>{s.title || s.signal_type}</strong>
                    {s.summary && <span style={{ color: '#666' }}> \u2014 {s.summary}</span>}
                    {!s.summary && s.message && <span style={{ color: '#666' }}> \u2014 {s.message}</span>}
                  </div>
                  <div style={{ display: 'flex', gap: 12, marginTop: 4, fontSize: '0.75rem', color: '#888' }}>
                    {s.source_entity_type && <span>{s.source_entity_type} #{s.source_entity_id}</span>}
                    {s.customer_id && <span>Customer #{s.customer_id}</span>}
                    <span>{new Date(s.created_at).toLocaleString()}</span>
                    <span style={{ fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.04em', color: s.status === 'ACTIVE' ? '#28a745' : '#adb5bd' }}>{s.status}</span>
                  </div>
                </div>
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
};

export default Advisory;

