import React, { useState, useEffect, useMemo } from 'react';
import { Contact, ContactAffiliation, Customer, SchemaDefinition, Site } from '../types';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';

interface ContactFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  initialData?: Contact;
  contextCustomerId?: number | null;
  contextSiteId?: number | null;
}

const ContactForm: React.FC<ContactFormProps> = ({
  onSuccess,
  onCancel,
  initialData,
  contextCustomerId,
  contextSiteId,
}) => {
  const [firstName, setFirstName] = useState(initialData?.firstName || '');
  const [lastName, setLastName] = useState(initialData?.lastName || '');
  const [email, setEmail] = useState(initialData?.email || '');
  const [phone, setPhone] = useState(initialData?.phone || '');
  const [affiliations, setAffiliations] = useState<ContactAffiliation[]>(initialData?.affiliations || []);
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});

  const [customers, setCustomers] = useState<Customer[]>([]);
  const [sites, setSites] = useState<Site[]>([]);
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const uniqueCustomers = useMemo(() => {
    const map = new Map<number, Customer>();
    customers.forEach(customer => {
      if (!map.has(customer.id)) {
        map.set(customer.id, customer);
      }
    });
    return Array.from(map.values());
  }, [customers]);

  const getSitesForCustomer = (customerId: number) => {
    const map = new Map<number, Site>();
    sites
      .filter(site => site.customerId === customerId)
      .forEach(site => {
        if (!map.has(site.id)) {
          map.set(site.id, site);
        }
      });
    return Array.from(map.values());
  };

  const getBranchName = (siteId: number | null) => {
    if (!siteId) return null;
    const branch = sites.find(site => site.id === siteId);
    if (branch?.name) {
      return branch.name;
    }
    return `Branch #${siteId}`;
  };

  const assignedBranchNames = useMemo(() => {
    const uniqueNames = new Set<string>();
    affiliations.forEach(aff => {
      const name = getBranchName(aff.siteId);
      if (name) uniqueNames.add(name);
    });
    return Array.from(uniqueNames);
  }, [affiliations, sites]);

  useEffect(() => {
    const fetchData = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        const [custRes, sitesRes, schemaRes] = await Promise.all([
          fetch(`${apiUrl}/customers`, {
            headers: { 'X-WP-Nonce': nonce }
          }),
          fetch(`${apiUrl}/sites`, {
            headers: { 'X-WP-Nonce': nonce }
          }),
          fetch(`${apiUrl}/schemas/contact?status=active`, {
            headers: { 'X-WP-Nonce': nonce }
          }),
        ]);

        if (custRes.ok) {
          const customerPayload = await custRes.json();
          setCustomers(Array.isArray(customerPayload) ? customerPayload : []);
        }
        if (sitesRes.ok) {
          const sitesPayload = await sitesRes.json();
          setSites(Array.isArray(sitesPayload) ? sitesPayload : []);
        }
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

  useEffect(() => {
    if (initialData || affiliations.length > 0) {
      return;
    }

    const defaultCustomerId = contextCustomerId || (uniqueCustomers.length === 1 ? uniqueCustomers[0].id : 0);
    const customerSites = defaultCustomerId ? getSitesForCustomer(defaultCustomerId) : [];
    const defaultSiteId = contextSiteId ?? (customerSites.length === 1 ? customerSites[0].id : null);

    setAffiliations([{
      customerId: defaultCustomerId,
      siteId: defaultSiteId,
      role: '',
      isPrimary: true,
    }]);
  }, [affiliations.length, contextCustomerId, contextSiteId, initialData, uniqueCustomers]);

  const handleMalleableChange = (key: string, value: any) => {
    setMalleableData(prev => ({ ...prev, [key]: value }));
  };

  const addAffiliation = () => {
    const defaultCustomerId = contextCustomerId || (uniqueCustomers.length === 1 ? uniqueCustomers[0].id : 0);
    const customerSites = defaultCustomerId ? getSitesForCustomer(defaultCustomerId) : [];
    const defaultSiteId = customerSites.length === 1 ? customerSites[0].id : null;
    setAffiliations([
      ...affiliations,
      { customerId: defaultCustomerId, siteId: defaultSiteId, role: '', isPrimary: affiliations.length === 0 }
    ]);
  };

  const updateAffiliation = (index: number, field: keyof ContactAffiliation, value: any) => {
    const newAffiliations = [...affiliations];
    const current = { ...newAffiliations[index], [field]: value };

    if (field === 'customerId') {
      const customerSites = getSitesForCustomer(Number(value));
      const existingSiteIsValid = current.siteId !== null && customerSites.some(site => site.id === current.siteId);
      if (!existingSiteIsValid) {
        current.siteId = customerSites.length === 1 ? customerSites[0].id : null;
      }
    }

    newAffiliations[index] = current;
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
      <h3>{initialData ? 'Edit Contact' : 'Add Contact'}</h3>
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
          <h4 style={{ borderBottom: '1px solid #eee', paddingBottom: '10px' }}>Assigned Branches</h4>
          {assignedBranchNames.length > 0 && (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginBottom: '10px' }}>
              {assignedBranchNames.map((branchName) => (
                <span key={branchName} style={{ fontSize: '12px', background: '#eef5ff', color: '#1d4f91', border: '1px solid #c9defa', borderRadius: '999px', padding: '2px 10px' }}>
                  {branchName}
                </span>
              ))}
            </div>
          )}
          {affiliations.map((aff, index) => {
            const customerSites = getSitesForCustomer(aff.customerId);
            return (
              <div key={index} style={{ display: 'flex', gap: '10px', alignItems: 'flex-end', marginBottom: '10px', background: '#fff', padding: '10px', border: '1px solid #eee' }}>
                <div style={{ flex: 2 }}>
                  <label style={{ display: 'block', fontSize: '12px' }}>Customer</label>
                  <select
                    value={aff.customerId}
                    onChange={(e) => updateAffiliation(index, 'customerId', parseInt(e.target.value, 10))}
                    style={{ width: '100%' }}
                    required
                  >
                    <option value="">-- Select Customer --</option>
                    {uniqueCustomers.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                </div>
                <div style={{ flex: 2 }}>
                  <label style={{ display: 'block', fontSize: '12px' }}>Assigned Branch</label>
                  <select
                    value={aff.siteId ?? ''}
                    onChange={(e) => updateAffiliation(index, 'siteId', e.target.value ? parseInt(e.target.value, 10) : null)}
                    style={{ width: '100%' }}
                    disabled={!aff.customerId}
                  >
                    <option value="">{aff.customerId ? '-- Optional: Select Branch --' : '-- Select Customer First --'}</option>
                    {customerSites.map(site => <option key={site.id} value={site.id}>{site.name}</option>)}
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
            );
          })}
          {affiliations.length === 0 && (
            <div className="notice notice-info inline" style={{ marginBottom: '10px' }}>
              <p>Add at least one customer affiliation for this contact.</p>
            </div>
          )}
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
            {loading ? 'Saving...' : (initialData ? 'Update Contact' : 'Save Contact')}
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
