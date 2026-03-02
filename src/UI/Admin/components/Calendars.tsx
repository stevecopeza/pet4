import React, { useEffect, useState } from 'react';
import { Calendar } from '../types';
import { DataTable, Column } from './DataTable';
import CalendarForm from './CalendarForm';

const Calendars = () => {
  const [calendars, setCalendars] = useState<Calendar[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingCalendar, setEditingCalendar] = useState<Calendar | null>(null);

  const fetchCalendars = async () => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/calendars`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
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

  const handleCreate = () => {
    setEditingCalendar(null);
    setShowForm(true);
  };

  const handleEdit = (calendar: Calendar) => {
    setEditingCalendar(calendar);
    setShowForm(true);
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this calendar?')) return;

    try {
      const response = await fetch(`${window.petSettings.apiUrl}/calendars/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to delete calendar');
      }

      setCalendars(prev => prev.filter(c => c.id !== id));
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Delete failed');
    }
  };

  const handleSave = async (calendar: Partial<Calendar>) => {
    try {
      const url = editingCalendar 
        ? `${window.petSettings.apiUrl}/calendars/${editingCalendar.id}`
        : `${window.petSettings.apiUrl}/calendars`;
      
      const method = editingCalendar ? 'POST' : 'POST'; // POST for update as per WP REST API usually, or PUT

      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify(calendar),
      });

      if (!response.ok) {
        throw new Error('Failed to save calendar');
      }

      const savedCalendar = await response.json();
      
      if (editingCalendar) {
        setCalendars(prev => prev.map(c => c.id === savedCalendar.id ? savedCalendar : c));
      } else {
        setCalendars(prev => [...prev, savedCalendar]);
      }
      
      setShowForm(false);
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Save failed');
    }
  };

  if (loading) return <div>Loading calendars...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  if (showForm) {
    return (
      <CalendarForm 
        initialData={editingCalendar || undefined}
        onSave={handleSave}
        onCancel={() => setShowForm(false)}
      />
    );
  }

  const columns: Column<Calendar>[] = [
    { key: 'name', header: 'Name', render: (val) => <strong>{val as string}</strong> },
    { key: 'timezone', header: 'Timezone' },
    { key: 'is_default', header: 'Default', render: (val) => val ? 'Yes' : 'No' },
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
    <div className="pet-calendars">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Calendars</h2>
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
          Add Calendar
        </button>
      </div>
      <DataTable columns={columns} data={calendars} />
    </div>
  );
};

export default Calendars;
