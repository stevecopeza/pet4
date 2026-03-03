import React, { useEffect, useState } from 'react';
import { TimeEntry } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import TimeEntryForm from './TimeEntryForm';

const TimeEntries = () => {
  const [entries, setEntries] = useState<TimeEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingEntry, setEditingEntry] = useState<TimeEntry | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);

  const fetchEntries = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/time-entries`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch time entries');
      }

      const data = await response.json();
      setEntries(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEntries();
  }, []);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingEntry(null);
    fetchEntries();
  };

  const handleEdit = (entry: TimeEntry) => {
    setEditingEntry(entry);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this time entry?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/time-entries/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive time entry');
      }

      fetchEntries();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} time entries?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    try {
      await Promise.all(selectedIds.map(id => 
        fetch(`${apiUrl}/time-entries/${id}`, {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': nonce,
          },
        })
      ));
      
      setSelectedIds([]);
      fetchEntries();
    } catch (e) {
      console.error('Failed to archive items', e);
      alert('Some items could not be archived');
    }
  };

  const columns: Column<TimeEntry>[] = [
    { key: 'id', header: 'ID' },
    { key: 'employeeId', header: 'Employee' },
    { key: 'ticketId', header: 'Ticket' },
    { key: 'start', header: 'Start', render: (val) => new Date(val as string).toLocaleString() },
    { key: 'end', header: 'End', render: (val) => new Date(val as string).toLocaleString() },
    { key: 'duration', header: 'Duration (m)' },
    { key: 'description', header: 'Description' },
    { key: 'billable', header: 'Billable', render: (_, item) => <span>{item.billable ? 'Yes' : 'No'}</span> },
  ];

  if (loading && !entries.length) return <div>Loading time entries...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-time-entries">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Time (Entries)</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Log Time Entry
          </button>
        )}
      </div>

      {showAddForm && (
        <TimeEntryForm 
          onSuccess={handleFormSuccess} 
          onCancel={() => { setShowAddForm(false); setEditingEntry(null); }} 
          initialData={editingEntry || undefined}
        />
      )}

      {selectedIds.length > 0 && (
        <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
          <strong>{selectedIds.length} items selected</strong>
          <button className="button button-link-delete" style={{ color: '#a00', borderColor: '#a00' }} onClick={handleBulkArchive}>Archive Selected</button>
        </div>
      )}

      <DataTable 
        columns={columns} 
        data={entries} 
        emptyMessage="No time entries found." 
        selection={{
          selectedIds,
          onSelectionChange: setSelectedIds
        }}
        actions={(item) => (
          <KebabMenu items={[
            { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
            { type: 'action', label: 'Archive', onClick: () => handleArchive(item.id), danger: true },
          ]} />
        )}
      />
    </div>
  );
};

export default TimeEntries;
