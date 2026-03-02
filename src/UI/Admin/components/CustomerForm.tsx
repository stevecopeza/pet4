import React, { useState, useEffect } from 'react';
import { SchemaDefinition, Customer } from '../types';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';

interface CustomerFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  initialData?: Customer;
}

const CustomerForm: React.FC<CustomerFormProps> = ({ onSuccess, onCancel, initialData }) => {
  const [name, setName] = useState(initialData?.name || '');
  const [legalName, setLegalName] = useState(initialData?.legalName || '');
  const [contactEmail, setContactEmail] = useState(initialData?.contactEmail || '');
  const [status, setStatus] = useState(initialData?.status || 'active');
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (initialData) {
      setName(initialData.name);
      setLegalName(initialData.legalName || '');
      setContactEmail(initialData.contactEmail);
      setStatus(initialData.status || 'active');
      setMalleableData(initialData.malleableData || {});
    }
  }, [initialData]);

  useEffect(() => {
    const fetchSchema = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        const response = await fetch(`${apiUrl}/schemas/customer?status=active`, {
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

    fetchSchema();
  }, []);

  const handleMalleableChange = (key: string, value: any) => {
    setMalleableData(prev => ({
      ...prev,
      [key]: value
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const isEditMode = !!initialData;
      const url = isEditMode 
        ? `${apiUrl}/customers/${initialData.id}`
        : `${apiUrl}/customers`;

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ 
          name, 
          legalName,
          contactEmail,
          status,
          malleableData
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to save customer');
      }

      setName('');
      setLegalName('');
      setContactEmail('');
      setStatus('active');
      setMalleableData({});
      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-form-container" style={{ padding: '20px', background: '#f9f9f9', border: '1px solid #ddd', marginBottom: '20px' }}>
      <h3>{initialData ? 'Edit Customer' : 'Add New Customer'}</h3>
      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Name:</label>
          <input 
            type="text" 
            value={name} 
            onChange={(e) => setName(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Legal Name:</label>
          <input 
            type="text" 
            value={legalName} 
            onChange={(e) => setLegalName(e.target.value)} 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Contact Email:</label>
          <input 
            type="email" 
            value={contactEmail} 
            onChange={(e) => setContactEmail(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Status:</label>
          <select 
            value={status} 
            onChange={(e) => setStatus(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px', padding: '8px', border: '1px solid #ddd', borderRadius: '4px' }}
          >
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="churned">Churned</option>
          </select>
        </div>

        {activeSchema && (
          <MalleableFieldsRenderer
            schema={activeSchema}
            values={malleableData}
            onChange={handleMalleableChange}
          />
        )}

        <div style={{ marginTop: '15px' }}>
          <button 
            type="submit" 
            className="button button-primary" 
            disabled={loading}
            style={{ marginRight: '10px' }}
          >
            {loading ? 'Saving...' : (initialData ? 'Update Customer' : 'Create Customer')}
          </button>
          <button 
            type="button" 
            className="button" 
            onClick={onCancel}
            disabled={loading}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default CustomerForm;
