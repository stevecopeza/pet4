import React, { useState, useEffect } from 'react';
import { Employee, Ticket, TimeEntry } from '../types';

interface TimeEntryFormProps {
  initialData?: TimeEntry;
  onSuccess: () => void;
  onCancel: () => void;
}

const TimeEntryForm: React.FC<TimeEntryFormProps> = ({ initialData, onSuccess, onCancel }) => {
  const isEditMode = !!initialData;
  const [employeeId, setEmployeeId] = useState(initialData?.employeeId?.toString() || '');
  const [ticketId, setTicketId] = useState(initialData?.ticketId?.toString() || '');
  // Format dates for datetime-local input (YYYY-MM-DDThh:mm)
  const [start, setStart] = useState(initialData?.start ? new Date(initialData.start).toISOString().slice(0, 16) : '');
  const [end, setEnd] = useState(initialData?.end ? new Date(initialData.end).toISOString().slice(0, 16) : '');
  const [description, setDescription] = useState(initialData?.description || '');
  const [isBillable, setIsBillable] = useState(initialData?.billable ?? true);
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});

  const [employees, setEmployees] = useState<Employee[]>([]);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState<string | null>(null);


  // Helper for malleable data
  const handleMalleableChange = (key: string, value: string) => {
    setMalleableData(prev => ({
      ...prev,
      [key]: value
    }));
  };

  const addMalleableField = () => {
    const key = prompt('Enter field name:');
    if (key && !malleableData[key]) {
      handleMalleableChange(key, '');
    }
  };

  const removeMalleableField = (key: string) => {
    const newData = { ...malleableData };
    delete newData[key];
    setMalleableData(newData);
  };

  useEffect(() => {
    const fetchData = async () => {
      try {
        // @ts-ignore
        const [empRes, ticketRes] = await Promise.all([
          fetch(`${window.petSettings.apiUrl}/employees`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } }),
          fetch(`${window.petSettings.apiUrl}/tickets`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } })
        ]);

        if (!empRes.ok || !ticketRes.ok) {
          throw new Error('Failed to fetch required data');
        }

        const empData = await empRes.json();
        const ticketData = await ticketRes.json();

        setEmployees(empData);
        setTickets(ticketData);

        if (!isEditMode && empData.length > 0) {
          setEmployeeId(empData[0].id.toString());
        }

        if (isEditMode && initialData?.ticketId) {
          setTicketId(initialData.ticketId.toString());
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load data');
      } finally {
        setLoadingData(false);
      }
    };

    fetchData();
  }, [isEditMode, initialData]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!employeeId || !ticketId || !start || !end) {
      setError('Please fill in all required fields');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings.nonce;
      
      const url = isEditMode 
        ? `${apiUrl}/time-entries/${initialData!.id}`
        : `${apiUrl}/time-entries`;

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ 
          employeeId: parseInt(employeeId, 10),
          ticketId: parseInt(ticketId, 10),
          start,
          end,
          isBillable,
          description,
          malleableData
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        const backendMessage = data.message || data.error || '';
        if (typeof backendMessage === 'string' && backendMessage.toLowerCase().includes('cannot accept time entries')) {
          throw new Error('Selected ticket is not currently time-loggable. Choose an assigned support ticket or an in-progress project ticket.');
        }
        throw new Error(backendMessage || 'Failed to save time entry');
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-form-container" style={{ padding: '20px', background: '#f9f9f9', border: '1px solid #ddd', marginBottom: '20px' }}>
      <h3>{isEditMode ? 'Edit Time Entry' : 'Log Time Entry'}</h3>
      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-time-employee" style={{ display: 'block', marginBottom: '5px' }}>Employee:</label>
          {loadingData ? (
            <div>Loading...</div>
          ) : (
            <select 
              id="pet-time-employee"
              value={employeeId} 
              onChange={(e) => setEmployeeId(e.target.value)}
              required
              style={{ width: '100%', maxWidth: '400px' }}
            >
              <option value="">Select an employee</option>
              {employees.map(e => (
                <option key={e.id} value={e.id}>{e.firstName} {e.lastName}</option>
              ))}
            </select>
          )}
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-time-ticket" style={{ display: 'block', marginBottom: '5px' }}>Ticket:</label>
          <select 
            id="pet-time-ticket"
            value={ticketId} 
            onChange={(e) => setTicketId(e.target.value)}
            required
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="">Select a ticket</option>
            {tickets.map(t => (
              <option key={t.id} value={t.id}>
                #{t.id} - {t.subject} [{t.lifecycleOwner || 'support'} / {t.status}]
              </option>
            ))}
          </select>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-time-start" style={{ display: 'block', marginBottom: '5px' }}>Start Time:</label>
          <input 
            id="pet-time-start"
            type="datetime-local" 
            value={start} 
            onChange={(e) => setStart(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-time-end" style={{ display: 'block', marginBottom: '5px' }}>End Time:</label>
          <input 
            id="pet-time-end"
            type="datetime-local" 
            value={end} 
            onChange={(e) => setEnd(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-time-description" style={{ display: 'block', marginBottom: '5px' }}>Description:</label>
          <textarea 
            id="pet-time-description"
            value={description} 
            onChange={(e) => setDescription(e.target.value)} 
            rows={3}
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>
            <input 
              type="checkbox" 
              checked={isBillable} 
              onChange={(e) => setIsBillable(e.target.checked)} 
              style={{ marginRight: '5px' }}
            />
            Billable
          </label>
        </div>

        <div style={{ marginBottom: '20px', padding: '10px', background: '#f0f0f0', borderRadius: '4px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
            <label style={{ fontWeight: 'bold' }}>Additional Data (Malleable)</label>
            <button 
              type="button" 
              className="button button-small" 
              onClick={addMalleableField}
            >
              Add Field
            </button>
          </div>
          {Object.entries(malleableData).length === 0 ? (
            <div style={{ color: '#666', fontStyle: 'italic', fontSize: '0.9em' }}>No additional data</div>
          ) : (
            Object.entries(malleableData).map(([key, value]) => (
              <div key={key} style={{ display: 'flex', gap: '10px', marginBottom: '5px', alignItems: 'center' }}>
                <span style={{ minWidth: '100px', fontWeight: '500' }}>{key}:</span>
                <input 
                  type="text" 
                  value={value as string} 
                  onChange={(e) => handleMalleableChange(key, e.target.value)}
                  style={{ flex: 1 }}
                />
                <button 
                  type="button" 
                  className="button button-link-delete" 
                  style={{ color: '#a00', textDecoration: 'none' }}
                  onClick={() => removeMalleableField(key)}
                >
                  &times;
                </button>
              </div>
            ))
          )}
        </div>

        <div style={{ display: 'flex', gap: '10px' }}>
          <button 
            type="submit" 
            disabled={loading || loadingData}
            className="button button-primary"
            style={{ marginRight: '10px' }}
          >
            {loading ? 'Saving...' : (isEditMode ? 'Update Entry' : 'Save Entry')}
          </button>
          <button 
            type="button" 
            onClick={onCancel}
            className="button"
            disabled={loading}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default TimeEntryForm;
