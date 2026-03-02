import React, { useEffect, useState } from 'react';
import { Customer } from '../types';
import { DataTable, Column } from './DataTable';
import CustomerForm from './CustomerForm';
import Sites from './Sites';
import Contacts from './Contacts';

const Customers = () => {
  const [activeTab, setActiveTab] = useState<'customers' | 'sites' | 'contacts'>('customers');
  
  // Customer State
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);

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
    if (!confirm('Are you sure you want to archive this customer?')) return;

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
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} customers?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process in parallel
    try {
      await Promise.all(selectedIds.map(id => 
        fetch(`${apiUrl}/customers/${id}`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce },
        })
      ));
      
      setSelectedIds([]);
      fetchCustomers();
    } catch (e) {
      console.error('Bulk archive failed', e);
      alert('Failed to archive some items');
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

          {error && <div style={{ color: 'red' }}>Error: {error}</div>}

          <div className="pet-actions-bar" style={{ marginBottom: '15px' }}>
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

          <DataTable 
            columns={columns} 
            data={customers} 
            loading={loading && !customers.length}
            emptyMessage="No customers found."
            selection={{
              selectedIds,
              onSelectionChange: setSelectedIds
            }}
            actions={(item) => (
              <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
                <button 
                  className="button button-small"
                  onClick={() => handleEdit(item)}
                >
                  Edit
                </button>
                <button 
                  className="button button-small button-link-delete"
                  onClick={() => handleArchive(item.id)}
                  style={{ color: '#b32d2e' }}
                >
                  Archive
                </button>
              </div>
            )}
          />
        </div>
      )}
    </div>
  );
};

export default Customers;
