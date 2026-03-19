import React, { useEffect, useState } from 'react';
import { Sla } from '../types';
import { DataTable, Column } from './DataTable';
import SlaDefinitionForm from './SlaDefinitionForm';
import KebabMenu from './KebabMenu';
import useConversation from '../hooks/useConversation';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

const SlaDefinitions = () => {
  const [slas, setSlas] = useState<Sla[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingSla, setEditingSla] = useState<Sla | null>(null);
  const { openConversation } = useConversation();

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
    if (!legacyConfirm('Are you sure you want to delete this SLA definition?')) return;

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
      legacyAlert(err instanceof Error ? err.message : 'Delete failed');
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
      legacyAlert(err instanceof Error ? err.message : 'Save failed');
    }
  };

  if (loading) return <div>Loading SLAs...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  const columns: Column<Sla>[] = [
    { key: 'name', header: 'Name', render: (val, item) => (
      <button
        type="button"
        onClick={(e) => { e.preventDefault(); e.stopPropagation(); handleEdit(item); }}
        style={{ fontWeight: 'bold', background: 'none', border: 'none', padding: 0, margin: 0, color: '#2271b1', cursor: 'pointer' }}
        className="button-link"
      >
        {String(val)}
      </button>
    ) },
    { key: 'is_tiered', header: 'Mode', render: (_val, item) => {
      if (item.is_tiered && item.tiers) {
        return <span>{item.tiers.length} tier{item.tiers.length !== 1 ? 's' : ''}</span>;
      }
      return <span>Single</span>;
    } },
    { key: 'response_target_minutes', header: 'Response Target (mins)', render: (val, item) => {
      if (item.is_tiered) return <span style={{ color: '#999' }}>—</span>;
      return <span>{String(val)}</span>;
    } },
    { key: 'resolution_target_minutes', header: 'Resolution Target (mins)', render: (val, item) => {
      if (item.is_tiered) return <span style={{ color: '#999' }}>—</span>;
      return <span>{String(val)}</span>;
    } },
  ];

  return (
    <div className="pet-slas">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>SLA Definitions</h2>
        <button 
          onClick={handleCreate}
          className="button button-primary"
        >
          Add SLA Definition
        </button>
      </div>

      {showForm && (
        <SlaDefinitionForm
          initialData={editingSla || undefined}
          onSave={handleSave}
          onCancel={() => { setShowForm(false); setEditingSla(null); }}
        />
      )}

      <DataTable
        columns={columns}
        data={slas}
        emptyMessage="No SLA definitions found."
        actions={(item) => (
          <KebabMenu items={[
            { type: 'action', label: 'Discuss', onClick: () => openConversation({ contextType: 'sla', contextId: String(item.id), subject: `SLA: ${item.name}` }) },
            { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
            { type: 'divider' },
            { type: 'action', label: 'Delete', onClick: () => handleDelete(item.id), danger: true },
          ]} />
        )}
      />

    </div>
  );
};

export default SlaDefinitions;
