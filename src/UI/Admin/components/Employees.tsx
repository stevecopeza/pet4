import React, { useEffect, useState } from 'react';
import { Employee, Team } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import EmployeeForm from './EmployeeForm';
import Teams from './Teams';

const Employees = () => {
  const [activeTab, setActiveTab] = useState<'org' | 'teams' | 'people' | 'kpis'>('people');
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
      fetchUtilization(eid);
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
    if (!confirm('Are you sure you want to archive this employee?')) return;

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
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} employees?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/employees/${id}`, {
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
    fetchEmployees();
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

  const columns: Column<Employee>[] = [
    { key: 'id', header: 'ID' },
    { 
      key: 'avatarUrl' as keyof Employee, 
      header: '', 
      render: (val: any) => val ? <img src={String(val)} alt="Avatar" style={{ width: '32px', height: '32px', borderRadius: '50%', verticalAlign: 'middle' }} /> : null 
    },
    { 
      key: 'firstName', 
      header: 'Name', 
      render: (val: any, item: Employee) => (
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
          {String(val)} {item.lastName}
        </button>
      )
    },
    { key: 'email', header: 'Email' },
    { key: 'status', header: 'Status', render: (val: any) => <span className={`status-badge status-${String(val).toLowerCase()}`}>{String(val)}</span> },
    { key: 'hireDate', header: 'Hire Date', render: (val: any) => val ? new Date(val).toLocaleDateString() : '-' },
    { key: 'managerId', header: 'Manager ID', render: (val: any) => val ? String(val) : '-' },
    // Add malleable fields if they exist in schema
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Employee,
      header: field.label,
      render: (_: any, item: Employee) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    { key: 'createdAt', header: 'Created At' },
    { key: 'archivedAt', header: 'Archived At', render: (val: any) => val ? <span style={{color: '#999'}}>{String(val)}</span> : '-' },
  ];

  return (
    <div className="pet-employees-container">
      <div style={{ marginBottom: '20px', borderBottom: '1px solid #eee' }}>
        <button 
          className={`button ${activeTab === 'org' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('org')}
          style={{ marginRight: '10px', marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          Org
        </button>
        <button 
          className={`button ${activeTab === 'teams' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('teams')}
          style={{ marginRight: '10px', marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          Teams
        </button>
        <button 
          className={`button ${activeTab === 'people' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('people')}
          style={{ marginRight: '10px', marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          People
        </button>
        <button 
          className={`button ${activeTab === 'kpis' ? 'button-primary' : ''}`}
          onClick={() => setActiveTab('kpis')}
          style={{ marginBottom: '-1px', borderRadius: '4px 4px 0 0' }}
        >
          KPIs
        </button>
      </div>

      {activeTab === 'org' && (
        <div className="pet-org">
          <h2>Organization Structure</h2>
          {orgLoading && <p>Loading organization...</p>}
          {orgError && !orgLoading && (
            <div style={{ color: 'red', marginBottom: '10px' }}>Error: {orgError}</div>
          )}
          {!orgLoading && !orgError && orgTeams.length === 0 && (
            <p>No teams defined yet.</p>
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

      {activeTab === 'kpis' && (
        <div className="pet-kpis">
          <h2>Staff KPIs</h2>
          <p>Coming Soon</p>
        </div>
      )}

      {activeTab === 'people' && (
        <div className="pet-employees">
          {loading && !employees.length ? <div>Loading employees...</div> :
          error ? <div style={{ color: 'red' }}>Error: {error}</div> :
          <>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
              <h2>People (Employees)</h2>
              {!showAddForm && (
                <button className="button button-primary" onClick={() => setShowAddForm(true)}>
                  Add New Employee
                </button>
              )}
            </div>

            {showAddForm && (
              <EmployeeForm 
                onSuccess={handleFormSuccess} 
                onCancel={() => { setShowAddForm(false); setEditingEmployee(null); }} 
                initialData={editingEmployee || undefined}
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
              data={employees} 
              emptyMessage="No employees found."
              selection={{
                selectedIds,
                onSelectionChange: setSelectedIds
              }}
              actions={(item) => (
                <KebabMenu items={[
                  { type: 'action', label: 'Edit', onClick: () => handleEdit(item) },
                  { type: 'action', label: 'Archive', onClick: () => handleArchive(item.id), danger: true },
                ]} />
              )}
            />

            {(editingEmployee || (selectedIds.length === 1 && typeof selectedIds[0] === 'number')) && (
              <div className="pet-card" style={{ marginTop: '20px', padding: '15px', border: '1px solid #eee', borderRadius: '6px' }}>
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
                          const employeeId = editingEmployee?.id || Number(selectedIds[0]);
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
                              employeeId,
                              date: overrideDate,
                              capacityPct: overridePct,
                              reason: 'Admin override',
                            })
                          });
                          if (res.ok) {
                            fetchUtilization(employeeId);
                            alert('Capacity override saved');
                          } else {
                            alert('Failed to save override');
                          }
                        }}
                      >
                        Save Override
                      </button>
                    </div>
                  </div>
                  <div style={{ flex: '2 1 480px', minWidth: '320px' }}>
                    <h4 style={{ marginTop: 0 }}>Last 7 Days Utilization</h4>
                    {utilLoading ? <div>Loading utilization...</div> :
                     utilError ? <div style={{ color: 'red' }}>Error: {utilError}</div> :
                     utilization.length === 0 ? <div>No data</div> :
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
              </div>
            )}

            {(editingEmployee || (selectedIds.length === 1 && typeof selectedIds[0] === 'number')) && (
              <div className="pet-card" style={{ marginTop: '20px', padding: '15px', border: '1px solid #eee', borderRadius: '6px' }}>
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
                      const employeeId = editingEmployee?.id || Number(selectedIds[0]);
                      // @ts-ignore
                      const apiUrl = window.petSettings?.apiUrl;
                      // @ts-ignore
                      const nonce = window.petSettings?.nonce;
                      const res = await fetch(`${apiUrl}/leave/requests`, {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                          employeeId,
                          leaveTypeId: lvTypeId,
                          startDate: lvStart,
                          endDate: lvEnd,
                          notes: lvNotes || null,
                        })
                      });
                      if (res.ok) {
                        fetchLeaveRequests(employeeId);
                        setLvNotes('');
                        alert('Leave request submitted');
                      } else {
                        alert('Failed to submit leave request');
                      }
                    }}
                  >
                    Submit Leave Request
                  </button>
                </div>
                <div style={{ marginTop: '15px' }}>
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
                      {requests.length === 0 ? (
                        <tr><td colSpan={7}>No leave requests</td></tr>
                      ) : requests.map((r) => (
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
                                      const employeeId = editingEmployee?.id || Number(selectedIds[0]);
                                      fetchLeaveRequests(employeeId);
                                      fetchUtilization(employeeId);
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
                                      const employeeId = editingEmployee?.id || Number(selectedIds[0]);
                                      fetchLeaveRequests(employeeId);
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
                                    const employeeId = editingEmployee?.id || Number(selectedIds[0]);
                                    fetchLeaveRequests(employeeId);
                                    fetchUtilization(employeeId);
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
                </div>
              </div>
            )}
          </>
          }
        </div>
      )}
    </div>
  );
};

export default Employees;
