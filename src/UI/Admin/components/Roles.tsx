import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import RoleForm from './RoleForm';
import Skills from './Skills';
import Certifications from './Certifications';
import KpiDefinitions from './KpiDefinitions';
import Assignments from './Assignments';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

interface Role {
  id: number;
  name: string;
  level: string;
  status: string;
  version: number;
  description: string;
  published_at: string | null;
}

const Roles = () => {
  const [activeTab, setActiveTab] = useState<'roles' | 'skills' | 'certifications' | 'kpis' | 'assignments'>('roles');
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingRole, setEditingRole] = useState<Role | null>(null);

  const openRole = (role: Role) => {
    setEditingRole(role);
    setShowAddForm(true);
  };

  const fetchRoles = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/roles`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        setRoles(data);
      }
    } catch (err) {
      console.error('Failed to fetch roles', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRoles();
  }, []);

  const handleCreate = () => {
    setEditingRole(null);
    setShowAddForm(true);
  };

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingRole(null);
    fetchRoles();
  };

  const handlePublish = async (role: Role) => {
    if (!legacyConfirm(`Are you sure you want to publish ${role.name}? This will make it immutable.`)) return;

    try {
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/roles/${role.id}/publish`, {
        method: 'POST',
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (response.ok) {
        fetchRoles();
      } else {
        legacyAlert('Failed to publish role');
      }
    } catch (err) {
      console.error('Error publishing role', err);
    }
  };

  const columns: Column<Role>[] = [
    { 
      key: 'name', 
      header: 'Name',
      render: (val, role) => (
        <button
          type="button"
          onClick={() => openRole(role)}
          style={{
            background: 'none',
            border: 'none',
            color: '#2271b1',
            cursor: 'pointer',
            padding: 0,
            textAlign: 'left',
            fontWeight: 'bold',
            fontSize: 'inherit',
          }}
        >
          {String(val)}
        </button>
      )
    },
    { key: 'level', header: 'Level' },
    { 
      key: 'status', 
      header: 'Status', 
      render: (value, role) => (
        <span style={{ 
          padding: '2px 6px', 
          borderRadius: '4px', 
          fontSize: '12px',
          background: role.status === 'published' ? '#e6fffa' : '#fffaf0',
          color: role.status === 'published' ? '#047481' : '#9c4221'
        }}>
          {role.status.toUpperCase()}
        </span>
      )
    },
    { key: 'version', header: 'Version' },
    { 
      key: 'id', 
      header: 'Actions', 
      render: (value, role) => (
        <div style={{ display: 'flex', gap: '8px' }}>
          {role.status === 'draft' && (
            <>
              <button 
                onClick={() => openRole(role)}
                className="button button-small"
              >
                Edit
              </button>
              <button 
                onClick={() => handlePublish(role)}
                className="button button-small"
                style={{ color: '#047481', borderColor: '#047481' }}
              >
                Publish
              </button>
            </>
          )}
          {role.status === 'published' && (
            <button
              onClick={() => openRole(role)}
              className="button button-small"
            >
              View
            </button>
          )}
        </div>
      ) 
    }
  ];

  if (showAddForm) {
    return (
      <div>
        <div style={{ marginBottom: '20px' }}>
          <button 
            className="button" 
            onClick={() => setShowAddForm(false)}
          >
            &larr; Back to Roles
          </button>
        </div>
        <RoleForm 
          role={editingRole} 
          onSuccess={handleFormSuccess} 
          onCancel={() => {
            setShowAddForm(false);
            setEditingRole(null);
          }} 
        />
      </div>
    );
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Roles & Capabilities</h2>
        {activeTab === 'roles' && (
          <button className="button button-primary" onClick={handleCreate}>
            Define New Role
          </button>
        )}
      </div>

      <div className="nav-tab-wrapper" style={{ marginBottom: '20px' }}>
        <button 
          className={`nav-tab ${activeTab === 'roles' ? 'nav-tab-active' : ''}`}
          onClick={() => setActiveTab('roles')}
        >
          Roles
        </button>
        <button 
          className={`nav-tab ${activeTab === 'skills' ? 'nav-tab-active' : ''}`}
          onClick={() => setActiveTab('skills')}
        >
          Skills Library
        </button>
        <button 
          className={`nav-tab ${activeTab === 'certifications' ? 'nav-tab-active' : ''}`}
          onClick={() => setActiveTab('certifications')}
        >
          Certifications
        </button>
        <button 
          className={`nav-tab ${activeTab === 'kpis' ? 'nav-tab-active' : ''}`}
          onClick={() => setActiveTab('kpis')}
        >
          KPI Library
        </button>
        <button 
          className={`nav-tab ${activeTab === 'assignments' ? 'nav-tab-active' : ''}`}
          onClick={() => setActiveTab('assignments')}
        >
          Assignments
        </button>
      </div>

      {activeTab === 'roles' ? (
        <DataTable
          data={roles}
          columns={columns}
          loading={loading}
          emptyMessage="No roles defined yet."
        />
      ) : activeTab === 'skills' ? (
        <Skills />
      ) : activeTab === 'certifications' ? (
        <Certifications />
      ) : activeTab === 'kpis' ? (
        <KpiDefinitions />
      ) : (
        <Assignments />
      )}
    </div>
  );
};

export default Roles;
