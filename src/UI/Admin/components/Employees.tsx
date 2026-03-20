import React, { useEffect, useMemo, useState } from 'react';
import { Employee, Team } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import EmployeeForm from './EmployeeForm';
import Teams from './Teams';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';
import LoadingState from './foundation/states/LoadingState';
import ErrorState from './foundation/states/ErrorState';
import EmptyState from './foundation/states/EmptyState';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';
import ActionBar from './foundation/ActionBar';

type EmployeeAttentionSignal = {
  key: string;
  label: string;
  title: string;
  tone: 'high' | 'medium' | 'low';
};

const formatDateLabel = (value?: string | null) => (
  value ? new Date(value).toLocaleDateString() : '—'
);

const getEmployeeAttentionSignals = (employee: Employee): EmployeeAttentionSignal[] => {
  const signals: EmployeeAttentionSignal[] = [];
  const status = String(employee.status || 'unknown').toLowerCase();

  if (employee.archivedAt) {
    signals.push({
      key: 'archived',
      label: 'Archived',
      title: 'Employee is archived',
      tone: 'low',
    });
  }

  if (!employee.archivedAt && status !== 'active') {
    signals.push({
      key: `status-${status}`,
      label: `Status: ${status}`,
      title: `Employee status is ${status}`,
      tone: 'medium',
    });
  }

  if (!employee.managerId && !employee.archivedAt) {
    signals.push({
      key: 'manager-missing',
      label: 'No Manager',
      title: 'No manager assigned',
      tone: 'high',
    });
  }

  if (!employee.hireDate) {
    signals.push({
      key: 'hire-date-missing',
      label: 'Missing Hire Date',
      title: 'Hire date is not set',
      tone: 'low',
    });
  }

  return signals;
};

