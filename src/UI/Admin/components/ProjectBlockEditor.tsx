import React, { useState } from 'react';
import { Employee, Team } from '../types';
import { OwnerOptions } from './SmartOwnerDropdown';
import ProjectUnitRow, { UnitDraft, RateCard } from './ProjectUnitRow';
import { generateLocalId } from '../utils/quoteTotals';
import KebabMenu from './KebabMenu';

interface CatalogItem {
  id: number;
  name: string;
  unit_price: number;
  unit_cost: number;
  type: string;
}

interface PhaseDraft {
  id: number | string | null;
  name: string;
  phaseTotalCost?: number | null;
  marginAmount?: number | null;
  marginPercentage?: number | null;
  hasMarginData?: boolean;
  units: UnitDraft[];
}

export interface ProjectDraft {
  description: string;
  phases: PhaseDraft[];
}

interface ProjectBlockEditorProps {
  draft: ProjectDraft;
  onDraftChange: (draft: ProjectDraft) => void;
  roles: { id: number; name: string }[];
  employees: Employee[];
  teams: Team[];
  catalogItems: CatalogItem[];
  ownerOptionsCache: Record<number, OwnerOptions>;
  rateCards: RateCard[];
  onRoleChange: (roleId: number | null) => void;
  onDiscussPhase?: (phaseName: string, phaseId: string | number | null) => void;
  onDiscussUnit?: (unitDescription: string, unitId: string | number | null) => void;
}

const emptyUnit = (): UnitDraft => ({
  id: generateLocalId(),
  title: '',
  description: '',
  catalogItemId: null,
  roleId: null,
  ownerType: '',
  ownerId: null,
  teamId: null,
  owner: '',
  team: '',
  quantity: '' as any,
  unit: 'hours',
  unitPrice: '' as any,
  totalValue: 0,
  price_override: false,
});

