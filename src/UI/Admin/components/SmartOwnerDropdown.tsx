import React from 'react';
import { Employee, Team } from '../types';

export interface OwnerOptions {
  recommended_teams: { id: number; name: string; is_primary?: boolean }[];
  recommended_employees: { id: number; name: string }[];
  other_teams: { id: number; name: string }[];
  other_employees: { id: number; name: string }[];
}

export interface OwnerValue {
  ownerType: '' | 'employee' | 'team';
  ownerId: number | null;
  teamId: number | null;
}

interface SmartOwnerDropdownProps {
  value: OwnerValue;
  onChange: (value: OwnerValue, displayName: string) => void;
  roleId: number | null;
  ownerOptions: OwnerOptions | null;
  employees: Employee[];
  teams: Team[];
  style?: React.CSSProperties;
}

const SmartOwnerDropdown: React.FC<SmartOwnerDropdownProps> = ({
  value,
  onChange,
  roleId,
  ownerOptions,
  employees,
  teams,
  style,
}) => {
  const selectValue =
    value.ownerType === 'employee' && typeof value.ownerId === 'number'
      ? `employee:${value.ownerId}`
      : value.ownerType === 'team' && typeof value.teamId === 'number'
      ? `team:${value.teamId}`
      : '';

  const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const raw = e.target.value;
    if (!raw) {
      onChange({ ownerType: '', ownerId: null, teamId: null }, '');
      return;
    }

    const [kind, idStr] = raw.split(':');
    const id = Number(idStr);

    if (kind === 'employee') {
      const employee = employees.find((emp) => emp.id === id);
      const name = employee ? `${employee.firstName} ${employee.lastName}` : '';
      onChange({ ownerType: 'employee', ownerId: id, teamId: null }, name);
    } else if (kind === 'team') {
      const team = teams.find((t) => t.id === id);
      const name = team ? team.name : '';
      onChange({ ownerType: 'team', ownerId: null, teamId: id }, name);
    }
  };

  const hasRecommendations =
    roleId != null &&
    ownerOptions != null &&
    (ownerOptions.recommended_teams.length > 0 ||
      ownerOptions.recommended_employees.length > 0);

  return (
    <select value={selectValue} onChange={handleChange} style={style}>
      <option value="">Select owner or team</option>
      {hasRecommendations ? (
        <>
          {ownerOptions!.recommended_teams.length > 0 && (
            <optgroup label="Recommended Teams">
              {ownerOptions!.recommended_teams.map((t) => (
                <option key={`rt-${t.id}`} value={`team:${t.id}`}>
                  {t.name}
                  {t.is_primary ? ' ★' : ''}
                </option>
              ))}
            </optgroup>
          )}
          {ownerOptions!.recommended_employees.length > 0 && (
            <optgroup label="Recommended Employees">
              {ownerOptions!.recommended_employees.map((emp) => (
                <option key={`re-${emp.id}`} value={`employee:${emp.id}`}>
                  {emp.name}
                </option>
              ))}
            </optgroup>
          )}
          {ownerOptions!.other_teams.length > 0 && (
            <optgroup label="Other Teams">
              {ownerOptions!.other_teams.map((t) => (
                <option key={`ot-${t.id}`} value={`team:${t.id}`}>
                  {t.name}
                </option>
              ))}
            </optgroup>
          )}
          {ownerOptions!.other_employees.length > 0 && (
            <optgroup label="Other Employees">
              {ownerOptions!.other_employees.map((emp) => (
                <option key={`oe-${emp.id}`} value={`employee:${emp.id}`}>
                  {emp.name}
                </option>
              ))}
            </optgroup>
          )}
        </>
      ) : (
        <>
          {teams
            .filter((t) => t.status === 'active')
            .slice()
            .sort((a, b) => a.name.localeCompare(b.name))
            .map((t) => (
              <option key={`team-${t.id}`} value={`team:${t.id}`}>
                {t.name}
              </option>
            ))}
          <option value="" disabled>
            ────────────────
          </option>
          {employees
            .filter((e) => e.status !== 'archived')
            .slice()
            .sort((a, b) => {
              const aName = `${a.firstName} ${a.lastName}`.toLowerCase();
              const bName = `${b.firstName} ${b.lastName}`.toLowerCase();
              return aName.localeCompare(bName);
            })
            .map((e) => (
              <option key={`employee-${e.id}`} value={`employee:${e.id}`}>
                {e.firstName} {e.lastName}
              </option>
            ))}
        </>
      )}
    </select>
  );
};

export default SmartOwnerDropdown;
