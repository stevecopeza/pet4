import React, { useState, useEffect, useCallback } from 'react';
import { Employee, Team } from '../types';
import RoleBadge from './RoleBadge';
import OwnerBadge from './OwnerBadge';
import PriceCell from './PriceCell';
import SmartOwnerDropdown, { OwnerOptions } from './SmartOwnerDropdown';
import { FieldValidation, ValidationErrors } from './InlineValidation';
import KebabMenu from './KebabMenu';

export interface UnitDraft {
  id: number | string | null;
  title: string;
  description: string;
  catalogItemId: number | null;
  roleId: number | null;
  ownerType: '' | 'employee' | 'team';
  ownerId: number | null;
  teamId: number | null;
  owner: string;
  team: string;
  quantity: number;
  unit: string;
  unitPrice: number;
  unitCost?: number | null;
  totalValue: number;
  totalCost?: number | null;
  marginAmount?: number | null;
  marginPercentage?: number | null;
  hasMarginData?: boolean;
  price_override: boolean;
}

interface CatalogItem {
  id: number;
  name: string;
  unit_price: number;
  unit_cost: number;
  type: string;
}

export interface RateCard {
  id: number;
  role_id: number;
  service_type_id: number;
  sell_rate: number;
  status: string;
}

interface ProjectUnitRowProps {
  unit: UnitDraft;
  isEditing: boolean;
  onEdit: () => void;
  onSave: (unit: UnitDraft) => void;
  onCancel: () => void;
  onDelete: () => void;
  onDiscuss?: () => void;
  onMoveUp?: () => void;
  onMoveDown?: () => void;
  roles: { id: number; name: string }[];
  employees: Employee[];
  teams: Team[];
  catalogItems: CatalogItem[];
  ownerOptionsCache: Record<number, OwnerOptions>;
  rateCards: RateCard[];
  onRoleChange: (roleId: number | null) => void;
}

const UNIT_OPTIONS = ['hours', 'days', 'licenses', 'months'];

const validateUnit = (draft: UnitDraft): ValidationErrors => {
  const errors: ValidationErrors = {};
  if (!draft.title?.trim() && !draft.description?.trim()) {
    errors.title = 'Title or description is required.';
  }
  const qty = Number(draft.quantity);
  if (!Number.isFinite(qty) || qty < 1) {
    errors.quantity = 'Quantity must be at least 1.';
  }
  const price = Number(draft.unitPrice);
  if (!Number.isFinite(price) || price < 0) {
    errors.unitPrice = 'Price cannot be negative.';
  }
  return errors;
};

