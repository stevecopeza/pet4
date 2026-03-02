import React, { useEffect, useState } from 'react';
import { Lead } from '../types';
import { DataTable, Column } from './DataTable';
import LeadForm from './LeadForm';

const Leads = () => {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingLead, setEditingLead] = useState<Lead | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);

  const fetchLeads = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/leads`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch leads');
      }

      const data = await response.json();
      setLeads(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLeads();
  }, []);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingLead(null);
    fetchLeads();
  };

  const handleEdit = (lead: Lead) => {
    setEditingLead(lead);
    setShowAddForm(true);
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this lead?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/leads/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to delete lead');
      }

      fetchLeads();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to delete');
    }
  };

  const handleBulkDelete = async () => {
    if (!confirm(`Are you sure you want to delete ${selectedIds.length} leads?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/leads/${id}`, {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': nonce,
          },
        });
      } catch (e) {
        console.error(`Failed to delete ${id}`, e);
      }
    }
    
    fetchLeads();
    setSelectedIds([]);
  };

  const columns: Column<Lead>[] = [
    { key: 'id', header: 'ID' },
    { key: 'subject', header: 'Subject' },
    { key: 'customerId', header: 'Customer', render: (val) => val ? val.toString() : '' },
    { key: 'status', header: 'Status' },
    { key: 'estimatedValue', header: 'Est. Value', render: (val) => val ? `$${val}` : '-' },
    { key: 'createdAt', header: 'Created' },
  ];

  if (showAddForm) {
    return (
      <LeadForm 
        initialData={editingLead || undefined}
        onSuccess={handleFormSuccess}
        onCancel={() => { setShowAddForm(false); setEditingLead(null); }}
      />
    );
  }

  return (
    <div className="pet-leads-container">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Leads</h2>
        <button 
          className="button button-primary"
          onClick={() => setShowAddForm(true)}
        >
          Add New Lead
        </button>
      </div>

      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}

      <DataTable
        data={leads}
        columns={columns}
        loading={loading}
        selection={{
          selectedIds,
          onSelectionChange: setSelectedIds
        }}
        actions={(lead) => (
          <>
            <button 
              className="button button-small" 
              onClick={() => handleEdit(lead)}
              style={{ marginRight: '5px' }}
            >
              Edit
            </button>
            <button 
              className="button button-small button-link-delete" 
              onClick={() => handleDelete(lead.id)}
              style={{ color: '#a00' }}
            >
              Delete
            </button>
          </>
        )}
      />

      {selectedIds.length > 0 && (
        <div style={{ marginTop: '15px' }}>
          <button 
            className="button" 
            onClick={handleBulkDelete}
            style={{ color: '#a00', borderColor: '#a00' }}
          >
            Delete Selected ({selectedIds.length})
          </button>
        </div>
      )}
    </div>
  );
};

export default Leads;
