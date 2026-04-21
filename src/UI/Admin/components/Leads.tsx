import React, { useEffect, useState, useMemo } from 'react';
import { Lead } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import LeadForm from './LeadForm';
import useConversation from '../hooks/useConversation';
import useConversationStatus from '../hooks/useConversationStatus';
import { computeLeadHealth } from '../healthCompute';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

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
  const { openConversation } = useConversation();

  const leadIds = useMemo(() => leads.map(l => String(l.id)), [leads]);
  const { statuses: convStatuses } = useConversationStatus('lead', leadIds);

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
    if (!legacyConfirm('Are you sure you want to delete this lead?')) return;

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
      legacyAlert(err instanceof Error ? err.message : 'Failed to delete');
    }
  };

  const handleConvertToQuote = async (lead: Lead) => {
    if (!legacyConfirm(`Convert lead "${lead.subject}" to a quote?`)) return;

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
      legacyAlert(err instanceof Error ? err.message : 'Failed to convert lead');
    }
  };

  const handleBulkDelete = async () => {
    if (!legacyConfirm(`Are you sure you want to delete ${selectedIds.length} leads?`)) return;

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

  const statusColors: Record<string, string> = { red: '#dc3545', amber: '#f0ad4e', green: '#28a745', blue: '#007bff' };

  const columns: Column<Lead>[] = [
    { key: 'id', header: 'ID' },
    { key: 'subject', header: 'Subject', render: (val, item) => {
      const s = convStatuses.get(String(item.id));
      const dot = s && s.status !== 'none' ? (
        <button
          type="button"
          title={`Conversation: ${s.status} — click to open`}
          onClick={(e) => { e.stopPropagation(); e.preventDefault(); openConversation({ contextType: 'lead', contextId: String(item.id), subject: `Lead: ${item.subject}`, subjectKey: `lead:${item.id}` }); }}
          style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 24, height: 24, borderRadius: '50%', background: 'transparent', marginRight: 2, marginLeft: -7, verticalAlign: 'middle', border: 'none', padding: 0, cursor: 'pointer', flexShrink: 0 }}
        >
          <span style={{ display: 'block', width: 10, height: 10, borderRadius: '50%', background: statusColors[s.status] || 'transparent' }} />
        </button>
      ) : null;
      return <>{dot}{String(val)}</>;
    }},
    { key: 'customerName', header: 'Customer', render: (val) => val ? String(val) : <span style={{ color: '#999', fontStyle: 'italic' }}>No customer yet</span> },
    { key: 'status', header: 'Status', render: (val) => {
      const statusRaw = val as string;
      const status = statusRaw === 'lost' ? 'disqualified' : statusRaw;
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
        onRowClick={handleEdit}
        actions={(lead) => {
          const items: KebabMenuItem[] = [];
          if (lead.status === 'new' || lead.status === 'qualified') {
            items.push({ type: 'action', label: 'Convert to Quote', onClick: () => handleConvertToQuote(lead) });
          }
          items.push({ type: 'action', label: 'Discuss', onClick: () => openConversation({ contextType: 'lead', contextId: String(lead.id), subject: `Lead: ${lead.subject}`, subjectKey: `lead:${lead.id}` }) });
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