const Employees = () => {
  const [activeTab, setActiveTab] = useState<'org' | 'teams' | 'people'>('people');
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [utilization, setUtilization] = useState<{ date: string; effective_capacity_hours: number; scheduled_hours: number; utilization_pct: number; }[]>([]);
  const [utilLoading, setUtilLoading] = useState(false);
  const [utilError, setUtilError] = useState<string | null>(null);
  const [overrideDate, setOverrideDate] = useState<string>(() => new Date().toISOString().slice(0,10));
  const [overridePct, setOverridePct] = useState<number>(100);
  const [leaveTypes, setLeaveTypes] = useState<{ id: number; name: string; paid: boolean }[]>([]);
  const [requests, setRequests] = useState<any[]>([]);
  const [lvStart, setLvStart] = useState<string>(() => new Date().toISOString().slice(0,10));
  const [lvEnd, setLvEnd] = useState<string>(() => new Date(Date.now() + 86400000).toISOString().slice(0,10));
  const [lvTypeId, setLvTypeId] = useState<number>(0);
  const [lvNotes, setLvNotes] = useState<string>('');
  const [orgTeams, setOrgTeams] = useState<Team[]>([]);
  const [orgLoading, setOrgLoading] = useState(false);
  const [orgError, setOrgError] = useState<string | null>(null);
  const [archiveBusy, setArchiveBusy] = useState(false);
  const [pendingArchiveId, setPendingArchiveId] = useState<number | null>(null);
  const [confirmBulkArchive, setConfirmBulkArchive] = useState(false);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [managerFilter, setManagerFilter] = useState<'all' | 'assigned' | 'unassigned'>('all');
  const [searchFilter, setSearchFilter] = useState<string>('');
  const [activePreset, setActivePreset] = useState<'none' | 'active' | 'no_manager' | 'archived'>('none');
  const toast = useToast();

  const fetchSchema = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/schemas/employee?status=active`, {
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

  const fetchEmployees = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/employees`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch employees');
      }

      const data = await response.json();
      setEmployees(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const fetchOrgTeams = async () => {
    try {
      setOrgLoading(true);
      setOrgError(null);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/teams`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch teams');
      }

      const data = await response.json();
      setOrgTeams(Array.isArray(data) ? data : []);
    } catch (err) {
      setOrgError(err instanceof Error ? err.message : 'Failed to load organization');
    } finally {
      setOrgLoading(false);
    }
  };

  const fetchUtilization = async (employeeId: number) => {
    try {
      setUtilLoading(true);
      setUtilError(null);
      const today = new Date();
      const start = new Date(today);
      start.setDate(today.getDate() - 6);
      const startStr = start.toISOString().slice(0,10);
      const endStr = today.toISOString().slice(0,10);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const response = await fetch(`${apiUrl}/work/utilization?employeeId=${employeeId}&startDate=${startStr}&endDate=${endStr}`, {
        headers: { 'X-WP-Nonce': nonce }
      });
      if (!response.ok) {
        throw new Error('Failed to fetch utilization');
      }
      const data = await response.json();
      setUtilization(Array.isArray(data) ? data : []);
    } catch (err) {
      setUtilError(err instanceof Error ? err.message : 'Failed to load utilization');
    } finally {
      setUtilLoading(false);
    }
  };

  const fetchLeaveTypes = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const res = await fetch(`${apiUrl}/leave/types`, { headers: { 'X-WP-Nonce': nonce } });
      if (res.ok) {
        const data = await res.json();
        setLeaveTypes(Array.isArray(data) ? data : []);
        if (Array.isArray(data) && data.length > 0) {
          setLvTypeId(Number(data[0].id));
        }
      }
    } catch (e) {
      console.error('Failed to fetch leave types', e);
    }
  };

  const fetchLeaveRequests = async (employeeId: number) => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const res = await fetch(`${apiUrl}/leave/requests?employeeId=${employeeId}`, { headers: { 'X-WP-Nonce': nonce } });
      if (res.ok) {
        const data = await res.json();
        setRequests(Array.isArray(data) ? data : []);
      }
    } catch (e) {
      console.error('Failed to fetch leave requests', e);
    }
  };

  useEffect(() => {
    if (activeTab === 'people') {
      fetchEmployees();
      fetchSchema();
      fetchLeaveTypes();
    }
    if (activeTab === 'org') {
      fetchEmployees();
      fetchOrgTeams();
    }
  }, [activeTab]);

  useEffect(() => {
    const eid = editingEmployee?.id || (typeof selectedIds[0] === 'number' ? Number(selectedIds[0]) : null);
    if (activeTab === 'people' && eid) {
      // @ts-ignore
      const flags = window.petSettings?.featureFlags;
      if (flags?.resilience_indicators_enabled) {
        fetchUtilization(eid);
      } else {
        setUtilization([]);
      }
      fetchLeaveRequests(eid);
    } else {
      setUtilization([]);
      setRequests([]);
    }
  }, [activeTab, editingEmployee, selectedIds]);

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingEmployee(null);
    fetchEmployees();
  };

  const handleEdit = (employee: Employee) => {
    setEditingEmployee(employee);
    setShowAddForm(true);
  };

  const handleArchive = async (id: number) => {
    setArchiveBusy(true);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/employees/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive employee');
      }

      fetchEmployees();
      setSelectedIds(prev => prev.filter(sid => sid !== id));
      toast.success('Employee archived');
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
          const response = await fetch(`${apiUrl}/employees/${id}`, {
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
      fetchEmployees();
      if (failedCount > 0) {
        toast.error(`Archived ${successCount} employees; ${failedCount} failed.`);
      } else {
        toast.success(`Archived ${successCount} employees.`);
      }
    } finally {
      setArchiveBusy(false);
      setConfirmBulkArchive(false);
    }
  };

  const findEmployeeById = (id: number): Employee | undefined => {
    return employees.find(e => e.id === id);
  };

  const openEmployeeFromOrg = (employeeId: number) => {
    const employee = findEmployeeById(employeeId);
    if (!employee) {
      return;
    }
    setEditingEmployee(employee);
    setShowAddForm(true);
    setActiveTab('people');
  };

  const renderTeamNode = (team: Team, depth: number = 0): React.ReactNode => {
    const manager = team.manager_id ? findEmployeeById(team.manager_id) : undefined;
    const members = (team.member_ids || [])
      .map(id => findEmployeeById(id))
      .filter((e): e is Employee => !!e);

    return (
      <div
        key={team.id}
        style={{
          border: '1px solid #ccd0d4',
          borderRadius: '4px',
          padding: '12px 16px',
          marginBottom: '12px',
          marginLeft: depth * 20,
          background: '#fff',
        }}
      >
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
          <div style={{ fontWeight: 600 }}>
            {team.name}
          </div>
          <div>
            <span className={`status-badge status-${String(team.status).toLowerCase()}`}>{team.status}</span>
          </div>
        </div>

        {manager && (
          <div style={{ marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '10px' }}>
            {/*
              Avatar comes from employees endpoint (avatarUrl field on EmployeeController).
              Use optional chaining in case shape changes.
            */}
            {/* @ts-ignore */}
            {manager.avatarUrl && (
              <img
                src={String((manager as any).avatarUrl)}
                alt=""
                style={{ width: '32px', height: '32px', borderRadius: '50%' }}
              />
            )}
            <button
              type="button"
              onClick={() => openEmployeeFromOrg(manager.id)}
              style={{
                background: 'none',
                border: 'none',
                padding: 0,
                cursor: 'pointer',
                color: '#2271b1',
                fontWeight: 600,
              }}
            >
              {manager.firstName} {manager.lastName}
            </button>
            <span style={{ color: '#666', fontSize: '12px' }}>Manager</span>
          </div>
        )}

        {members.length > 0 && (
          <div style={{ marginTop: '4px' }}>
            <div style={{ fontWeight: 600, marginBottom: '6px', fontSize: '13px' }}>Team Members</div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
              {members.map((member) => (
                <button
                  key={member.id}
                  type="button"
                  onClick={() => openEmployeeFromOrg(member.id)}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '6px',
                    padding: '6px 10px',
                    borderRadius: '20px',
                    border: '1px solid #dcdcde',
                    background: '#f6f7f7',
                    cursor: 'pointer',
                  }}
                >
                  {/* @ts-ignore */}
                  {member.avatarUrl && (
                    <img
                      src={String((member as any).avatarUrl)}
                      alt=""
                      style={{ width: '24px', height: '24px', borderRadius: '50%' }}
                    />
                  )}
                  <span style={{ fontSize: '12px' }}>
                    {member.firstName} {member.lastName}
                  </span>
                </button>
              ))}
            </div>
          </div>
        )}

        {team.children && team.children.length > 0 && (
          <div style={{ marginTop: '10px' }}>
            {team.children.map(child => renderTeamNode(child, depth + 1))}
          </div>
        )}
      </div>
    );
  };

  const employeesById = useMemo(() => {
    const byId = new Map<number, Employee>();
    employees.forEach((employee) => {
      byId.set(employee.id, employee);
    });
    return byId;
  }, [employees]);

  const statusOptions = useMemo(
    () => Array.from(new Set(employees.map((employee) => String(employee.status || 'unknown').toLowerCase()))).sort(),
    [employees]
  );

  const filteredEmployees = useMemo(() => {
    const query = searchFilter.trim().toLowerCase();
    return employees.filter((employee) => {
      const status = String(employee.status || 'unknown').toLowerCase();
      if (statusFilter !== 'all' && status !== statusFilter) {
        return false;
      }
      if (managerFilter === 'assigned' && !employee.managerId) {
        return false;
      }
      if (managerFilter === 'unassigned' && Boolean(employee.managerId)) {
        return false;
      }
      if (activePreset === 'active' && (status !== 'active' || Boolean(employee.archivedAt))) {
        return false;
      }
      if (activePreset === 'no_manager' && (Boolean(employee.managerId) || Boolean(employee.archivedAt))) {
        return false;
      }
      if (activePreset === 'archived' && !employee.archivedAt) {
        return false;
      }
      if (!query) {
        return true;
      }
      const displayName = employee.displayName || [employee.firstName, employee.lastName].filter(Boolean).join(' ');
      const searchable = `${employee.id} ${displayName} ${employee.email}`.toLowerCase();
      return searchable.includes(query);
    });
  }, [activePreset, employees, managerFilter, searchFilter, statusFilter]);

  const peopleSummary = useMemo(() => {
    const total = filteredEmployees.length;
    const activeCount = filteredEmployees.filter((employee) => String(employee.status || '').toLowerCase() === 'active' && !employee.archivedAt).length;
    const archivedCount = filteredEmployees.filter((employee) => Boolean(employee.archivedAt)).length;
    const withManagerCount = filteredEmployees.filter((employee) => Boolean(employee.managerId)).length;
    const withoutManagerCount = filteredEmployees.filter((employee) => !employee.managerId).length;
    const ninetyDaysAgo = Date.now() - (90 * 24 * 60 * 60 * 1000);
    const newHireCount = filteredEmployees.filter((employee) => employee.hireDate && new Date(employee.hireDate).getTime() >= ninetyDaysAgo).length;
    const attentionCount = filteredEmployees.filter((employee) => getEmployeeAttentionSignals(employee).length > 0).length;
    return {
      total,
      activeCount,
      archivedCount,
      withManagerCount,
      withoutManagerCount,
      newHireCount,
      attentionCount,
      selectedCount: selectedIds.length,
    };
  }, [filteredEmployees, selectedIds.length]);

  const activeEmployeeId = editingEmployee?.id || (selectedIds.length === 1 && typeof selectedIds[0] === 'number' ? Number(selectedIds[0]) : null);

  const applyPeoplePreset = (preset: 'none' | 'active' | 'no_manager' | 'archived') => {
    setActivePreset(preset);
    setSelectedIds([]);
    if (preset === 'none') {
      setStatusFilter('all');
      setManagerFilter('all');
      return;
    }
    if (preset === 'active') {
      setStatusFilter('active');
      return;
    }
    if (preset === 'no_manager') {
      setManagerFilter('unassigned');
      return;
    }
    if (preset === 'archived') {
      setStatusFilter('all');
      setManagerFilter('all');
    }
  };

  const columns: Column<Employee>[] = [
    {
      key: 'firstName',
      header: 'Employee',
      render: (_, item: Employee) => {
        const displayName = item.displayName || [item.firstName, item.lastName].filter(Boolean).join(' ').trim() || `Employee #${item.id}`;
        const subtitle = item.jobTitle ? `${item.email} · ${item.jobTitle}` : item.email;
        // @ts-ignore
        const avatarUrl = item.avatarUrl ? String((item as any).avatarUrl) : '';
        return (
          <span className="pet-employee-row-primary">
            <span className="pet-employee-row-title-line">
              {avatarUrl ? <img className="pet-employee-row-avatar" src={avatarUrl} alt="" /> : null}
              <button
                type="button"
                className="pet-employee-row-link"
                onClick={() => handleEdit(item)}
              >
                {displayName}
              </button>
            </span>
            <span className="pet-employee-row-meta">{subtitle || '—'}</span>
          </span>
        );
      },
    },
    {
      key: 'managerId',
      header: 'Org Context',
      render: (_, item: Employee) => {
        const manager = item.managerId ? employeesById.get(item.managerId) : undefined;
        const managerName = manager
          ? (manager.displayName || `${manager.firstName} ${manager.lastName}`.trim() || `Manager #${manager.id}`)
          : (item.managerId ? `Manager #${item.managerId}` : 'No manager assigned');
        const teamCount = item.teamIds?.length ?? 0;
        return (
          <span className="pet-employee-row-context">
            <span className="pet-employee-row-context-primary">{managerName}</span>
            <span className="pet-employee-row-context-secondary">
              {teamCount > 0 ? `${teamCount} team${teamCount === 1 ? '' : 's'}` : 'No team membership'}
            </span>
          </span>
        );
      },
    },
    {
      key: 'hireDate',
      header: 'Employment',
      render: (_, item: Employee) => (
        <span className="pet-employee-row-context">
          <span className="pet-employee-row-context-primary">Hired: {formatDateLabel(item.hireDate)}</span>
          <span className="pet-employee-row-context-secondary">Created: {formatDateLabel(item.createdAt)}</span>
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Signals',
      render: (_, item: Employee) => {
        const status = String(item.status || 'unknown').toLowerCase();
        const signals = getEmployeeAttentionSignals(item);
        return (
          <span className="pet-employee-row-signals">
            <span className={`pet-status-badge status-${status}`}>{status}</span>
            {signals.length > 0 ? (
              <span className="pet-employee-attention-list" aria-label={`Attention signals: ${signals.map((signal) => signal.label).join(', ')}`}>
                {signals.map((signal) => (
                  <span
                    key={`${item.id}-${signal.key}`}
                    className={`pet-employee-attention-tag pet-employee-attention-tag--${signal.tone}`}
                    title={signal.title}
                  >
                    {signal.label}
                  </span>
                ))}
              </span>
            ) : (
              <span className="pet-employee-attention-empty">—</span>
            )}
          </span>
        );
      },
    },
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Employee,
      header: field.label,
      render: (_: any, item: Employee) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    { key: 'archivedAt', header: 'Archived', render: (val: any) => val ? <span style={{ color: '#999' }}>{formatDateLabel(String(val))}</span> : '-' },
  ];

  return (
    <div className="pet-employees-container">
      <div className="pet-employees-tabs">
        <button
          className={`button ${activeTab === 'org' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('org')}
          style={{ marginRight: '10px' }}
        >
          Org
        </button>
        <button
          className={`button ${activeTab === 'teams' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('teams')}
          style={{ marginRight: '10px' }}
        >
          Teams
        </button>
        <button
          className={`button ${activeTab === 'people' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('people')}
          style={{ marginRight: '10px' }}
        >
          People
        </button>
      </div>

      {activeTab === 'org' && (
        <div className="pet-org">
          <h2>Organization Structure</h2>
          {orgLoading && <LoadingState label="Loading organization…" />}
          {orgError && !orgLoading && (
            <ErrorState message={orgError} onRetry={fetchOrgTeams} />
          )}
          {!orgLoading && !orgError && orgTeams.length === 0 && (
            <EmptyState message="No teams defined yet." />
          )}
          {!orgLoading && !orgError && orgTeams.length > 0 && (
            <div>
              {orgTeams.map(team => renderTeamNode(team))}
            </div>
          )}
        </div>
      )}

      {activeTab === 'teams' && (
        <Teams />
      )}


      {activeTab === 'people' && (
        <PageShell
          title="People (Employees)"
          subtitle="Manage staff records, operational capacity context, and leave workflows."
          className="pet-employees"
          testId="employees-shell"
          actions={!showAddForm ? (
            <button className="button button-primary" onClick={() => setShowAddForm(true)}>
              Add New Employee
            </button>
          ) : null}
        >
          <Panel className="pet-employees-summary-panel" testId="employees-summary-panel">
            <div className="pet-employees-summary-grid">
              <div className="pet-employees-summary-item">
                <span className="pet-employees-summary-label">Employees</span>
                <strong className="pet-employees-summary-value">{peopleSummary.total}</strong>
              </div>
              <div className="pet-employees-summary-item">
                <span className="pet-employees-summary-label">Active</span>
                <strong className="pet-employees-summary-value">{peopleSummary.activeCount}</strong>
              </div>
              <div className="pet-employees-summary-item">
                <span className="pet-employees-summary-label">Managed</span>
                <strong className="pet-employees-summary-value">{peopleSummary.withManagerCount}</strong>
              </div>
              <div className="pet-employees-summary-item">
                <span className="pet-employees-summary-label">Unassigned</span>
                <strong className="pet-employees-summary-value">{peopleSummary.withoutManagerCount}</strong>
              </div>
              <div className="pet-employees-summary-item">
                <span className="pet-employees-summary-label">New Hires (90d)</span>
                <strong className="pet-employees-summary-value">{peopleSummary.newHireCount}</strong>
              </div>
              <div className="pet-employees-summary-item">
                <span className="pet-employees-summary-label">Archived</span>
                <strong className="pet-employees-summary-value">{peopleSummary.archivedCount}</strong>
              </div>
              <div className="pet-employees-summary-item">
                <span className="pet-employees-summary-label">Needs Attention</span>
                <strong className="pet-employees-summary-value">{peopleSummary.attentionCount}</strong>
              </div>
              <div className="pet-employees-summary-item">
                <span className="pet-employees-summary-label">Selected</span>
                <strong className="pet-employees-summary-value">{peopleSummary.selectedCount}</strong>
              </div>
            </div>
          </Panel>

          <Panel className="pet-employees-filters-panel" testId="employees-filters-panel">
            <div className="pet-employees-filters-grid">
              <label className="pet-employees-filter-field" htmlFor="pet-employee-filter-status">
                <span>Status</span>
                <select
                  id="pet-employee-filter-status"
                  value={statusFilter}
                  onChange={(event) => {
                    setStatusFilter(event.target.value);
                    setActivePreset('none');
                    setSelectedIds([]);
                  }}
                >
                  <option value="all">All statuses</option>
                  {statusOptions.map((status) => (
                    <option key={status} value={status}>
                      {status}
                    </option>
                  ))}
                </select>
              </label>
              <label className="pet-employees-filter-field" htmlFor="pet-employee-filter-manager">
                <span>Manager Coverage</span>
                <select
                  id="pet-employee-filter-manager"
                  value={managerFilter}
                  onChange={(event) => {
                    setManagerFilter(event.target.value as 'all' | 'assigned' | 'unassigned');
                    setActivePreset('none');
                    setSelectedIds([]);
                  }}
                >
                  <option value="all">All</option>
                  <option value="assigned">With manager</option>
                  <option value="unassigned">Without manager</option>
                </select>
              </label>
              <label className="pet-employees-filter-field" htmlFor="pet-employee-filter-search">
                <span>Employee Search</span>
                <input
                  id="pet-employee-filter-search"
                  type="search"
                  value={searchFilter}
                  onChange={(event) => {
                    setSearchFilter(event.target.value);
                    setSelectedIds([]);
                  }}
                  placeholder="Name, email, or employee ID"
                />
              </label>
              <div className="pet-employees-filter-actions">
                <button
                  type="button"
                  className="button"
                  onClick={() => {
                    setStatusFilter('all');
                    setManagerFilter('all');
                    setSearchFilter('');
                    setActivePreset('none');
                    setSelectedIds([]);
                  }}
                  disabled={statusFilter === 'all' && managerFilter === 'all' && !searchFilter}
                >
                  Clear Filters
                </button>
              </div>
            </div>
            <div className="pet-employees-preset-bar" role="group" aria-label="Employee quick presets">
              <button
                type="button"
                className={`button pet-employees-preset-btn ${activePreset === 'active' ? 'is-active' : ''}`}
                onClick={() => applyPeoplePreset('active')}
              >
                Active
              </button>
              <button
                type="button"
                className={`button pet-employees-preset-btn ${activePreset === 'no_manager' ? 'is-active' : ''}`}
                onClick={() => applyPeoplePreset('no_manager')}
              >
                No Manager
              </button>
              <button
                type="button"
                className={`button pet-employees-preset-btn ${activePreset === 'archived' ? 'is-active' : ''}`}
                onClick={() => applyPeoplePreset('archived')}
              >
                Archived
              </button>
            </div>
          </Panel>

          {showAddForm && (
            <Panel className="pet-employees-form-panel">
              <EmployeeForm
                onSuccess={handleFormSuccess}
                onCancel={() => { setShowAddForm(false); setEditingEmployee(null); }}
                initialData={editingEmployee || undefined}
              />
            </Panel>
          )}

          {selectedIds.length > 0 && (
            <ActionBar className="pet-employees-bulk-strip" testId="employees-bulk-strip">
              <div className="pet-employees-bulk-text">
                <span className="pet-employees-bulk-eyebrow">Bulk actions</span>
                <strong>{selectedIds.length} items selected</strong>
              </div>
              <button className="button button-link-delete pet-action-danger" onClick={() => setConfirmBulkArchive(true)}>
                Archive Selected
              </button>
            </ActionBar>
          )}

          <Panel className="pet-employees-table-panel" testId="employees-main-panel">
            <div className="pet-employees-table-header">
              <h3>Employee List</h3>
              <p>Review people records, management coverage, and staffing signals at a glance.</p>
            </div>
            <DataTable
              columns={columns}
              data={filteredEmployees}
              loading={loading}
              error={error}
              onRetry={fetchEmployees}
              emptyMessage="No employees found."
              compatibilityMode="wp"
              selection={{
                selectedIds,
                onSelectionChange: setSelectedIds
              }}
              rowClassName={(employee) => (
                activePreset === 'no_manager' && !employee.managerId && !employee.archivedAt
                  ? 'pet-employee-row--attention'
                  : ''
              )}
              actions={(item) => (
                <KebabMenu items={[
                  { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
                  { type: 'action', label: 'Archive', onClick: () => setPendingArchiveId(item.id), danger: true },
                ]} />
              )}
            />
          </Panel>

          {activeEmployeeId !== null && (
            <Panel className="pet-employees-capacity-panel">
              <h3 style={{ marginTop: 0 }}>Capacity & Utilization</h3>
              <div style={{ display: 'flex', gap: '20px', flexWrap: 'wrap' }}>
                <div style={{ flex: '1 1 320px', minWidth: '280px' }}>
                  <h4 style={{ marginTop: 0 }}>Set Capacity Override</h4>
                  <div style={{ display: 'grid', gridTemplateColumns: '120px 1fr', gap: '10px', alignItems: 'center' }}>
                    <label>Date</label>
                    <input type="date" value={overrideDate} onChange={(e) => setOverrideDate(e.target.value)} />
                    <label>Capacity %</label>
                    <input type="number" min={0} max={100} value={overridePct} onChange={(e) => setOverridePct(Number(e.target.value))} />
                  </div>
                  <div style={{ marginTop: '10px' }}>
                    <button
                      className="button button-primary"
                      onClick={async () => {
                        // @ts-ignore
                        const apiUrl = window.petSettings?.apiUrl;
                        // @ts-ignore
                        const nonce = window.petSettings?.nonce;
                        const res = await fetch(`${apiUrl}/leave/capacity-override`, {
                          method: 'POST',
                          headers: {
                            'X-WP-Nonce': nonce,
                            'Content-Type': 'application/json',
                          },
                          body: JSON.stringify({
                            employeeId: activeEmployeeId,
                            date: overrideDate,
                            capacityPct: overridePct,
                            reason: 'Admin override',
                          })
                        });
                        if (res.ok) {
                          fetchUtilization(activeEmployeeId);
                          toast.success('Capacity override saved');
                        } else {
                          toast.error('Failed to save override');
                        }
                      }}
                    >
                      Save Override
                    </button>
                  </div>
                </div>
                <div style={{ flex: '2 1 480px', minWidth: '320px' }}>
                  <h4 style={{ marginTop: 0 }}>Last 7 Days Utilization</h4>
                  {utilLoading ? <LoadingState label="Loading utilization…" /> :
                   utilError ? <ErrorState message={utilError} onRetry={() => fetchUtilization(activeEmployeeId)} /> :
                   utilization.length === 0 ? <EmptyState message="No utilization data." /> :
                   <table className="widefat striped">
                     <thead>
                       <tr>
                         <th>Date</th>
                         <th>Capacity (h)</th>
                         <th>Scheduled (h)</th>
                         <th>Utilization (%)</th>
                       </tr>
                     </thead>
                     <tbody>
                       {utilization.map((row) => (
                         <tr key={row.date}>
                           <td>{row.date}</td>
                           <td>{row.effective_capacity_hours.toFixed(2)}</td>
                           <td>{row.scheduled_hours.toFixed(2)}</td>
                           <td>{row.utilization_pct.toFixed(2)}</td>
                         </tr>
                       ))}
                     </tbody>
                   </table>
                  }
                </div>
              </div>
            </Panel>
          )}

          {activeEmployeeId !== null && (
            <Panel className="pet-employees-leave-panel">
              <h3 style={{ marginTop: 0 }}>Leave Requests</h3>
              <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr 1fr 1fr', gap: '10px', alignItems: 'center' }}>
                <label>Leave Type</label>
                <select value={lvTypeId} onChange={(e) => setLvTypeId(Number(e.target.value))}>
                  {leaveTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
                <label>Notes</label>
                <input type="text" value={lvNotes} onChange={(e) => setLvNotes(e.target.value)} placeholder="Optional notes" />
                <label>Start</label>
                <input type="date" value={lvStart} onChange={(e) => setLvStart(e.target.value)} />
                <label>End</label>
                <input type="date" value={lvEnd} onChange={(e) => setLvEnd(e.target.value)} />
              </div>
              <div style={{ marginTop: '10px' }}>
                <button
                  className="button button-primary"
                  onClick={async () => {
                    // @ts-ignore
                    const apiUrl = window.petSettings?.apiUrl;
                    // @ts-ignore
                    const nonce = window.petSettings?.nonce;
                    const res = await fetch(`${apiUrl}/leave/requests`, {
                      method: 'POST',
                      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                      body: JSON.stringify({
                        employeeId: activeEmployeeId,
                        leaveTypeId: lvTypeId,
                        startDate: lvStart,
                        endDate: lvEnd,
                        notes: lvNotes || null,
                      })
                    });
                    if (res.ok) {
                      fetchLeaveRequests(activeEmployeeId);
                      setLvNotes('');
                      toast.success('Leave request submitted');
                    } else {
                      toast.error('Failed to submit leave request');
                    }
                  }}
                >
                  Submit Leave Request
                </button>
              </div>
              <div style={{ marginTop: '15px' }}>
                {requests.length === 0 ? (
                  <EmptyState message="No leave requests." />
                ) : (
                  <table className="widefat striped">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Decided At</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {requests.map((r) => (
                        <tr key={r.id}>
                          <td>{r.id}</td>
                          <td>{leaveTypes.find(t => t.id === r.leaveTypeId)?.name || r.leaveTypeId}</td>
                          <td>{r.startDate}</td>
                          <td>{r.endDate}</td>
                          <td>{r.status}</td>
                          <td>{r.decidedAt || '-'}</td>
                          <td style={{ textAlign: 'right' }}>
                            <div style={{ display: 'flex', gap: '6px', justifyContent: 'flex-end' }}>
                              {r.status === 'submitted' && (
                                <>
                                  <button
                                    className="button button-small"
                                    onClick={async () => {
                                      // @ts-ignore
                                      const apiUrl = window.petSettings?.apiUrl;
                                      // @ts-ignore
                                      const nonce = window.petSettings?.nonce;
                                      await fetch(`${apiUrl}/leave/requests/${r.id}/decide`, {
                                        method: 'POST',
                                        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ decidedByEmployeeId: 0, decision: 'approved' })
                                      });
                                      fetchLeaveRequests(activeEmployeeId);
                                      fetchUtilization(activeEmployeeId);
                                    }}
                                  >
                                    Approve
                                  </button>
                                  <button
                                    className="button button-small"
                                    onClick={async () => {
                                      // @ts-ignore
                                      const apiUrl = window.petSettings?.apiUrl;
                                      // @ts-ignore
                                      const nonce = window.petSettings?.nonce;
                                      await fetch(`${apiUrl}/leave/requests/${r.id}/decide`, {
                                        method: 'POST',
                                        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ decidedByEmployeeId: 0, decision: 'rejected', reason: 'Not approved' })
                                      });
                                      fetchLeaveRequests(activeEmployeeId);
                                    }}
                                  >
                                    Reject
                                  </button>
                                </>
                              )}
                              {(r.status === 'approved' || r.status === 'rejected') && (
                                <button
                                  className="button button-small"
                                  onClick={async () => {
                                    // @ts-ignore
                                    const apiUrl = window.petSettings?.apiUrl;
                                    // @ts-ignore
                                    const nonce = window.petSettings?.nonce;
                                    await fetch(`${apiUrl}/leave/requests/${r.id}/decide`, {
                                      method: 'POST',
                                      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                                      body: JSON.stringify({ decidedByEmployeeId: 0, decision: 'cancelled' })
                                    });
                                    fetchLeaveRequests(activeEmployeeId);
                                    fetchUtilization(activeEmployeeId);
                                  }}
                                >
                                  Cancel
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </Panel>
          )}
        </PageShell>
      )}

      <ConfirmationDialog
        open={pendingArchiveId !== null}
        title="Archive employee?"
        description="This action will archive the selected employee."
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
        title="Archive selected employees?"
        description={`This action will archive ${selectedIds.length} selected employees.`}
        confirmLabel="Archive selected"
        busy={archiveBusy}
        onCancel={() => setConfirmBulkArchive(false)}
        onConfirm={handleBulkArchive}
      />
    </div>
  );
};

export default Employees;
