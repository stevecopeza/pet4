import React, { useState, useEffect } from 'react';
import { SchemaDefinition, Site, Customer } from '../types';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';

interface SiteFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  initialData?: Site;
}

const SiteForm: React.FC<SiteFormProps> = ({ onSuccess, onCancel, initialData }) => {
  const [customerId, setCustomerId] = useState<number>(initialData?.customerId || 0);
  const [name, setName] = useState(initialData?.name || '');
  const [addressLines, setAddressLines] = useState(initialData?.addressLines || '');
  const [city, setCity] = useState(initialData?.city || '');
  const [state, setState] = useState(initialData?.state || '');
  const [postalCode, setPostalCode] = useState(initialData?.postalCode || '');
  const [country, setCountry] = useState(initialData?.country || '');
  const [status, setStatus] = useState(initialData?.status || 'active');
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  const [customers, setCustomers] = useState<Customer[]>([]);
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (initialData) {
      setCustomerId(initialData.customerId);
      setName(initialData.name);
      setAddressLines(initialData.addressLines || '');
      setCity(initialData.city || '');
      setState(initialData.state || '');
      setPostalCode(initialData.postalCode || '');
      setCountry(initialData.country || '');
      setStatus(initialData.status || 'active');
      setMalleableData(initialData.malleableData || {});
    }
  }, [initialData]);

  useEffect(() => {
    const fetchData = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        // Fetch schema
        const schemaResponse = await fetch(`${apiUrl}/schemas/site?status=active`, {
          headers: { 'X-WP-Nonce': nonce },
        });
        if (schemaResponse.ok) {
          const data = await schemaResponse.json();
          if (Array.isArray(data) && data.length > 0) {
            setActiveSchema(data[0]);
          }
        }

        // Fetch customers for dropdown
        const customersResponse = await fetch(`${apiUrl}/customers`, {
          headers: { 'X-WP-Nonce': nonce },
        });
        if (customersResponse.ok) {
          const data = await customersResponse.json();
          setCustomers(data);
        }
      } catch (err) {
        console.error('Failed to fetch data', err);
      }
    };

    fetchData();
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

    if (!customerId) {
      setError('Please select a customer');
      setLoading(false);
      return;
    }

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const isEditMode = !!initialData;
      const url = isEditMode 
        ? `${apiUrl}/sites/${initialData.id}`
        : `${apiUrl}/sites`;

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ 
          customerId,
          name, 
          addressLines,
          city,
          state,
          postalCode,
          country,
          status,
          malleableData
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to save site');
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-form-container">
      <h3>{initialData ? 'Edit Site' : 'Add New Site'}</h3>
      
      {error && (
        <div className="notice notice-error inline">
          <p>{error}</p>
        </div>
      )}

      <form onSubmit={handleSubmit}>
        <div className="pet-form-grid">
          <div className="pet-form-group">
            <label>Customer <span className="required">*</span></label>
            <select 
              value={customerId} 
              onChange={(e) => setCustomerId(Number(e.target.value))}
              required
              className="regular-text"
            >
              <option value="">Select Customer</option>
              {customers.map(c => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          </div>

          <div className="pet-form-group">
            <label>Site Name <span className="required">*</span></label>
            <input 
              type="text" 
              value={name} 
              onChange={(e) => setName(e.target.value)} 
              required 
              className="regular-text"
            />
          </div>

          <div className="pet-form-group">
            <label>Address</label>
            <textarea 
              value={addressLines} 
              onChange={(e) => setAddressLines(e.target.value)} 
              className="regular-text"
              rows={3}
            />
          </div>

          <div className="pet-form-group">
            <label>City</label>
            <input 
              type="text" 
              value={city} 
              onChange={(e) => setCity(e.target.value)} 
              className="regular-text"
            />
          </div>

          <div className="pet-form-group">
            <label>State/Province</label>
            <input 
              type="text" 
              value={state} 
              onChange={(e) => setState(e.target.value)} 
              className="regular-text"
            />
          </div>

          <div className="pet-form-group">
            <label>Postal Code</label>
            <input 
              type="text" 
              value={postalCode} 
              onChange={(e) => setPostalCode(e.target.value)} 
              className="regular-text"
            />
          </div>

          <div className="pet-form-group">
            <label>Country</label>
            <input 
              type="text" 
              value={country} 
              onChange={(e) => setCountry(e.target.value)} 
              className="regular-text"
            />
          </div>

          <div className="pet-form-group">
            <label>Status</label>
            <select 
              value={status} 
              onChange={(e) => setStatus(e.target.value)}
              className="regular-text"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>

        {activeSchema && (
          <MalleableFieldsRenderer
            schema={activeSchema}
            values={malleableData}
            onChange={handleMalleableChange}
          />
        )}

        <div className="pet-form-actions">
          <button type="submit" className="button button-primary" disabled={loading}>
            {loading ? 'Saving...' : 'Save Site'}
          </button>
          <button type="button" className="button" onClick={onCancel} disabled={loading}>
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default SiteForm;