const ProjectBlockEditor: React.FC<ProjectBlockEditorProps> = ({
  draft,
  onDraftChange,
  roles,
  employees,
  teams,
  catalogItems,
  ownerOptionsCache,
  rateCards,
  onRoleChange,
  onDiscussPhase,
  onDiscussUnit,
}) => {
  const [editingUnitId, setEditingUnitId] = useState<string | number | null>(null);

  const addPhase = () => {
    const newPhase: PhaseDraft = {
      id: generateLocalId(),
      name: `Phase ${draft.phases.length + 1}`,
      units: [],
    };
    onDraftChange({
      ...draft,
      phases: [...draft.phases, newPhase],
    });
  };

  const deletePhase = (phaseIndex: number) => {
    const next = draft.phases.filter((_, i) => i !== phaseIndex);
    onDraftChange({ ...draft, phases: next });
  };

  const addUnit = (phaseIndex: number) => {
    const phases = draft.phases.map((phase, i) => {
      if (i !== phaseIndex) return phase;
      const unit = emptyUnit();
      setEditingUnitId(unit.id);
      return { ...phase, units: [...phase.units, unit] };
    });
    onDraftChange({ ...draft, phases });
  };

  const updateUnit = (phaseIndex: number, unitIndex: number, updated: UnitDraft) => {
    const phases = draft.phases.map((phase, pi) => {
      if (pi !== phaseIndex) return phase;
      const units = phase.units.map((u, ui) => (ui === unitIndex ? updated : u));
      return { ...phase, units };
    });
    onDraftChange({ ...draft, phases });
    setEditingUnitId(null);
  };

  const deleteUnit = (phaseIndex: number, unitIndex: number) => {
    const phases = draft.phases.map((phase, pi) => {
      if (pi !== phaseIndex) return phase;
      return { ...phase, units: phase.units.filter((_, ui) => ui !== unitIndex) };
    });
    onDraftChange({ ...draft, phases });
    setEditingUnitId(null);
  };

  const movePhase = (phaseIndex: number, direction: -1 | 1) => {
    const target = phaseIndex + direction;
    if (target < 0 || target >= draft.phases.length) return;
    const phases = [...draft.phases];
    [phases[phaseIndex], phases[target]] = [phases[target], phases[phaseIndex]];
    onDraftChange({ ...draft, phases });
  };

  const moveUnit = (phaseIndex: number, unitIndex: number, direction: -1 | 1) => {
    const phases = draft.phases.map((phase, pi) => {
      if (pi !== phaseIndex) return phase;
      const target = unitIndex + direction;
      if (target < 0 || target >= phase.units.length) return phase;
      const units = [...phase.units];
      [units[unitIndex], units[target]] = [units[target], units[unitIndex]];
      return { ...phase, units };
    });
    onDraftChange({ ...draft, phases });
  };

  return (
    <div style={{ padding: '10px 0' }}>

      {/* Phase accordion */}
      {draft.phases.map((phase, phaseIndex) => {
        const phaseId = phase.id ?? phaseIndex;
        const phaseTotal = phase.units.reduce(
          (sum, u) => sum + (Number(u.totalValue) || 0),
          0
        );
        const phaseMarginAmount = typeof (phase as any).marginAmount === 'number' ? (phase as any).marginAmount : null;
        const phaseMarginPercentage = typeof (phase as any).marginPercentage === 'number' ? (phase as any).marginPercentage : null;
        const totalHours = phase.units.reduce(
          (sum, u) => sum + (u.unit === 'hours' ? Number(u.quantity) || 0 : 0),
          0
        );

        return (
          <div
            key={phaseId}
            style={{
              marginBottom: '8px',
              border: '1px solid #ddd',
              borderRadius: '3px',
              background: '#fafafa',
            }}
          >
            {/* Phase header */}
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                padding: '6px 10px',
                background: '#f0f0f0',
                borderBottom: '1px solid #ddd',
              }}
            >
              <input
                type="text"
                value={phase.name}
                onChange={(e) => {
                  const phases = draft.phases.map((p, i) =>
                    i === phaseIndex ? { ...p, name: e.target.value } : p
                  );
                  onDraftChange({ ...draft, phases });
                }}
                style={{
                  border: '1px solid transparent',
                  background: 'transparent',
                  fontWeight: 600,
                  fontSize: '13px',
                  padding: '2px 4px',
                  flex: 1,
                }}
              />
              <span style={{ fontSize: '11px', color: '#666', whiteSpace: 'nowrap' }}>
                {phase.units.length} unit{phase.units.length !== 1 ? 's' : ''}
              </span>
              <button
                type="button"
                title="Move up"
                className="button button-small"
                onClick={() => movePhase(phaseIndex, -1)}
                disabled={phaseIndex === 0}
              >
                ↑
              </button>
              <button
                type="button"
                title="Move down"
                className="button button-small"
                onClick={() => movePhase(phaseIndex, 1)}
                disabled={phaseIndex === draft.phases.length - 1}
              >
                ↓
              </button>
              <button
                type="button"
                title="Delete phase"
                className="button button-small"
                onClick={() => deletePhase(phaseIndex)}
              >
                ✕
              </button>
              <KebabMenu
                items={[
                  ...(onDiscussPhase
                    ? [{ type: 'action' as const, label: 'Discuss', onClick: () => onDiscussPhase(phase.name || `Phase ${phaseIndex + 1}`, phase.id) }]
                    : []),
                  ...(phaseIndex > 0
                    ? [{ type: 'action' as const, label: 'Move Up', onClick: () => movePhase(phaseIndex, -1) }]
                    : []),
                  ...(phaseIndex < draft.phases.length - 1
                    ? [{ type: 'action' as const, label: 'Move Down', onClick: () => movePhase(phaseIndex, 1) }]
                    : []),
                  { type: 'divider' as const },
                  { type: 'action' as const, label: 'Delete Phase', onClick: () => deletePhase(phaseIndex), danger: true },
                ]}
              />
            </div>

            {/* Phase units */}
            <div>
                {phase.units.length > 0 ? (
                  <table style={{ width: '100%', borderCollapse: 'collapse', tableLayout: 'fixed' }}>
                    <colgroup>
                      <col style={{ width: '24%' }} />
                      <col style={{ width: '10%' }} />
                      <col style={{ width: '11%' }} />
                      <col style={{ width: '6%' }} />
                      <col style={{ width: '7%' }} />
                      <col style={{ width: '10%' }} />
                      <col style={{ width: '10%' }} />
                      <col style={{ width: '12%' }} />
                      <col style={{ width: '10%' }} />
                    </colgroup>
                    <tbody>
                      {phase.units.map((unit, unitIndex) => (
                        <ProjectUnitRow
                          key={unit.id ?? unitIndex}
                          unit={unit}
                          isEditing={editingUnitId === unit.id}
                          onEdit={() => setEditingUnitId(unit.id)}
                          onSave={(updated) => updateUnit(phaseIndex, unitIndex, updated)}
                          onCancel={() => setEditingUnitId(null)}
                          onDelete={() => deleteUnit(phaseIndex, unitIndex)}
                          onDiscuss={onDiscussUnit ? () => onDiscussUnit(unit.title || unit.description || `Unit ${unitIndex + 1}`, unit.id) : undefined}
                          onMoveUp={unitIndex > 0 ? () => moveUnit(phaseIndex, unitIndex, -1) : undefined}
                          onMoveDown={unitIndex < phase.units.length - 1 ? () => moveUnit(phaseIndex, unitIndex, 1) : undefined}
                          roles={roles}
                          employees={employees}
                          teams={teams}
                          catalogItems={catalogItems}
                          ownerOptionsCache={ownerOptionsCache}
                          rateCards={rateCards}
                          onRoleChange={onRoleChange}
                        />
                      ))}
                      {/* Phase totals row */}
                      <tr style={{ borderTop: '1px solid #ddd', background: '#f8f8f8', fontSize: '12px', fontWeight: 600 }}>
                        <td colSpan={3} style={{ padding: '6px 10px', paddingLeft: '40px', color: '#555' }}>
                          Phase Total
                        </td>
                        <td style={{ padding: '6px 10px', textAlign: 'right', color: '#555' }}>
                          {totalHours > 0 ? totalHours : ''}
                        </td>
                        <td style={{ padding: '6px 10px', textAlign: 'center', color: '#888', fontSize: '11px' }}>
                          {totalHours > 0 ? 'hours' : ''}
                        </td>
                        <td style={{ padding: '6px 10px' }} />
                        <td style={{ padding: '6px 10px', textAlign: 'right' }}>
                          ${phaseTotal.toFixed(2)}
                        </td>
                        <td style={{ padding: '6px 10px', textAlign: 'right' }}>
                          {phaseMarginAmount !== null ? (
                            <div style={{ display: 'inline-flex', flexDirection: 'column', alignItems: 'flex-end' }}>
                              <span style={{ color: phaseMarginAmount < 0 ? '#b32d2e' : undefined }}>
                                ${phaseMarginAmount.toFixed(2)}
                              </span>
                              {phaseMarginPercentage !== null && (
                                <span style={{ fontSize: '10px', color: '#666' }}>
                                  {phaseMarginPercentage.toFixed(1)}%
                                </span>
                              )}
                            </div>
                          ) : (
                            <span style={{ color: '#999' }}>—</span>
                          )}
                        </td>
                        <td style={{ padding: '6px 10px' }} />
                      </tr>
                    </tbody>
                  </table>
                ) : (
                  <div style={{ padding: '10px', fontSize: '12px', color: '#999', textAlign: 'center' }}>
                    No units. Click "+ Add Unit" to create one.
                  </div>
                )}
                <div style={{ padding: '6px 10px', borderTop: '1px solid #eee' }}>
                  <button
                    type="button"
                    className="button button-small"
                    onClick={() => addUnit(phaseIndex)}
                  >
                    + Add Unit
                  </button>
                </div>
            </div>
          </div>
        );
      })}

      {draft.phases.length === 0 && (
        <div style={{ padding: '20px', textAlign: 'center', color: '#999', fontSize: '13px' }}>
          No phases yet. Click "+ Add Phase" to get started.
        </div>
      )}

      {/* Add Phase at the bottom */}
      <div style={{ padding: '8px 10px', textAlign: 'left' }}>
        <button
          type="button"
          className="button button-secondary button-small"
          onClick={addPhase}
        >
          + Add Phase
        </button>
      </div>
    </div>
  );
};

export default ProjectBlockEditor;

/** Compute a summary string for a project block's phases. */
export const computeProjectSummary = (payload: Record<string, any>): string => {
  const phases = Array.isArray(payload.phases) ? payload.phases : [];
  const unitCount = phases.reduce(
    (sum: number, phase: any) =>
      sum + (Array.isArray(phase.units) ? phase.units.length : 0),
    0
  );
  const totalHours = phases.reduce(
    (sum: number, phase: any) => {
      if (!Array.isArray(phase.units)) return sum;
      return sum + phase.units.reduce(
        (uSum: number, u: any) =>
          uSum + ((u.unit === 'hours' || !u.unit) ? (Number(u.quantity) || 0) : 0),
        0
      );
    },
    0
  );
  const parts = [`${phases.length} phase${phases.length !== 1 ? 's' : ''}`];
  parts.push(`${unitCount} unit${unitCount !== 1 ? 's' : ''}`);
  if (totalHours > 0) {
    parts.push(`${totalHours}h`);
  }
  return parts.join(' · ');
};
