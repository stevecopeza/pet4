import React, { useState, useEffect } from 'react';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';
import { Customer, SchemaDefinition, Ticket, Site, Sla, Contact, Team, Employee } from '../types';

interface TicketFormProps {
  initialData?: Ticket;
  onSuccess: () => void;
  onCancel: () => void;
}

const TicketForm: React.FC<TicketFormProps> = ({ initialData, onSuccess, onCancel }) => {
  const isEditMode = !!initialData;
  const [customerId, setCustomerId] = useState(initialData?.customerId?.toString() || '');
  const [siteId, setSiteId] = useState(initialData?.siteId?.toString() || '');
  const [slaId, setSlaId] = useState(initialData?.slaId?.toString() || '');
  const [subject, setSubject] = useState(initialData?.subject || '');
  const [description, setDescription] = useState(initialData?.description || '');
  const [priority, setPriority] = useState(initialData?.priority || 'medium');
  const [status, setStatus] = useState(initialData?.status || 'new');
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [sites, setSites] = useState<Site[]>([]);
  const [slas, setSlas] = useState<Sla[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [category, setCategory] = useState<string>(initialData?.category || '');
  const [subcategory, setSubcategory] = useState<string>(initialData?.subcategory || '');
  const [source, setSource] = useState<string>(initialData?.intake_source || 'portal');
  const [contactId, setContactId] = useState<string>(initialData?.contactId ? String(initialData.contactId) : '');
  const [assignment, setAssignment] = useState<string>(() => {
    if (initialData?.queueId) {
      return `queue:${initialData.queueId}`;
    }
    if (initialData?.ownerUserId) {
      return `user:${initialData.ownerUserId}`;
    }
    return '';
  });
  // @ts-ignore
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingCustomers, setLoadingCustomers] = useState(true);
  const [loadingSites, setLoadingSites] = useState(false);
  const [loadingSlas, setLoadingSlas] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const flattenTeams = (nodes: Team[]): Team[] => {
    const flat: Team[] = [];
    const walk = (items: Team[]) => {
      items.forEach((item) => {
        flat.push(item);
        if (item.children && item.children.length > 0) {
          walk(item.children);
        }
      });
    };
    walk(nodes);
    return flat;
  };

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

    const fetchSlas = async () => {
      setLoadingSlas(true);
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/slas`, {
          headers: {
            // @ts-ignore
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setSlas(data);
        }
      } catch (err) {
        console.error('Failed to fetch SLAs', err);
      } finally {
        setLoadingSlas(false);
      }
    };

    const fetchSchema = async () => {
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/schemas/ticket?status=active`, {
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

    const fetchContactsTeamsEmployees = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings.nonce;

        const [contactsRes, teamsRes, employeesRes] = await Promise.all([
          fetch(`${apiUrl}/contacts`, { headers: { 'X-WP-Nonce': nonce } }),
          fetch(`${apiUrl}/teams`, { headers: { 'X-WP-Nonce': nonce } }),
          fetch(`${apiUrl}/employees`, { headers: { 'X-WP-Nonce': nonce } }),
        ]);

        if (contactsRes.ok) {
          setContacts(await contactsRes.json());
        }
        if (teamsRes.ok) {
          setTeams(await teamsRes.json());
        }
        if (employeesRes.ok) {
          setEmployees(await employeesRes.json());
        }
      } catch (err) {
        console.error('Failed to fetch contacts/teams/employees', err);
      }
    };

    fetchCustomers();
    fetchSlas();
    fetchSchema();
    fetchContactsTeamsEmployees();
  }, [isEditMode]);

  useEffect(() => {
    const fetchSites = async () => {
      if (!customerId) {
        setSites([]);
        setSiteId('');
        return;
      }

      setLoadingSites(true);
      try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/sites?customer_id=${customerId}`, {
          headers: {
            // @ts-ignore
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setSites(data);
          
          // If editing and we have a siteId, it will be set by initialData state
          // If changing customer, reset siteId unless it matches (unlikely)
          if (!isEditMode || (initialData?.customerId.toString() !== customerId)) {
            // Don't auto-select site for now
            setSiteId('');
          }
        }
      } catch (err) {
        console.error('Failed to fetch sites', err);
      } finally {
        setLoadingSites(false);
      }
    };

    fetchSites();
  }, [customerId, isEditMode, initialData]);

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

    if (!source) {
      setError('Source is required');
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
        ? `${apiUrl}/tickets/${initialData!.id}`
        : `${apiUrl}/tickets`;

      const trimmedCategory = category.trim();
      const trimmedSubcategory = subcategory.trim();

      const body: any = { 
        customerId: parseInt(customerId, 10),
        subject,
        description,
        priority,
        source,
        malleableData
      };

      if (trimmedCategory !== '') {
        body.category = trimmedCategory;
      }

      if (trimmedSubcategory !== '') {
        body.subcategory = trimmedSubcategory;
      }

      if (siteId) {
        body.siteId = parseInt(siteId, 10);
      }

      if (slaId) {
        body.slaId = parseInt(slaId, 10);
      }

      if (contactId) {
        body.contactId = parseInt(contactId, 10);
      }

      if (assignment) {
        body.assignment = assignment;
      }

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
        throw new Error(data.message || `Failed to ${isEditMode ? 'update' : 'create'} ticket`);
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
      <h3>{isEditMode ? 'Edit Ticket' : 'Create New Ticket'}</h3>
      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-customer" style={{ display: 'block', marginBottom: '5px' }}>Customer:</label>
          {loadingCustomers ? (
            <div>Loading customers...</div>
          ) : (
            <select 
              id="pet-ticket-customer"
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

        {customerId && (
          <div style={{ marginBottom: '10px' }}>
            <label htmlFor="pet-ticket-site" style={{ display: 'block', marginBottom: '5px' }}>Site (Optional):</label>
            {loadingSites ? (
              <div>Loading sites...</div>
            ) : (
              <select 
                id="pet-ticket-site"
                value={siteId} 
                onChange={(e) => setSiteId(e.target.value)}
                style={{ width: '100%', maxWidth: '400px' }}
              >
                <option value="">Select a site</option>
                {sites.map(s => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
            )}
          </div>
        )}

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-sla" style={{ display: 'block', marginBottom: '5px' }}>SLA (Optional):</label>
          {loadingSlas ? (
            <div>Loading SLAs...</div>
          ) : (
            <select
              id="pet-ticket-sla"
              value={slaId}
              onChange={(e) => setSlaId(e.target.value)}
              style={{ width: '100%', maxWidth: '400px' }}
            >
              <option value="">Select SLA</option>
              {slas.map(s => (
                <option key={s.id} value={s.id}>{s.name} ({s.response_target_minutes != null ? Math.round(s.response_target_minutes / 60) : '—'}h / {s.resolution_target_minutes != null ? Math.round(s.resolution_target_minutes / 60) : '—'}h)</option>
              ))}
            </select>
          )}
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-subject" style={{ display: 'block', marginBottom: '5px' }}>Subject:</label>
          <input 
            id="pet-ticket-subject"
            type="text" 
            value={subject} 
            onChange={(e) => setSubject(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-category" style={{ display: 'block', marginBottom: '5px' }}>Category:</label>
          <input
            id="pet-ticket-category"
            type="text"
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-subcategory" style={{ display: 'block', marginBottom: '5px' }}>Subcategory:</label>
          <input
            id="pet-ticket-subcategory"
            type="text"
            value={subcategory}
            onChange={(e) => setSubcategory(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-source" style={{ display: 'block', marginBottom: '5px' }}>Source:</label>
          <select
            id="pet-ticket-source"
            value={source}
            onChange={(e) => setSource(e.target.value)}
            required
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="portal">Portal</option>
            <option value="email">Email</option>
            <option value="phone">Phone</option>
            <option value="api">API</option>
            <option value="monitoring">Monitoring</option>
          </select>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-contact" style={{ display: 'block', marginBottom: '5px' }}>Contact (Optional):</label>
          <select
            id="pet-ticket-contact"
            value={contactId}
            onChange={(e) => setContactId(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="">Select contact</option>
            {contacts
              .filter((c) => {
                if (!customerId) return true;
                return (c.affiliations || []).some((a) => a.customerId === Number(customerId));
              })
              .map((c) => (
                <option key={c.id} value={c.id}>
                  {c.firstName} {c.lastName} ({c.email})
                </option>
              ))}
          </select>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-assignment" style={{ display: 'block', marginBottom: '5px' }}>Assignment:</label>
          <select
            id="pet-ticket-assignment"
            value={assignment}
            onChange={(e) => setAssignment(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="">Unassigned</option>
            <optgroup label="Queues">
              {flattenTeams(teams)
                .filter((t) => t.status === 'active')
                .map((t) => (
                  <option key={`queue-${t.id}`} value={`queue:${t.id}`}>
                    {t.name}
                  </option>
                ))}
            </optgroup>
            <optgroup label="People">
              {employees
                .filter((e) => e.status !== 'archived')
                .map((e) => (
                  <option key={`user-${e.wpUserId}`} value={`user:${e.wpUserId}`}>
                    {e.firstName} {e.lastName}
                  </option>
                ))}
            </optgroup>
          </select>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-priority" style={{ display: 'block', marginBottom: '5px' }}>Priority:</label>
          <select 
            id="pet-ticket-priority"
            value={priority} 
            onChange={(e) => setPriority(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
        </div>

        {isEditMode && (
          <div style={{ marginBottom: '10px' }}>
            <label htmlFor="pet-ticket-status" style={{ display: 'block', marginBottom: '5px' }}>Status:</label>
            <select 
              id="pet-ticket-status"
              value={status} 
              onChange={(e) => setStatus(e.target.value)}
              style={{ width: '100%', maxWidth: '400px' }}
            >
              <option value="new">New</option>
              <option value="open">Open</option>
              <option value="pending">Pending</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        )}

        <div style={{ marginBottom: '10px' }}>
          <label htmlFor="pet-ticket-description" style={{ display: 'block', marginBottom: '5px' }}>Description:</label>
          <textarea 
            id="pet-ticket-description"
            value={description} 
            onChange={(e) => setDescription(e.target.value)} 
            required 
            rows={4}
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        {activeSchema && (
          <div style={{ marginBottom: '20px', padding: '15px', background: '#fff', border: '1px solid #eee' }}>
            <h4 style={{ marginTop: 0, marginBottom: '15px' }}>Additional Information</h4>
            <MalleableFieldsRenderer 
              schema={activeSchema}
              values={malleableData}
              onChange={handleMalleableChange}
            />
          </div>
        )}

        <div style={{ marginTop: '15px' }}>
          <button 
            type="submit" 
            disabled={loading || loadingCustomers}
            className="button button-primary"
            style={{ marginRight: '10px' }}
          >
            {loading ? 'Saving...' : (isEditMode ? 'Update Ticket' : 'Create Ticket')}
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

export default TicketForm;
