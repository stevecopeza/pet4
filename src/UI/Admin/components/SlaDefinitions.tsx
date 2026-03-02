import React, { useEffect, useState } from 'react';
import { Sla } from '../types';
import { DataTable, Column } from './DataTable';
import SlaDefinitionForm from './SlaDefinitionForm';

const SlaDefinitions = () => {
  const [slas, setSlas] = useState<Sla[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingSla, setEditingSla] = useState<Sla | null>(null);

  const fetchSlas = async () => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/slas`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch SLAs');
      }

      const data = await response.json();
      setSlas(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSlas();
  }, []);

  const handleCreate = () => {
    setEditingSla(null);
    setShowForm(true);
  };

  const handleEdit = (sla: Sla) => {
    setEditingSla(sla);
    setShowForm(true);
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this SLA definition?')) return;

    try {
      const response = await fetch(`${window.petSettings.apiUrl}/slas/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to delete SLA');
      }

      setSlas(prev => prev.filter(s => s.id !== id));
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Delete failed');
    }
  };

  const handleSave = async (sla: Partial<Sla>) => {
    try {
      const url = editingSla 
        ? `${window.petSettings.apiUrl}/slas/${editingSla.id}`
        : `${window.petSettings.apiUrl}/slas`;
      
      const method = editingSla ? 'POST' : 'POST';

      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify(sla),
      });

      if (!response.ok) {
        throw new Error('Failed to save SLA');
      }

      const savedSla = await response.json();
      
      if (editingSla) {
        setSlas(prev => prev.map(s => s.id === savedSla.id ? savedSla : s));
      } else {
        setSlas(prev => [...prev, savedSla]);
      }
      
      setShowForm(false);
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Save failed');
    }
  };

  if (loading) return <div>Loading SLAs...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  if (showForm) {
    return (
      <SlaDefinitionForm 
        initialData={editingSla || undefined}
        onSave={handleSave}
        onCancel={() => setShowForm(false)}
      />
    );
  }

  const columns: Column<Sla>[] = [
    { key: 'name', header: 'Name', render: (val) => <strong>{val as string}</strong> },
    { key: 'target_response_minutes', header: 'Response Target (mins)' },
    { key: 'target_resolution_minutes', header: 'Resolution Target (mins)' },
    { 
      key: 'id', 
      header: 'Actions', 
      render: (_, item) => (
        <div>
          <button onClick={() => handleEdit(item)} style={{ marginRight: '10px' }}>Edit</button>
          <button onClick={() => handleDelete(item.id)} style={{ color: 'red' }}>Delete</button>
        </div>
      )
    },
  ];

  return (
    <div className="pet-slas">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>SLA Definitions</h2>
        <button 
          onClick={handleCreate}
          style={{
            background: '#007cba',
            color: '#fff',
            border: 'none',
            padding: '10px 20px',
            borderRadius: '4px',
            cursor: 'pointer'
          }}
        >
          Add SLA Definition
        </button>
      </div>
      <DataTable columns={columns} data={slas} />
    </div>
  );
};

export default SlaDefinitions;
