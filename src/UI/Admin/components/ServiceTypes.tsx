import React, { useState, useEffect } from 'react';
import { DataTable, Column } from './DataTable';

interface ServiceType {
  id: number;
  name: string;
  description: string | null;
  status: string;
  created_at: string;
}

const ServiceTypes = () => {
  const [items, setItems] = useState<ServiceType[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editItem, setEditItem] = useState<ServiceType | null>(null);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');

  const fetchItems = async () => {
    try {
      setLoading(true);
      const res = await fetch(`${window.petSettings.apiUrl}/service-types`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce }
      });
      if (!res.ok) throw new Error('Failed to fetch');
      setItems(await res.json());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchItems(); }, []);

  const resetForm = () => { setName(''); setDescription(''); setEditItem(null); setShowForm(false); };

  const openEdit = (item: ServiceType) => {
    setEditItem(item);
    setName(item.name);
    setDescription(item.description || '');
    setShowForm(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const url = editItem
      ? `${window.petSettings.apiUrl}/service-types/${editItem.id}`
      : `${window.petSettings.apiUrl}/service-types`;
    const method = editItem ? 'PUT' : 'POST';
    try {
      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.petSettings.nonce },
        body: JSON.stringify({ name, description: description || null }),
      });
      if (!res.ok) { const d = await res.json(); throw new Error(d.error || 'Failed'); }
      resetForm();
      fetchItems();
    } catch (err) { alert(err instanceof Error ? err.message : 'Error'); }
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Archive this service type?')) return;
    try {
      const res = await fetch(`${window.petSettings.apiUrl}/service-types/${id}`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      });
      if (!res.ok) throw new Error('Failed');
      fetchItems();
    } catch (err) { alert(err instanceof Error ? err.message : 'Error'); }
  };

  const columns: Column<ServiceType>[] = [
    { key: 'name', header: 'Name' },
    { key: 'description', header: 'Description' },
    { key: 'status', header: 'Status', render: (_, r) => (
      <span style={{ padding: '2px 8px', borderRadius: '3px', fontSize: '12px', background: r.status === 'active' ? '#e7f5e7' : '#f0f0f1', color: r.status === 'active' ? '#2e7d32' : '#666' }}>
        {r.status}
      </span>
    )},
    { key: 'id', header: 'Actions', render: (_, r) => r.status === 'active' ? (
      <div style={{ display: 'flex', gap: '8px' }}>
        <button className="button button-small" onClick={() => openEdit(r)}>Edit</button>
        <button className="button button-small" onClick={() => handleArchive(r.id)}>Archive</button>
      </div>
    ) : null },
  ];

  if (loading && items.length === 0) return <div>Loading service types...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
        <h3>Service Types</h3>
        <button className="button button-primary" onClick={() => { resetForm(); setShowForm(!showForm); }}>
          {showForm ? 'Cancel' : 'Add Service Type'}
        </button>
      </div>

      {showForm && (
        <div className="card" style={{ padding: '20px', marginBottom: '20px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
          <h4>{editItem ? 'Edit Service Type' : 'New Service Type'}</h4>
          <form onSubmit={handleSubmit} style={{ display: 'grid', gap: '15px', maxWidth: '500px' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Name *</label>
              <input type="text" className="regular-text" style={{ width: '100%' }} value={name} onChange={e => setName(e.target.value)} required />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Description</label>
              <textarea className="regular-text" style={{ width: '100%', height: '60px' }} value={description} onChange={e => setDescription(e.target.value)} />
            </div>
            <div>
              <button type="submit" className="button button-primary">{editItem ? 'Update' : 'Create'}</button>
              <button type="button" className="button" style={{ marginLeft: '10px' }} onClick={resetForm}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      <DataTable data={items.filter(i => i.status === 'active')} columns={columns} />
    </div>
  );
};

export default ServiceTypes;
