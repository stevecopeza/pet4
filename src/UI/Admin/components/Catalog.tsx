import React, { useState, useEffect } from 'react';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';

interface CatalogItem {
  id: number;
  sku: string | null;
  name: string;
  type?: string;
  description: string | null;
  category: string | null;
  unit_price: number;
  unit_cost: number;
  wbs_template?: WbsTask[];
}

interface WbsTask {
  description: string;
  hours: number;
}

const Catalog = () => {
  const [items, setItems] = useState<CatalogItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingItemId, setEditingItemId] = useState<number | null>(null);

  const [activeTab, setActiveTab] = useState<'all' | 'product' | 'service'>('all');

  // Form State
  const [newName, setNewName] = useState('');
  const [newSku, setNewSku] = useState('');
  const [newDesc, setNewDesc] = useState('');
  const [newCategory, setNewCategory] = useState('');
  const [newType, setNewType] = useState('product');
  const [newPrice, setNewPrice] = useState(0);
  const [newCost, setNewCost] = useState(0);
  const [newWbsTemplate, setNewWbsTemplate] = useState<WbsTask[]>([]);

  const fetchItems = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/catalog-items`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce }
      });
      if (!response.ok) throw new Error('Failed to fetch catalog items');
      const data = await response.json();
      setItems(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchItems();
  }, []);

  useEffect(() => {
    // Update default new type based on active tab (if specific)
    if (activeTab === 'product' || activeTab === 'service') {
      setNewType(activeTab);
    }
  }, [activeTab]);

  const filteredItems = items.filter(item => {
    if (activeTab === 'all') return true;
    return item.type === activeTab;
  });

  const addWbsTask = () => {
    setNewWbsTemplate([...newWbsTemplate, { description: '', hours: 0 }]);
  };

  const updateWbsTask = (index: number, field: keyof WbsTask, value: string | number) => {
    const updated = [...newWbsTemplate];
    // @ts-ignore
    updated[index] = { ...updated[index], [field]: value };
    setNewWbsTemplate(updated);
  };

  const removeWbsTask = (index: number) => {
    setNewWbsTemplate(newWbsTemplate.filter((_, i) => i !== index));
  };

  // Check if the entered SKU already exists (excluding the item being edited)
  const skuIsDuplicate =
    newSku.trim() !== '' &&
    items.some(
      (item) =>
        item.sku !== null &&
        item.sku.toLowerCase() === newSku.trim().toLowerCase() &&
        item.id !== editingItemId
    );

  const resetForm = () => {
    setNewName('');
    setNewSku('');
    setNewDesc('');
    setNewCategory('');
    setNewType('product');
    setNewPrice(0);
    setNewCost(0);
    setNewWbsTemplate([]);
    setEditingItemId(null);
  };

  const openEditForm = (item: CatalogItem) => {
    setNewName(item.name);
    setNewSku(item.sku || '');
    setNewDesc(item.description || '');
    setNewCategory(item.category || '');
    setNewType(item.type || 'product');
    setNewPrice(item.unit_price);
    setNewCost(item.unit_cost);
    setNewWbsTemplate(item.wbs_template || []);
    setEditingItemId(item.id);
    setShowAddForm(true);
  };

  const handleSaveItem = async (e: React.FormEvent) => {
    e.preventDefault();
    if (skuIsDuplicate) return;

    const payload = {
      name: newName,
      sku: newSku || null,
      description: newDesc || null,
      category: newCategory || null,
      type: newType,
      unit_price: newPrice,
      unit_cost: newCost,
      wbs_template: newWbsTemplate,
    };

    try {
      const url = editingItemId
        ? `${window.petSettings.apiUrl}/catalog-items/${editingItemId}`
        : `${window.petSettings.apiUrl}/catalog-items`;
      const method = editingItemId ? 'PATCH' : 'POST';

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(data?.error || `Failed to ${editingItemId ? 'update' : 'create'} item`);
      }

      setShowAddForm(false);
      resetForm();
      fetchItems();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error saving item');
    }
  };

  const handleDeleteItem = async (id: number) => {
    if (!confirm('Are you sure you want to delete this catalog item?')) return;
    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/catalog-items/${id}`,
        {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': window.petSettings.nonce },
        }
      );
      if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(data?.error || 'Failed to delete item');
      }
      fetchItems();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error deleting item');
    }
  };

  const columns: Column<CatalogItem>[] = [
    { key: 'sku', header: 'SKU' },
    { key: 'name', header: 'Name' },
    { key: 'type', header: 'Type', render: (_, item) => <span style={{ textTransform: 'capitalize' }}>{item.type || 'product'}</span> },
    { key: 'category', header: 'Category' },
    { key: 'unit_price', header: 'Price', render: (_, item) => <span>${item.unit_price.toFixed(2)}</span> },
    { key: 'unit_cost', header: 'Cost', render: (_, item) => <span>${item.unit_cost.toFixed(2)}</span> },
  ];

  if (loading && items.length === 0) return <div>Loading catalog...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
        <h3>Catalog Items</h3>
        <button
          className="button button-primary"
          onClick={() => {
            if (showAddForm) {
              setShowAddForm(false);
              resetForm();
            } else {
              resetForm();
              setShowAddForm(true);
            }
          }}
        >
          {showAddForm ? 'Cancel' : 'Add Item'}
        </button>
      </div>

      {showAddForm && (
        <div className="card" style={{ padding: '20px', marginBottom: '20px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
          <h4>{editingItemId ? 'Edit Catalog Item' : 'New Catalog Item'}</h4>
          <form onSubmit={handleSaveItem} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Name *</label>
              <input type="text" className="regular-text" style={{ width: '100%' }} value={newName} onChange={e => setNewName(e.target.value)} required />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>SKU</label>
              <input
                type="text"
                className="regular-text"
                style={{
                  width: '100%',
                  ...(skuIsDuplicate ? { border: '2px solid #d63638', boxShadow: '0 0 0 1px #d63638' } : {}),
                }}
                value={newSku}
                onChange={(e) => setNewSku(e.target.value)}
              />
              {skuIsDuplicate && (
                <span style={{ color: '#d63638', fontSize: '12px', marginTop: '2px', display: 'block' }}>
                  This SKU already exists
                </span>
              )}
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Category</label>
              <input type="text" className="regular-text" style={{ width: '100%' }} value={newCategory} onChange={e => setNewCategory(e.target.value)} placeholder="e.g. Hosting, Development" />
            </div>
            <div style={{ gridColumn: '1 / -1' }}>
              <label style={{ display: 'block', marginBottom: '5px' }}>Description</label>
              <textarea className="regular-text" style={{ width: '100%', height: '60px' }} value={newDesc} onChange={e => setNewDesc(e.target.value)} />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Type</label>
              <select className="regular-text" style={{ width: '100%' }} value={newType} onChange={e => setNewType(e.target.value)}>
                <option value="product">Product</option>
                <option value="service">Service</option>
              </select>
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Unit Price *</label>
              <input type="number" step="0.01" className="regular-text" style={{ width: '100%' }} value={newPrice} onChange={e => setNewPrice(parseFloat(e.target.value))} required />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Unit Cost</label>
              <input type="number" step="0.01" className="regular-text" style={{ width: '100%' }} value={newCost} onChange={e => setNewCost(parseFloat(e.target.value))} />
            </div>
            
            {newType === 'service' && (
              <div style={{ gridColumn: '1 / -1', borderTop: '1px solid #ccc', paddingTop: '15px' }}>
                <h5 style={{ margin: '0 0 10px 0' }}>WBS Template (Tasks)</h5>
                {newWbsTemplate.map((task, index) => (
                  <div key={index} style={{ display: 'flex', gap: '10px', marginBottom: '10px' }}>
                    <input 
                      type="text" 
                      placeholder="Task Description" 
                      className="regular-text"
                      value={task.description} 
                      onChange={(e) => updateWbsTask(index, 'description', e.target.value)} 
                      style={{ flex: 2 }}
                    />
                    <input 
                      type="number" 
                      placeholder="Hours" 
                      className="regular-text"
                      value={task.hours} 
                      onChange={(e) => updateWbsTask(index, 'hours', parseFloat(e.target.value) || 0)} 
                      style={{ width: '80px' }}
                    />
                    <button type="button" className="button" onClick={() => removeWbsTask(index)}>Remove</button>
                  </div>
                ))}
                <button type="button" className="button" onClick={addWbsTask}>Add Task Definition</button>
              </div>
            )}

            <div style={{ gridColumn: '1 / -1', marginTop: '10px' }}>
              <button type="submit" className="button button-primary" disabled={skuIsDuplicate}>
                {editingItemId ? 'Update Item' : 'Save Item'}
              </button>
            </div>
          </form>
        </div>
      )}

      <DataTable
        columns={columns}
        data={filteredItems}
        emptyMessage="No items in catalog."
        onRowClick={(item) => openEditForm(item)}
        actions={(item) => (
          <KebabMenu
            items={[
              { type: 'action', label: 'Edit', onClick: () => openEditForm(item) },
              { type: 'divider' },
              { type: 'action', label: 'Delete', onClick: () => handleDeleteItem(item.id), danger: true },
            ]}
          />
        )}
      />
    </div>
  );
};

export default Catalog;
