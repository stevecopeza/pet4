import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import Employees, { deriveStaffSetupState } from '../components/Employees';
import ToastProvider from '../components/foundation/ToastProvider';
import type { Employee } from '../types';

const renderWithToast = (node: ReactNode) => render(
  <ToastProvider>
    {node}
  </ToastProvider>
);

const makeEmployee = (overrides: Partial<Employee>): Employee => ({
  id: 1,
  wpUserId: 101,
  firstName: 'Alex',
  lastName: 'Jones',
  email: 'alex@example.com',
  status: 'active',
  hireDate: '2026-01-01',
  managerId: 2,
  teamIds: [11],
  malleableData: {},
  createdAt: '2026-01-01T00:00:00Z',
  archivedAt: null,
  ...overrides,
});

type AssignmentPayload = {
  id?: number;
  employee_id: number;
  role_id?: number;
  status?: string;
  end_date?: string | null;
};

type FetchCall = {
  url: string;
  method: string;
  body: any;
};

const jsonResponse = (payload: unknown, status: number = 200) => (
  Promise.resolve(new Response(JSON.stringify(payload), { status }))
);

const mockEmployeesFetch = (
  employeesPayload: Employee[],
  assignmentsPayload: AssignmentPayload[] = [],
  rolesPayload: Array<{ id: number; name: string }> = [{ id: 7, name: 'Engineer' }]
) => {
  const calls: FetchCall[] = [];
  const assignmentsStore: AssignmentPayload[] = assignmentsPayload.map((assignment, index) => ({
    id: assignment.id ?? (index + 1),
    status: assignment.status ?? 'active',
    end_date: assignment.end_date ?? null,
    ...assignment,
  }));
  let nextAssignmentId = assignmentsStore.reduce((maxId, assignment) => Math.max(maxId, Number(assignment.id || 0)), 0) + 1;

  vi.spyOn(window, 'fetch').mockImplementation((input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input);
    const method = String(init?.method || 'GET').toUpperCase();
    const body = typeof init?.body === 'string'
      ? (() => {
          try {
            return JSON.parse(init.body as string);
          } catch {
            return init.body;
          }
        })()
      : null;

    calls.push({ url, method, body });

    if (url.includes('/schemas/employee')) {
      return jsonResponse([]);
    }
    if (url.includes('/employees/available-users')) {
      return jsonResponse([]);
    }
    if (url.includes('/leave/types')) {
      return jsonResponse([]);
    }
    if (url.includes('/leave/requests')) {
      return jsonResponse([]);
    }
    if (url.endsWith('/teams')) {
      return jsonResponse([]);
    }
    if (url.endsWith('/roles')) {
      return jsonResponse(rolesPayload);
    }
    if (url.includes('/assignments?employee_id=')) {
      const params = new URLSearchParams(url.split('?')[1] || '');
      const employeeId = Number(params.get('employee_id'));
      return jsonResponse(assignmentsStore.filter((assignment) => Number(assignment.employee_id) === employeeId));
    }
    if (url.endsWith('/assignments') && method === 'POST') {
      const newAssignment: AssignmentPayload = {
        id: nextAssignmentId++,
        employee_id: Number(body?.employee_id),
        role_id: Number(body?.role_id),
        status: 'active',
        end_date: body?.end_date ?? null,
      };
      assignmentsStore.push(newAssignment);
      return jsonResponse(newAssignment, 201);
    }
    if (url.endsWith('/assignments')) {
      return jsonResponse(assignmentsStore);
    }
    if (url.endsWith('/employees') && method === 'GET') {
      return jsonResponse(employeesPayload);
    }
    return jsonResponse({});
  });

  return { calls };
};

const getEmployeeRowOrder = () => (
  Array.from(document.querySelectorAll('.pet-employee-row-link'))
    .map((node) => node.textContent?.trim() || '')
    .filter(Boolean)
);

describe('Staff setup journey readiness derivation', () => {
  it('derives incomplete, partial, and ready states with direct-report org exception', () => {
    const base = makeEmployee({});
    const topOfStructure = makeEmployee({ managerId: undefined, teamIds: [11] });

    expect(deriveStaffSetupState(makeEmployee({ firstName: '', lastName: '' }), 1).readiness).toBe('incomplete');
    expect(deriveStaffSetupState(makeEmployee({ managerId: undefined, teamIds: [] }), 1).readiness).toBe('incomplete');
    expect(deriveStaffSetupState(base, 0).readiness).toBe('partial');
    expect(deriveStaffSetupState(base, 1).readiness).toBe('ready');

    expect(deriveStaffSetupState(topOfStructure, 0, false).readiness).toBe('incomplete');
    expect(deriveStaffSetupState(topOfStructure, 0, true).readiness).toBe('partial');
    expect(deriveStaffSetupState(topOfStructure, 1, true).readiness).toBe('ready');
  });
});

