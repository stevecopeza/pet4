import React, { useState, useEffect } from 'react';
import { Contact, ContactAffiliation, Customer, SchemaDefinition } from '../types';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';

interface ContactFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  initialData?: Contact;
}

const ContactForm: React.FC<ContactFormProps> = ({ onSuccess, onCancel, initialData }) => {
  const [firstName, setFirstName] = useState(initialData?.firstName || '');
  const [lastName, setLastName] = useState(initialData?.lastName || '');
  const [email, setEmail] = useState(initialData?.email || '');
  const [phone, setPhone] = useState(initialData?.phone || '');
  const [affiliations, setAffiliations] = useState<ContactAffiliation[]>(initialData?.affiliations || []);
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        // Fetch customers for affiliation selection
        const custRes = await fetch(`${apiUrl}/customers`, {
          headers: { 'X-WP-Nonce': nonce }
        });
        if (custRes.ok) {
          setCustomers(await custRes.json());
        }

        // Fetch schema
        const schemaRes = await fetch(`${apiUrl}/schemas/contact?status=active`, {
          headers: { 'X-WP-Nonce': nonce }
        });
        if (schemaRes.ok) {
          const data = await schemaRes.json();
          if (Array.isArray(data) && data.length > 0) {
            setActiveSchema(data[0]);
          }
        }
      } catch (err) {
        console.error('Failed to fetch contact form data', err);
      }
    };

    fetchData();
  }, []);

  const handleMalleableChange = (key: string, value: any) => {
    setMalleableData(prev => ({ ...prev, [key]: value }));
  };

  const addAffiliation = () => {
    setAffiliations([...affiliations, { customerId: 0, siteId: null, role: '', isPrimary: affiliations.length === 0 }]);
  };

  const updateAffiliation = (index: number, field: keyof ContactAffiliation, value: any) => {
    const newAffiliations = [...affiliations];
    newAffiliations[index] = { ...newAffiliations[index], [field]: value };
    setAffiliations(newAffiliations);
  };

  const removeAffiliation = (index: number) => {
    setAffiliations(affiliations.filter((_, i) => i !== index));
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

      const method = initialData ? 'PUT' : 'POST';
      const url = initialData ? `${apiUrl}/contacts/${initialData.id}` : `${apiUrl}/contacts`;

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce
        },
        body: JSON.stringify({
          firstName,
          lastName,
          email,
          phone,
          affiliations,
          malleableData
        })
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to save contact');
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-form-container" style={{ background: '#f9f9f9', padding: '20px', borderRadius: '8px', border: '1px solid #ddd', marginBottom: '20px' }}>
      <h3>{initialData ? 'Edit Contact' : 'Add New Contact'}</h3>
      <form onSubmit={handleSubmit}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px', marginBottom: '15px' }}>
          <div>
            <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>First Name *</label>
            <input 
              type="text" 
              value={firstName} 
              onChange={(e) => setFirstName(e.target.value)} 
              required 
              style={{ width: '100%' }}
            />
          </div>
          <div>
            <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Last Name *</label>
            <input 
              type="text" 
              value={lastName} 
              onChange={(e) => setLastName(e.target.value)} 
              required 
              style={{ width: '100%' }}
            />
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px', marginBottom: '15px' }}>
          <div>
            <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Email *</label>
            <input 
              type="email" 
              value={email} 
              onChange={(e) => setEmail(e.target.value)} 
              required 
              style={{ width: '100%' }}
            />
          </div>
          <div>
            <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Mobile / Phone</label>
            <input 
              type="text" 
              value={phone} 
              onChange={(e) => setPhone(e.target.value)} 
              style={{ width: '100%' }}
              placeholder="e.g. +1 234 567 890"
            />
          </div>
        </div>

        <div style={{ marginBottom: '20px' }}>
          <h4 style={{ borderBottom: '1px solid #eee', paddingBottom: '10px' }}>Affiliations (Customers)</h4>
          {affiliations.map((aff, index) => (
            <div key={index} style={{ display: 'flex', gap: '10px', alignItems: 'flex-end', marginBottom: '10px', background: '#fff', padding: '10px', border: '1px solid #eee' }}>
              <div style={{ flex: 2 }}>
                <label style={{ display: 'block', fontSize: '12px' }}>Customer</label>
                <select 
                  value={aff.customerId} 
                  onChange={(e) => updateAffiliation(index, 'customerId', parseInt(e.target.value))}
                  style={{ width: '100%' }}
                  required
                >
                  <option value="">-- Select Customer --</option>
                  {customers.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
              <div style={{ flex: 1 }}>
                <label style={{ display: 'block', fontSize: '12px' }}>Role</label>
                <input 
                  type="text" 
                  value={aff.role || ''} 
                  onChange={(e) => updateAffiliation(index, 'role', e.target.value)}
                  style={{ width: '100%' }}
                  placeholder="e.g. Manager"
                />
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '5px', paddingBottom: '5px' }}>
                <input 
                  type="checkbox" 
                  checked={aff.isPrimary} 
                  onChange={(e) => updateAffiliation(index, 'isPrimary', e.target.checked)}
                />
                <label style={{ fontSize: '12px' }}>Primary</label>
              </div>
              <button 
                type="button" 
                className="button button-link-delete" 
                onClick={() => removeAffiliation(index)}
                style={{ color: '#a00' }}
              >
                Remove
              </button>
            </div>
          ))}
          <button type="button" className="button" onClick={addAffiliation}>+ Add Affiliation</button>
        </div>

        {activeSchema && (
          <MalleableFieldsRenderer 
            schema={activeSchema} 
            values={malleableData} 
            onChange={handleMalleableChange} 
          />
        )}

        {error && <div style={{ color: 'red', marginTop: '10px' }}>{error}</div>}

        <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
          <button type="submit" className="button button-primary" disabled={loading}>
            {loading ? 'Saving...' : (initialData ? 'Update Contact' : 'Add Contact')}
          </button>
          <button type="button" className="button" onClick={onCancel} disabled={loading}>
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default ContactForm;
