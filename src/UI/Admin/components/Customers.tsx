import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Customer, Contact, Site } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import CustomerForm from './CustomerForm';
import Sites from './Sites';
import Contacts from './Contacts';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';

type CustomerSetupStatus = 'incomplete' | 'partial' | 'ready';

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
    () => customers.find(customer => customer.id === selectedCustomerId) || null,
    [customers, selectedCustomerId]
  );

  const customerSetupById = useMemo(() => {
    const byCustomerId = new Map<number, { branchCount: number; contactCount: number; status: CustomerSetupStatus }>();
    customers.forEach(customer => {
      const branchCount = sites.filter(site => site.customerId === customer.id).length;
      const contactCount = contacts.filter(contact =>
        (contact.affiliations || []).some(aff => aff.customerId === customer.id)
        || (contact as any).customerId === customer.id
      ).length;
      const status: CustomerSetupStatus = branchCount === 0 ? 'incomplete' : (contactCount === 0 ? 'partial' : 'ready');
      byCustomerId.set(customer.id, { branchCount, contactCount, status });
    });
    return byCustomerId;
  }, [customers, sites, contacts]);

  const selectedCustomerBranchCount = useMemo(() => {
    if (!selectedCustomerId) return 0;
    return sites.filter(site => site.customerId === selectedCustomerId).length;
  }, [selectedCustomerId, sites]);

  const selectedCustomerContactCount = useMemo(() => {
    if (!selectedCustomerId) return 0;
    return contacts.filter(contact =>
      (contact.affiliations || []).some(aff => aff.customerId === selectedCustomerId)
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
      setSelectedIds(prev => prev.filter(sid => sid !== id));
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
      const results = await Promise.allSettled(selectedIds.map(id =>
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
          onClick={() => openCustomerJourney(item)}
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
    { key: 'legalName', header: 'Legal Name' },
    { key: 'contactEmail', header: 'Email' },
    {
      key: 'id',
      header: 'Setup',
      render: (_val: any, item: Customer) => {
        const setup = customerSetupById.get(item.id) || { branchCount: 0, contactCount: 0, status: 'incomplete' as const };
        const statusText = setup.status === 'ready' ? 'Ready' : setup.status === 'partial' ? 'Partially configured' : 'Incomplete';
        return (
          <div>
            <span className={`pet-status-badge status-${setup.status === 'ready' ? 'active' : setup.status === 'partial' ? 'inactive' : 'churned'}`}>
              {statusText}
            </span>
            <div style={{ marginTop: '4px', fontSize: '12px', color: '#666' }}>
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
      render: (val: any) => val ? <span style={{ color: '#999' }}>Yes</span> : '-'
    },
  ];

  const scrollToBranches = () => {
    branchSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const scrollToContacts = () => {
    contactSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const inCustomerContext = selectedCustomer !== null;

  return (
    <div className="pet-crm-container">
      <div className="pet-customers">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
          <h2>{inCustomerContext ? 'Customer Setup' : 'Customers'}</h2>
          <div style={{ display: 'flex', gap: '10px' }}>
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
        </div>

        {showAddForm && !inCustomerContext && (
          <CustomerForm
            onSuccess={handleFormSuccess}
            onCancel={() => { setShowAddForm(false); setEditingCustomer(null); }}
            initialData={editingCustomer || undefined}
          />
        )}

        {inCustomerContext && selectedCustomer && (
          <>
            <div style={{ marginBottom: '22px', background: '#fff', border: '1px solid #dcdcde', borderRadius: '8px', padding: '20px', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '12px', flexWrap: 'wrap', marginBottom: '16px' }}>
                <div>
                  <h1 style={{ margin: 0, fontSize: '30px', lineHeight: 1.2 }}>{selectedCustomer.name}</h1>
                  <p style={{ margin: '6px 0 0', color: '#666', fontSize: '13px', fontWeight: 700, letterSpacing: '0.03em' }}>CUSTOMER SETUP</p>
                </div>
                <span className={`pet-status-badge status-${setupStatus === 'ready' ? 'active' : setupStatus === 'partial' ? 'inactive' : 'churned'}`}>
                  {setupStatusLabel}
                </span>
              </div>

              <div style={{ borderTop: '1px solid #f0f0f1', paddingTop: '14px' }}>
                <h3 style={{ marginTop: 0, marginBottom: '10px', fontSize: '18px' }}>Setup Summary</h3>
                <p style={{ margin: 0, fontSize: '15px' }}>
                  <strong>{selectedCustomerBranchCount}</strong> {selectedCustomerBranchCount === 1 ? 'branch' : 'branches'} · <strong>{selectedCustomerContactCount}</strong> {selectedCustomerContactCount === 1 ? 'contact' : 'contacts'}
                </p>
                <p style={{ margin: '8px 0 0', color: '#555', fontSize: '14px' }}>
                  {setupStatus === 'ready' && 'Customer ready for operations.'}
                  {setupStatus === 'incomplete' && 'Add at least one branch to continue setup.'}
                  {setupStatus === 'partial' && 'Add at least one contact to complete setup.'}
                </p>
              </div>
              <div
                style={{
                  marginTop: '14px',
                  background: setupStatus === 'ready' ? '#ecf8ef' : setupStatus === 'partial' ? '#fff8e8' : '#f6f7f7',
                  border: `1px solid ${setupStatus === 'ready' ? '#b7e4c1' : setupStatus === 'partial' ? '#f0d29f' : '#dcdcde'}`,
                  borderRadius: '6px',
                  padding: '12px',
                }}
              >
                <strong style={{ display: 'block', marginBottom: '4px' }}>
                  {setupStatus === 'ready' ? '✅ Customer setup complete' : setupStatus === 'partial' ? '🟠 Setup in progress' : '⚪ Setup not started'}
                </strong>
                <span style={{ fontSize: '13px', color: '#444' }}>
                  {setupStatus === 'ready' && 'Ready for operations.'}
                  {setupStatus === 'partial' && 'Branches are configured. Add contacts to complete setup.'}
                  {setupStatus === 'incomplete' && 'Start by adding the first branch.'}
                </span>
              </div>
            </div>

            <div style={{ marginBottom: '20px', display: 'flex', gap: '10px' }}>
              <button className="button" type="button" onClick={scrollToBranches}>Go to Step 1 — Branches</button>
              <button className="button" type="button" onClick={scrollToContacts}>Go to Step 2 — Contacts</button>
            </div>

            <section
              ref={branchSectionRef}
              style={{ marginBottom: '26px', background: '#fff', border: '1px solid #dcdcde', borderRadius: '8px', padding: '18px', boxShadow: '0 1px 3px rgba(0,0,0,0.05)', borderLeft: '5px solid #2271b1' }}
            >
              <div style={{ marginBottom: '12px' }}>
                <div style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', minWidth: '28px', height: '28px', borderRadius: '14px', background: '#e8f3ff', color: '#135e96', fontWeight: 700, marginBottom: '8px' }}>1</div>
                <h2 style={{ margin: 0, fontSize: '24px' }}>Step 1 — Branches</h2>
                <p style={{ margin: '6px 0 0', color: '#555', fontSize: '14px' }}>Branches are required before contact assignment.</p>
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
            </section>

            <section
              ref={contactSectionRef}
              style={{ marginBottom: '26px', background: '#fff', border: '1px solid #dcdcde', borderRadius: '8px', padding: '18px', boxShadow: '0 1px 3px rgba(0,0,0,0.05)', borderLeft: '5px solid #4f94d4' }}
            >
              <div style={{ marginBottom: '12px' }}>
                <div style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', minWidth: '28px', height: '28px', borderRadius: '14px', background: '#edf6ff', color: '#135e96', fontWeight: 700, marginBottom: '8px' }}>2</div>
                <h2 style={{ margin: 0, fontSize: '24px' }}>Step 2 — Contacts</h2>
                <p style={{ margin: '6px 0 0', color: '#555', fontSize: '14px' }}>Assign contacts to customer branches for operational readiness.</p>
              </div>
              <Contacts
                contextCustomerId={selectedCustomerId}
                contextSiteId={contactContextSiteId}
                contextCustomerName={selectedCustomer?.name ?? null}
                onReturnToCustomer={scrollToContacts}
                onDataUpdated={fetchData}
              />
            </section>
          </>
        )}

        {!inCustomerContext && (
          <>
            <div className="pet-actions-bar" style={{ marginBottom: '15px' }}>
              {selectedIds.length > 0 && (
                <button
                  className="button"
                  onClick={() => setConfirmBulkArchive(true)}
                  style={{ color: '#b32d2e', borderColor: '#b32d2e' }}
                >
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
              actions={(item) => (
                <KebabMenu items={[
                  { type: 'action', label: 'Open Setup', onClick: () => openCustomerJourney(item) },
                  { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
                  { type: 'action', label: 'Archive', onClick: () => setPendingArchiveId(item.id), danger: true },
                ]} />
              )}
            />
          </>
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
      </div>
    </div>
  );
};

export default Customers;
