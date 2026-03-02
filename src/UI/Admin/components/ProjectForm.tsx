import React, { useState, useEffect } from 'react';
import { Customer, Project, SchemaDefinition } from '../types';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';

interface ProjectFormProps {
  initialData?: Project;
  onSuccess: () => void;
  onCancel: () => void;
}

const ProjectForm: React.FC<ProjectFormProps> = ({ initialData, onSuccess, onCancel }) => {
  const isEditMode = !!initialData;
  const [name, setName] = useState(initialData?.name || '');
  const [customerId, setCustomerId] = useState(initialData?.customerId?.toString() || '');
  const [soldHours, setSoldHours] = useState(initialData?.soldHours?.toString() || '0');
  const [soldValue, setSoldValue] = useState(initialData?.soldValue?.toString() || '0.00');
  const [status, setStatus] = useState(initialData?.state || 'planned');
  const [startDate, setStartDate] = useState(initialData?.startDate || '');
  const [endDate, setEndDate] = useState(initialData?.endDate || '');
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadingCustomers, setLoadingCustomers] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const apiUrl = window.petSettings.apiUrl;
  const nonce = window.petSettings.nonce;

  useEffect(() => {
    const fetchCustomers = async () => {
      try {
        const response = await fetch(`${apiUrl}/customers`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch customers');
        }

        const data = await response.json();
        setCustomers(data);
        if (data.length > 0 && !customerId && !isEditMode) {
          setCustomerId(data[0].id.toString());
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load customers');
      } finally {
        setLoadingCustomers(false);
      }
    };

    const fetchSchema = async () => {
      try {
        const response = await fetch(`${apiUrl}/schemas/project?status=active`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          if (Array.isArray(data) && data.length > 0) {
            setActiveSchema(data[0]);
          }
        }
      } catch (err) {
        console.error('Failed to fetch schema', err);
      }
    };

    fetchCustomers();
    fetchSchema();
  }, [apiUrl, nonce, isEditMode]); // removed customerId dependency to avoid reset

  const handleMalleableChange = (key: string, value: any) => {
    setMalleableData(prev => ({
      ...prev,
      [key]: value
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!customerId) {
      setError('Please select a customer');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const url = isEditMode 
        ? `${apiUrl}/projects/${initialData!.id}`
        : `${apiUrl}/projects`;

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ 
          name, 
          customerId: parseInt(customerId, 10), 
          soldHours: parseFloat(soldHours),
          soldValue: parseFloat(soldValue),
          status,
          startDate,
          endDate,
          malleableData
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || `Failed to ${isEditMode ? 'update' : 'create'} project`);
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
      <h3>{isEditMode ? 'Edit Project' : 'Add New Project'}</h3>
      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Project Name:</label>
          <input 
            type="text" 
            value={name} 
            onChange={(e) => setName(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Customer:</label>
          {loadingCustomers ? (
            <div>Loading customers...</div>
          ) : (
            <select 
              value={customerId} 
              onChange={(e) => setCustomerId(e.target.value)}
              required
              style={{ width: '100%', maxWidth: '400px' }}
            >
              <option value="">Select a customer</option>
              {customers.map(c => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          )}
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Status:</label>
          <select 
            value={status} 
            onChange={(e) => setStatus(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px', padding: '8px', border: '1px solid #ddd', borderRadius: '4px' }}
          >
            <option value="planned">Planned</option>
            <option value="active">Active</option>
            <option value="on_hold">On Hold</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Sold Hours:</label>
          <input 
            type="number" 
            step="0.1"
            value={soldHours} 
            onChange={(e) => setSoldHours(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Sold Value:</label>
          <input 
            type="number" 
            step="0.01"
            value={soldValue} 
            onChange={(e) => setSoldValue(e.target.value)} 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Start Date:</label>
          <input 
            type="date" 
            value={startDate} 
            onChange={(e) => setStartDate(e.target.value)} 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>End Date:</label>
          <input 
            type="date" 
            value={endDate} 
            onChange={(e) => setEndDate(e.target.value)} 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <MalleableFieldsRenderer 
          schema={activeSchema}
          values={malleableData}
          onChange={handleMalleableChange}
        />

        <div style={{ marginTop: '15px' }}>
          <button 
            type="submit" 
            disabled={loading || loadingCustomers}
            className="button button-primary"
            style={{ marginRight: '10px' }}
          >
            {loading ? 'Saving...' : 'Save Project'}
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

export default ProjectForm;
