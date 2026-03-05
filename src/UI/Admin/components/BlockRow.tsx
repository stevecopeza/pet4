import React from 'react';
import { QuoteBlock } from '../types';
import RoleBadge from './RoleBadge';
import OwnerBadge from './OwnerBadge';
import PriceCell from './PriceCell';
import KebabMenu from './KebabMenu';

/** Icons for block-type prefix in description cell. */
const blockTypeIcons: Record<string, string> = {
  OnceOffSimpleServiceBlock: '🔧',
  OnceOffProjectBlock: '📁',
  HardwareBlock: '📦',
  RepeatHardwareBlock: '🔄',
  RepeatServiceBlock: '🔁',
  PriceAdjustmentBlock: '±',
  TextBlock: '📝',
};

export interface BlockRowCallbacks {
  onEdit: (block: QuoteBlock) => void;
  onDelete: (blockId: number) => void;
  onDiscuss: (block: QuoteBlock) => void;
  onRevert?: (blockId: number) => void;
  onMoveUp?: (blockId: number) => void;
  onMoveDown?: (blockId: number) => void;
}

interface BlockRowProps {
  block: QuoteBlock;
  roles: { id: number; name: string }[];
  callbacks: BlockRowCallbacks;
  hasNotification?: boolean;
  isEditing?: boolean;
  /** For project blocks: phase/unit summary text, e.g. "3 phases · 8 units · 72h" */
  projectSummary?: string;
  /** Toggles the project accordion (disclosure triangle click). */
  onToggleAccordion?: () => void;
  isAccordionOpen?: boolean;
  /** When provided, makes the description cell click-to-edit inline. */
  editableDescription?: { value: string; onChange: (val: string) => void };
}

