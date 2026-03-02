import React, { useState, useEffect } from 'react';
import { Customer, Quote } from '../types';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';

interface QuoteFormProps {
  onSuccess: (quote?: Quote) => void;
  onCancel: () => void;
  initialData?: Quote;
}

const QuoteForm: React.FC<QuoteFormProps> = ({ onSuccess, onCancel, initialData }) => {
  const isEditMode = !!initialData;
  const [customerId, setCustomerId] = useState(initialData?.customerId?.toString() || '');
  const [title, setTitle] = useState(initialData?.title || '');
  const [description, setDescription] = useState(initialData?.description || '');
  const [totalValue, setTotalValue] = useState(initialData?.totalValue?.toString() || '0.00');
  const [currency, setCurrency] = useState(initialData?.currency || 'USD');
  const [acceptedAt, setAcceptedAt] = useState(initialData?.acceptedAt || '');
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadingCustomers, setLoadingCustomers] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchSchema = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        const response = await fetch(`${apiUrl}/schemas/quote?status=active`, {
          headers: { 'X-WP-Nonce': nonce }
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

    const fetchCustomers = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

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
        if (!isEditMode && data.length > 0 && !customerId) {
          setCustomerId(data[0].id.toString());
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load customers');
      } finally {
        setLoadingCustomers(false);
      }
    };

    fetchCustomers();
    fetchSchema();
  }, [isEditMode]);

  const handleSubmit = async (e?: React.FormEvent | React.MouseEvent) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    
    if (!customerId) {
      setError('Please select a customer');
      return;
    }

    if (!title) {
      setError('Please enter a quote title');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      console.log('Submitting quote form...', { isEditMode, customerId, totalValue });
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const url = isEditMode 
        ? `${apiUrl}/quotes/${initialData!.id}`
        : `${apiUrl}/quotes`;

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ 
          customerId: parseInt(customerId, 10),
          title,
          description,
          totalValue: parseFloat(totalValue),
          currency,
          acceptedAt: acceptedAt || null,
          malleableData
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        console.error('Quote submission failed:', data);
        throw new Error(data.message || `Failed to ${isEditMode ? 'update' : 'create'} quote`);
      }

      const savedQuote = await response.json();
      console.log('Quote submission success. Saved quote:', savedQuote);
      onSuccess(savedQuote);
    } catch (err) {
      console.error('Quote form error:', err);
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="card" style={{ padding: '20px', maxWidth: '800px', background: '#fff', border: '1px solid #ccd0d4' }}>
      <h2>{isEditMode ? 'Edit Quote' : 'Step 1: Create Quote Header'}</h2>
      
      {!isEditMode && (
        <div className="notice notice-info inline" style={{ marginBottom: '20px' }}>
          <p>Create the quote header first. You will be able to add line items and select catalog services on the next screen.</p>
        </div>
      )}

      {error && <div className="notice notice-error inline"><p>{error}</p></div>}
      
      <div onKeyDown={(e) => e.key === 'Enter' && handleSubmit(e)}>
        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Customer</label>
          <select 
            value={customerId} 
            onChange={(e) => setCustomerId(e.target.value)}
            style={{ width: '100%', maxWidth: '300px' }}
            disabled={loading || loadingCustomers}
          >
            <option value="">Select Customer</option>
            {customers.map(c => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
        </div>

        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Quote Title</label>
          <input 
            type="text" 
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
            disabled={loading}
            placeholder="e.g. Q123 - Server Upgrade"
          />
        </div>

        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Description (Optional)</label>
          <textarea 
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            style={{ width: '100%', maxWidth: '500px', minHeight: '80px' }}
            disabled={loading}
          />
        </div>

        {/* Total Value is derived from line items, not manually entered */}
        {/* 
        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Total Value</label>
          <input 
            type="number" 
            value={totalValue}
            onChange={(e) => setTotalValue(e.target.value)}
            style={{ width: '100%', maxWidth: '200px' }}
            disabled={loading}
            min="0"
            step="0.01"
          />
        </div>
        */}

        {/* Accepted At removed as per user request - quotes start as drafts */}
        {/*
        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Accepted At (Optional)</label>
          <input 
            type="datetime-local" 
            value={acceptedAt}
            onChange={(e) => setAcceptedAt(e.target.value)}
            style={{ width: '100%', maxWidth: '300px' }}
            disabled={loading}
          />
        </div>
        */}

        {activeSchema && (
          <MalleableFieldsRenderer 
            schema={activeSchema} 
            values={malleableData} 
            onChange={(key, value) => setMalleableData(prev => ({ ...prev, [key]: value }))} 
          />
        )}

        <div className="pet-form-actions" style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
          <button 
            type="button" 
            className="button button-primary" 
            onClick={handleSubmit}
            disabled={loading}
          >
            {loading ? 'Saving...' : (isEditMode ? 'Update Quote' : 'Start building quote')}
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
      </div>
    </div>
  );
};

export default QuoteForm;
