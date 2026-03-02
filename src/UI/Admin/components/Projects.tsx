import React, { useEffect, useState } from 'react';
import { Project } from '../types';
import { DataTable, Column } from './DataTable';
import ProjectForm from './ProjectForm';
import ProjectDetails from './ProjectDetails';

const Projects = () => {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingProject, setEditingProject] = useState<Project | null>(null);
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);

  const fetchSchema = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/schemas/project?status=active`, {
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

  const fetchProjects = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/projects`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch projects');
      }

      const data = await response.json();
      setProjects(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProjects();
    fetchSchema();
  }, []);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingProject(null);
    fetchProjects();
  };

  const handleEdit = (project: Project) => {
    setEditingProject(project);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this project?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/projects/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive project');
      }

      fetchProjects();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} projects?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/projects/${id}`, {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': nonce,
          },
        });
      } catch (e) {
        console.error(`Failed to archive ${id}`, e);
      }
    }
    
    setSelectedIds([]);
    fetchProjects();
  };

  const columns: Column<Project>[] = [
    { key: 'id', header: 'ID' },
    { 
      key: 'name', 
      header: 'Project Name', 
      render: (_, item) => (
        <a 
          href="#" 
          onClick={(e) => { 
            e.preventDefault(); 
            setSelectedProjectId(item.id); 
          }}
          style={{ fontWeight: 'bold' }}
        >
          {item.name}
        </a>
      ) 
    },
    { key: 'customerId', header: 'Customer ID' },
    { key: 'soldHours', header: 'Sold Hours' },
    { key: 'state', header: 'Status', render: (val: any) => <span className={`status-badge status-${String(val)}`}>{String(val)}</span> },
    // Add malleable fields if they exist in schema
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Project,
      header: field.label,
      render: (_: any, item: Project) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    { key: 'tasks', header: 'Tasks', render: (_, item) => <span>{item.tasks.length} tasks</span> },
    { key: 'archivedAt', header: 'Archived', render: (val: any) => val ? <span style={{color: '#999'}}>{String(val)}</span> : '-' },
  ];

  if (selectedProjectId) {
    return (
      <ProjectDetails 
        projectId={selectedProjectId} 
        onBack={() => {
          setSelectedProjectId(null);
          fetchProjects(); // Refresh list when returning
        }} 
      />
    );
  }

  if (loading && !projects.length) return <div>Loading projects...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-projects">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Delivery (Projects)</h2>
        {!showAddForm && (
          <button className="button button-primary" onClick={() => setShowAddForm(true)}>
            Add New Project
          </button>
        )}
      </div>

      {showAddForm && (
        <ProjectForm 
          onSuccess={handleFormSuccess} 
          onCancel={() => { setShowAddForm(false); setEditingProject(null); }} 
          initialData={editingProject || undefined}
        />
      )}

      {selectedIds.length > 0 && (
        <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
          <strong>{selectedIds.length} items selected</strong>
          <button className="button button-link-delete" style={{ color: '#a00', borderColor: '#a00' }} onClick={handleBulkArchive}>Archive Selected</button>
        </div>
      )}

      <DataTable 
        columns={columns} 
        data={projects} 
        emptyMessage="No projects found." 
        selection={{
          selectedIds,
          onSelectionChange: setSelectedIds
        }}
        actions={(item) => (
          <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
            <button 
              className="button button-small"
              onClick={() => setSelectedProjectId(item.id)}
            >
              Tasks
            </button>
            <button 
              className="button button-small"
              onClick={() => handleEdit(item)}
            >
              Edit
            </button>
            <button 
              className="button button-small button-link-delete"
              style={{ color: '#a00', borderColor: '#a00' }}
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

export default Projects;
