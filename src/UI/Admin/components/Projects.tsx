import React, { useEffect, useState, useMemo } from 'react';
import { Project } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import ProjectForm from './ProjectForm';
import ProjectDetails from './ProjectDetails';
import { computeProjectHealth } from '../healthCompute';
import useConversationStatus from '../hooks/useConversationStatus';
import useConversation from '../hooks/useConversation';

const Projects = () => {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingProject, setEditingProject] = useState<Project | null>(null);
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const { openConversation } = useConversation();

  const projectIds = useMemo(() => projects.map(p => String(p.id)), [projects]);
  const { statuses: convStatuses } = useConversationStatus('project', projectIds);

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

  const statusColors: Record<string, string> = { red: '#dc3545', amber: '#f0ad4e', green: '#28a745', blue: '#007bff' };

  const columns: Column<Project>[] = [
    { key: 'id', header: 'ID' },
    { 
      key: 'name', 
      header: 'Project Name', 
      render: (_, item) => {
        const cs = convStatuses.get(String(item.id));
        const dot = cs && cs.status !== 'none' ? (
          <button
            type="button"
            title={`Conversation: ${cs.status} — click to open`}
            onClick={(e) => { e.stopPropagation(); e.preventDefault(); openConversation({ contextType: 'project', contextId: String(item.id), subject: `Project: ${item.name}`, subjectKey: `project:${item.id}` }); }}
            style={{ display: 'inline-block', width: 10, height: 10, borderRadius: '50%', background: statusColors[cs.status] || 'transparent', marginRight: 6, verticalAlign: 'middle', border: 'none', padding: 0, cursor: 'pointer', flexShrink: 0 }}
          />
        ) : null;
        return (
          <>
            {dot}
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
          </>
        );
      }
    },
    { key: 'customerId', header: 'Customer ID' },
    { key: 'soldHours', header: 'Sold Hours' },
    { key: 'state', header: 'Status', render: (val: any) => <span className={`pet-status-badge status-${String(val)}`}>{String(val)}</span> },
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
        rowClassName={(p) => computeProjectHealth(p).className}
        actions={(item) => (
          <KebabMenu items={[
            { type: 'action', label: 'Tasks', onClick: () => setSelectedProjectId(item.id) },
            { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
            { type: 'action', label: 'Archive', onClick: () => handleArchive(item.id), danger: true },
          ]} />
        )}
      />
    </div>
  );
};

export default Projects;
