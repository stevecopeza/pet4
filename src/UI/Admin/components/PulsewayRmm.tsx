import React, { useEffect, useState, useCallback } from 'react';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';
import LoadingState from './foundation/states/LoadingState';
import ErrorState from './foundation/states/ErrorState';
import EmptyState from './foundation/states/EmptyState';

declare const window: Window & {
  petSettings: { apiUrl: string; nonce: string };
};

const api = (path: string, opts: RequestInit = {}) =>
  fetch(`${window.petSettings.apiUrl}/pulseway${path}`, {
    ...opts,
    headers: {
      'X-WP-Nonce': window.petSettings.nonce,
      'Content-Type': 'application/json',
      ...((opts.headers as Record<string, string>) || {}),
    },
  }).then(async (r) => {
    const json = await r.json();
    if (!r.ok) throw new Error(json.error || `HTTP ${r.status}`);
    return json;
  });

type Tab = 'integrations' | 'mappings' | 'notifications' | 'devices' | 'rules';
type ToastApi = {
  success: (message: string) => void;
  error: (message: string) => void;
};

const tabStyle = (active: boolean): React.CSSProperties => ({
  padding: '10px 20px',
  border: 'none',
  background: active ? '#fff' : 'transparent',
  borderBottom: active ? '2px solid #007cba' : 'none',
  cursor: 'pointer',
  fontWeight: active ? 'bold' : 'normal',
  color: active ? '#000' : '#555',
  fontSize: '14px',
});

const badge = (color: string, text: string): React.CSSProperties => ({
  display: 'inline-block',
  padding: '2px 8px',
  borderRadius: '3px',
  fontSize: '11px',
  fontWeight: 600,
  color: '#fff',
  background: color,
  marginRight: '4px',
});

const PulsewayRmm = () => {
  const toast = useToast();
  const [tab, setTab] = useState<Tab>('integrations');
  const [integrations, setIntegrations] = useState<any[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchIntegrations = useCallback(async () => {
    setLoading(true);
    try {
      const data = await api('/integrations');
      setIntegrations(data);
      if (data.length > 0 && !selectedId) setSelectedId(data[0].id);
      setError(null);
    } catch (e: any) {
      console.error(e);
      setError(e instanceof Error ? e.message : 'Failed to load integrations');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchIntegrations(); }, [fetchIntegrations]);

  return (
    <div className="pet-pulseway">

      <div style={{ marginBottom: '20px', borderBottom: '1px solid #ddd' }}>
        {(['integrations', 'mappings', 'notifications', 'devices', 'rules'] as Tab[]).map((t) => (
          <button key={t} onClick={() => setTab(t)} style={tabStyle(tab === t)}>
            {t === 'integrations' ? 'Connections' : t === 'mappings' ? 'Org Mappings' : t === 'rules' ? 'Ticket Rules' : t.charAt(0).toUpperCase() + t.slice(1)}
          </button>
        ))}
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchIntegrations} />
      ) : (
        <>
          {tab === 'integrations' && <IntegrationsTab integrations={integrations} onRefresh={fetchIntegrations} toast={toast} />}
          {tab === 'mappings' && selectedId && <MappingsTab integrationId={selectedId} toast={toast} />}
          {tab === 'notifications' && selectedId && <NotificationsTab integrationId={selectedId} />}
          {tab === 'devices' && selectedId && <DevicesTab integrationId={selectedId} />}
          {tab === 'rules' && selectedId && <RulesTab integrationId={selectedId} toast={toast} />}
          {(tab !== 'integrations') && !selectedId && <EmptyState message="No integration selected. Create one first." />}

          {integrations.length > 1 && tab !== 'integrations' && (
            <div style={{ marginTop: '16px', padding: '8px', background: '#f9f9f9', borderRadius: '4px' }}>
              <label style={{ marginRight: '8px', fontWeight: 600 }}>Integration:</label>
              <select value={selectedId ?? ''} onChange={(e) => setSelectedId(Number(e.target.value))}>
                {integrations.map((i: any) => <option key={i.id} value={i.id}>{i.label}</option>)}
              </select>
            </div>
          )}
        </>
      )}
    </div>
  );
};

// ─── Integrations Tab ────────────────────────────────────────────

