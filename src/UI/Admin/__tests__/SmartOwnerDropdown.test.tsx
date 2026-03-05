import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SmartOwnerDropdown from '../components/SmartOwnerDropdown';
import type { OwnerOptions } from '../components/SmartOwnerDropdown';
import type { Employee, Team } from '../types';

const employees: Employee[] = [
  { id: 1, wpUserId: 1, firstName: 'Alice', lastName: 'Smith', email: 'a@x.com', status: 'active', createdAt: '', archivedAt: null },
  { id: 2, wpUserId: 2, firstName: 'Bob', lastName: 'Jones', email: 'b@x.com', status: 'active', createdAt: '', archivedAt: null },
  { id: 3, wpUserId: 3, firstName: 'Charlie', lastName: 'Zed', email: 'c@x.com', status: 'archived', createdAt: '', archivedAt: '2024-01-01' },
];

const teams: Team[] = [
  { id: 10, name: 'Alpha', status: 'active', created_at: '' },
  { id: 11, name: 'Beta', status: 'active', created_at: '' },
  { id: 12, name: 'Inactive', status: 'inactive', created_at: '' },
];

const ownerOptions: OwnerOptions = {
  recommended_teams: [{ id: 10, name: 'Alpha', is_primary: true }],
  recommended_employees: [{ id: 1, name: 'Alice Smith' }],
  other_teams: [{ id: 11, name: 'Beta' }],
  other_employees: [{ id: 2, name: 'Bob Jones' }],
};

describe('SmartOwnerDropdown', () => {
  it('renders generic options when no role is set', () => {
    render(
      <SmartOwnerDropdown
        value={{ ownerType: '', ownerId: null, teamId: null }}
        onChange={vi.fn()}
        roleId={null}
        ownerOptions={null}
        employees={employees}
        teams={teams}
      />
    );
    expect(screen.getByText('Select owner or team')).toBeInTheDocument();
    // Active teams shown
    expect(screen.getByText('Alpha')).toBeInTheDocument();
    expect(screen.getByText('Beta')).toBeInTheDocument();
    // Inactive team hidden
    expect(screen.queryByText('Inactive')).not.toBeInTheDocument();
    // Active employees shown, archived hidden
    expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    expect(screen.getByText('Bob Jones')).toBeInTheDocument();
    expect(screen.queryByText('Charlie Zed')).not.toBeInTheDocument();
  });

  it('renders recommended options when role + ownerOptions provided', () => {
    render(
      <SmartOwnerDropdown
        value={{ ownerType: '', ownerId: null, teamId: null }}
        onChange={vi.fn()}
        roleId={1}
        ownerOptions={ownerOptions}
        employees={employees}
        teams={teams}
      />
    );
    // Recommended sections should be present (optgroup labels aren't directly rendered as text,
    // but the options inside them are)
    const options = screen.getAllByRole('option');
    const optionTexts = options.map((o) => o.textContent?.trim());
    expect(optionTexts).toContain('Alpha ★');
    expect(optionTexts).toContain('Alice Smith');
    expect(optionTexts).toContain('Beta');
    expect(optionTexts).toContain('Bob Jones');
  });

  it('calls onChange with employee when employee selected', async () => {
    const onChange = vi.fn();
    render(
      <SmartOwnerDropdown
        value={{ ownerType: '', ownerId: null, teamId: null }}
        onChange={onChange}
        roleId={null}
        ownerOptions={null}
        employees={employees}
        teams={teams}
      />
    );
    await userEvent.selectOptions(screen.getByRole('combobox'), 'employee:1');
    expect(onChange).toHaveBeenCalledWith(
      { ownerType: 'employee', ownerId: 1, teamId: null },
      'Alice Smith'
    );
  });

  it('calls onChange with team when team selected', async () => {
    const onChange = vi.fn();
    render(
      <SmartOwnerDropdown
        value={{ ownerType: '', ownerId: null, teamId: null }}
        onChange={onChange}
        roleId={null}
        ownerOptions={null}
        employees={employees}
        teams={teams}
      />
    );
    await userEvent.selectOptions(screen.getByRole('combobox'), 'team:10');
    expect(onChange).toHaveBeenCalledWith(
      { ownerType: 'team', ownerId: null, teamId: 10 },
      'Alpha'
    );
  });

  it('calls onChange with empty values when cleared', async () => {
    const onChange = vi.fn();
    render(
      <SmartOwnerDropdown
        value={{ ownerType: 'employee', ownerId: 1, teamId: null }}
        onChange={onChange}
        roleId={null}
        ownerOptions={null}
        employees={employees}
        teams={teams}
      />
    );
    await userEvent.selectOptions(screen.getByRole('combobox'), '');
    expect(onChange).toHaveBeenCalledWith(
      { ownerType: '', ownerId: null, teamId: null },
      ''
    );
  });

  it('reflects current employee value in select', () => {
    render(
      <SmartOwnerDropdown
        value={{ ownerType: 'employee', ownerId: 1, teamId: null }}
        onChange={vi.fn()}
        roleId={null}
        ownerOptions={null}
        employees={employees}
        teams={teams}
      />
    );
    const select = screen.getByRole('combobox') as HTMLSelectElement;
    expect(select.value).toBe('employee:1');
  });

  it('reflects current team value in select', () => {
    render(
      <SmartOwnerDropdown
        value={{ ownerType: 'team', ownerId: null, teamId: 10 }}
        onChange={vi.fn()}
        roleId={null}
        ownerOptions={null}
        employees={employees}
        teams={teams}
      />
    );
    const select = screen.getByRole('combobox') as HTMLSelectElement;
    expect(select.value).toBe('team:10');
  });
});
