import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Customer, Contact, Site } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import CustomerForm from './CustomerForm';
import Sites from './Sites';
import Contacts from './Contacts';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';
import useToast from './foundation/useToast';

type CustomerSetupStatus = 'incomplete' | 'partial' | 'ready';

const setupStatusBadgeClass = (status: CustomerSetupStatus): string => (
  `pet-status-badge status-${status === 'ready' ? 'active' : status === 'partial' ? 'inactive' : 'churned'}`
);

const Customers = () => {
  const toast = useToast();
  const branchSectionRef = useRef<HTMLElement | null>(null);
  const contactSectionRef = useRef<HTMLElement | null>(null);

  const [customers, setCustomers] = useState<Customer[]>([]);
  const [sites, setSites] = useState<Site[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null);
  const [contactContextSiteId, setContactContextSiteId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [archiveBusy, setArchiveBusy] = useState(false);
  const [pendingArchiveId, setPendingArchiveId] = useState<number | null>(null);
  const [confirmBulkArchive, setConfirmBulkArchive] = useState(false);

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

  const fetchData = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const [customersRes, sitesRes, contactsRes] = await Promise.all([
        fetch(`${apiUrl}/customers`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        }),
        fetch(`${apiUrl}/sites`, {
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

      if (!customersRes.ok) {
        throw new Error('Failed to fetch customers');
      }

      const customersPayload = await customersRes.json();
      setCustomers(Array.isArray(customersPayload) ? customersPayload : []);
      if (sitesRes.ok) {
        const sitesPayload = await sitesRes.json();
        setSites(Array.isArray(sitesPayload) ? sitesPayload : []);
      }
      if (contactsRes.ok) {
        const contactsPayload = await contactsRes.json();
        setContacts(Array.isArray(contactsPayload) ? contactsPayload : []);
      }
      setError(null);
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

  const selectedCustomer = useMemo(
    () => customers.find((customer) => customer.id === selectedCustomerId) || null,
    [customers, selectedCustomerId]
  );

  const customerSetupById = useMemo(() => {
    const byCustomerId = new Map<number, { branchCount: number; contactCount: number; status: CustomerSetupStatus }>();
    customers.forEach((customer) => {
      const branchCount = sites.filter((site) => site.customerId === customer.id).length;
      const contactCount = contacts.filter((contact) =>
        (contact.affiliations || []).some((aff) => aff.customerId === customer.id)
        || (contact as any).customerId === customer.id
      ).length;
      const status: CustomerSetupStatus = branchCount === 0 ? 'incomplete' : (contactCount === 0 ? 'partial' : 'ready');
      byCustomerId.set(customer.id, { branchCount, contactCount, status });
    });
    return byCustomerId;
  }, [customers, sites, contacts]);

  const selectedCustomerBranchCount = useMemo(() => {
    if (!selectedCustomerId) return 0;
    return sites.filter((site) => site.customerId === selectedCustomerId).length;
  }, [selectedCustomerId, sites]);

  const selectedCustomerContactCount = useMemo(() => {
    if (!selectedCustomerId) return 0;
    return contacts.filter((contact) =>
      (contact.affiliations || []).some((aff) => aff.customerId === selectedCustomerId)
      || (contact as any).customerId === selectedCustomerId
    ).length;
  }, [contacts, selectedCustomerId]);

  const setupStatus: CustomerSetupStatus = useMemo(() => {
    if (!selectedCustomerId || selectedCustomerBranchCount === 0) return 'incomplete';
    if (selectedCustomerContactCount === 0) return 'partial';
    return 'ready';
  }, [selectedCustomerBranchCount, selectedCustomerContactCount, selectedCustomerId]);

  const setupStatusLabel = setupStatus === 'incomplete'
    ? 'Incomplete'
    : setupStatus === 'partial'
    ? 'Partially configured'
    : 'Ready';

  const orderedCustomers = useMemo(() => {
    const rank: Record<CustomerSetupStatus, number> = { incomplete: 0, partial: 1, ready: 2 };
    return [...customers].sort((a, b) => {
      const aSetup = customerSetupById.get(a.id)?.status || 'incomplete';
      const bSetup = customerSetupById.get(b.id)?.status || 'incomplete';
      if (rank[aSetup] !== rank[bSetup]) return rank[aSetup] - rank[bSetup];
      return a.name.localeCompare(b.name);
    });
  }, [customerSetupById, customers]);

  const openCustomerJourney = (customer: Customer) => {
    setSelectedCustomerId(customer.id);
    setShowAddForm(false);
    setEditingCustomer(null);
    setContactContextSiteId(null);
  };

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingCustomer(null);
    fetchData();
  };

  const handleEdit = (customer: Customer) => {
    setEditingCustomer(customer);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    setArchiveBusy(true);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/customers/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive customer');
      }

      fetchData();
      if (selectedCustomerId === id) {
        setSelectedCustomerId(null);
      }
      setSelectedIds((prev) => prev.filter((sid) => sid !== id));
      toast.success('Customer archived');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Failed to archive');
    } finally {
      setArchiveBusy(false);
      setPendingArchiveId(null);
    }
  };

  const handleBulkArchive = async () => {
    setArchiveBusy(true);

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    try {
      const results = await Promise.allSettled(selectedIds.map((id) =>
        fetch(`${apiUrl}/customers/${id}`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce },
        }).then((response) => {
          if (!response.ok) {
            throw new Error(`Failed to archive customer ${id}`);
          }
        })
      ));

      const failedCount = results.filter((result) => result.status === 'rejected').length;
      const successCount = selectedIds.length - failedCount;

      if (successCount > 0) {
        setSelectedIds([]);
      }
      if (selectedCustomerId && selectedIds.includes(selectedCustomerId)) {
        setSelectedCustomerId(null);
      }
      fetchData();
      if (failedCount > 0) {
        toast.error(`Archived ${successCount} customers; ${failedCount} failed.`);
      } else {
        toast.success(`Archived ${successCount} customers.`);
      }
    } catch (e) {
      console.error('Bulk archive failed', e);
      toast.error('Failed to archive selected customers.');
    } finally {
      setArchiveBusy(false);
      setConfirmBulkArchive(false);
    }
  };

  const columns: Column<Customer>[] = [
    {
      key: 'name',
      header: 'Customer',
      render: (val: any, item: Customer) => (
        <button
          type="button"
          className="button-link pet-customers-name-link"
          onClick={() => openCustomerJourney(item)}
        >
          {String(val)}
        </button>
      )
    },
    { key: 'legalName', header: 'Legal Name' },
    { key: 'contactEmail', header: 'Email' },
    {
      key: 'id',
      header: 'Setup',
      render: (_val: any, item: Customer) => {
        const setup = customerSetupById.get(item.id) || { branchCount: 0, contactCount: 0, status: 'incomplete' as const };
        const statusText = setup.status === 'ready' ? 'Ready' : setup.status === 'partial' ? 'Partially configured' : 'Incomplete';
        return (
          <div className="pet-customers-setup-cell">
            <span className={setupStatusBadgeClass(setup.status)}>
              {statusText}
            </span>
            <div className="pet-customers-setup-meta">
              {setup.branchCount} {setup.branchCount === 1 ? 'branch' : 'branches'} · {setup.contactCount} {setup.contactCount === 1 ? 'contact' : 'contacts'}
            </div>
          </div>
        );
      }
    },
    {
      key: 'status',
      header: 'Status',
      render: (val: any) => (
        <span className={`pet-status-badge status-${String(val).toLowerCase()}`}>
          {String(val)}
        </span>
      )
    },
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Customer,
      header: field.label,
      render: (_: any, item: Customer) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    {
      key: 'createdAt',
      header: 'Created',
      render: (val: any) => val ? new Date(val as string).toLocaleDateString() : '-'
    },
    {
      key: 'archivedAt',
      header: 'Archived',
      render: (val: any) => val ? <span className="pet-customers-archived-flag">Yes</span> : '-'
    },
  ];

  const scrollToBranches = () => {
    branchSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const scrollToContacts = () => {
    contactSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const inCustomerContext = selectedCustomer !== null;
  const setupCalloutTitle = setupStatus === 'ready'
    ? 'Customer setup complete'
    : setupStatus === 'partial'
    ? 'Setup in progress'
    : 'Setup not started';
  const setupCalloutBody = setupStatus === 'ready'
    ? 'Ready for operations.'
    : setupStatus === 'partial'
    ? 'Branches are configured. Add contacts to complete setup.'
    : 'Start by adding the first branch.';

  return (
    <PageShell
      className="pet-customers-page"
      title={inCustomerContext ? 'Customer Setup' : 'Customers'}
      subtitle={inCustomerContext && selectedCustomer
        ? `Guided onboarding for ${selectedCustomer.name}.`
        : 'Manage customers and complete operational readiness setup.'}
      actions={(
        <div className="pet-customers-shell-actions">
          {inCustomerContext && (
            <button className="button" type="button" onClick={() => setSelectedCustomerId(null)}>
              Back to Customer List
            </button>
          )}
          {!showAddForm && !inCustomerContext && (
            <button className="button button-primary" onClick={() => setShowAddForm(true)}>
              Add New Customer
            </button>
          )}
        </div>
      )}
    >
      {showAddForm && !inCustomerContext && (
        <Panel className="pet-customers-form-panel">
          <CustomerForm
            onSuccess={handleFormSuccess}
            onCancel={() => { setShowAddForm(false); setEditingCustomer(null); }}
            initialData={editingCustomer || undefined}
          />
        </Panel>
      )}

      {inCustomerContext && selectedCustomer && (
        <>
          <Panel className="pet-customers-hero-panel">
            <div className="pet-customers-hero-header">
              <div>
                <h3>{selectedCustomer.name}</h3>
                <p>CUSTOMER SETUP</p>
              </div>
              <span className={setupStatusBadgeClass(setupStatus)}>{setupStatusLabel}</span>
            </div>

            <div className="pet-customers-summary-grid">
              <div className="pet-customers-summary-item">
                <span className="pet-customers-summary-label">Branches</span>
                <strong className="pet-customers-summary-value">{selectedCustomerBranchCount}</strong>
              </div>
              <div className="pet-customers-summary-item">
                <span className="pet-customers-summary-label">Contacts</span>
                <strong className="pet-customers-summary-value">{selectedCustomerContactCount}</strong>
              </div>
              <div className="pet-customers-summary-item">
                <span className="pet-customers-summary-label">Setup state</span>
                <strong className="pet-customers-summary-value">{setupStatusLabel}</strong>
              </div>
            </div>

            <div className={`pet-customers-setup-callout pet-customers-setup-callout--${setupStatus}`}>
              <strong>{setupCalloutTitle}</strong>
              <span>{setupCalloutBody}</span>
            </div>
          </Panel>

          <Panel className="pet-customers-jump-panel">
            <button className="button" type="button" onClick={scrollToBranches}>Go to Step 1 — Branches</button>
            <button className="button" type="button" onClick={scrollToContacts}>Go to Step 2 — Contacts</button>
          </Panel>

          <section ref={branchSectionRef}>
            <Panel className="pet-customers-step-panel pet-customers-step-panel--branches">
              <div className="pet-customers-step-header">
                <div className="pet-customers-step-index">1</div>
                <div>
                  <h3>Step 1 — Branches</h3>
                  <p>Branches are required before contact assignment.</p>
                </div>
              </div>
              <Sites
                contextCustomerId={selectedCustomerId}
                contextCustomerName={selectedCustomer?.name ?? null}
                onStartAddContactFromBranch={({ customerId, siteId }) => {
                  setSelectedCustomerId(customerId);
                  setContactContextSiteId(siteId);
                  scrollToContacts();
                }}
                onReturnToCustomer={scrollToBranches}
                onDataUpdated={fetchData}
              />
            </Panel>
          </section>

          <section ref={contactSectionRef}>
            <Panel className="pet-customers-step-panel pet-customers-step-panel--contacts">
              <div className="pet-customers-step-header">
                <div className="pet-customers-step-index">2</div>
                <div>
                  <h3>Step 2 — Contacts</h3>
                  <p>Assign contacts to customer branches for operational readiness.</p>
                </div>
              </div>
              <Contacts
                contextCustomerId={selectedCustomerId}
                contextSiteId={contactContextSiteId}
                contextCustomerName={selectedCustomer?.name ?? null}
                onReturnToCustomer={scrollToContacts}
                onDataUpdated={fetchData}
              />
            </Panel>
          </section>
        </>
      )}

      {!inCustomerContext && !showAddForm && (
        <Panel className="pet-customers-table-panel">
          <div className="pet-customers-table-toolbar">
            {selectedIds.length > 0 && (
              <button className="button pet-customers-archive-selected-btn" onClick={() => setConfirmBulkArchive(true)}>
                Archive Selected ({selectedIds.length})
              </button>
            )}
          </div>

          <DataTable
            columns={columns}
            data={orderedCustomers}
            loading={loading}
            error={error}
            onRetry={fetchData}
            emptyMessage="No customers found."
            compatibilityMode="wp"
            selection={{
              selectedIds,
              onSelectionChange: setSelectedIds
            }}
            rowClassName={(item) => `pet-customers-row pet-customers-row--${customerSetupById.get(item.id)?.status || 'incomplete'}`}
            actions={(item) => (
              <KebabMenu items={[
                { type: 'action', label: 'Open Setup', onClick: () => openCustomerJourney(item) },
                { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
                { type: 'action', label: 'Archive', onClick: () => setPendingArchiveId(item.id), danger: true },
              ]} />
            )}
          />
        </Panel>
      )}

      <ConfirmationDialog
        open={pendingArchiveId !== null}
        title="Archive customer?"
        description="This action will archive the selected customer."
        confirmLabel="Archive"
        busy={archiveBusy}
        onCancel={() => setPendingArchiveId(null)}
        onConfirm={() => {
          if (pendingArchiveId !== null) {
            handleArchive(pendingArchiveId);
          }
        }}
      />

      <ConfirmationDialog
        open={confirmBulkArchive}
        title="Archive selected customers?"
        description={`This action will archive ${selectedIds.length} selected customers.`}
        confirmLabel="Archive selected"
        busy={archiveBusy}
        onCancel={() => setConfirmBulkArchive(false)}
        onConfirm={handleBulkArchive}
      />
    </PageShell>
  );
};

export default Customers;