describe('Employees staff setup journey orchestration', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
      featureFlags: {
        resilience_indicators_enabled: false,
        staff_setup_journey_enabled: true,
      },
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('maintains soft step navigation, focused details sections, progress meter, and setup-focus mode', async () => {
    const employee = makeEmployee({ managerId: 2, teamIds: [11] });
    mockEmployeesFetch([employee], []);

    renderWithToast(<Employees />);
    await screen.findByText('Alex Jones');

    fireEvent.click(screen.getByLabelText('Select row 1'));
    await screen.findByText('Capacity & Utilization');
    await screen.findByText('Leave Requests');

    fireEvent.click(screen.getByRole('button', { name: 'Alex Jones' }));
    await screen.findByText('Staff Setup Journey');

    expect(screen.getByText(/Progress \d\/5/)).toBeInTheDocument();
    expect(screen.getByText(/Required \d\/3/)).toBeInTheDocument();
    expect(screen.queryByText('Capacity & Utilization')).not.toBeInTheDocument();
    expect(screen.queryByText('Leave Requests')).not.toBeInTheDocument();

    expect(screen.getByText('First Name:')).toBeInTheDocument();
    expect(screen.queryByText('Manager:')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Org Placement/i }));
    await screen.findByText('Manager:');
    expect(screen.queryByText('First Name:')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Role Assignment/i }));
    await screen.findByText('Role Assignments');
    fireEvent.click(screen.getByRole('button', { name: /Identity/i }));
    await screen.findByText('First Name:');
  });

  it('supports assigning a role directly in the journey role step', async () => {
    const employee = makeEmployee({ managerId: 2, teamIds: [11] });
    const { calls } = mockEmployeesFetch([employee], []);

    renderWithToast(<Employees />);
    await screen.findByText('Alex Jones');

    fireEvent.click(screen.getByRole('button', { name: 'Alex Jones' }));
    await screen.findByText('Staff Setup Journey');

    fireEvent.click(screen.getByRole('button', { name: /Role Assignment/i }));
    await screen.findByText('Role Assignments');

    fireEvent.click(screen.getByRole('button', { name: 'Assign Role' }));
    await screen.findByText('Assign Role to Person');

    const fixedEmployeeInput = screen.getByDisplayValue('Alex Jones');
    expect(fixedEmployeeInput).toBeDisabled();

    fireEvent.change(screen.getByLabelText('Role'), { target: { value: '7' } });
    const assignmentForm = screen.getByText('Assign Role to Person').closest('form');
    expect(assignmentForm).not.toBeNull();
    fireEvent.submit(assignmentForm!);

    await waitFor(() => {
      expect(
        calls.some((call) => call.url.endsWith('/assignments') && call.method === 'POST')
      ).toBe(true);
    });

    const assignmentPost = calls.find((call) => call.url.endsWith('/assignments') && call.method === 'POST');
    expect(assignmentPost?.body?.employee_id).toBe(employee.id);
    expect(assignmentPost?.body?.role_id).toBe(7);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Cancel Role Assignment' })).not.toBeInTheDocument();
    });
  });

  it('defaults to readiness sorting and supports explicit name sorting', async () => {
    const readyEmployee = makeEmployee({
      id: 1,
      firstName: 'Amy',
      lastName: 'Ready',
      email: 'amy@example.com',
      managerId: 3,
      teamIds: [11],
    });
    const incompleteEmployee = makeEmployee({
      id: 2,
      firstName: 'Zack',
      lastName: 'Incomplete',
      email: 'zack@example.com',
      managerId: undefined,
      teamIds: [],
    });
    mockEmployeesFetch(
      [readyEmployee, incompleteEmployee],
      [{ employee_id: readyEmployee.id, status: 'active' }]
    );

    renderWithToast(<Employees />);
    await screen.findByText('Amy Ready');
    await screen.findByText('Zack Incomplete');

    const readinessOrder = getEmployeeRowOrder();
    expect(readinessOrder.indexOf('Zack Incomplete')).toBeLessThan(readinessOrder.indexOf('Amy Ready'));

    fireEvent.change(screen.getByLabelText('Sort By'), { target: { value: 'name' } });

    await waitFor(() => {
      const nameOrder = getEmployeeRowOrder();
      expect(nameOrder.indexOf('Amy Ready')).toBeLessThan(nameOrder.indexOf('Zack Incomplete'));
    });
  });
});

describe('Employees legacy edit flow regression guard (flag off)', () => {
  beforeEach(() => {
    (window as any).petSettings = {
      apiUrl: '/wp-json/pet/v1',
      nonce: 'test-nonce',
      featureFlags: {
        resilience_indicators_enabled: false,
        staff_setup_journey_enabled: false,
      },
    };
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('keeps existing tab-driven edit form when journey flag is disabled', async () => {
    const employee = makeEmployee({});
    mockEmployeesFetch([employee], []);

    renderWithToast(<Employees />);
    await screen.findByText('Alex Jones');

    fireEvent.click(screen.getByRole('button', { name: 'Alex Jones' }));
    await screen.findByRole('button', { name: 'Roles' });
    expect(screen.queryByText('Staff Setup Journey')).not.toBeInTheDocument();
  });
});
