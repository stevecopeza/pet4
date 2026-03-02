import React from 'react';

export interface Column<T> {
  key: keyof T;
  header: string;
  render?: (value: T[keyof T], item: T) => React.ReactNode;
  width?: string | number;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  loading?: boolean;
  emptyMessage?: string;
  selection?: {
    selectedIds: (string | number)[];
    onSelectionChange: (ids: (string | number)[]) => void;
  };
  actions?: (item: T) => React.ReactNode;
  rowDetails?: (item: T) => React.ReactNode;
}

export function DataTable<T extends { id: string | number }>({ 
  columns, 
  data, 
  loading = false, 
  emptyMessage = 'No data found.',
  selection,
  actions,
  rowDetails
}: DataTableProps<T>) {
  const [expandedIds, setExpandedIds] = React.useState<(string | number)[]>([]);

  if (loading) {
    return <div className="pet-data-table-loading">Loading...</div>;
  }

  if (data.length === 0) {
    return <div className="pet-data-table-empty">{emptyMessage}</div>;
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

  return (
    <div className="pet-data-table-container" style={{ overflowX: 'auto' }}>
      <table className="wp-list-table widefat fixed striped" style={{ width: '100%', borderCollapse: 'collapse', tableLayout: 'auto' }}>
        <thead>
          <tr>
            {selection && (
              <td className="manage-column column-cb check-column" style={{ padding: '8px', width: '2.2em' }}>
                <input 
                  type="checkbox" 
                  checked={allSelected} 
                  ref={input => { if (input) input.indeterminate = !!someSelected; }}
                  onChange={handleSelectAll} 
                />
              </td>
            )}
            {columns.map((col) => {
              const headerStyle: React.CSSProperties = { textAlign: 'left', padding: '8px' };
              if (col.width !== undefined) {
                headerStyle.width = col.width;
              }
              return (
                <th key={String(col.key)} scope="col" style={headerStyle}>
                  {col.header}
                </th>
              );
            })}
            {actions && <th scope="col" style={{ textAlign: 'right', padding: '8px' }}>Actions</th>}
          </tr>
        </thead>
        <tbody>
          {data.map((item) => (
            <React.Fragment key={item.id}>
              <tr
                onClick={rowDetails ? () => toggleRowExpanded(item.id) : undefined}
                style={rowDetails ? { cursor: 'pointer' } : undefined}
              >
                {selection && (
                  <th scope="row" className="check-column" style={{ padding: '8px' }}>
                    <input 
                      type="checkbox" 
                      checked={selection.selectedIds.includes(item.id)} 
                      onChange={(e) => handleSelectRow(item.id, e.target.checked)} 
                      onClick={(e) => e.stopPropagation()}
                    />
                  </th>
                )}
                {columns.map((col) => {
                  const cellStyle: React.CSSProperties = { padding: '8px' };
                  if (col.width !== undefined) {
                    cellStyle.width = col.width;
                  }
                  return (
                    <td key={`${item.id}-${String(col.key)}`} style={cellStyle}>
                      {col.render ? col.render(item[col.key], item) : renderValue(item[col.key])}
                    </td>
                  );
                })}
                {actions && (
                  <td style={{ textAlign: 'right', padding: '8px' }}>
                    {actions(item)}
                  </td>
                )}
              </tr>
              {rowDetails && expandedIds.includes(item.id) && (
                <tr className="pet-data-table-row-details">
                  <td colSpan={columnCount} style={{ padding: '12px 16px', background: '#f9f9f9' }}>
                    {rowDetails(item)}
                  </td>
                </tr>
              )}
            </React.Fragment>
          ))}
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
