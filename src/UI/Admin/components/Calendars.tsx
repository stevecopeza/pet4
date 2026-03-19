import React, { useEffect, useState } from 'react';
import { Calendar } from '../types';
import { DataTable, Column } from './DataTable';
import CalendarForm from './CalendarForm';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

const Calendars = () => {
  const [calendars, setCalendars] = useState<Calendar[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingCalendar, setEditingCalendar] = useState<Calendar | null>(null);

  const fetchCalendars = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/calendars`, {
        headers: { 'X-WP-Nonce': nonce },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch calendars');
      }

      const data = await response.json();
      setCalendars(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCalendars();
  }, []);

  const handleEdit = (calendar: Calendar) => {
    setEditingCalendar(calendar);
    setShowForm(true);
  };

  const handleDelete = async (id: number) => {
    if (!legacyConfirm('Are you sure you want to delete this calendar?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/calendars/${id}`, {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': nonce },
      });

      if (!response.ok) throw new Error('Failed to delete calendar');
      fetchCalendars();
    } catch (err) {
      legacyAlert(err instanceof Error ? err.message : 'Delete failed');
    }
  };

  const columns: Column<Calendar>[] = [
    { key: 'name', header: 'Name', render: (val, item) => (
      <button
        type="button"
        onClick={() => handleEdit(item)}
        style={{ background: 'none', border: 'none', color: '#2271b1', cursor: 'pointer', padding: 0, textAlign: 'left', fontWeight: 'bold', fontSize: 'inherit' }}
      >
        {val as string}
      </button>
    )},
    { key: 'timezone', header: 'Timezone' },
    { key: 'is_default', header: 'Default', render: (val) => val ? 'Yes' : 'No' },
  ];

  if (loading && !calendars.length) return <div>Loading calendars...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-calendars">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Calendars</h2>
        {!showForm && (
          <button className="button button-primary" onClick={() => { setEditingCalendar(null); setShowForm(true); }}>
            Add Calendar
          </button>
        )}
      </div>

      {showForm && (
        <CalendarForm
          initialData={editingCalendar || undefined}
          onSuccess={() => { setShowForm(false); setEditingCalendar(null); fetchCalendars(); }}
          onCancel={() => { setShowForm(false); setEditingCalendar(null); }}
        />
      )}

      <DataTable
        columns={columns}
        data={calendars}
        emptyMessage="No calendars found."
        actions={(item) => (
          <KebabMenu items={[
            { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
            { type: 'action', label: 'Delete', onClick: () => handleDelete(item.id), danger: true },
          ]} />
        )}
      />
    </div>
  );
};

export default Calendars;
