import React from 'react';
import LoadingState from './foundation/states/LoadingState';
import EmptyState from './foundation/states/EmptyState';
import ErrorState from './foundation/states/ErrorState';

export interface Column<T> {
  id?: string;
  key: keyof T;
  header: string;
  render?: (value: T[keyof T], item: T) => React.ReactNode;
  width?: string | number;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  loading?: boolean;
  error?: string | null;
  onRetry?: () => void;
  emptyMessage?: string;
  compatibilityMode?: 'wp' | 'modern';
  selection?: {
    selectedIds: (string | number)[];
    onSelectionChange: (ids: (string | number)[]) => void;
  };
  actions?: (item: T) => React.ReactNode;
  rowDetails?: (item: T) => React.ReactNode;
  /** Optional callback to add CSS class(es) to each row's <tr> */
  rowClassName?: (item: T) => string;
  /** Optional callback when a row is clicked */
  onRowClick?: (item: T) => void;
}

export function DataTable<T extends { id: string | number }>({
  columns,
  data,
  loading = false,
  error = null,
  onRetry,
  emptyMessage = 'No data found.',
  compatibilityMode = 'wp',
  selection,
  actions,
  rowDetails,
  rowClassName,
  onRowClick
}: DataTableProps<T>) {
  const [expandedIds, setExpandedIds] = React.useState<(string | number)[]>([]);

  if (loading) {
    return <LoadingState />;
  }

  if (error) {
    return <ErrorState message={error} onRetry={onRetry} />;
  }

  if (data.length === 0) {
    return <EmptyState message={emptyMessage} />;
  }

  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (selection) {
      if (e.target.checked) {
        selection.onSelectionChange(data.map(item => item.id));
      } else {
        selection.onSelectionChange([]);
      }
    }
  };

  const handleSelectRow = (id: string | number, checked: boolean) => {
    if (selection) {
      if (checked) {
        selection.onSelectionChange([...selection.selectedIds, id]);
      } else {
        selection.onSelectionChange(selection.selectedIds.filter(sid => sid !== id));
      }
    }
  };

  const allSelected = selection && data.length > 0 && data.every(item => selection.selectedIds.includes(item.id));
  const someSelected = selection && data.some(item => selection.selectedIds.includes(item.id)) && !allSelected;

  const toggleRowExpanded = (id: string | number) => {
    if (!rowDetails) return;
    setExpandedIds(prev =>
      prev.includes(id) ? prev.filter(existingId => existingId !== id) : [...prev, id]
    );
  };

  const columnCount = columns.length + (selection ? 1 : 0) + (actions ? 1 : 0);
  const tableClassName = compatibilityMode === 'wp'
    ? 'wp-list-table widefat fixed striped pet-data-table'
    : 'pet-data-table';

  return (
    <div className="pet-data-table-container">
      <table className={tableClassName} aria-live="polite">
        <thead>
          <tr>
            {selection && (
              <td className="manage-column column-cb check-column pet-data-table-checkbox-header">
                <input 
                  type="checkbox" 
                  checked={allSelected} 
                  ref={input => { if (input) input.indeterminate = !!someSelected; }}
                  onChange={handleSelectAll} 
                  aria-label="Select all rows"
                />
              </td>
            )}
            {columns.map((col) => {
              const columnIdentifier = col.id || String(col.key);
              const headerStyle: React.CSSProperties = { textAlign: 'left', padding: '8px' };
              if (col.width !== undefined) {
                headerStyle.width = col.width;
              }
              return (
                <th key={columnIdentifier} scope="col" style={headerStyle}>
                  {col.header}
                </th>
              );
            })}
            {actions && <th scope="col" className="pet-data-table-actions-header">Actions</th>}
          </tr>
        </thead>
        <tbody>
          {data.map((item) => {
            const isInteractive = Boolean(rowDetails || onRowClick);
            const isExpanded = expandedIds.includes(item.id);
            return (
            <React.Fragment key={item.id}>
              <tr
                className={`${rowClassName ? rowClassName(item) : ''}${isInteractive ? ' pet-data-table-row-interactive' : ''}`.trim() || undefined}
                onClick={
                  rowDetails
                    ? () => toggleRowExpanded(item.id)
                    : onRowClick
                    ? () => onRowClick(item)
                    : undefined
                }
                onKeyDown={isInteractive ? (event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    if (rowDetails) {
                      toggleRowExpanded(item.id);
                    } else if (onRowClick) {
                      onRowClick(item);
                    }
                  }
                } : undefined}
                role={isInteractive ? 'button' : undefined}
                tabIndex={isInteractive ? 0 : undefined}
                aria-expanded={rowDetails ? isExpanded : undefined}
              >
                {selection && (
                  <th scope="row" className="check-column pet-data-table-checkbox-cell">
                    <input 
                      type="checkbox" 
                      checked={selection.selectedIds.includes(item.id)} 
                      onChange={(e) => handleSelectRow(item.id, e.target.checked)} 
                      onClick={(e) => e.stopPropagation()}
                      aria-label={`Select row ${item.id}`}
                    />
                  </th>
                )}
                {columns.map((col) => {
                  const columnIdentifier = col.id || String(col.key);
                  const cellStyle: React.CSSProperties = { padding: '8px' };
                  if (col.width !== undefined) {
                    cellStyle.width = col.width;
                  }
                  return (
                    <td key={`${item.id}-${columnIdentifier}`} style={cellStyle}>
                      {col.render ? col.render(item[col.key], item) : renderValue(item[col.key])}
                    </td>
                  );
                })}
                {actions && (
                  <td className="pet-data-table-actions-cell">
                    {actions(item)}
                  </td>
                )}
              </tr>
              {rowDetails && isExpanded && (
                <tr className="pet-data-table-row-details">
                  <td colSpan={columnCount} className="pet-data-table-row-details-cell">
                    {rowDetails(item)}
                  </td>
                </tr>
              )}
            </React.Fragment>
          )})}
        </tbody>
      </table>
    </div>
  );
}

function renderValue(value: unknown): React.ReactNode {
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return value;
  }
  if (value === null || value === undefined) {
    return '-';
  }
  if (Array.isArray(value)) {
    return `[Array ${value.length}]`;
  }
  if (typeof value === 'object') {
    return '[Object]';
  }
  return String(value);
}
