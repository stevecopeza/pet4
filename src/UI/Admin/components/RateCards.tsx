import React, { useState, useEffect } from 'react';
import { DataTable, Column } from './DataTable';

interface RateCard {
  id: number;
  role_id: number;
  service_type_id: number;
  sell_rate: number;
  contract_id: number | null;
  valid_from: string | null;
  valid_to: string | null;
  status: string;
}

interface Role { id: number; name: string; }
interface ServiceType { id: number; name: string; status: string; }

const RateCards = () => {
  const [items, setItems] = useState<RateCard[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [serviceTypes, setServiceTypes] = useState<ServiceType[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);

  // Form
  const [roleId, setRoleId] = useState<number>(0);
  const [serviceTypeId, setServiceTypeId] = useState<number>(0);
  const [sellRate, setSellRate] = useState<number>(0);
  const [contractId, setContractId] = useState<string>('');
  const [validFrom, setValidFrom] = useState('');
  const [validTo, setValidTo] = useState('');

  const api = window.petSettings.apiUrl;
  const nonce = window.petSettings.nonce;

  const fetchAll = async () => {
    try {
      setLoading(true);
      const [rcRes, roleRes, stRes] = await Promise.all([
        fetch(`${api}/rate-cards`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${api}/roles`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${api}/service-types`, { headers: { 'X-WP-Nonce': nonce } }),
      ]);
      if (!rcRes.ok || !roleRes.ok || !stRes.ok) throw new Error('Failed to fetch');
      setItems(await rcRes.json());
      setRoles(await roleRes.json());
      setServiceTypes(await stRes.json());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchAll(); }, []);

  const roleName = (id: number) => roles.find(r => r.id === id)?.name || `#${id}`;
  const stName = (id: number) => serviceTypes.find(s => s.id === id)?.name || `#${id}`;

  const resetForm = () => {
    setRoleId(0); setServiceTypeId(0); setSellRate(0);
    setContractId(''); setValidFrom(''); setValidTo('');
    setShowForm(false);
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await fetch(`${api}/rate-cards`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({
          role_id: roleId,
          service_type_id: serviceTypeId,
          sell_rate: sellRate,
          contract_id: contractId ? parseInt(contractId) : null,
          valid_from: validFrom || null,
          valid_to: validTo || null,
        }),
      });
      if (!res.ok) { const d = await res.json(); throw new Error(d.error || 'Failed'); }
      resetForm();
      fetchAll();
    } catch (err) { alert(err instanceof Error ? err.message : 'Error'); }
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Archive this rate card?')) return;
    try {
      const res = await fetch(`${api}/rate-cards/${id}/archive`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': nonce },
      });
      if (!res.ok) throw new Error('Failed');
      fetchAll();
    } catch (err) { alert(err instanceof Error ? err.message : 'Error'); }
  };

  const columns: Column<RateCard>[] = [
    { key: 'role_id', header: 'Role', render: (_, r) => <span>{roleName(r.role_id)}</span> },
    { key: 'service_type_id', header: 'Service Type', render: (_, r) => <span>{stName(r.service_type_id)}</span> },
    { key: 'sell_rate', header: 'Sell Rate', render: (_, r) => <span>${r.sell_rate.toFixed(2)}</span> },
    { key: 'contract_id', header: 'Scope', render: (_, r) => r.contract_id ? `Contract #${r.contract_id}` : 'Global' },
    { key: 'valid_from', header: 'Valid From', render: (_, r) => <span>{r.valid_from || '—'}</span> },
    { key: 'valid_to', header: 'Valid To', render: (_, r) => <span>{r.valid_to || '—'}</span> },
    { key: 'status', header: 'Status', render: (_, r) => (
      <span style={{ padding: '2px 8px', borderRadius: '3px', fontSize: '12px', background: r.status === 'active' ? '#e7f5e7' : '#f0f0f1', color: r.status === 'active' ? '#2e7d32' : '#666' }}>
        {r.status}
      </span>
    )},
    { key: 'id', header: 'Actions', render: (_, r) => r.status === 'active' ? (
      <button className="button button-small" onClick={() => handleArchive(r.id)}>Archive</button>
    ) : null },
  ];

  if (loading && items.length === 0) return <div>Loading rate cards...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
        <h3>Rate Cards</h3>
        <button className="button button-primary" onClick={() => { resetForm(); setShowForm(!showForm); }}>
          {showForm ? 'Cancel' : 'Add Rate Card'}
        </button>
      </div>

      {showForm && (
        <div className="card" style={{ padding: '20px', marginBottom: '20px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
          <h4>New Rate Card</h4>
          <form onSubmit={handleCreate} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px', maxWidth: '700px' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Role *</label>
              <select style={{ width: '100%' }} value={roleId} onChange={e => setRoleId(parseInt(e.target.value))} required>
                <option value={0}>Select role...</option>
                {roles.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
              </select>
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Service Type *</label>
              <select style={{ width: '100%' }} value={serviceTypeId} onChange={e => setServiceTypeId(parseInt(e.target.value))} required>
                <option value={0}>Select service type...</option>
                {serviceTypes.filter(s => s.status !== 'archived').map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Sell Rate ($/hr) *</label>
              <input type="number" step="0.01" min="0.01" style={{ width: '100%' }} value={sellRate || ''} onChange={e => setSellRate(parseFloat(e.target.value) || 0)} required />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Contract ID (optional)</label>
              <input type="text" style={{ width: '100%' }} value={contractId} onChange={e => setContractId(e.target.value)} placeholder="Leave blank for global" />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Valid From</label>
              <input type="date" style={{ width: '100%' }} value={validFrom} onChange={e => setValidFrom(e.target.value)} />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Valid To</label>
              <input type="date" style={{ width: '100%' }} value={validTo} onChange={e => setValidTo(e.target.value)} />
            </div>
            <div style={{ gridColumn: '1 / -1' }}>
              <button type="submit" className="button button-primary">Create</button>
              <button type="button" className="button" style={{ marginLeft: '10px' }} onClick={resetForm}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      <DataTable data={items.filter(i => i.status === 'active')} columns={columns} />
    </div>
  );
};

export default RateCards;