const ProjectUnitRow: React.FC<ProjectUnitRowProps> = ({
  unit,
  isEditing,
  onEdit,
  onSave,
  onCancel,
  onDelete,
  onDiscuss,
  onMoveUp,
  onMoveDown,
  roles,
  employees,
  teams,
  catalogItems,
  ownerOptionsCache,
  rateCards,
  onRoleChange,
}) => {
  const [draft, setDraft] = useState<UnitDraft>({ ...unit });
  const [errors, setErrors] = useState<ValidationErrors>({});

  useEffect(() => {
    if (isEditing) {
      setDraft({ ...unit });
    }
  }, [isEditing]);

  useEffect(() => {
    if (isEditing) {
      setErrors(validateUnit(draft));
    }
  }, [draft, isEditing]);

  const hasErrors = Object.keys(errors).length > 0;

  // Resolve owner options from cache using the draft's roleId (not the parent's)
  const ownerOptions = draft.roleId ? ownerOptionsCache[draft.roleId] ?? null : null;

  const updateField = useCallback((field: keyof UnitDraft, value: any) => {
    setDraft((prev) => {
      const next = { ...prev, [field]: value };
      // Auto-calculate total
      if (field === 'quantity' || field === 'unitPrice') {
        const qty = Number(field === 'quantity' ? value : next.quantity);
        const price = Number(field === 'unitPrice' ? value : next.unitPrice);
        if (Number.isFinite(qty) && Number.isFinite(price)) {
          next.totalValue = qty * price;
        }
      }
      return next;
    });
  }, []);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter' && !e.shiftKey && !hasErrors) {
        e.preventDefault();
        onSave(draft);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        onCancel();
      }
    },
    [hasErrors, draft, onSave, onCancel]
  );

  // Role name
  const roleName = unit.roleId
    ? roles.find((r) => r.id === unit.roleId)?.name ?? null
    : null;
  const ownerName =
    unit.ownerType === 'employee' ? unit.owner : unit.ownerType === 'team' ? unit.team : null;
  const total = Number(unit.totalValue) || 0;
  const marginAmount = typeof (unit as any).marginAmount === 'number' ? (unit as any).marginAmount : null;
  const marginPercentage = typeof (unit as any).marginPercentage === 'number' ? (unit as any).marginPercentage : null;

  if (!isEditing) {
    return (
      <tr
        style={{ cursor: 'pointer', fontSize: '13px' }}
        onClick={onEdit}
      >
        <td style={{ padding: '6px 10px', paddingLeft: '40px' }}>
          {unit.title || unit.description || <em style={{ color: '#999' }}>Untitled</em>}
        </td>
        <td style={{ padding: '6px 10px' }}>
          <RoleBadge roleName={roleName} />
        </td>
        <td style={{ padding: '6px 10px' }}>
          <OwnerBadge ownerName={ownerName} ownerType={unit.ownerType as 'employee' | 'team' | ''} />
        </td>
        <td style={{ padding: '6px 10px', textAlign: 'right' }}>{unit.quantity}</td>
        <td style={{ padding: '6px 10px', textAlign: 'center', fontSize: '12px', color: '#666' }}>
          {unit.unit || '–'}
        </td>
        <td style={{ padding: '6px 10px', textAlign: 'right' }}>
          <PriceCell amount={unit.unitPrice} isOverride={unit.price_override} />
        </td>
        <td style={{ padding: '6px 10px', textAlign: 'right', fontWeight: 600 }}>
          ${total.toFixed(2)}
        </td>
        <td style={{ padding: '6px 10px', textAlign: 'right' }}>
          {marginAmount !== null ? (
            <div style={{ display: 'inline-flex', flexDirection: 'column', alignItems: 'flex-end' }}>
              <span style={{ fontWeight: 600, color: marginAmount < 0 ? '#b32d2e' : undefined }}>
                ${marginAmount.toFixed(2)}
              </span>
              {marginPercentage !== null && (
                <span style={{ fontSize: '10px', color: '#666' }}>
                  {marginPercentage.toFixed(1)}%
                </span>
              )}
            </div>
          ) : (
            <span style={{ color: '#999' }}>—</span>
          )}
        </td>
        <td style={{ padding: '6px 10px', textAlign: 'right' }} onClick={(e) => e.stopPropagation()}>
          <KebabMenu
            items={[
              { type: 'action', label: 'Edit', onClick: onEdit },
              ...(onDiscuss ? [{ type: 'action' as const, label: 'Discuss', onClick: onDiscuss }] : []),
              ...((onMoveUp || onMoveDown) ? [
                { type: 'divider' as const },
                ...(onMoveUp ? [{ type: 'action' as const, label: 'Move Up', onClick: onMoveUp }] : []),
                ...(onMoveDown ? [{ type: 'action' as const, label: 'Move Down', onClick: onMoveDown }] : []),
              ] : []),
              { type: 'divider' },
              { type: 'action', label: 'Delete', onClick: onDelete, danger: true },
            ]}
          />
        </td>
      </tr>
    );
  }

  // Edit mode
  const editTotal = Number(draft.totalValue) || 0;

  return (
    <tr
      style={{ background: '#f0f7ff', borderLeft: '3px solid #46b450' }}
      onKeyDown={handleKeyDown}
    >
      <td style={{ padding: '6px 10px', paddingLeft: '40px', verticalAlign: 'top' }}>
        <FieldValidation error={errors.title}>
          <input
            type="text"
            value={draft.title || draft.description}
            onChange={(e) => {
              updateField('title', e.target.value);
              updateField('description', e.target.value);
            }}
            placeholder="Unit description"
            style={{ width: '100%', marginBottom: '4px' }}
            autoFocus
          />
        </FieldValidation>
        <select
          value={draft.catalogItemId ?? ''}
          onChange={(e) => {
            const id = e.target.value ? Number(e.target.value) : null;
            updateField('catalogItemId', id);
            if (id !== null) {
              const item = catalogItems.find((c) => c.id === id && c.type === 'service');
              if (item) {
                updateField('title', item.name);
                updateField('description', item.name);
                updateField('unitPrice', item.unit_price);
                updateField('price_override', false);
              }
            }
          }}
          style={{ width: '100%', fontSize: '11px', color: '#666' }}
        >
          <option value="">Catalog…</option>
          {catalogItems
            .filter((item) => item.type === 'service')
            .map((item) => (
              <option key={item.id} value={item.id}>{item.name}</option>
            ))}
        </select>
      </td>
      <td style={{ padding: '6px 10px', verticalAlign: 'top' }}>
        <select
          value={draft.roleId ?? ''}
          onChange={(e) => {
            const roleId = e.target.value ? Number(e.target.value) : null;
            updateField('roleId', roleId);
            onRoleChange(roleId);
            // Prepopulate unit price from rate card (unless manually overridden)
            if (roleId && !draft.price_override) {
              const rc = rateCards.find((r) => r.role_id === roleId);
              if (rc) {
                updateField('unitPrice', rc.sell_rate);
              }
            }
          }}
          style={{ width: '100%' }}
        >
          <option value="">No role</option>
          {roles.map((r) => (
            <option key={r.id} value={r.id}>{r.name}</option>
          ))}
        </select>
      </td>
      <td style={{ padding: '6px 10px', verticalAlign: 'top' }}>
        <SmartOwnerDropdown
          value={{ ownerType: draft.ownerType, ownerId: draft.ownerId, teamId: draft.teamId }}
          onChange={(val, name) => {
            updateField('ownerType', val.ownerType);
            updateField('ownerId', val.ownerId);
            updateField('teamId', val.teamId);
            if (val.ownerType === 'employee') {
              updateField('owner', name);
              updateField('team', '');
            } else if (val.ownerType === 'team') {
              updateField('team', name);
              updateField('owner', '');
            } else {
              updateField('owner', '');
              updateField('team', '');
            }
          }}
          roleId={draft.roleId}
          ownerOptions={ownerOptions}
          employees={employees}
          teams={teams}
          style={{ width: '100%' }}
        />
      </td>
      <td style={{ padding: '6px 10px', textAlign: 'right', verticalAlign: 'top' }}>
        <FieldValidation error={errors.quantity}>
          <input
            type="number"
            min={1}
            value={draft.quantity}
            onChange={(e) => updateField('quantity', e.target.value)}
            style={{ width: '60px', textAlign: 'right' }}
          />
        </FieldValidation>
      </td>
      <td style={{ padding: '6px 10px', textAlign: 'center', verticalAlign: 'top' }}>
        <select
          value={draft.unit || 'hours'}
          onChange={(e) => updateField('unit', e.target.value)}
          style={{ width: '70px', fontSize: '12px' }}
        >
          {UNIT_OPTIONS.map((u) => (
            <option key={u} value={u}>{u}</option>
          ))}
        </select>
      </td>
      <td style={{ padding: '6px 10px', textAlign: 'right', verticalAlign: 'top' }}>
        <FieldValidation error={errors.unitPrice}>
          <input
            type="number"
            min={0}
            step="0.01"
            value={draft.unitPrice}
            onChange={(e) => {
              updateField('unitPrice', e.target.value);
              updateField('price_override', true);
            }}
            style={{ width: '80px', textAlign: 'right' }}
          />
        </FieldValidation>
      </td>
      <td style={{ padding: '6px 10px', textAlign: 'right', fontWeight: 600, verticalAlign: 'top' }}>
        ${editTotal.toFixed(2)}
      </td>
      <td style={{ padding: '6px 10px', textAlign: 'right', verticalAlign: 'top', color: '#999' }}>
        —
      </td>
      <td style={{ padding: '6px 10px', textAlign: 'right', verticalAlign: 'top', whiteSpace: 'nowrap' }}>
        <button
          type="button"
          className="button button-primary button-small"
          onClick={() => onSave(draft)}
          disabled={hasErrors}
          style={{ marginRight: '2px' }}
        >
          ✓
        </button>
        <button
          type="button"
          className="button button-small"
          onClick={onCancel}
        >
          ✕
        </button>
      </td>
    </tr>
  );
};

export default ProjectUnitRow;
