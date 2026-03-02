import React, { useEffect, useState } from 'react';
import { Site } from '../types';
import { DataTable, Column } from './DataTable';
import SiteForm from './SiteForm';

const Sites = () => {
  const [sites, setSites] = useState<Site[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingSite, setEditingSite] = useState<Site | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);

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

  const fetchSites = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/sites`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch sites');
      }

      const data = await response.json();
      setSites(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSites();
    fetchSchema();
  }, []);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingSite(null);
    fetchSites();
  };

  const handleEdit = (site: Site) => {
    setEditingSite(site);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this site?')) return;

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
        throw new Error('Failed to archive site');
      }

      fetchSites();
      setSelectedIds(prev => prev.filter(sid => sid !== id));
    } catch (err) {
      alert('Failed to archive site');
    }
  };

  const handleBulkArchive = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} sites?`)) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      // Execute sequentially or parallel? Parallel is faster.
      await Promise.all(selectedIds.map(id => 
        fetch(`${apiUrl}/sites/${id}`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': nonce },
        })
      ));

      fetchSites();
      setSelectedIds([]);
    } catch (err) {
      alert('Failed to archive some sites');
    }
  };

  const columns: Column<Site>[] = [
    { 
      key: 'name', 
      header: 'Site Name',
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
    { key: 'city', header: 'City' },
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
    // Add malleable fields if they exist in schema
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
      />
    );
  }

  return (
    <div className="pet-sites-container">
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
        <button className="button button-primary" onClick={() => setShowAddForm(true)}>
          Add New Site
        </button>
      </div>

      {error && (
        <div className="notice notice-error inline">
          <p>{error}</p>
        </div>
      )}

      <DataTable
        columns={columns}
        data={sites}
        loading={loading}
        selection={{
          selectedIds,
          onSelectionChange: setSelectedIds
        }}
        actions={(site) => (
          <div className="pet-row-actions">
            <button 
              className="button button-small" 
              onClick={() => handleEdit(site)}
              style={{ marginRight: '5px' }}
            >
              Edit
            </button>
            <button 
              className="button button-small button-link-delete" 
              onClick={() => handleArchive(site.id)}
              style={{ color: '#b32d2e' }}
            >
              Archive
            </button>
          </div>
        )}
      />
    </div>
  );
};

export default Sites;
