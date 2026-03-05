import React, { useState, useEffect, useCallback } from 'react';
import { Employee, Team } from '../types';
import SmartOwnerDropdown, { OwnerOptions, OwnerValue } from './SmartOwnerDropdown';
import { FieldValidation, RowErrorSummary, ServerErrorBanner, validateBlockDraft, ValidationErrors } from './InlineValidation';

interface CatalogItem {
  id: number;
  name: string;
  unit_price: number;
  unit_cost: number;
  type: string;
}

export interface ServiceBlockDraft {
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
  sellValue: number;
  price_override: boolean;
}

interface ServiceBlockEditorProps {
  draft: ServiceBlockDraft;
  onDraftChange: (field: keyof ServiceBlockDraft, value: any) => void;
  onSave: () => void;
  onCancel: () => void;
  saving: boolean;
  serverError: string | null;
  catalogItems: CatalogItem[];
  roles: { id: number; name: string }[];
  employees: Employee[];
  teams: Team[];
  ownerOptions: OwnerOptions | null;
  onRoleChange: (roleId: number | null) => void;
}

const UNIT_OPTIONS = ['hours', 'days', 'licenses', 'months'];

const ServiceBlockEditor: React.FC<ServiceBlockEditorProps> = ({
  draft,
  onDraftChange,
  onSave,
  onCancel,
  saving,
  serverError,
  catalogItems,
  roles,
  employees,
  teams,
  ownerOptions,
  onRoleChange,
}) => {
  const [errors, setErrors] = useState<ValidationErrors>({});

  // Revalidate on draft changes
  useEffect(() => {
    setErrors(validateBlockDraft(draft as any, 'OnceOffSimpleServiceBlock'));
  }, [draft]);

  const hasErrors = Object.keys(errors).length > 0;

  const handleCatalogChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      const id = e.target.value ? Number(e.target.value) : null;
      onDraftChange('catalogItemId', id);
      if (id !== null) {
        const item = catalogItems.find((c) => c.id === id && c.type === 'service');
        if (item) {
          onDraftChange('description', item.name);
          onDraftChange('sellValue', item.unit_price);
          onDraftChange('price_override', false); // Catalog selection resets to derived
        }
      }
    },
    [catalogItems, onDraftChange]
  );

  const handleRoleChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      const roleId = e.target.value ? Number(e.target.value) : null;
      onDraftChange('roleId', roleId);
      onRoleChange(roleId);
    },
    [onDraftChange, onRoleChange]
  );

  const handleOwnerChange = useCallback(
    (value: OwnerValue, displayName: string) => {
      onDraftChange('ownerType', value.ownerType);
      onDraftChange('ownerId', value.ownerId);
      onDraftChange('teamId', value.teamId);
      if (value.ownerType === 'employee') {
        onDraftChange('owner', displayName);
        onDraftChange('team', '');
      } else if (value.ownerType === 'team') {
        onDraftChange('team', displayName);
        onDraftChange('owner', '');
      } else {
        onDraftChange('owner', '');
        onDraftChange('team', '');
      }
    },
    [onDraftChange]
  );

  const handlePriceChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      onDraftChange('sellValue', e.target.value);
      onDraftChange('price_override', true); // Manual edit = override
    },
    [onDraftChange]
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter' && !e.shiftKey && !hasErrors && !saving) {
        e.preventDefault();
        onSave();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        onCancel();
      }
    },
    [hasErrors, saving, onSave, onCancel]
  );

  const total = (() => {
    const qty = Number(draft.quantity);
    const price = Number(draft.sellValue);
    if (Number.isFinite(qty) && Number.isFinite(price)) {
      return qty * price;
    }
    return 0;
  })();

  return (
    <>
      <tr
        style={{
          background: '#f0f7ff',
          borderLeft: '3px solid #46b450',
        }}
        onKeyDown={handleKeyDown}
      >
        {/* Description + Catalog */}
        <td style={{ padding: '8px 10px', verticalAlign: 'top' }}>
          <FieldValidation error={errors.description}>
            <input
              type="text"
              value={draft.description}
              onChange={(e) => onDraftChange('description', e.target.value)}
              placeholder="Description"
              style={{ width: '100%', marginBottom: '4px' }}
              autoFocus
            />
          </FieldValidation>
          <select
            value={draft.catalogItemId ?? ''}
            onChange={handleCatalogChange}
            style={{ width: '100%', fontSize: '11px', color: '#666' }}
          >
            <option value="">Catalog: Select service…</option>
            {catalogItems
              .filter((item) => item.type === 'service')
              .map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
          </select>
        </td>

        {/* Role */}
        <td style={{ padding: '8px 10px', verticalAlign: 'top' }}>
          <select
            value={draft.roleId ?? ''}
            onChange={handleRoleChange}
            style={{ width: '100%' }}
          >
            <option value="">No role</option>
            {roles.map((r) => (
              <option key={r.id} value={r.id}>
                {r.name}
              </option>
            ))}
          </select>
        </td>

        {/* Owner/Team */}
        <td style={{ padding: '8px 10px', verticalAlign: 'top' }}>
          <SmartOwnerDropdown
            value={{
              ownerType: draft.ownerType,
              ownerId: draft.ownerId,
              teamId: draft.teamId,
            }}
            onChange={handleOwnerChange}
            roleId={draft.roleId}
            ownerOptions={ownerOptions}
            employees={employees}
            teams={teams}
            style={{ width: '100%' }}
          />
        </td>

        {/* Qty */}
        <td style={{ padding: '8px 10px', textAlign: 'right', verticalAlign: 'top' }}>
          <FieldValidation error={errors.quantity}>
            <input
              type="number"
              min={1}
              value={draft.quantity}
              onChange={(e) => onDraftChange('quantity', e.target.value)}
              style={{ width: '70px', textAlign: 'right' }}
            />
          </FieldValidation>
        </td>

        {/* Unit */}
        <td style={{ padding: '8px 10px', textAlign: 'center', verticalAlign: 'top' }}>
          <select
            value={draft.unit || 'hours'}
            onChange={(e) => onDraftChange('unit', e.target.value)}
            style={{ width: '80px', fontSize: '12px' }}
          >
            {UNIT_OPTIONS.map((u) => (
              <option key={u} value={u}>
                {u}
              </option>
            ))}
          </select>
        </td>

        {/* Unit Price */}
        <td style={{ padding: '8px 10px', textAlign: 'right', verticalAlign: 'top' }}>
          <FieldValidation error={errors.price}>
            <div>
              <input
                type="number"
                min={0}
                step="0.01"
                value={draft.sellValue}
                onChange={handlePriceChange}
                style={{
                  width: '90px',
                  textAlign: 'right',
                  background: draft.price_override ? undefined : '#f0faf0',
                }}
              />
              {draft.price_override && (
                <div
                  style={{ fontSize: '10px', color: '#2271b1', cursor: 'pointer', marginTop: '2px' }}
                  onClick={() => {
                    // Reset to derived: re-apply catalog price
                    if (draft.catalogItemId) {
                      const item = catalogItems.find((c) => c.id === draft.catalogItemId);
                      if (item) {
                        onDraftChange('sellValue', item.unit_price);
                        onDraftChange('price_override', false);
                      }
                    }
                  }}
                >
                  Reset to rate card
                </div>
              )}
              {!draft.price_override && (
                <div style={{ fontSize: '10px', color: '#999', marginTop: '2px' }}>auto</div>
              )}
            </div>
          </FieldValidation>
        </td>

        {/* Total (read-only) */}
        <td
          style={{
            padding: '8px 10px',
            textAlign: 'right',
            fontWeight: 600,
            verticalAlign: 'top',
          }}
        >
          ${total.toFixed(2)}
        </td>

        {/* Save / Cancel */}
        <td style={{ padding: '8px 10px', textAlign: 'right', verticalAlign: 'top', whiteSpace: 'nowrap' }}>
          <button
            type="button"
            className="button button-primary"
            onClick={onSave}
            disabled={saving || hasErrors}
            title={hasErrors ? 'Fix errors before saving' : 'Save'}
            style={{ marginRight: '4px', minWidth: '32px' }}
          >
            {saving ? '…' : '✓'}
          </button>
          <button
            type="button"
            className="button"
            onClick={onCancel}
            disabled={saving}
            title="Cancel"
            style={{ minWidth: '32px' }}
          >
            ✕
          </button>
        </td>
      </tr>
      {Object.keys(errors).length > 1 && (
        <tr>
          <td colSpan={8} style={{ padding: 0 }}>
            <RowErrorSummary errors={errors} />
          </td>
        </tr>
      )}
      {serverError && (
        <tr>
          <td colSpan={8} style={{ padding: 0 }}>
            <ServerErrorBanner message={serverError} onRetry={onSave} />
          </td>
        </tr>
      )}
    </>
  );
};

export default ServiceBlockEditor;
