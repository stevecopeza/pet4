import React, { useState, useEffect } from 'react';
import { Employee, Ticket } from '../types';

interface LogTimeModalProps {
  ticket: Ticket;
  onSuccess: () => void;
  onClose: () => void;
}

const LogTimeModal: React.FC<LogTimeModalProps> = ({ ticket, onSuccess, onClose }) => {
  const [employeeId, setEmployeeId] = useState('');
  const [start, setStart] = useState('');
  const [end, setEnd] = useState('');
  const [description, setDescription] = useState('');
  const [isBillable, setIsBillable] = useState(ticket.isBillableDefault ?? true);

  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchEmployees = async () => {
      try {
        const response = await fetch(`${window.petSettings.apiUrl}/employees`, {
          headers: { 'X-WP-Nonce': window.petSettings.nonce }
        });

        if (!response.ok) {
          throw new Error('Failed to fetch employees');
        }

        const data = await response.json();
        setEmployees(data);
        if (data.length > 0) setEmployeeId(data[0].id.toString());
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load employees');
      } finally {
        setLoadingData(false);
      }
    };

    fetchEmployees();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!employeeId || !start || !end) {
      setError('Please fill in all required fields');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`${window.petSettings.apiUrl}/time-entries`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({ 
          employeeId: parseInt(employeeId, 10),
          ticketId: ticket.id,
          start,
          end,
          isBillable,
          description
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || data.error || 'Failed to log time');
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-modal-overlay" style={{
      position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
      backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', justifyContent: 'center', alignItems: 'center',
      zIndex: 1000
    }}>
      <div className="pet-modal-content" style={{
        background: 'white', padding: '20px', borderRadius: '5px', width: '500px', maxWidth: '90%',
        boxShadow: '0 2px 10px rgba(0,0,0,0.1)'
      }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
          <h3 style={{ margin: 0 }}>Log Time</h3>
          <button onClick={onClose} style={{ border: 'none', background: 'none', cursor: 'pointer', fontSize: '1.2em' }}>&times;</button>
        </div>

        {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}

        <form onSubmit={handleSubmit}>
          <div style={{ marginBottom: '15px' }}>
            <strong>Ticket:</strong> {ticket.subject}
          </div>

          <div style={{ marginBottom: '15px' }}>
            <label style={{ display: 'block', marginBottom: '5px' }}>Employee:</label>
            {loadingData ? (
              <div>Loading employees...</div>
            ) : (
              <select 
                value={employeeId} 
                onChange={(e) => setEmployeeId(e.target.value)}
                required
                style={{ width: '100%' }}
              >
                <option value="">Select an employee</option>
                {employees.map(e => (
                  <option key={e.id} value={e.id}>{e.firstName} {e.lastName}</option>
                ))}
              </select>
            )}
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px', marginBottom: '15px' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>Start Time:</label>
              <input 
                type="datetime-local" 
                value={start} 
                onChange={(e) => setStart(e.target.value)} 
                required 
                style={{ width: '100%' }}
              />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px' }}>End Time:</label>
              <input 
                type="datetime-local" 
                value={end} 
                onChange={(e) => setEnd(e.target.value)} 
                required 
                style={{ width: '100%' }}
              />
            </div>
          </div>

          <div style={{ marginBottom: '15px' }}>
            <label style={{ display: 'block', marginBottom: '5px' }}>Description:</label>
            <textarea 
              value={description} 
              onChange={(e) => setDescription(e.target.value)} 
              rows={3}
              style={{ width: '100%' }}
            />
          </div>

          <div style={{ marginBottom: '20px' }}>
            <label>
              <input 
                type="checkbox" 
                checked={isBillable} 
                onChange={(e) => setIsBillable(e.target.checked)} 
              />
              {' '}Billable
            </label>
          </div>

          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
            <button type="button" className="button" onClick={onClose}>Cancel</button>
            <button type="submit" className="button button-primary" disabled={loading || loadingData}>
              {loading ? 'Saving...' : 'Save Time Entry'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default LogTimeModal;
