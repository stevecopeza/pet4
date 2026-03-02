import React, { useEffect, useState } from 'react';
import { Contact, Customer } from '../types';
import { DataTable, Column } from './DataTable';
import ContactForm from './ContactForm';

const Contacts = () => {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingContact, setEditingContact] = useState<Contact | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);

  const fetchData = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const [contactRes, custRes, schemaRes] = await Promise.all([
        fetch(`${apiUrl}/contacts`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/customers`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/schemas/contact?status=active`, { headers: { 'X-WP-Nonce': nonce } })
      ]);

      if (contactRes.ok) setContacts(await contactRes.json());
      if (custRes.ok) setCustomers(await custRes.json());
      if (schemaRes.ok) {
        const schemaData = await schemaRes.json();
        if (Array.isArray(schemaData) && schemaData.length > 0) {
          setActiveSchema(schemaData[0]);
        }
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleEdit = (contact: Contact) => {
    setEditingContact(contact);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this contact?')) return;
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/contacts/${id}`, {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': nonce }
      });

      if (!response.ok) throw new Error('Failed to archive contact');
      fetchData();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} contacts?`)) return;
    
    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/contacts/${id}`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce }
        });
      } catch (e) {
        console.error(`Failed to archive ${id}`, e);
      }
    }
    
    setSelectedIds([]);
    fetchData();
  };

  const getCustomerName = (customerId: number) => {
    return customers.find(c => c.id === customerId)?.name || `ID: ${customerId}`;
  };

  const columns: Column<Contact>[] = [
    { key: 'id', header: 'ID' },
    { 
      key: 'firstName', 
      header: 'Name', 
      render: (val: any, item: Contact) => <strong>{String(val)} {item.lastName}</strong> 
    },
    { key: 'email', header: 'Email' },
    { key: 'phone', header: 'Mobile', render: (val: any) => String(val) || '-' },
    { 
      key: 'affiliations', 
      header: 'Customers', 
      render: (val: any) => {
        const affs = val as Contact['affiliations'];
        if (!affs || affs.length === 0) return '-';
        return (
          <div style={{ fontSize: '12px' }}>
            {affs.map((a, i) => (
              <div key={i} style={{ marginBottom: '2px' }}>
                {getCustomerName(a.customerId)} {a.role ? `(${a.role})` : ''} {a.isPrimary ? '⭐' : ''}
              </div>
            ))}
          </div>
        );
      }
    },
    // Add malleable fields
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Contact,
      header: field.label,
      render: (_: any, item: Contact) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    { key: 'createdAt', header: 'Created' },
    { key: 'archivedAt', header: 'Archived', render: (val: any) => val ? <span style={{color: '#999'}}>{String(val)}</span> : '-' },
  ];

  if (loading && !contacts.length) return <div>Loading contacts...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-contacts">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>People (Contacts)</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Add New Contact
          </button>
        )}
      </div>

      {showAddForm && (
        <ContactForm 
          onSuccess={() => { setShowAddForm(false); setEditingContact(null); fetchData(); }} 
          onCancel={() => { setShowAddForm(false); setEditingContact(null); }} 
          initialData={editingContact || undefined}
        />
      )}

      {selectedIds.length > 0 && (
        <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
          <strong>{selectedIds.length} items selected</strong>
          <button className="button" onClick={handleBulkArchive}>Archive Selected</button>
        </div>
      )}

      <DataTable 
        columns={columns} 
        data={contacts} 
        emptyMessage="No contacts found." 
        selection={{
          selectedIds,
          onSelectionChange: setSelectedIds
        }}
        actions={(item) => (
          <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
            <button className="button button-small" onClick={() => handleEdit(item)}>Edit</button>
            <button 
              className="button button-small button-link-delete" 
              style={{ color: '#a00' }}
              onClick={() => handleArchive(item.id)}
            >
              Archive
            </button>
          </div>
        )}
      />
    </div>
  );
};

export default Contacts;
