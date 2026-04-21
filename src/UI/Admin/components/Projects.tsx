import React, { useEffect, useMemo, useState } from 'react';
import { Customer, Project } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import ProjectForm from './ProjectForm';
import ProjectDetails from './ProjectDetails';
import Fulfillments from './Fulfillments';
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
type DeliveryWorkspaceMode = 'list' | 'detail';

const isCompletedDeliveryStatus = (status: unknown): boolean => {
  const normalized = String(status || '').toLowerCase();
  return normalized === 'completed' || normalized === 'resolved' || normalized === 'closed';
};

const buildProjectTasksFromTickets = (tickets: any[]): Map<number, any[]> => {
  const projectTasks = new Map<number, any[]>();
  tickets.forEach((ticket) => {
    const lifecycleOwner = String(ticket?.lifecycleOwner || '').toLowerCase();
    if (lifecycleOwner && lifecycleOwner !== 'project') {
      return;
    }
    const projectId = Number(ticket?.projectId || 0);
    if (projectId <= 0) {
      return;
    }
    const estimatedMinutes = Number(ticket?.estimatedMinutes ?? ticket?.soldMinutes ?? 0);
    const existing = projectTasks.get(projectId) || [];
    existing.push({
      id: Number(ticket?.id || 0),
      name: String(ticket?.subject || `Ticket #${ticket?.id}`),
      estimatedHours: Number.isFinite(estimatedMinutes) ? estimatedMinutes / 60 : 0,
      completed: isCompletedDeliveryStatus(ticket?.status),
    });
    projectTasks.set(projectId, existing);
  });
  return projectTasks;
};

const getProjectTickets = (project: Project): any[] => {
  const record = project as any;
  if (Array.isArray(record.tickets)) return record.tickets;
  return [];
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

  if (getProjectTickets(project).length === 0 && String(project.state) !== 'completed') {
    signals.push({
      key: 'no-tickets',
      label: 'No Tickets',
      title: 'Project has no delivery tickets yet',
      tone: 'medium',
    });
  }

  return signals;
};

type DeliveryTab = 'projects' | 'fulfillments';