const BlockRow: React.FC<BlockRowProps> = ({
  block,
  roles,
  callbacks,
  hasNotification = false,
  isEditing = false,
  projectSummary,
  onToggleAccordion,
  isAccordionOpen = false,
  editableDescription,
}) => {
  const [editingName, setEditingName] = React.useState(false);
  const payload = block.payload || {};
  const isProject = block.type === 'OnceOffProjectBlock';
  const isAdjustment = block.type === 'PriceAdjustmentBlock';
  const isText = block.type === 'TextBlock';

  // Description
  let description = '';
  if (isText) {
    const text = typeof payload.text === 'string' ? payload.text : '';
    description = text.length > 80 ? `${text.slice(0, 80)}…` : text;
  } else if (typeof payload.description === 'string') {
    description = payload.description;
  }

  // Role
  const roleId = payload.roleId as number | null | undefined;
  const roleName = roleId
    ? roles.find((r) => r.id === roleId)?.name ?? null
    : null;

  // Owner
  const ownerType = (payload.ownerType as string) || '';
  const ownerName =
    ownerType === 'employee'
      ? (payload.owner as string) || null
      : ownerType === 'team'
      ? (payload.team as string) || null
      : null;

  // Qty / Unit / Price / Total
  const qty =
    typeof payload.quantity === 'number' ? payload.quantity : null;
  const unit = (payload.unit as string) || null;
  const unitPrice =
    typeof payload.sellValue === 'number'
      ? payload.sellValue
      : typeof payload.unitPrice === 'number'
      ? payload.unitPrice
      : null;
  const isOverride = payload.price_override === true;

  let total: number | null = null;
  if (isAdjustment) {
    const rawAmount =
      typeof payload.amount === 'number'
        ? payload.amount
        : typeof payload.amount === 'string'
        ? parseFloat(payload.amount)
        : 0;
    total = Number.isFinite(rawAmount) ? rawAmount : 0;
  } else if (typeof payload.totalValue === 'number') {
    total = payload.totalValue;
  } else if (typeof payload.sellValue === 'number') {
    total = payload.sellValue;
  }

  const icon = blockTypeIcons[block.type] || '';

  return (
    <tr
      style={{
        cursor: 'pointer',
        background: isEditing ? '#f0f7ff' : undefined,
      }}
      tabIndex={0}
      onClick={() => {
        if (!isEditing) callbacks.onEdit(block);
      }}
      onKeyDown={(e) => {
        if (e.key === 'Enter' && !isEditing) {
          e.preventDefault();
          callbacks.onEdit(block);
        }
      }}
    >
      {/* Description */}
      <td style={{ padding: '10px', maxWidth: '300px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
          {isProject && onToggleAccordion && (
            <span
              onClick={(e) => {
                e.stopPropagation();
                onToggleAccordion();
              }}
              style={{ cursor: 'pointer', fontSize: '12px', userSelect: 'none' }}
            >
              {isAccordionOpen ? '▾' : '▸'}
            </span>
          )}
          {icon && (
            <span style={{ fontSize: '14px' }} title={block.type}>
              {icon}
            </span>
          )}
          {editableDescription && editingName ? (
            <input
              type="text"
              value={editableDescription.value}
              onChange={(e) => editableDescription.onChange(e.target.value)}
              onBlur={() => setEditingName(false)}
              onKeyDown={(e) => { if (e.key === 'Enter' || e.key === 'Escape') setEditingName(false); }}
              onClick={(e) => e.stopPropagation()}
              autoFocus
              style={{ fontWeight: 600, fontSize: '13px', border: '1px solid #ccc', padding: '2px 4px', flex: 1 }}
            />
          ) : (
            <span
              onClick={editableDescription ? (e) => { e.stopPropagation(); setEditingName(true); } : undefined}
              style={editableDescription ? { cursor: 'text', fontWeight: 600 } : undefined}
              title={editableDescription ? 'Click to edit name' : undefined}
            >
              {(editableDescription?.value || description) || <em style={{ color: '#999' }}>No description</em>}
            </span>
          )}
        </div>
        {isProject && projectSummary && (
          <div
            style={{
              marginTop: '4px',
              fontSize: '11px',
              color: '#666',
              paddingLeft: '32px',
            }}
          >
            {projectSummary}
          </div>
        )}
      </td>

      {/* Role */}
      <td style={{ padding: '10px' }}>
        {!isText && !isAdjustment && <RoleBadge roleName={roleName} />}
      </td>

      {/* Owner/Team */}
      <td style={{ padding: '10px' }}>
        {!isText && !isAdjustment && (
          <OwnerBadge ownerName={ownerName} ownerType={ownerType as 'employee' | 'team' | ''} />
        )}
      </td>

      {/* Qty */}
      <td style={{ padding: '10px', textAlign: 'right' }}>
        {qty !== null ? qty : '–'}
      </td>

      {/* Unit */}
      <td style={{ padding: '10px', textAlign: 'center', fontSize: '12px', color: '#666' }}>
        {unit || '–'}
      </td>

      {/* Unit Price */}
      <td style={{ padding: '10px', textAlign: 'right' }}>
        {!isText && !isProject && !isAdjustment ? (
          <PriceCell amount={unitPrice} isOverride={isOverride} />
        ) : (
          '–'
        )}
      </td>

      {/* Total */}
      <td style={{ padding: '10px', textAlign: 'right', fontWeight: 600 }}>
        {total !== null ? (
          <PriceCell amount={total} bold />
        ) : (
          '–'
        )}
      </td>

      {/* Actions */}
      <td
        style={{ padding: '10px', textAlign: 'right' }}
        onClick={(e) => e.stopPropagation()}
      >
        <KebabMenu
          hasNotification={hasNotification}
          items={[
            {
              type: 'action',
              label: isEditing ? 'Close' : 'Edit',
              onClick: () => callbacks.onEdit(block),
            },
            {
              type: 'action',
              label: 'Discuss',
              onClick: () => callbacks.onDiscuss(block),
              hasNotification,
            },
            ...((callbacks.onMoveUp || callbacks.onMoveDown)
              ? [
                  { type: 'divider' as const },
                  ...(callbacks.onMoveUp
                    ? [{ type: 'action' as const, label: 'Move Up', onClick: () => callbacks.onMoveUp!(block.id) }]
                    : []),
                  ...(callbacks.onMoveDown
                    ? [{ type: 'action' as const, label: 'Move Down', onClick: () => callbacks.onMoveDown!(block.id) }]
                    : []),
                ]
              : []),
            ...(callbacks.onRevert
              ? [
                  { type: 'divider' as const },
                  {
                    type: 'action' as const,
                    label: 'Revert',
                    onClick: () => callbacks.onRevert!(block.id),
                  },
                ]
              : []),
            { type: 'divider' as const },
            {
              type: 'action' as const,
              label: 'Delete',
              onClick: () => callbacks.onDelete(block.id),
              danger: true,
              disabled: isEditing,
              disabledReason: 'Cannot delete while editing',
            },
          ]}
        />
      </td>
    </tr>
  );
};

export default BlockRow;
