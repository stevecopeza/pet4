import React, { useEffect, useState } from 'react';
import { Lead } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import LeadForm from './LeadForm';
import { computeLeadHealth } from '../healthCompute';

interface LeadsProps {
  onNavigateToQuote?: (quoteId: number) => void;
}

const Leads: React.FC<LeadsProps> = ({ onNavigateToQuote }) => {
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

  const handleConvertToQuote = async (lead: Lead) => {
    if (!confirm(`Convert lead "${lead.subject}" to a quote?`)) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/leads/${lead.id}/convert`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({}),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || data.error || 'Failed to convert lead');
      }

      const data = await response.json();
      fetchLeads();
      if (onNavigateToQuote && data.quoteId) {
        onNavigateToQuote(data.quoteId);
      }
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to convert lead');
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
    { key: 'status', header: 'Status', render: (val) => {
      const status = val as string;
      return <span className={`pet-status-badge status-${status}`}>{status}</span>;
    }},
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
    <div>
      <div className="pet-page-header">
        <h2>Leads</h2>
        <button className="button button-primary" onClick={() => setShowAddForm(true)}>
          Add New Lead
        </button>
      </div>

      {error && <div className="notice notice-error inline"><p>{error}</p></div>}

      <DataTable
        data={leads}
        columns={columns}
        loading={loading}
        selection={{
          selectedIds,
          onSelectionChange: setSelectedIds
        }}
        rowClassName={(lead) => computeLeadHealth(lead).className}
        actions={(lead) => {
          const items: KebabMenuItem[] = [];
          if (lead.status === 'new' || lead.status === 'qualified') {
            items.push({ type: 'action', label: 'Convert to Quote', onClick: () => handleConvertToQuote(lead) });
          }
          items.push({ type: 'action', label: 'Edit', onClick: () => handleEdit(lead) });
          items.push({ type: 'action', label: 'Delete', onClick: () => handleDelete(lead.id), danger: true });
          return <KebabMenu items={items} />;
        }}
      />

      {selectedIds.length > 0 && (
        <div className="pet-bulk-bar">
          <strong>{selectedIds.length} selected</strong>
          <button className="button button-small button-link-delete" onClick={handleBulkDelete}>
            Delete Selected
          </button>
        </div>
      )}
    </div>
  );
};

export default Leads;