const Projects = () => {
  const [activeTab, setActiveTab] = useState<DeliveryTab>('projects');
  const [projects, setProjects] = useState<Project[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingProject, setEditingProject] = useState<Project | null>(null);
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null);
  const [viewMode, setViewMode] = useState<DeliveryWorkspaceMode>('list');
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [archiveBusy, setArchiveBusy] = useState(false);
  const [pendingArchiveId, setPendingArchiveId] = useState<number | null>(null);
  const [confirmBulkArchive, setConfirmBulkArchive] = useState(false);
  const [stateFilter, setStateFilter] = useState<string>('all');
  const [searchFilter, setSearchFilter] = useState<string>('');
  const [needsAttentionOnly, setNeedsAttentionOnly] = useState(false);
  const toast = useToast();
  const { openConversation } = useConversation();

  const projectIds = useMemo(() => projects.map((project) => String(project.id)), [projects]);
  const { statuses: convStatuses } = useConversationStatus('project', projectIds);

  const fetchCustomers = async () => {
    try {
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/customers`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      if (!response.ok) {
        throw new Error('Failed to fetch customers');
      }
      const data = await response.json();
      setCustomers(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('Failed to fetch customers', err);
      setCustomers([]);
    }
  };

  const fetchProjects = async () => {
    try {
      setLoading(true);
      setError(null);
      // @ts-ignore
      const apiUrl = window.petSettings.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings.nonce;
      const [projectsResponse, ticketsResponse] = await Promise.all([
        fetch(`${apiUrl}/projects`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        }),
        fetch(`${apiUrl}/tickets`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        }),
      ]);

      if (!projectsResponse.ok) {
        throw new Error('Failed to fetch projects');
      }
      const projectData = await projectsResponse.json();
      const ticketData = ticketsResponse.ok ? await ticketsResponse.json() : [];
      const projectTasks = buildProjectTasksFromTickets(Array.isArray(ticketData) ? ticketData : []);
      const normalizedProjects = (Array.isArray(projectData) ? projectData : []).map((project: any) => ({
        ...project,
        tickets: projectTasks.get(Number(project?.id)) || [],
        tasks: projectTasks.get(Number(project?.id)) || [],
      }));
      setProjects(normalizedProjects);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProjects();
    fetchCustomers();
  }, []);

  const customerNameById = useMemo(() => {
    const map = new Map<number, string>();
    for (const customer of customers) {
      map.set(Number(customer.id), customer.name);
    }
    return map;
  }, [customers]);

  useEffect(() => {
    const applyHashSelection = () => {
      const hash = window.location.hash || '';
      const match = hash.match(/project=(\d+)/);
      if (!match) {
        return;
      }
      const parsed = Number(match[1]);
      if (!Number.isNaN(parsed)) {
        setSelectedProjectId(parsed);
        setViewMode('detail');
      }
    };

    applyHashSelection();
    window.addEventListener('hashchange', applyHashSelection);
    return () => window.removeEventListener('hashchange', applyHashSelection);
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
      setSelectedIds((prev) => prev.filter((sid) => sid !== id));
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
    () => Array.from(new Set(projects.map((project) => String(project.state || '').trim()).filter(Boolean))).sort(),
    [projects]
  );

  const projectRows = useMemo(() => (
    projects.map((project) => {
      const health = computeProjectHealth(project);
      const ticketRows = getProjectTickets(project);
      const taskCount = ticketRows.length;
      const completedTaskCount = ticketRows.filter((task) => task.completed).length;
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
      if (stateFilter !== 'all' && String(project.state || '') !== stateFilter) {
        return false;
      }
      if (needsAttentionOnly && attentionSignals.length === 0) {
        return false;
      }
      if (!search) {
        return true;
      }
      const customerLabel = customerNameById.get(Number(project.customerId)) || '';
      const quoteLabel = project.sourceQuoteId ? `quote ${project.sourceQuoteId}` : '';
      const searchable = `${project.id} ${project.name} ${customerLabel} ${quoteLabel}`.toLowerCase();
      return searchable.includes(search);
    });
  }, [customerNameById, needsAttentionOnly, projectRows, searchFilter, stateFilter]);

  const filteredProjects = useMemo(
    () => filteredRows.map((row) => row.project),
    [filteredRows]
  );

  const selectedRow = useMemo(
    () => filteredRows.find((row) => Number(row.project.id) === selectedProjectId) ?? null,
    [filteredRows, selectedProjectId]
  );
  const selectedProject = selectedRow?.project ?? null;
  const openProjectDetail = (projectId: number) => {
    setSelectedProjectId(projectId);
    setViewMode('detail');
  };
  const handleBackToProjects = () => {
    setViewMode('list');
  };

  useEffect(() => {
    if (loading) {
      return;
    }
    if (filteredProjects.length === 0) {
      if (selectedProjectId !== null) {
        setSelectedProjectId(null);
      }
      return;
    }
    if (selectedProjectId === null || !filteredProjects.some((project) => Number(project.id) === selectedProjectId)) {
      setSelectedProjectId(Number(filteredProjects[0].id));
    }
  }, [filteredProjects, loading, selectedProjectId]);

  useEffect(() => {
    if (!selectedProjectId) return;
    const nextHash = `#project=${selectedProjectId}`;
    if (window.location.hash === nextHash) return;
    try {
      window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}${nextHash}`);
    } catch (_) { /* noop */ }
  }, [selectedProjectId]);
  useEffect(() => {
    if (!loading && viewMode === 'detail' && selectedProjectId === null) {
      setViewMode('list');
    }
  }, [loading, selectedProjectId, viewMode]);

  const summary = useMemo(() => {
    const projectCount = filteredRows.length;
    const activeCount = filteredRows.filter(({ project }) => String(project.state || '') === 'active').length;
    const completedCount = filteredRows.filter(({ project }) => String(project.state || '') === 'completed').length;
    const plannedCount = filteredRows.filter(({ project }) => String(project.state || '') === 'planned').length;
    const totalSoldHours = filteredRows.reduce((sum, { project }) => sum + Number(project.soldHours || 0), 0);
    const totalTickets = filteredRows.reduce((sum, { taskCount }) => sum + taskCount, 0);
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
      totalTickets,
      attentionCount,
      conversationCount,
    };
  }, [convStatuses, filteredRows]);

  const columns: Column<Project>[] = [
    {
      id: 'project',
      key: 'name',
      header: 'Project',
      width: '46%',
      render: (_, item) => {
        const cs = convStatuses.get(String(item.id));
        const customerLabel = customerNameById.get(Number(item.customerId)) || `Customer #${item.customerId}`;
        const dot = cs && cs.status !== 'none' ? (
          <button
            type="button"
            aria-label={`Conversation: ${cs.status}`}
            title={`Conversation: ${cs.status} — click to open`}
            onClick={(e) => {
              e.stopPropagation();
              e.preventDefault();
              openConversation({
                contextType: 'project',
                contextId: String(item.id),
                subject: `Project: ${item.name}`,
                subjectKey: `project:${item.id}`,
              });
            }}
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
                className="pet-project-row-link pet-project-row-link--wrap"
                onClick={(e) => {
                  e.stopPropagation();
                  e.preventDefault();
                  openProjectDetail(Number(item.id));
                }}
                style={{ fontWeight: selectedProjectId === Number(item.id) ? 700 : 500 }}
              >
                {item.name}
              </button>
            </span>
            <span className="pet-project-row-meta">
              #{item.id} · {customerLabel}{item.sourceQuoteId ? ` · Quote #${item.sourceQuoteId}` : ''}
            </span>
          </span>
        );
      }
    },
    {
      id: 'status',
      key: 'state',
      header: 'Status / Signals',
      width: '28%',
      render: (_, item) => {
        const health = computeProjectHealth(item);
        const signals = getProjectAttentionSignals(item);
        const visibleSignals = signals.slice(0, 2);
        return (
          <span className="pet-project-row-signal-stack">
            <span className="pet-project-row-signals pet-project-row-signals--compact">
              <span className={`pet-status-badge status-${String(item.state).toLowerCase()}`}>{String(item.state)}</span>
              {health.state !== 'green' && health.state !== 'blue' && (
                <span className={`pet-project-health-dot pet-project-health-dot--${health.state}`} title={`Health: ${health.state}`}>
                  {health.state.toUpperCase()}
                </span>
              )}
              <span className="pet-project-row-signal-meta">
                {signals.length > 0 ? `${signals.length} signal${signals.length === 1 ? '' : 's'}` : 'Clear'}
              </span>
            </span>
            {signals.length > 0 ? (
              <span className="pet-project-attention-list" aria-label={`Attention signals: ${signals.map((signal) => signal.label).join(', ')}`}>
                {visibleSignals.map((signal) => (
                  <span
                    key={`${item.id}-${signal.key}`}
                    className={`pet-project-attention-tag pet-project-attention-tag--${signal.tone}`}
                    title={signal.title}
                  >
                    {signal.label}
                  </span>
                ))}
                {signals.length > visibleSignals.length && (
                  <span className="pet-project-attention-tag pet-project-attention-tag--low" title={`${signals.length - visibleSignals.length} additional signals`}>
                    +{signals.length - visibleSignals.length}
                  </span>
                )}
              </span>
            ) : null}
          </span>
        );
      },
    },
    {
      id: 'tickets',
      key: 'soldHours',
      header: 'Tickets',
      width: '26%',
      render: (_, item) => {
        const ticketRows = getProjectTickets(item);
        const taskCount = ticketRows.length;
        const completedTaskCount = ticketRows.filter((task) => task.completed).length;
        const progress = taskCount > 0 ? Math.round((completedTaskCount / taskCount) * 100) : 0;
        return (
          <span className="pet-project-row-delivery">
            <span className="pet-project-row-hours">
              {taskCount > 0 ? `${completedTaskCount}/${taskCount} complete` : 'No tickets yet'}
            </span>
            <span className="pet-project-row-tasks">{progress}% progress · {item.soldHours || 0}h sold</span>
          </span>
        );
      },
    },
  ];

  const selectedProgress = selectedRow?.taskCount
    ? Math.round((selectedRow.completedTaskCount / selectedRow.taskCount) * 100)
    : 0;
  const selectedEstimatedHours = selectedProject
    ? getProjectTickets(selectedProject).reduce((sum, task) => sum + Number(task.estimatedHours || 0), 0)
    : 0;

  return (
    <PageShell
      title="Delivery (Projects)"
      subtitle="Plan, monitor, and maintain delivery execution health from a single operational surface."
      className="pet-projects"
      testId="projects-shell"
      actions={activeTab === 'projects' && viewMode === 'list' && !showAddForm ? (
        <button className="button button-primary" onClick={() => setShowAddForm(true)}>
          Add New Project
        </button>
      ) : null}
    >
      {/* Tab switcher */}
      <div style={{ display: 'flex', gap: 0, borderBottom: '1px solid #dcdcde', marginBottom: 20 }}>
        {([
          { key: 'projects', label: 'Projects' },
          { key: 'fulfillments', label: 'Fulfillment Deliverables' },
        ] as { key: DeliveryTab; label: string }[]).map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            style={{
              padding: '8px 16px', background: 'none', border: 'none',
              borderBottom: activeTab === tab.key ? '3px solid #2271b1' : '3px solid transparent',
              cursor: 'pointer', fontWeight: activeTab === tab.key ? 700 : 400,
              color: activeTab === tab.key ? '#2271b1' : '#50575e',
              fontSize: 13, marginBottom: -1,
            }}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {activeTab === 'fulfillments' && <Fulfillments />}

      {activeTab === 'projects' && viewMode === 'list' && (
        <>
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
                <span className="pet-projects-summary-label">Tickets</span>
                <strong className="pet-projects-summary-value">{summary.totalTickets}</strong>
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
            <div className="pet-projects-filters-row">
              <label className="pet-projects-filter-field pet-projects-filter-field--search" htmlFor="pet-project-filter-search">
                <span>Search</span>
                <input
                  id="pet-project-filter-search"
                  type="search"
                  value={searchFilter}
                  onChange={(event) => {
                    setSearchFilter(event.target.value);
                    setSelectedIds([]);
                  }}
                  placeholder="Project name, customer, quote, or ID"
                />
              </label>
              <label className="pet-projects-filter-field pet-projects-filter-field--state" htmlFor="pet-project-filter-state">
                <span>Status</span>
                <select
                  id="pet-project-filter-state"
                  value={stateFilter}
                  onChange={(event) => {
                    setStateFilter(event.target.value);
                    setSelectedIds([]);
                  }}
                >
                  <option value="all">All statuses</option>
                  {projectStateOptions.map((state) => (
                    <option key={state} value={state}>
                      {state}
                    </option>
                  ))}
                </select>
              </label>
              <label className="pet-projects-filter-toggle" htmlFor="pet-project-filter-attention">
                <input
                  id="pet-project-filter-attention"
                  type="checkbox"
                  checked={needsAttentionOnly}
                  onChange={(event) => {
                    setNeedsAttentionOnly(event.target.checked);
                    setSelectedIds([]);
                  }}
                />
                Needs attention only
              </label>
              <div className="pet-projects-filter-actions">
                <button
                  type="button"
                  className="button"
                  onClick={() => {
                    setStateFilter('all');
                    setSearchFilter('');
                    setNeedsAttentionOnly(false);
                    setSelectedIds([]);
                  }}
                  disabled={stateFilter === 'all' && !searchFilter && !needsAttentionOnly}
                >
                  Clear
                </button>
              </div>
            </div>
          </Panel>
        </>
      )}

      {activeTab === 'projects' && showAddForm && (
        <Panel className="pet-projects-form-panel">
          <ProjectForm
            onSuccess={handleFormSuccess}
            onCancel={() => { setShowAddForm(false); setEditingProject(null); }}
            initialData={editingProject || undefined}
          />
        </Panel>
      )}

      {activeTab === 'projects' && viewMode === 'list' && selectedIds.length > 0 && (
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

      {activeTab === 'projects' && viewMode === 'list' && (
        <Panel className="pet-projects-table-panel pet-projects-list-panel" testId="projects-main-panel">
          <div className="pet-projects-selector-shell">
            <div className="pet-projects-table-header">
              <h3>Project Selector</h3>
              <p>Choose a project to start focused delivery work.</p>
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
                const attentionClass = needsAttentionOnly && getProjectAttentionSignals(project).length > 0
                  ? 'pet-project-row--attention'
                  : '';
                const selectedClass = selectedProjectId === Number(project.id) ? 'pet-project-row--selected' : '';
                return `${healthClass} ${attentionClass} ${selectedClass}`.trim();
              }}
              onRowClick={(project) => openProjectDetail(Number(project.id))}
              actions={(item) => (
                <KebabMenu items={[
                  { type: 'action', label: 'Open', onClick: () => openProjectDetail(Number(item.id)) },
                  { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
                  { type: 'action', label: 'Archive', onClick: () => setPendingArchiveId(Number(item.id)), danger: true },
                ]} />
              )}
            />
          </div>
        </Panel>
      )}

      {activeTab === 'projects' && viewMode === 'detail' && (
        <Panel className="pet-projects-workspace-panel pet-projects-detail-view-panel" testId="projects-detail-panel">
          <div className="pet-projects-detail-back-row">
            <button type="button" className="button pet-projects-back-button" onClick={handleBackToProjects}>
              &larr; Back to Projects
            </button>
          </div>

          {!selectedProject && (
            <div className="pd-empty pet-projects-workspace-empty">
              No project selected.
            </div>
          )}

          {selectedProject && (
            <div className="pet-project-workspace">
              <div className="pet-project-workspace-header">
                <div className="pet-project-workspace-title-block">
                  <h3 className="pet-project-workspace-title">{selectedProject.name}</h3>
                  <p className="pet-project-workspace-subtitle">
                    Project #{selectedProject.id} · {customerNameById.get(Number(selectedProject.customerId)) || `Customer #${selectedProject.customerId}`}
                    {selectedProject.sourceQuoteId ? ` · Quote #${selectedProject.sourceQuoteId}` : ''}
                  </p>
                  {selectedRow && selectedRow.attentionSignals.length > 0 && (
                    <div className="pet-project-workspace-attention">
                      {selectedRow.attentionSignals.map((signal) => (
                        <span
                          key={`workspace-${selectedProject.id}-${signal.key}`}
                          className={`pet-project-attention-tag pet-project-attention-tag--${signal.tone}`}
                          title={signal.title}
                        >
                          {signal.label}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
                <div className="pet-project-workspace-metrics">
                  <span className={`pet-status-badge status-${String(selectedProject.state).toLowerCase()}`}>{String(selectedProject.state)}</span>
                  <span className="pd-badge">{`${selectedRow?.completedTaskCount ?? 0}/${selectedRow?.taskCount ?? 0} tickets`}</span>
                  <span className="pd-badge">{`${selectedProgress}% progress`}</span>
                  <span className="pd-badge">{`${selectedEstimatedHours.toFixed(1)}h est.`}</span>
                  <span className="pd-badge">{`${selectedProject.soldHours || 0}h sold`}</span>
                </div>
                <div className="pet-project-workspace-actions">
                  <button
                    type="button"
                    className="button button-primary"
                    onClick={() => openConversation({
                      contextType: 'project',
                      contextId: String(selectedProject.id),
                      subject: `Project: ${selectedProject.name}`,
                      subjectKey: `project:${selectedProject.id}`,
                    })}
                  >
                    Discuss
                  </button>
                  <button type="button" className="button" onClick={() => handleEdit(selectedProject)}>
                    Edit Project
                  </button>
                  <button
                    type="button"
                    className="button"
                    onClick={() => document.getElementById(`project-${selectedProject.id}-tickets`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                  >
                    View Tickets
                  </button>
                </div>
              </div>

              <div className="pet-projects-ticket-panel">
                <ProjectDetails projectId={Number(selectedProject.id)} embedded />
              </div>
            </div>
          )}
        </Panel>
      )}

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