const IntegrationsTab = ({ integrations, onRefresh, toast }: { integrations: any[]; onRefresh: () => void; toast: ToastApi }) => {
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ label: '', api_base_url: 'https://api.pulseway.com/v3', token_id: '', token_secret: '', poll_interval_seconds: 300 });
  const [busy, setBusy] = useState(false);
  const [pendingResetId, setPendingResetId] = useState<number | null>(null);
  const [resetBusy, setResetBusy] = useState(false);

  const handleCreate = async () => {
    if (!form.label || !form.token_id || !form.token_secret) {
      toast.error('Label, Token ID and Token Secret are required');
      return;
    }
    setBusy(true);
    try {
      await api('/integrations', { method: 'POST', body: JSON.stringify(form) });
      toast.success('Integration created');
      setShowForm(false);
      setForm({ label: '', api_base_url: 'https://api.pulseway.com/v3', token_id: '', token_secret: '', poll_interval_seconds: 300 });
      onRefresh();
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      setBusy(false);
    }
  };

  const handleTest = async (id: number) => {
    try {
      const res = await api(`/integrations/${id}/test`, { method: 'POST' });
      toast.success(`Test: ${res.status} — ${res.message}`);
      onRefresh();
    } catch (e: any) {
      toast.error(e.message);
    }
  };

  const handlePoll = async (id: number) => {
    try {
      const res = await api(`/integrations/${id}/poll`, { method: 'POST' });
      toast.success(`Poll: ${res.status ?? 'done'} — ingested ${res.ingested ?? 0}, dupes ${res.duplicates ?? 0}`);
      onRefresh();
    } catch (e: any) {
      toast.error(e.message);
    }
  };

  const handleSync = async (id: number) => {
    try {
      const res = await api(`/integrations/${id}/sync-devices`, { method: 'POST' });
      toast.success(`Sync: ${res.status ?? 'done'} — ${res.devices_synced ?? 0} devices`);
      onRefresh();
    } catch (e: any) {
      toast.error(e.message);
    }
  };

  const handleResetCircuit = async (id: number) => {
    setResetBusy(true);
    try {
      await api(`/integrations/${id}/reset-circuit`, { method: 'POST' });
      toast.success('Circuit breaker reset');
      onRefresh();
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      setResetBusy(false);
      setPendingResetId(null);
    }
  };

  const handleToggle = async (id: number, currentActive: boolean) => {
    try {
      await api(`/integrations/${id}`, { method: 'PUT', body: JSON.stringify({ is_active: !currentActive }) });
      toast.success(currentActive ? 'Integration disabled.' : 'Integration enabled.');
      onRefresh();
    } catch (e: any) {
      toast.error(e.message);
    }
  };

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
        <h3 style={{ margin: 0 }}>Pulseway Integrations</h3>
        <button className="button button-primary" onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Cancel' : '+ Add Integration'}
        </button>
      </div>

      {showForm && (
        <div style={{ background: '#f9f9f9', padding: '16px', borderRadius: '4px', marginBottom: '16px', border: '1px solid #ddd' }}>
          <h4 style={{ marginTop: 0 }}>New Integration</h4>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', maxWidth: '700px' }}>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px' }}>Label *</label>
              <input type="text" value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} style={{ width: '100%' }} placeholder="e.g. Production" />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px' }}>API Base URL</label>
              <input type="text" value={form.api_base_url} onChange={(e) => setForm({ ...form, api_base_url: e.target.value })} style={{ width: '100%' }} />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px' }}>Token ID *</label>
              <input type="text" value={form.token_id} onChange={(e) => setForm({ ...form, token_id: e.target.value })} style={{ width: '100%' }} placeholder="Pulseway API Token ID" />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px' }}>Token Secret *</label>
              <input type="password" value={form.token_secret} onChange={(e) => setForm({ ...form, token_secret: e.target.value })} style={{ width: '100%' }} placeholder="Pulseway API Token Secret" />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px' }}>Poll Interval (seconds)</label>
              <input type="number" value={form.poll_interval_seconds} onChange={(e) => setForm({ ...form, poll_interval_seconds: Number(e.target.value) })} style={{ width: '120px' }} min={60} />
            </div>
          </div>
          <div style={{ marginTop: '12px' }}>
            <button className="button button-primary" onClick={handleCreate} disabled={busy}>
              {busy ? 'Creating…' : 'Create Integration'}
            </button>
          </div>
        </div>
      )}

      {integrations.length === 0 ? (
        <EmptyState message="No integrations configured. Click &quot;Add Integration&quot; to get started." />
      ) : (
        <table className="widefat striped" style={{ marginTop: '8px' }}>
          <thead>
            <tr>
              <th>Label</th>
              <th>Status</th>
              <th>Last Poll</th>
              <th>Last Success</th>
              <th>Health</th>
              <th style={{ width: '280px' }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {integrations.map((i: any) => {
              const failures = Number(i.consecutive_failures || 0);
              const isCircuitOpen = failures >= 6;
              const isActive = Number(i.is_active) === 1;
              return (
                <tr key={i.id}>
                  <td><strong>{i.label}</strong><br /><span style={{ fontSize: '11px', color: '#888' }}>{i.api_base_url}</span></td>
                  <td>
                    <span style={badge(isActive ? '#4caf50' : '#999', isActive ? 'Active' : 'Inactive')} />
                    {isCircuitOpen && <span style={badge('#f44336', 'Circuit Open')} />}
                  </td>
                  <td style={{ fontSize: '12px' }}>{i.last_poll_at || '—'}</td>
                  <td style={{ fontSize: '12px' }}>{i.last_success_at || '—'}</td>
                  <td>
                    {failures > 0 ? (
                      <span style={{ color: '#f44336', fontWeight: 600 }}>{failures} failure{failures > 1 ? 's' : ''}</span>
                    ) : (
                      <span style={{ color: '#4caf50' }}>Healthy</span>
                    )}
                    {i.last_error_message && <br />}
                    {i.last_error_message && <span style={{ fontSize: '11px', color: '#999' }}>{String(i.last_error_message).substring(0, 80)}</span>}
                  </td>
                  <td>
                    <button className="button button-small" onClick={() => handleTest(i.id)} title="Test Connection">Test</button>{' '}
                    <button className="button button-small" onClick={() => handlePoll(i.id)} title="Poll Notifications Now">Poll</button>{' '}
                    <button className="button button-small" onClick={() => handleSync(i.id)} title="Sync Devices Now">Sync</button>{' '}
                    <button className="button button-small" onClick={() => handleToggle(i.id, isActive)}>{isActive ? 'Disable' : 'Enable'}</button>{' '}
                    {isCircuitOpen && <button className="button button-small" onClick={() => setPendingResetId(i.id)} style={{ color: '#f44336' }}>Reset Circuit</button>}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}

      <ConfirmationDialog
        open={pendingResetId !== null}
        title="Reset circuit breaker?"
        description="Polling will resume for this integration."
        confirmLabel="Reset circuit"
        busy={resetBusy}
        onCancel={() => setPendingResetId(null)}
        onConfirm={() => {
          if (pendingResetId !== null) {
            handleResetCircuit(pendingResetId);
          }
        }}
      />
    </div>
  );
};

// ─── Org Mappings Tab ────────────────────────────────────────────

const MappingsTab = ({ integrationId, toast }: { integrationId: number; toast: ToastApi }) => {
  const [mappings, setMappings] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ pulseway_org_id: '', pulseway_site_id: '', pulseway_group_id: '', pet_customer_id: '', pet_site_id: '', pet_team_id: '' });

  const fetchMappings = async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api(`/integrations/${integrationId}/mappings`);
      setMappings(data);
    } catch (e: any) {
      setError(e.message);
    }
    finally { setLoading(false); }
  };

  useEffect(() => { fetchMappings(); }, [integrationId]);

  const handleCreate = async () => {
    try {
      const body: any = {};
      if (form.pulseway_org_id) body.pulseway_org_id = form.pulseway_org_id;
      if (form.pulseway_site_id) body.pulseway_site_id = form.pulseway_site_id;
      if (form.pulseway_group_id) body.pulseway_group_id = form.pulseway_group_id;
      if (form.pet_customer_id) body.pet_customer_id = Number(form.pet_customer_id);
      if (form.pet_site_id) body.pet_site_id = Number(form.pet_site_id);
      if (form.pet_team_id) body.pet_team_id = Number(form.pet_team_id);
      await api(`/integrations/${integrationId}/mappings`, { method: 'POST', body: JSON.stringify(body) });
      toast.success('Mapping created');
      setShowForm(false);
      setForm({ pulseway_org_id: '', pulseway_site_id: '', pulseway_group_id: '', pet_customer_id: '', pet_site_id: '', pet_team_id: '' });
      await fetchMappings();
    } catch (e: any) {
      toast.error(e.message);
    }
  };

  if (loading) return <LoadingState label="Loading mappings…" />;
  if (error) return <ErrorState message={error} onRetry={fetchMappings} />;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
        <h3 style={{ margin: 0 }}>Organisation Mappings</h3>
        <button className="button button-primary" onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Cancel' : '+ Add Mapping'}
        </button>
      </div>
      <p style={{ color: '#666', marginTop: 0 }}>Map Pulseway organisations, sites, and groups to PET customers, sites, and teams. This determines where tickets are routed.</p>

      {showForm && (
        <div style={{ background: '#f9f9f9', padding: '16px', borderRadius: '4px', marginBottom: '16px', border: '1px solid #ddd' }}>
          <h4 style={{ marginTop: 0 }}>New Mapping</h4>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '12px', maxWidth: '800px' }}>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Pulseway Org ID</label>
              <input type="text" value={form.pulseway_org_id} onChange={(e) => setForm({ ...form, pulseway_org_id: e.target.value })} style={{ width: '100%' }} />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Pulseway Site ID</label>
              <input type="text" value={form.pulseway_site_id} onChange={(e) => setForm({ ...form, pulseway_site_id: e.target.value })} style={{ width: '100%' }} />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Pulseway Group ID</label>
              <input type="text" value={form.pulseway_group_id} onChange={(e) => setForm({ ...form, pulseway_group_id: e.target.value })} style={{ width: '100%' }} />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>PET Customer ID</label>
              <input type="number" value={form.pet_customer_id} onChange={(e) => setForm({ ...form, pet_customer_id: e.target.value })} style={{ width: '100%' }} />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>PET Site ID</label>
              <input type="number" value={form.pet_site_id} onChange={(e) => setForm({ ...form, pet_site_id: e.target.value })} style={{ width: '100%' }} />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>PET Team ID</label>
              <input type="number" value={form.pet_team_id} onChange={(e) => setForm({ ...form, pet_team_id: e.target.value })} style={{ width: '100%' }} />
            </div>
          </div>
          <div style={{ marginTop: '12px' }}>
            <button className="button button-primary" onClick={handleCreate}>Create Mapping</button>
          </div>
        </div>
      )}

      {mappings.length === 0 ? (
        <EmptyState message="No mappings configured yet." />
      ) : (
        <table className="widefat striped">
          <thead>
            <tr>
              <th>Pulseway Org</th>
              <th>Pulseway Site</th>
              <th>Pulseway Group</th>
              <th>→ PET Customer</th>
              <th>→ PET Site</th>
              <th>→ PET Team</th>
              <th>Active</th>
            </tr>
          </thead>
          <tbody>
            {mappings.map((m: any) => (
              <tr key={m.id}>
                <td>{m.pulseway_org_id || '(any)'}</td>
                <td>{m.pulseway_site_id || '(any)'}</td>
                <td>{m.pulseway_group_id || '(any)'}</td>
                <td>{m.pet_customer_id || '—'}</td>
                <td>{m.pet_site_id || '—'}</td>
                <td>{m.pet_team_id || '—'}</td>
                <td>{Number(m.is_active) ? '✓' : '✗'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

// ─── Notifications Tab ──────────────────────────────────────────

const NotificationsTab = ({ integrationId }: { integrationId: number }) => {
  const [notifications, setNotifications] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchNotifications = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api(`/integrations/${integrationId}/notifications?limit=100`);
      setNotifications(data);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [integrationId]);

  useEffect(() => { fetchNotifications(); }, [fetchNotifications]);

  if (loading) return <LoadingState label="Loading notifications…" />;
  if (error) return <ErrorState message={error} onRetry={fetchNotifications} />;

  return (
    <div>
      <h3 style={{ marginTop: 0 }}>Recent Notifications</h3>
      {notifications.length === 0 ? (
        <EmptyState message="No notifications ingested yet. Run a poll first." />
      ) : (
        <table className="widefat striped">
          <thead>
            <tr>
              <th style={{ width: '140px' }}>Received</th>
              <th>Severity</th>
              <th>Category</th>
              <th>Title</th>
              <th>Device</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {notifications.map((n: any) => (
              <tr key={n.id}>
                <td style={{ fontSize: '12px' }}>{n.received_at}</td>
                <td>
                  {n.severity && (
                    <span style={badge(
                      n.severity === 'Critical' ? '#f44336' : n.severity === 'Elevated' ? '#ff9800' : '#2196f3',
                      n.severity
                    )}>{n.severity}</span>
                  )}
                </td>
                <td style={{ fontSize: '12px' }}>{n.category || '—'}</td>
                <td>{n.title}</td>
                <td style={{ fontSize: '12px' }}>{n.device_external_id || '—'}</td>
                <td>
                  <span style={badge(
                    n.routing_status === 'routed' ? '#4caf50' : n.routing_status === 'unroutable' ? '#f44336' : '#ff9800',
                    n.routing_status
                  )}>{n.routing_status}</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

// ─── Devices Tab ────────────────────────────────────────────────

const DevicesTab = ({ integrationId }: { integrationId: number }) => {
  const [devices, setDevices] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDevices = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api(`/integrations/${integrationId}/devices`);
      setDevices(data);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [integrationId]);

  useEffect(() => { fetchDevices(); }, [fetchDevices]);

  if (loading) return <LoadingState label="Loading devices…" />;
  if (error) return <ErrorState message={error} onRetry={fetchDevices} />;

  return (
    <div>
      <h3 style={{ marginTop: 0 }}>Monitored Devices ({devices.length})</h3>
      {devices.length === 0 ? (
        <EmptyState message="No devices synced yet. Run a device sync first." />
      ) : (
        <table className="widefat striped">
          <thead>
            <tr>
              <th>Name</th>
              <th>Platform</th>
              <th>Status</th>
              <th>Last Seen</th>
              <th>Org</th>
              <th>Site</th>
            </tr>
          </thead>
          <tbody>
            {devices.map((d: any) => (
              <tr key={d.id}>
                <td><strong>{d.display_name}</strong></td>
                <td>{d.platform || '—'}</td>
                <td>
                  {d.status && (
                    <span style={badge(
                      d.status === 'Online' ? '#4caf50' : d.status === 'Offline' ? '#f44336' : '#ff9800',
                      d.status
                    )}>{d.status}</span>
                  )}
                </td>
                <td style={{ fontSize: '12px' }}>{d.last_seen_at || '—'}</td>
                <td style={{ fontSize: '12px' }}>{d.external_org_id || '—'}</td>
                <td style={{ fontSize: '12px' }}>{d.external_site_id || '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

// ─── Ticket Rules Tab ───────────────────────────────────────────

const RulesTab = ({ integrationId, toast }: { integrationId: number; toast: ToastApi }) => {
  const [rules, setRules] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    rule_name: '', match_severity: '', match_category: '',
    output_ticket_kind: 'incident', output_priority: 'medium',
    output_queue_id: '', output_owner_user_id: '', sort_order: 0,
  });

  const fetchRules = async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api(`/integrations/${integrationId}/rules`);
      setRules(data);
    } catch (e: any) {
      setError(e.message);
    }
    finally { setLoading(false); }
  };

  useEffect(() => { fetchRules(); }, [integrationId]);

  const handleCreate = async () => {
    try {
      const body: any = { rule_name: form.rule_name, output_ticket_kind: form.output_ticket_kind, output_priority: form.output_priority, sort_order: form.sort_order };
      if (form.match_severity) body.match_severity = form.match_severity;
      if (form.match_category) body.match_category = form.match_category;
      if (form.output_queue_id) body.output_queue_id = form.output_queue_id;
      if (form.output_owner_user_id) body.output_owner_user_id = form.output_owner_user_id;
      await api(`/integrations/${integrationId}/rules`, { method: 'POST', body: JSON.stringify(body) });
      toast.success('Rule created');
      setShowForm(false);
      await fetchRules();
    } catch (e: any) {
      toast.error(e.message);
    }
  };

  if (loading) return <LoadingState label="Loading rules…" />;
  if (error) return <ErrorState message={error} onRetry={fetchRules} />;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
        <h3 style={{ margin: 0 }}>Ticket Creation Rules</h3>
        <button className="button button-primary" onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Cancel' : '+ Add Rule'}
        </button>
      </div>
      <p style={{ color: '#666', marginTop: 0 }}>Rules are evaluated in sort order (lowest first). First matching rule wins. Notifications that match no rule will not create tickets.</p>

      {showForm && (
        <div style={{ background: '#f9f9f9', padding: '16px', borderRadius: '4px', marginBottom: '16px', border: '1px solid #ddd' }}>
          <h4 style={{ marginTop: 0 }}>New Rule</h4>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '12px', maxWidth: '800px' }}>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Rule Name *</label>
              <input type="text" value={form.rule_name} onChange={(e) => setForm({ ...form, rule_name: e.target.value })} style={{ width: '100%' }} placeholder="e.g. Critical alerts" />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Match Severity</label>
              <select value={form.match_severity} onChange={(e) => setForm({ ...form, match_severity: e.target.value })} style={{ width: '100%' }}>
                <option value="">(any)</option>
                <option value="Critical">Critical</option>
                <option value="Elevated">Elevated</option>
                <option value="Normal">Normal</option>
                <option value="Low">Low</option>
              </select>
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Match Category</label>
              <input type="text" value={form.match_category} onChange={(e) => setForm({ ...form, match_category: e.target.value })} style={{ width: '100%' }} placeholder="Optional" />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Ticket Kind</label>
              <select value={form.output_ticket_kind} onChange={(e) => setForm({ ...form, output_ticket_kind: e.target.value })} style={{ width: '100%' }}>
                <option value="incident">Incident</option>
                <option value="support">Support</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Ticket Priority</label>
              <select value={form.output_priority} onChange={(e) => setForm({ ...form, output_priority: e.target.value })} style={{ width: '100%' }}>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Sort Order</label>
              <input type="number" value={form.sort_order} onChange={(e) => setForm({ ...form, sort_order: Number(e.target.value) })} style={{ width: '80px' }} />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Queue / Team ID</label>
              <input type="text" value={form.output_queue_id} onChange={(e) => setForm({ ...form, output_queue_id: e.target.value })} style={{ width: '100%' }} />
            </div>
            <div>
              <label style={{ display: 'block', fontWeight: 600, marginBottom: '4px', fontSize: '12px' }}>Owner / Assignee ID</label>
              <input type="text" value={form.output_owner_user_id} onChange={(e) => setForm({ ...form, output_owner_user_id: e.target.value })} style={{ width: '100%' }} />
            </div>
          </div>
          <div style={{ marginTop: '12px' }}>
            <button className="button button-primary" onClick={handleCreate}>Create Rule</button>
          </div>
        </div>
      )}

      {rules.length === 0 ? (
        <EmptyState message="No ticket rules configured. This is fine for Phase 1 — rules are used in Phase 2 (ticket auto-creation)." />
      ) : (
        <table className="widefat striped">
          <thead>
            <tr>
              <th>Order</th>
              <th>Name</th>
              <th>Severity</th>
              <th>Category</th>
              <th>Kind</th>
              <th>Priority</th>
              <th>Queue</th>
              <th>Owner</th>
              <th>Active</th>
            </tr>
          </thead>
          <tbody>
            {rules.map((r: any) => (
              <tr key={r.id}>
                <td>{r.sort_order}</td>
                <td><strong>{r.rule_name}</strong></td>
                <td>{r.match_severity || '(any)'}</td>
                <td>{r.match_category || '(any)'}</td>
                <td>{r.output_ticket_kind}</td>
                <td>{r.output_priority}</td>
                <td>{r.output_queue_id || '—'}</td>
                <td>{r.output_owner_user_id || '—'}</td>
                <td>{Number(r.is_active) ? '✓' : '✗'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default PulsewayRmm;
