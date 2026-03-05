import React, { useState, useEffect } from 'react';
import { DataTable, Column } from './DataTable';

interface CatalogProduct {
  id: number;
  sku: string;
  name: string;
  description: string | null;
  category: string | null;
  unit_price: number;
  unit_cost: number;
  status: string;
}

const CatalogProducts = () => {
  const [items, setItems] = useState<CatalogProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editItem, setEditItem] = useState<CatalogProduct | null>(null);

  // Form
  const [sku, setSku] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState('');
  const [unitPrice, setUnitPrice] = useState(0);
  const [unitCost, setUnitCost] = useState(0);

  const api = window.petSettings.apiUrl;
  const nonce = window.petSettings.nonce;

  const fetchItems = async () => {
    try {
      setLoading(true);
      const res = await fetch(`${api}/catalog-products`, { headers: { 'X-WP-Nonce': nonce } });
      if (!res.ok) throw new Error('Failed to fetch');
      setItems(await res.json());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchItems(); }, []);

  const resetForm = () => {
    setSku(''); setName(''); setDescription(''); setCategory('');
    setUnitPrice(0); setUnitCost(0); setEditItem(null); setShowForm(false);
  };

  const openEdit = (item: CatalogProduct) => {
    setEditItem(item);
    setSku(item.sku);
    setName(item.name);
    setDescription(item.description || '');
    setCategory(item.category || '');
    setUnitPrice(item.unit_price);
    setUnitCost(item.unit_cost);
    setShowForm(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const url = editItem
      ? `${api}/catalog-products/${editItem.id}`
      : `${api}/catalog-products`;
    const method = editItem ? 'PUT' : 'POST';
    try {
      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({
          sku: editItem ? undefined : sku,
          name,
          unit_price: unitPrice,
          unit_cost: unitCost,
          description: description || null,
          category: category || null,
        }),
      });
      if (!res.ok) { const d = await res.json(); throw new Error(d.error || 'Failed'); }
      resetForm();
      fetchItems();
    } catch (err) { alert(err instanceof Error ? err.message : 'Error'); }
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Archive this product?')) return;
    try {
      const res = await fetch(`${api}/catalog-products/${id}/archive`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': nonce },
      });
      if (!res.ok) throw new Error('Failed');
      fetchItems();
    } catch (err) { alert(err instanceof Error ? err.message : 'Error'); }
  };

  const columns: Column<CatalogProduct>[] = [
    { key: 'sku', header: 'SKU' },
    { key: 'name', header: 'Name' },
    { key: 'category', header: 'Category' },
    { key: 'unit_price', header: 'Price', render: (_, r) => <span>${r.unit_price.toFixed(2)}</span> },
    { key: 'unit_cost', header: 'Cost', render: (_, r) => <span>${r.unit_cost.toFixed(2)}</span> },
    { key: 'id', header: 'Actions', render: (_, r) => (
      <div style={{ display: 'flex', gap: '8px' }}>
        <button className="button button-small" onClick={() => openEdit(r)}>Edit</button>
        <button className="button button-small" onClick={() => handleArchive(r.id)}>Archive</button>
      </div>
    )},
  ];

  if (loading && items.length === 0) return <div>Loading products...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
        <h3>Catalog Products</h3>
        <button className="button button-primary" onClick={() => { resetForm(); setShowForm(!showForm); }}>
          {showForm ? 'Cancel' : 'Add Product'}
        </button>
      </div>

      {showForm && (
        <div className="card" style={{ padding: '20px', marginBottom: '20px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
          <h4>{editItem ? 'Edit Product' : 'New Product'}</h4>
          <form onSubmit={handleSubmit} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px', maxWidth: '700px' }}>
            {!editItem && (
              <div>
                <label htmlFor="cp-sku" style={{ display: 'block', marginBottom: '5px' }}>SKU *</label>
                <input id="cp-sku" type="text" className="regular-text" style={{ width: '100%' }} value={sku} onChange={e => setSku(e.target.value)} required />
              </div>
            )}
            <div>
              <label htmlFor="cp-name" style={{ display: 'block', marginBottom: '5px' }}>Name *</label>
              <input id="cp-name" type="text" className="regular-text" style={{ width: '100%' }} value={name} onChange={e => setName(e.target.value)} required />
            </div>
            <div>
              <label htmlFor="cp-category" style={{ display: 'block', marginBottom: '5px' }}>Category</label>
              <input id="cp-category" type="text" className="regular-text" style={{ width: '100%' }} value={category} onChange={e => setCategory(e.target.value)} placeholder="e.g. Hardware, Software" />
            </div>
            <div>
              <label htmlFor="cp-unit-price" style={{ display: 'block', marginBottom: '5px' }}>Unit Price *</label>
              <input id="cp-unit-price" type="number" step="0.01" min="0" className="regular-text" style={{ width: '100%' }} value={unitPrice || ''} onChange={e => setUnitPrice(parseFloat(e.target.value) || 0)} required />
            </div>
            <div>
              <label htmlFor="cp-unit-cost" style={{ display: 'block', marginBottom: '5px' }}>Unit Cost</label>
              <input id="cp-unit-cost" type="number" step="0.01" min="0" className="regular-text" style={{ width: '100%' }} value={unitCost || ''} onChange={e => setUnitCost(parseFloat(e.target.value) || 0)} />
            </div>
            <div style={{ gridColumn: '1 / -1' }}>
              <label htmlFor="cp-description" style={{ display: 'block', marginBottom: '5px' }}>Description</label>
              <textarea id="cp-description" className="regular-text" style={{ width: '100%', height: '60px' }} value={description} onChange={e => setDescription(e.target.value)} />
            </div>
            <div style={{ gridColumn: '1 / -1' }}>
              <button type="submit" className="button button-primary">{editItem ? 'Update' : 'Create'}</button>
              <button type="button" className="button" style={{ marginLeft: '10px' }} onClick={resetForm}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      <DataTable data={items} columns={columns} />
    </div>
  );
};

export default CatalogProducts;
