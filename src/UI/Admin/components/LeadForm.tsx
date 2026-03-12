import React, { useState, useEffect, useMemo } from 'react';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';
import { Customer, SchemaDefinition, Lead } from '../types';
import useConversation from '../hooks/useConversation';
import useConversationStatus from '../hooks/useConversationStatus';

interface LeadFormProps {
  initialData?: Lead;
  onSuccess: () => void;
  onCancel: () => void;
}

const LeadForm: React.FC<LeadFormProps> = ({ initialData, onSuccess, onCancel }) => {
  const isEditMode = !!initialData;
  const [customerId, setCustomerId] = useState(initialData?.customerId?.toString() || '');
  const [subject, setSubject] = useState(initialData?.subject || '');
  const [description, setDescription] = useState(initialData?.description || '');
  const [status, setStatus] = useState(initialData?.status || 'new');
  const [source, setSource] = useState(initialData?.source || '');
  const [estimatedValue, setEstimatedValue] = useState(initialData?.estimatedValue?.toString() || '');
  
  const [customers, setCustomers] = useState<Customer[]>([]);
  // @ts-ignore
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingCustomers, setLoadingCustomers] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const { openConversation } = useConversation();
  const leadIdArr = useMemo(() => isEditMode ? [String(initialData!.id)] : [], [isEditMode, initialData]);
  const { statuses: convStatuses } = useConversationStatus('lead', leadIdArr);

  useEffect(() => {
    const fetchCustomers = async () => {
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/customers`, {
          headers: {
            // @ts-ignore
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch customers');
        }

        const data = await response.json();
        setCustomers(data);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load customers');
      } finally {
        setLoadingCustomers(false);
      }
    };

    const fetchSchema = async () => {
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/schemas/lead?status=active`, {
          headers: {
            // @ts-ignore
            'X-WP-Nonce': window.petSettings.nonce,
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
  }, []);

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
      // @ts-ignore
      const apiUrl = window.petSettings.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings.nonce;
      
      const url = isEditMode 
        ? `${apiUrl}/leads/${initialData!.id}`
        : `${apiUrl}/leads`;

      const body: any = { 
        customerId: parseInt(customerId, 10),
        subject,
        description,
        source: source || null,
        estimatedValue: estimatedValue ? parseFloat(estimatedValue) : null,
        malleableData
      };

      if (isEditMode) {
        body.status = status;
      }

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(body),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || `Failed to ${isEditMode ? 'update' : 'create'} lead`);
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const leadConvStatus = isEditMode ? convStatuses.get(String(initialData!.id)) : undefined;
  const statusDotColor = leadConvStatus && leadConvStatus.status !== 'none'
    ? ({ red: '#dc3545', amber: '#f0ad4e', green: '#28a745', blue: '#007bff' }[leadConvStatus.status] || undefined)
    : undefined;

  return (
    <div className="pet-form-card">
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <h3 style={{ margin: 0 }}>{isEditMode ? 'Edit Lead' : 'Create New Lead'}</h3>
        {isEditMode && (
          <button
            type="button"
            className="button"
            onClick={() => openConversation({
              contextType: 'lead',
              contextId: String(initialData!.id),
              subject: `Lead: ${initialData!.subject}`,
              subjectKey: `lead:${initialData!.id}`
            })}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
          >
            {statusDotColor && <span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: '50%', background: statusDotColor }} />}
            Discuss
          </button>
        )}
      </div>
      {error && <div className="notice notice-error inline"><p>{error}</p></div>}
      <form onSubmit={handleSubmit}>
        <div className="pet-field">
          <label>Customer</label>
          {loadingCustomers ? (
            <p>Loading customers…</p>
          ) : (
            <select
              className="regular-text"
              value={customerId}
              onChange={(e) => setCustomerId(e.target.value)}
              required
              disabled={isEditMode}
            >
              <option value="">Select Customer</option>
              {customers.map(c => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          )}
        </div>

        <div className="pet-field">
          <label>Subject</label>
          <input
            type="text"
            className="regular-text"
            value={subject}
            onChange={(e) => setSubject(e.target.value)}
            required
          />
        </div>

        <div className="pet-field">
          <label>Description</label>
          <textarea
            className="large-text"
            rows={4}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            required
          />
        </div>

        <div className="pet-field">
          <label>Source</label>
          <input
            type="text"
            className="regular-text"
            value={source}
            onChange={(e) => setSource(e.target.value)}
            placeholder="e.g. Website, Referral"
          />
        </div>

        <div className="pet-field">
          <label>Estimated Value</label>
          <input
            type="number"
            className="regular-text"
            step="0.01"
            value={estimatedValue}
            onChange={(e) => setEstimatedValue(e.target.value)}
            placeholder="0.00"
          />
        </div>

        {isEditMode && (
          <div className="pet-field">
            <label>Status</label>
            <select
              className="regular-text"
              value={status}
              onChange={(e) => setStatus(e.target.value)}
            >
              <option value="new">New</option>
              <option value="contacted">Contacted</option>
              <option value="qualified">Qualified</option>
              <option value="converted">Converted</option>
              <option value="lost">Lost</option>
            </select>
          </div>
        )}

        {activeSchema && (
          <MalleableFieldsRenderer
            schema={activeSchema}
            values={malleableData}
            onChange={handleMalleableChange}
          />
        )}

        <div className="pet-form-actions">
          <button type="submit" disabled={loading} className="button button-primary">
            {loading ? 'Saving...' : (isEditMode ? 'Update Lead' : 'Create Lead')}
          </button>
          <button type="button" onClick={onCancel} className="button">
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default LeadForm;
