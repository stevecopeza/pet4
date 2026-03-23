import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import { Employee } from '../types';
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

interface RoleDetails {
  id: number;
  name: string;
  description: string;
  success_criteria: string;
}

interface RoleLookup {
  [id: number]: RoleDetails;
}

interface EmployeeRolesProps {
  employee: Employee;
  allowAssignments?: boolean;
  onAssignmentsChanged?: () => void;
}

const EmployeeRoles: React.FC<EmployeeRolesProps> = ({ employee, allowAssignments = false, onAssignmentsChanged }) => {
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [roles, setRoles] = useState<RoleLookup>({});
  const [loading, setLoading] = useState(true);
  const [showAssignForm, setShowAssignForm] = useState(false);

  // @ts-ignore
  const apiUrl = window.petSettings?.apiUrl;
  // @ts-ignore
  const nonce = window.petSettings?.nonce;

  const fetchRoles = async () => {
    try {
      const res = await fetch(`${apiUrl}/roles`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (res.ok) {
        const data = await res.json();
        const map: RoleLookup = {};
        data.forEach((r: any) => {
          map[r.id] = {
            id: r.id,
            name: r.name,
            description: r.description || '',
            success_criteria: r.success_criteria || '',
          };
        });
        setRoles(map);
      }
    } catch (e) {
      console.error('Failed to fetch roles lookup', e);
    }
  };

  const fetchAssignments = async () => {
    try {
      setLoading(true);
      const res = await fetch(`${apiUrl}/assignments?employee_id=${employee.id}`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (res.ok) {
        const data = await res.json();
        setAssignments(data);
      }
    } catch (e) {
      console.error('Failed to fetch employee assignments', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRoles();
    fetchAssignments();
  }, [employee.id]);

  const columns: Column<Assignment>[] = [
    {
      key: 'role_id',
      header: 'Role',
      render: (id) => {
        const role = roles[id as number];
        return role ? role.name : `ID: ${id}`;
      },
    },
    { key: 'start_date', header: 'Start Date' },
    { key: 'end_date', header: 'End Date' },
    { key: 'allocation_pct', header: 'Allocation %' },
    { key: 'status', header: 'Status' },
  ];

  const renderAssignmentDetails = (assignment: Assignment) => {
    const role = roles[assignment.role_id];

    if (!role) {
      return <div>Role details not available.</div>;
    }

    return (
      <div>
        <div style={{ marginBottom: '8px' }}>
          <strong>{role.name}</strong>
        </div>
        {role.description && (
          <div style={{ marginBottom: '8px' }}>
            <div style={{ fontWeight: 600, marginBottom: '4px' }}>Role Description</div>
            <div>{role.description}</div>
          </div>
        )}
        {role.success_criteria && (
          <div>
            <div style={{ fontWeight: 600, marginBottom: '4px' }}>Success Criteria</div>
            <div style={{ whiteSpace: 'pre-wrap' }}>{role.success_criteria}</div>
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="employee-roles">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px', gap: '10px', flexWrap: 'wrap' }}>
        <h3 style={{ margin: 0 }}>Role Assignments</h3>
        {allowAssignments && (
          <button
            type="button"
            className="button button-primary button-small"
            onClick={() => setShowAssignForm((prev) => !prev)}
          >
            {showAssignForm ? 'Cancel Role Assignment' : 'Assign Role'}
          </button>
        )}
      </div>
      {allowAssignments && showAssignForm && (
        <div style={{ marginBottom: '12px' }}>
          <AssignRoleForm
            fixedEmployeeId={employee.id}
            fixedEmployeeLabel={employee.displayName || `${employee.firstName} ${employee.lastName}`.trim()}
            onSuccess={() => {
              setShowAssignForm(false);
              fetchAssignments();
              if (onAssignmentsChanged) {
                onAssignmentsChanged();
              }
            }}
            onCancel={() => setShowAssignForm(false)}
          />
        </div>
      )}
      <DataTable
        data={assignments}
        columns={columns}
        loading={loading}
        emptyMessage="No role assignments found."
        rowDetails={renderAssignmentDetails}
      />
    </div>
  );
};

export default EmployeeRoles;
