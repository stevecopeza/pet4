import React, { useEffect, useState } from 'react';
import { Customer } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import CustomerForm from './CustomerForm';
import Sites from './Sites';
import Contacts from './Contacts';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';

const Customers = () => {
  const [activeTab, setActiveTab] = useState<'customers' | 'sites' | 'contacts'>('customers');
  const toast = useToast();
  
  // Customer State
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
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

  const fetchCustomers = async () => {
    try {
      setLoading(true);
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
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCustomers();
    fetchSchema();
  }, []);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingCustomer(null);
    fetchCustomers();
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

      fetchCustomers();
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
      fetchCustomers();
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
      header: 'Name', 
      render: (val: any, item: Customer) => (
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
    { key: 'legalName', header: 'Legal Name' },
    { key: 'contactEmail', header: 'Email' },
    { 
      key: 'status', 
      header: 'Status', 
      render: (val: any) => (
        <span className={`pet-status-badge status-${String(val).toLowerCase()}`}>
          {String(val)}
        </span>
      ) 
    },
    // Add malleable fields if they exist in schema
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
      render: (val: any) => val ? <span style={{color: '#999'}}>Yes</span> : '-' 
    },
  ];

  return (
    <div className="pet-crm-container">
      <div style={{ marginBottom: '20px', borderBottom: '1px solid #eee' }}>
        <button 
          className={`button ${activeTab === 'customers' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('customers')}
          style={{ marginRight: '10px', marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          Customers
        </button>
        <button 
          className={`button ${activeTab === 'sites' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('sites')}
          style={{ marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          Sites
        </button>
        <button 
          className={`button ${activeTab === 'contacts' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('contacts')}
          style={{ marginLeft: '10px', marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          Contacts
        </button>
      </div>

      {activeTab === 'sites' ? (
        <Sites />
      ) : activeTab === 'contacts' ? (
        <Contacts />
      ) : (
        <div className="pet-customers">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
            <h2>Customers</h2>
            {!showAddForm && (
              <button className="button button-primary" onClick={() => setShowAddForm(true)}>
                Add New Customer
              </button>
            )}
          </div>

          {showAddForm && (
            <CustomerForm 
              onSuccess={handleFormSuccess} 
              onCancel={() => { setShowAddForm(false); setEditingCustomer(null); }} 
              initialData={editingCustomer || undefined}
            />
          )}


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
            data={customers} 
            loading={loading}
            error={error}
            onRetry={fetchCustomers}
            emptyMessage="No customers found."
            compatibilityMode="wp"
            selection={{
              selectedIds,
              onSelectionChange: setSelectedIds
            }}
            actions={(item) => (
              <KebabMenu items={[
                { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
                { type: 'action', label: 'Archive', onClick: () => setPendingArchiveId(item.id), danger: true },
              ]} />
            )}
          />

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
      )}
    </div>
  );
};

export default Customers;
