import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import AssignRoleForm from './AssignRoleForm';

interface Assignment {
  id: number;
  employee_id: number;
  role_id: number;
  start_date: string;
  end_date: string | null;
  allocation_pct: number;
  status: string;
}

interface Role {
    id: number;
    name: string;
}

interface Employee {
    id: number;
    display_name: string;
}

const Assignments = () => {
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAssignForm, setShowAssignForm] = useState(false);
  const [roles, setRoles] = useState<{[key: number]: string}>({});
  const [employees, setEmployees] = useState<{[key: number]: string}>({});
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<string>('');

  const fetchAssignments = async () => {
    try {
      setLoading(true);
      let url = `${window.petSettings.apiUrl}/assignments`;
      if (selectedEmployeeId) {
          url += `?employee_id=${selectedEmployeeId}`;
      }

      // @ts-ignore
      const response = await fetch(url, { 
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        setAssignments(data);
      }
    } catch (err) {
      console.error('Failed to fetch assignments', err);
    } finally {
      setLoading(false);
    }
  };
  
  // Need to fetch roles and employees to map IDs to names
  const fetchLookups = async () => {
      // Fetch Roles
      try {
        // @ts-ignore
        const res = await fetch(`${window.petSettings.apiUrl}/roles`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } });
        if (res.ok) {
            const data: Role[] = await res.json();
            const map: any = {};
            data.forEach(r => map[r.id] = r.name);
            setRoles(map);
        }
      } catch (e) {}

      // Fetch Employees
      try {
        // @ts-ignore
        const res = await fetch(`${window.petSettings.apiUrl}/employees`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } });
        if (res.ok) {
            const data: Employee[] = await res.json();
            const map: any = {};
            data.forEach(e => map[e.id] = e.display_name);
            setEmployees(map);
        }
      } catch (e) {}
  };

  useEffect(() => {
    const init = async () => {
        setLoading(true);
        if (Object.keys(roles).length === 0) await fetchLookups();
        await fetchAssignments();
        setLoading(false);
    };
    init();
  }, [selectedEmployeeId]);

  const handleEndAssignment = async (assignment: Assignment) => {
    const today = new Date().toISOString().split('T')[0];
    const date = window.prompt('Enter End Date (YYYY-MM-DD):', today);
    
    if (!date) return;

    try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/assignments/${assignment.id}/end`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // @ts-ignore
                'X-WP-Nonce': window.petSettings.nonce,
            },
            body: JSON.stringify({ end_date: date }),
        });

        if (response.ok) {
            fetchAssignments();
        } else {
            const err = await response.json();
            alert(`Error: ${err.error || 'Failed to end assignment'}`);
        }
    } catch (e) {
        console.error(e);
        alert('Failed to end assignment');
    }
  };

  const columns: Column<Assignment>[] = [
    { 
        key: 'employee_id', 
        header: 'Person',
        render: (id) => employees[id as number] || `ID: ${id}`
    },
    { 
        key: 'role_id', 
        header: 'Role',
        render: (id) => roles[id as number] || `ID: ${id}`
    },
    { key: 'start_date', header: 'Start Date' },
    { key: 'end_date', header: 'End Date' },
    { key: 'allocation_pct', header: 'Allocation %' },
    { key: 'status', header: 'Status' },
    {
        key: 'id',
        header: 'Actions',
        render: (_, row) => (
            row.status === 'active' ? (
                <button 
                    className="button button-small"
                    onClick={() => handleEndAssignment(row)}
                >
                    End Assignment
                </button>
            ) : null
        )
    }
  ];

  if (showAssignForm) {
      return (
          <div>
              <div style={{ marginBottom: '20px' }}>
                  <button 
                      className="button" 
                      onClick={() => setShowAssignForm(false)}
                  >
                      &larr; Back to Assignments
                  </button>
              </div>
              <AssignRoleForm 
                  onSuccess={() => {
                      setShowAssignForm(false);
                      fetchAssignments();
                  }} 
                  onCancel={() => setShowAssignForm(false)} 
              />
          </div>
      );
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h3>Role Assignments</h3>
        <div style={{ display: 'flex', gap: '10px' }}>
            <select 
                value={selectedEmployeeId} 
                onChange={(e) => setSelectedEmployeeId(e.target.value)}
                style={{ minWidth: '200px' }}
            >
                <option value="">All Employees</option>
                {Object.entries(employees).map(([id, name]) => (
                    <option key={id} value={id}>{name}</option>
                ))}
            </select>
            <button className="button button-primary" onClick={() => setShowAssignForm(true)}>
                Assign Role
            </button>
        </div>
      </div>

      <DataTable
        data={assignments}
        columns={columns}
        loading={loading}
        emptyMessage="No assignments found."
      />
    </div>
  );
};

export default Assignments;
