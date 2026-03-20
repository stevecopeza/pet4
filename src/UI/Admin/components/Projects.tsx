import React, { useEffect, useMemo, useState } from 'react';
import { Project } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import ProjectForm from './ProjectForm';
import ProjectDetails from './ProjectDetails';
import { computeProjectHealth } from '../healthCompute';
import useConversationStatus from '../hooks/useConversationStatus';
import useConversation from '../hooks/useConversation';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';
import ActionBar from './foundation/ActionBar';

type ProjectAttentionSignal = {
  key: string;
  label: string;
  title: string;
  tone: 'high' | 'medium' | 'low';
};

const getProjectAttentionSignals = (project: Project): ProjectAttentionSignal[] => {
  const health = computeProjectHealth(project);
  const signals: ProjectAttentionSignal[] = [];

  health.reasons.forEach((reason, index) => {
    signals.push({
      key: `health-${index}-${reason.label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`,
      label: reason.label,
      title: `Health signal: ${reason.label}`,
      tone: reason.color === 'red' ? 'high' : reason.color === 'amber' ? 'medium' : 'low',
    });
  });

  if ((project.tasks?.length ?? 0) === 0 && project.state !== 'completed') {
    signals.push({
      key: 'no-tasks',
      label: 'No Tasks',
      title: 'Project has no delivery tasks yet',
      tone: 'medium',
    });
  }

  return signals;
};

