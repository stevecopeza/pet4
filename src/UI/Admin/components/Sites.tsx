import React, { useEffect, useMemo, useState } from 'react';
import { Site, Customer, Contact } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import SiteForm from './SiteForm';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

interface SitesProps {
  contextCustomerId?: number | null;
  contextCustomerName?: string | null;
  onStartAddContactFromBranch?: (payload: { customerId: number; siteId: number | null }) => void;
  onReturnToCustomer?: () => void;
  onDataUpdated?: () => void;
}

const Sites: React.FC<SitesProps> = ({
  contextCustomerId = null,
  contextCustomerName = null,
  onStartAddContactFromBranch,
  onReturnToCustomer,
  onDataUpdated,
}) => {
  const [sites, setSites] = useState<Site[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingSite, setEditingSite] = useState<Site | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [createdSiteContext, setCreatedSiteContext] = useState<{ customerId: number; siteId: number | null } | null>(null);

  const fetchSchema = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/schemas/site?status=active`, {
        headers: { 'X-WP-Nonce': nonce },
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

  const fetchData = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const [sitesRes, customersRes, contactsRes] = await Promise.all([
        fetch(`${apiUrl}/sites`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        }),
        fetch(`${apiUrl}/customers`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        }),
        fetch(`${apiUrl}/contacts`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        }),
      ]);

      if (!sitesRes.ok) {
        throw new Error('Failed to fetch branches');
      }
      if (!customersRes.ok) {
        throw new Error('Failed to fetch customers');
      }

      const sitesPayload = await sitesRes.json();
      const customersPayload = await customersRes.json();
      setSites(Array.isArray(sitesPayload) ? sitesPayload : []);
      setCustomers(Array.isArray(customersPayload) ? customersPayload : []);
      if (contactsRes.ok) {
        const contactsPayload = await contactsRes.json();
        setContacts(Array.isArray(contactsPayload) ? contactsPayload : []);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    fetchSchema();
  }, []);

  const customerNameById = useMemo(() => {
    const map = new Map<number, string>();
    customers.forEach(customer => map.set(customer.id, customer.name));
    return map;
  }, [customers]);

  const visibleSites = useMemo(() => {
    if (!contextCustomerId) {
      return sites;
    }
    return sites.filter(site => site.customerId === contextCustomerId);
  }, [contextCustomerId, sites]);

  const contactCountByBranchId = useMemo(() => {
    const counts = new Map<number, number>();
    contacts.forEach(contact => {
      (contact.affiliations || []).forEach(affiliation => {
        if (!affiliation.siteId) return;
        counts.set(affiliation.siteId, (counts.get(affiliation.siteId) || 0) + 1);
      });
    });
    return counts;
  }, [contacts]);

  const handleFormSuccess = (savedSite?: Site) => {
    setShowAddForm(false);
    setEditingSite(null);
    const fallbackCustomerId = contextCustomerId || savedSite?.customerId || 0;
    setCreatedSiteContext({
      customerId: savedSite?.customerId || fallbackCustomerId,
      siteId: savedSite?.id ?? null,
    });
    fetchData();
    onDataUpdated?.();
  };

  const handleEdit = (site: Site) => {
    setEditingSite(site);
    setShowAddForm(true);
    setCreatedSiteContext(null);
  };

  const handleArchive = async (id: number) => {
    if (!legacyConfirm('Are you sure you want to archive this branch?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/sites/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive branch');
      }

      fetchData();
      onDataUpdated?.();
      setSelectedIds(prev => prev.filter(sid => sid !== id));
    } catch (err) {
      legacyAlert('Failed to archive branch');
    }
  };

  const handleBulkArchive = async () => {
    if (selectedIds.length === 0) return;
    if (!legacyConfirm(`Are you sure you want to archive ${selectedIds.length} branches?`)) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      await Promise.all(selectedIds.map(id =>
        fetch(`${apiUrl}/sites/${id}`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce },
        })
      ));

      fetchData();
      onDataUpdated?.();
      setSelectedIds([]);
    } catch (err) {
      legacyAlert('Failed to archive some branches');
    }
  };

  const columns: Column<Site>[] = [
    {
      key: 'name',
      header: 'Branch Name',
      render: (val: any, item: Site) => (
        <button
          type="button"
          onClick={() => handleEdit(item)}
          style={{
            background: 'none',
            border: 'none',
            color: '#2271b1',
            cursor: 'pointer',
            padding: 0,
            textAlign: 'left',
            fontWeight: 'bold',
            fontSize: 'inherit'
          }}
        >
          {String(val)}
        </button>
      )
    },
    ...(contextCustomerId ? [] : [{
      key: 'customerId' as keyof Site,
      header: 'Customer',
      render: (value: any) => customerNameById.get(Number(value)) || `ID: ${value}`,
    }]),
    { key: 'city', header: 'City' },
    {
      key: 'id',
      header: 'Contacts',
      render: (value: any) => {
        const branchId = Number(value);
        const count = contactCountByBranchId.get(branchId) || 0;
        return <span style={{ fontWeight: 600 }}>{count}</span>;
      }
    },
    { key: 'state', header: 'State' },
    { key: 'country', header: 'Country' },
    {
      key: 'status',
      header: 'Status',
      render: (value) => (
        <span className={`pet-status-badge status-${String(value).toLowerCase()}`}>
          {String(value)}
        </span>
      )
    },
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Site,
      header: field.label,
      render: (_: any, item: Site) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    {
      key: 'createdAt',
      header: 'Created',
      render: (value) => value ? new Date(value as string).toLocaleDateString() : '-'
    }
  ];

  if (showAddForm) {
    return (
      <SiteForm
        onSuccess={handleFormSuccess}
        onCancel={() => {
          setShowAddForm(false);
          setEditingSite(null);
        }}
        initialData={editingSite || undefined}
        contextCustomerId={contextCustomerId}
        lockCustomerSelection={Boolean(contextCustomerId)}
      />
    );
  }

  return (
    <div className="pet-sites-container">
      {contextCustomerName && (
        <div style={{ marginBottom: '12px' }}>
          <h2 style={{ margin: 0 }}>Branches for {contextCustomerName}</h2>
          <p style={{ margin: '4px 0 0', color: '#666' }}>Customer Setup</p>
        </div>
      )}
      {createdSiteContext && (
        <div className="notice notice-success inline" style={{ marginBottom: '15px' }}>
          <p style={{ marginBottom: '10px' }}>
            Branch saved. Continue onboarding by adding a contact for this customer.
          </p>
          <div style={{ display: 'flex', gap: '10px' }}>
            <button
              className="button button-primary"
              onClick={() => onStartAddContactFromBranch?.(createdSiteContext)}
              type="button"
            >
              Add Contact
            </button>
            <button
              className="button"
              onClick={() => {
                setCreatedSiteContext(null);
                onReturnToCustomer?.();
              }}
              type="button"
            >
              Return to Customer
            </button>
          </div>
        </div>
      )}
      <div className="pet-actions-bar" style={{ marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div className="pet-bulk-actions">
          {selectedIds.length > 0 && (
            <button
              className="button"
              onClick={handleBulkArchive}
              style={{ color: '#b32d2e', borderColor: '#b32d2e' }}
            >
              Archive Selected ({selectedIds.length})
            </button>
          )}
        </div>
        <button
          className="button button-primary"
          onClick={() => {
            setCreatedSiteContext(null);
            setShowAddForm(true);
          }}
        >
          Add Branch
        </button>
      </div>

      {error && (
        <div className="notice notice-error inline">
          <p>{error}</p>
        </div>
      )}

      {!loading && !error && visibleSites.length === 0 ? (
        <div className="notice notice-info inline" style={{ padding: '16px' }}>
          <h3 style={{ marginTop: 0 }}>No branches added yet</h3>
          <p style={{ marginBottom: '12px' }}>
            Add the first branch to continue the customer onboarding journey.
          </p>
          <button className="button button-primary" onClick={() => setShowAddForm(true)} type="button">
            Add first branch
          </button>
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={visibleSites}
          loading={loading}
          selection={{
            selectedIds,
            onSelectionChange: setSelectedIds
          }}
          actions={(site) => (
            <KebabMenu items={[
              { type: 'action', label: 'Edit', onClick: () => handleEdit(site) },
              { type: 'action', label: 'Add Contact', onClick: () => onStartAddContactFromBranch?.({ customerId: site.customerId, siteId: site.id }) },
              { type: 'action', label: 'Archive', onClick: () => handleArchive(site.id), danger: true },
            ]} />
          )}
        />
      )}
    </div>
  );
};

export default Sites;