const Projects = () => {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingProject, setEditingProject] = useState<Project | null>(null);
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [archiveBusy, setArchiveBusy] = useState(false);
  const [pendingArchiveId, setPendingArchiveId] = useState<number | null>(null);
  const [confirmBulkArchive, setConfirmBulkArchive] = useState(false);
  const [stateFilter, setStateFilter] = useState<string>('all');
  const [searchFilter, setSearchFilter] = useState<string>('');
  const [activePreset, setActivePreset] = useState<'none' | 'active' | 'completed' | 'attention'>('none');
  const toast = useToast();
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
    setArchiveBusy(true);

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
      setSelectedIds(prev => prev.filter(sid => sid !== id));
      toast.success('Project archived');
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
    let failedCount = 0;
    try {
      // Process sequentially
      for (const id of selectedIds) {
        try {
          const response = await fetch(`${apiUrl}/projects/${id}`, {
            method: 'DELETE',
            headers: {
              'X-WP-Nonce': nonce,
            },
          });
          if (!response.ok) {
            failedCount += 1;
          }
        } catch (e) {
          console.error(`Failed to archive ${id}`, e);
          failedCount += 1;
        }
      }

      const successCount = selectedIds.length - failedCount;
      setSelectedIds([]);
      fetchProjects();
      if (failedCount > 0) {
        toast.error(`Archived ${successCount} projects; ${failedCount} failed.`);
      } else {
        toast.success(`Archived ${successCount} projects.`);
      }
    } finally {
      setArchiveBusy(false);
      setConfirmBulkArchive(false);
    }
  };

  const statusColors: Record<string, string> = { red: '#dc3545', amber: '#f0ad4e', green: '#28a745', blue: '#007bff' };
  const projectStateOptions = useMemo(
    () => Array.from(new Set(projects.map((project) => project.state))).sort(),
    [projects]
  );

  const projectRows = useMemo(() => (
    projects.map((project) => {
      const health = computeProjectHealth(project);
      const taskCount = project.tasks?.length ?? 0;
      const completedTaskCount = project.tasks?.filter((task) => task.completed).length ?? 0;
      const attentionSignals = getProjectAttentionSignals(project);
      return {
        project,
        health,
        taskCount,
        completedTaskCount,
        attentionSignals,
      };
    })
  ), [projects]);

  const filteredRows = useMemo(() => {
    const search = searchFilter.trim().toLowerCase();
    return projectRows.filter(({ project, attentionSignals }) => {
      if (stateFilter !== 'all' && project.state !== stateFilter) {
        return false;
      }
      if (activePreset === 'attention' && attentionSignals.length === 0) {
        return false;
      }
      if (!search) {
        return true;
      }
      const searchable = `${project.id} ${project.name}`.toLowerCase();
      return searchable.includes(search);
    });
  }, [activePreset, projectRows, searchFilter, stateFilter]);

  const filteredProjects = useMemo(
    () => filteredRows.map((row) => row.project),
    [filteredRows]
  );

  const summary = useMemo(() => {
    const projectCount = filteredRows.length;
    const activeCount = filteredRows.filter(({ project }) => project.state === 'active').length;
    const completedCount = filteredRows.filter(({ project }) => project.state === 'completed').length;
    const plannedCount = filteredRows.filter(({ project }) => project.state === 'planned').length;
    const totalSoldHours = filteredRows.reduce((sum, { project }) => sum + (project.soldHours || 0), 0);
    const totalTasks = filteredRows.reduce((sum, { taskCount }) => sum + taskCount, 0);
    const attentionCount = filteredRows.filter(({ attentionSignals }) => attentionSignals.length > 0).length;
    const conversationCount = filteredRows.filter(({ project }) => {
      const status = convStatuses.get(String(project.id));
      return Boolean(status && status.status !== 'none');
    }).length;

    return {
      projectCount,
      activeCount,
      completedCount,
      plannedCount,
      totalSoldHours,
      totalTasks,
      attentionCount,
      conversationCount,
    };
  }, [convStatuses, filteredRows]);

  const applyPreset = (preset: 'none' | 'active' | 'completed' | 'attention') => {
    setActivePreset(preset);
    setSelectedIds([]);
    if (preset === 'active') {
      setStateFilter('active');
      return;
    }
    if (preset === 'completed') {
      setStateFilter('completed');
      return;
    }
    if (preset === 'none') {
      setStateFilter('all');
    }
  };

  const columns: Column<Project>[] = [
    { 
      key: 'name', 
      header: 'Project',
      render: (_, item) => {
        const cs = convStatuses.get(String(item.id));
        const dot = cs && cs.status !== 'none' ? (
          <button
            type="button"
            aria-label={`Conversation: ${cs.status}`}
            title={`Conversation: ${cs.status} — click to open`}
            onClick={(e) => { e.stopPropagation(); e.preventDefault(); openConversation({ contextType: 'project', contextId: String(item.id), subject: `Project: ${item.name}`, subjectKey: `project:${item.id}` }); }}
            className="pet-project-conversation-dot"
            style={{ background: statusColors[cs.status] || 'transparent' }}
          />
        ) : null;
        return (
          <span className="pet-project-row-primary">
            <span className="pet-project-row-title-line">
              {dot}
              <button
                type="button"
                className="pet-project-row-link"
                onClick={(e) => {
                  e.preventDefault();
                  setSelectedProjectId(item.id);
                }}
              >
                {item.name}
              </button>
            </span>
            <span className="pet-project-row-meta">Project #{item.id}</span>
          </span>
        );
      }
    },
    {
      key: 'customerId',
      header: 'Customer / Quote',
      render: (_, item) => (
        <span className="pet-project-row-context">
          <span className="pet-project-row-context-primary">Customer #{item.customerId}</span>
          <span className="pet-project-row-context-secondary">
            {item.sourceQuoteId ? `Quote #${item.sourceQuoteId}` : 'No source quote'}
          </span>
        </span>
      ),
    },
    {
      key: 'soldHours',
      header: 'Delivery',
      render: (_, item) => {
        const taskCount = item.tasks?.length ?? 0;
        const completedTaskCount = item.tasks?.filter((task) => task.completed).length ?? 0;
        return (
          <span className="pet-project-row-delivery">
            <span className="pet-project-row-hours">{`${item.soldHours || 0}h sold`}</span>
            <span className="pet-project-row-tasks">
              {taskCount > 0 ? `${completedTaskCount}/${taskCount} tasks complete` : 'No tasks yet'}
            </span>
          </span>
        );
      },
    },
    {
      key: 'state',
      header: 'Signals',
      render: (_, item) => {
        const health = computeProjectHealth(item);
        const signals = getProjectAttentionSignals(item);
        return (
          <span className="pet-project-row-signals">
            <span className={`pet-status-badge status-${String(item.state).toLowerCase()}`}>{item.state}</span>
            {health.state !== 'green' && health.state !== 'blue' && (
              <span className={`pet-project-health-dot pet-project-health-dot--${health.state}`} title={`Health: ${health.state}`}>
                {health.state.toUpperCase()}
              </span>
            )}
            {signals.length > 0 ? (
              <span className="pet-project-attention-list" aria-label={`Attention signals: ${signals.map((signal) => signal.label).join(', ')}`}>
                {signals.map((signal) => (
                  <span
                    key={`${item.id}-${signal.key}`}
                    className={`pet-project-attention-tag pet-project-attention-tag--${signal.tone}`}
                    title={signal.title}
                  >
                    {signal.label}
                  </span>
                ))}
              </span>
            ) : (
              <span className="pet-project-attention-empty">—</span>
            )}
          </span>
        );
      },
    },
    // Add malleable fields if they exist in schema
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Project,
      header: field.label,
      render: (_: any, item: Project) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    { key: 'archivedAt', header: 'Archived', render: (val: any) => val ? <span style={{ color: '#999' }}>{String(val)}</span> : '-' },
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


  return (
    <PageShell
      title="Delivery (Projects)"
      subtitle="Plan, monitor, and maintain delivery execution health from a single operational surface."
      className="pet-projects"
      testId="projects-shell"
      actions={!showAddForm ? (
        <button className="button button-primary" onClick={() => setShowAddForm(true)}>
          Add New Project
        </button>
      ) : null}
    >
      <Panel className="pet-projects-summary-panel" testId="projects-summary-panel">
        <div className="pet-projects-summary-grid">
          <div className="pet-projects-summary-item">
            <span className="pet-projects-summary-label">Projects</span>
            <strong className="pet-projects-summary-value">{summary.projectCount}</strong>
          </div>
          <div className="pet-projects-summary-item">
            <span className="pet-projects-summary-label">Active</span>
            <strong className="pet-projects-summary-value">{summary.activeCount}</strong>
          </div>
          <div className="pet-projects-summary-item">
            <span className="pet-projects-summary-label">Planned</span>
            <strong className="pet-projects-summary-value">{summary.plannedCount}</strong>
          </div>
          <div className="pet-projects-summary-item">
            <span className="pet-projects-summary-label">Completed</span>
            <strong className="pet-projects-summary-value">{summary.completedCount}</strong>
          </div>
          <div className="pet-projects-summary-item">
            <span className="pet-projects-summary-label">Sold Hours</span>
            <strong className="pet-projects-summary-value">{summary.totalSoldHours}h</strong>
          </div>
          <div className="pet-projects-summary-item">
            <span className="pet-projects-summary-label">Tasks</span>
            <strong className="pet-projects-summary-value">{summary.totalTasks}</strong>
          </div>
          <div className="pet-projects-summary-item">
            <span className="pet-projects-summary-label">Needs Attention</span>
            <strong className="pet-projects-summary-value">{summary.attentionCount}</strong>
          </div>
          <div className="pet-projects-summary-item">
            <span className="pet-projects-summary-label">Conversations</span>
            <strong className="pet-projects-summary-value">{summary.conversationCount}</strong>
          </div>
        </div>
      </Panel>

      <Panel className="pet-projects-filters-panel" testId="projects-filters-panel">
        <div className="pet-projects-filters-grid">
          <label className="pet-projects-filter-field" htmlFor="pet-project-filter-state">
            <span>State</span>
            <select
              id="pet-project-filter-state"
              value={stateFilter}
              onChange={(event) => {
                setStateFilter(event.target.value);
                setActivePreset('none');
                setSelectedIds([]);
              }}
            >
              <option value="all">All states</option>
              {projectStateOptions.map((state) => (
                <option key={state} value={state}>
                  {state}
                </option>
              ))}
            </select>
          </label>
          <label className="pet-projects-filter-field" htmlFor="pet-project-filter-search">
            <span>Project Search</span>
            <input
              id="pet-project-filter-search"
              type="search"
              value={searchFilter}
              onChange={(event) => {
                setSearchFilter(event.target.value);
                setSelectedIds([]);
              }}
              placeholder="Name or project ID"
            />
          </label>
          <div className="pet-projects-filter-actions">
            <button
              type="button"
              className="button"
              onClick={() => {
                setStateFilter('all');
                setSearchFilter('');
                setActivePreset('none');
                setSelectedIds([]);
              }}
              disabled={stateFilter === 'all' && !searchFilter}
            >
              Clear Filters
            </button>
          </div>
        </div>
        <div className="pet-projects-preset-bar" role="group" aria-label="Project quick presets">
          <button
            type="button"
            className={`button pet-projects-preset-btn ${activePreset === 'active' ? 'is-active' : ''}`}
            onClick={() => applyPreset('active')}
          >
            Active
          </button>
          <button
            type="button"
            className={`button pet-projects-preset-btn ${activePreset === 'completed' ? 'is-active' : ''}`}
            onClick={() => applyPreset('completed')}
          >
            Completed
          </button>
          <button
            type="button"
            className={`button pet-projects-preset-btn ${activePreset === 'attention' ? 'is-active' : ''}`}
            onClick={() => applyPreset('attention')}
          >
            Needs Attention
          </button>
        </div>
      </Panel>

      {showAddForm && (
        <Panel className="pet-projects-form-panel">
          <ProjectForm
            onSuccess={handleFormSuccess}
            onCancel={() => { setShowAddForm(false); setEditingProject(null); }}
            initialData={editingProject || undefined}
          />
        </Panel>
      )}

      {selectedIds.length > 0 && (
        <ActionBar className="pet-projects-bulk-strip" testId="projects-bulk-strip">
          <div className="pet-projects-bulk-text">
            <span className="pet-projects-bulk-eyebrow">Bulk actions</span>
            <strong>{selectedIds.length} items selected</strong>
          </div>
          <button className="button button-link-delete pet-action-danger" onClick={() => setConfirmBulkArchive(true)}>
            Archive Selected
          </button>
        </ActionBar>
      )}

      <Panel className="pet-projects-table-panel" testId="projects-main-panel">
        <div className="pet-projects-table-header">
          <h3>Project List</h3>
          <p>Review delivery scope, monitor project health signals, and manage archives from one place.</p>
        </div>
        <DataTable
          columns={columns}
          data={filteredProjects}
          loading={loading}
          error={error}
          onRetry={fetchProjects}
          emptyMessage="No projects found."
          compatibilityMode="wp"
          selection={{
            selectedIds,
            onSelectionChange: setSelectedIds
          }}
          rowClassName={(project) => {
            const healthClass = computeProjectHealth(project).className;
            const attentionClass = activePreset === 'attention' && getProjectAttentionSignals(project).length > 0
              ? 'pet-project-row--attention'
              : '';
            return `${healthClass} ${attentionClass}`.trim();
          }}
          actions={(item) => (
            <KebabMenu items={[
              { type: 'action', label: 'Tasks', onClick: () => setSelectedProjectId(item.id) },
              { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
              { type: 'action', label: 'Archive', onClick: () => setPendingArchiveId(item.id), danger: true },
            ]} />
          )}
        />
      </Panel>

      <ConfirmationDialog
        open={pendingArchiveId !== null}
        title="Archive project?"
        description="This action will archive the selected project."
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
        title="Archive selected projects?"
        description={`This action will archive ${selectedIds.length} selected projects.`}
        confirmLabel="Archive selected"
        busy={archiveBusy}
        onCancel={() => setConfirmBulkArchive(false)}
        onConfirm={handleBulkArchive}
      />
    </PageShell>
  );
};

export default Projects;
