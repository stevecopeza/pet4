import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import KebabMenu from './KebabMenu';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';
import LoadingState from './foundation/states/LoadingState';
import ErrorState from './foundation/states/ErrorState';
import EmptyState from './foundation/states/EmptyState';

type BillingExportRow = {
  id: number;
  uuid: string;
  customerId: number;
  periodStart: string;
  periodEnd: string;
  status: string;
  createdByEmployeeId: number;
  createdAt: string;
  updatedAt: string;
};

type BillingExportItemRow = {
  id: number;
  exportId: number;
  sourceType: string;
  sourceId: number;
  quantity: number;
  unitPrice: number;
  amount: number;
  description: string;
  qbItemRef?: string | null;
  status: string;
  createdAt: string;
};

type CustomerLite = {
  id: number;
  name: string;
};

type EmployeeLite = {
  id: number;
  firstName: string;
  lastName: string;
};

type TicketLite = {
  id: number;
  customerId: number;
};

type TimeEntryLite = {
  id: number;
  ticketId: number;
  duration: number;
  description: string;
  billable: boolean;
  start: string;
};

const Finance: React.FC = () => {
  const toast = useToast();
  const [exportsData, setExportsData] = useState<BillingExportRow[]>([]);
  const [customers, setCustomers] = useState<CustomerLite[]>([]);
  const [employees, setEmployees] = useState<EmployeeLite[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedExport, setSelectedExport] = useState<BillingExportRow | null>(null);
  const [items, setItems] = useState<BillingExportItemRow[]>([]);
  const [itemsLoading, setItemsLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [creatingExport, setCreatingExport] = useState(false);
  const [newCustomerId, setNewCustomerId] = useState<number>(0);
  const [newPeriodStart, setNewPeriodStart] = useState<string>('');
  const [newPeriodEnd, setNewPeriodEnd] = useState<string>('');
  const [newCreatedByEmployeeId, setNewCreatedByEmployeeId] = useState<number>(0);
  const [lifecycleBusy, setLifecycleBusy] = useState(false);
  const [pendingLifecycleAction, setPendingLifecycleAction] = useState<{ id: number; action: 'queue' | 'confirm' } | null>(null);

  const loadExports = async () => {
    const res = await fetch(`${window.petSettings.apiUrl}/billing/exports`, {
      headers: { 'X-WP-Nonce': window.petSettings.nonce },
    });
    if (!res.ok) throw new Error('Failed to fetch billing exports');
    setExportsData(await res.json());
    setError(null);
  };

  const loadExportItems = async (exportId: number) => {
    const res = await fetch(`${window.petSettings.apiUrl}/billing/exports/${exportId}/items`, {
      headers: { 'X-WP-Nonce': window.petSettings.nonce },
    });
    if (!res.ok) throw new Error('Failed to load items');
    setItems(await res.json());
  };

  const loadReferenceData = async () => {
    const [custRes, empRes] = await Promise.all([
      fetch(`${window.petSettings.apiUrl}/customers`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      }),
      fetch(`${window.petSettings.apiUrl}/employees`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      }),
    ]);

    if (custRes.ok) {
      const custData = await custRes.json();
      setCustomers(custData);
      if (Array.isArray(custData) && custData.length > 0 && newCustomerId === 0) {
        setNewCustomerId(Number(custData[0].id));
      }
    }

    if (empRes.ok) {
      const empData = await empRes.json();
      setEmployees(empData);
      if (Array.isArray(empData) && empData.length > 0 && newCreatedByEmployeeId === 0) {
        setNewCreatedByEmployeeId(Number(empData[0].id));
      }
    }
  };

  useEffect(() => {
    const run = async () => {
      try {
        await Promise.all([loadExports(), loadReferenceData()]);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    };
    run();
  }, []);

  const columns: Column<BillingExportRow>[] = [
    { key: 'id', header: 'ID', render: (val) => String(val) },
    { key: 'status', header: 'Status', render: (val) => <span style={{ textTransform: 'uppercase' }}>{val}</span> },
    { key: 'customerId', header: 'Customer', render: (val) => String(val) },
    { key: 'periodStart', header: 'Start', render: (val) => val },
    { key: 'periodEnd', header: 'End', render: (val) => val },
    { key: 'createdAt', header: 'Created', render: (val) => val },
  ];

  const handleCreateExport = async () => {
    if (!newCustomerId || !newPeriodStart || !newPeriodEnd || !newCreatedByEmployeeId) {
      setActionError('Customer, period dates, and created-by employee are required.');
      return;
    }
    if (newPeriodStart > newPeriodEnd) {
      setActionError('Period start must be before or equal to period end.');
      return;
    }

    setCreatingExport(true);
    setActionError(null);
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/billing/exports`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({
          customerId: newCustomerId,
          periodStart: newPeriodStart,
          periodEnd: newPeriodEnd,
          createdByEmployeeId: newCreatedByEmployeeId,
        }),
      });

      const payload = await response.json().catch(() => null);
      if (!response.ok) {
        throw new Error((payload && (payload.error || payload.message)) || 'Failed to create export');
      }

      await loadExports();
      toast.success('Billing export created.');
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Failed to create export');
      toast.error(e instanceof Error ? e.message : 'Failed to create export');
    } finally {
      setCreatingExport(false);
    }
  };

  const runLifecycleAction = async (rowId: number, action: 'queue' | 'confirm') => {
    setLifecycleBusy(true);
    setActionError(null);
    try {
      const res = await fetch(`${window.petSettings.apiUrl}/billing/exports/${rowId}/${action}`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      });
      const payload = await res.json().catch(() => null);
      if (!res.ok) throw new Error((payload && (payload.error || payload.message)) || `Failed to ${action} export`);
      await loadExports();
      toast.success(action === 'queue' ? 'Export queued.' : 'Export confirmed.');
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Unknown error');
      toast.error(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLifecycleBusy(false);
      setPendingLifecycleAction(null);
    }
  };

  if (loading) return <LoadingState />;
  if (error) return <ErrorState message={error} onRetry={loadExports} />;

  return (
    <div className="pet-card">
      <h2 style={{ marginTop: 0 }}>Billing Exports</h2>

      <div className="pet-card" style={{ marginBottom: '16px', padding: '12px' }}>
        <h3 style={{ marginTop: 0 }}>Create Billing Export</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(170px, 1fr))', gap: '10px' }}>
          <div>
            <label>Customer</label>
            <select value={newCustomerId} onChange={(e) => setNewCustomerId(Number(e.target.value))}>
              <option value={0}>Select customer</option>
              {customers.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label>Period Start</label>
            <input type="date" value={newPeriodStart} onChange={(e) => setNewPeriodStart(e.target.value)} />
          </div>
          <div>
            <label>Period End</label>
            <input type="date" value={newPeriodEnd} onChange={(e) => setNewPeriodEnd(e.target.value)} />
          </div>
          <div>
            <label>Created By (Employee)</label>
            <select value={newCreatedByEmployeeId} onChange={(e) => setNewCreatedByEmployeeId(Number(e.target.value))}>
              <option value={0}>Select employee</option>
              {employees.map((emp) => (
                <option key={emp.id} value={emp.id}>{emp.firstName} {emp.lastName} (#{emp.id})</option>
              ))}
            </select>
          </div>
        </div>
        <div style={{ marginTop: '10px' }}>
          <button className="button button-primary" disabled={creatingExport} onClick={handleCreateExport}>
            {creatingExport ? 'Creating...' : 'Create Export'}
          </button>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={exportsData}
        loading={loading}
        error={error}
        onRetry={loadExports}
        emptyMessage="No billing exports yet."
        compatibilityMode="wp"
        actions={(row) => (
          <KebabMenu items={[
            {
              type: 'action',
              label: 'View',
              onClick: async () => {
                setSelectedExport(row);
                setItemsLoading(true);
                setActionError(null);
                try {
                  await loadExportItems(row.id);
                } catch (e) {
                  setActionError(e instanceof Error ? e.message : 'Unknown error');
                } finally {
                  setItemsLoading(false);
                }
              },
            },
            {
              type: 'action',
              label: 'Queue',
              onClick: () => setPendingLifecycleAction({ id: row.id, action: 'queue' }),
              disabled: row.status !== 'draft',
              disabledReason: 'Only draft exports can be queued',
            },
            {
              type: 'action',
              label: 'Confirm',
              onClick: () => setPendingLifecycleAction({ id: row.id, action: 'confirm' }),
              disabled: row.status !== 'sent',
              disabledReason: 'Only sent exports can be confirmed',
            },
          ]} />
        )}
      />

      {selectedExport && (
        <div className="pet-card" style={{ marginTop: '20px' }}>
          <h3 style={{ marginTop: 0 }}>Export #{selectedExport.id} Items</h3>
          {itemsLoading ? (
            <LoadingState label="Loading items…" />
          ) : (
            <DataTable
              columns={[
                { key: 'id', header: 'ID', render: (v) => String(v) },
                { key: 'description', header: 'Description' },
                { key: 'quantity', header: 'Qty', render: (v) => String(v) },
                { key: 'unitPrice', header: 'Unit Price', render: (v) => `$${Number(v).toFixed(2)}` },
                { key: 'amount', header: 'Amount', render: (v) => `$${Number(v).toFixed(2)}` },
                { key: 'status', header: 'Status' },
              ]}
              data={items}
              emptyMessage="No items yet."
              compatibilityMode="wp"
            />
          )}

          <div style={{ marginTop: '15px' }}>
            <AddItemForm
              exportId={selectedExport.id}
              onItemAdded={async () => {
                await loadExportItems(selectedExport.id);
                await loadExports();
              }}
            />
          </div>

          <AddFromBillableTimePanel
            exportRow={selectedExport}
            onItemsAdded={async () => {
              await loadExportItems(selectedExport.id);
              await loadExports();
            }}
          />

          <DispatchLog exportId={selectedExport.id} />

          {actionError && <div style={{ color: 'red', marginTop: '10px' }}>Error: {actionError}</div>}
        </div>
      )}

      <div className="pet-card" style={{ marginTop: '20px' }}>
        <h2 style={{ marginTop: 0 }}>QuickBooks Invoices</h2>
        <QuickBooksInvoices />
      </div>

      <div className="pet-card" style={{ marginTop: '20px' }}>
        <h2 style={{ marginTop: 0 }}>QuickBooks Payments</h2>
        <QuickBooksPayments />
      </div>

      <ConfirmationDialog
        open={pendingLifecycleAction !== null}
        title={pendingLifecycleAction?.action === 'queue' ? 'Queue export?' : 'Confirm export?'}
        description={pendingLifecycleAction?.action === 'queue'
          ? 'This action will queue the selected export.'
          : 'This action will confirm the selected export.'}
        confirmLabel={pendingLifecycleAction?.action === 'queue' ? 'Queue' : 'Confirm'}
        busy={lifecycleBusy}
        onCancel={() => setPendingLifecycleAction(null)}
        onConfirm={() => {
          if (pendingLifecycleAction) {
            runLifecycleAction(pendingLifecycleAction.id, pendingLifecycleAction.action);
          }
        }}
      />
    </div>
  );
};

const QuickBooksInvoices: React.FC = () => {
  const [rows, setRows] = useState<Array<any>>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const run = async () => {
      try {
        const res = await fetch(`${window.petSettings.apiUrl}/finance/qb/invoices`, {
          headers: { 'X-WP-Nonce': window.petSettings.nonce },
        });
        if (!res.ok) throw new Error('Failed to fetch invoices');
        setRows(await res.json());
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    };
    run();
  }, []);

  if (loading) return <LoadingState />;
  if (error) return <ErrorState message={error} />;

  return (
    <DataTable
      columns={[
        { key: 'id', header: 'ID', render: (v) => String(v) },
        { key: 'qb_invoice_id', header: 'QB ID' },
        { key: 'doc_number', header: 'Doc' },
        { key: 'status', header: 'Status' },
        { key: 'issue_date', header: 'Issued' },
        { key: 'currency', header: 'Currency' },
        { key: 'total', header: 'Total', render: (v) => `$${Number(v).toFixed(2)}` },
        { key: 'balance', header: 'Balance', render: (v) => `$${Number(v).toFixed(2)}` },
        { key: 'last_synced_at', header: 'Synced' },
      ]}
      data={rows}
      emptyMessage="No invoices."
      compatibilityMode="wp"
    />
  );
};

const QuickBooksPayments: React.FC = () => {
  const [rows, setRows] = useState<Array<any>>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const run = async () => {
      try {
        const res = await fetch(`${window.petSettings.apiUrl}/finance/qb/payments`, {
          headers: { 'X-WP-Nonce': window.petSettings.nonce },
        });
        if (!res.ok) throw new Error('Failed to fetch payments');
        setRows(await res.json());
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    };
    run();
  }, []);

  if (loading) return <LoadingState />;
  if (error) return <ErrorState message={error} />;

  return (
    <DataTable
      columns={[
        { key: 'id', header: 'ID', render: (v) => String(v) },
        { key: 'qb_payment_id', header: 'QB Payment' },
        { key: 'received_date', header: 'Received' },
        { key: 'currency', header: 'Currency' },
        { key: 'amount', header: 'Amount', render: (v) => `$${Number(v).toFixed(2)}` },
        { key: 'last_synced_at', header: 'Synced' },
      ]}
      data={rows}
      emptyMessage="No payments."
      compatibilityMode="wp"
    />
  );
};

export default Finance;

type AddItemFormProps = {
  exportId: number;
  onItemAdded: () => void;
};

const AddItemForm: React.FC<AddItemFormProps> = ({ exportId, onItemAdded }) => {
  const toast = useToast();
  const [sourceType, setSourceType] = useState('time_entry');
  const [sourceId, setSourceId] = useState<number>(0);
  const [quantity, setQuantity] = useState<number>(1);
  const [unitPrice, setUnitPrice] = useState<number>(0);
  const [description, setDescription] = useState<string>('');
  const [qbItemRef, setQbItemRef] = useState<string>('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <div className="pet-card" style={{ padding: '10px' }}>
      <h4 style={{ marginTop: 0 }}>Add Item</h4>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '8px' }}>
        <div>
          <label>Source Type</label>
          <select value={sourceType} onChange={(e) => setSourceType(e.target.value)}>
            <option value="time_entry">Time Entry</option>
            <option value="quote_line">Quote Line</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <label>Source ID</label>
          <input type="number" value={sourceId} onChange={(e) => setSourceId(Number(e.target.value))} />
        </div>
        <div>
          <label>Quantity</label>
          <input type="number" value={quantity} onChange={(e) => setQuantity(Number(e.target.value))} step="0.01" />
        </div>
        <div>
          <label>Unit Price</label>
          <input type="number" value={unitPrice} onChange={(e) => setUnitPrice(Number(e.target.value))} step="0.01" />
        </div>
        <div>
          <label>Description</label>
          <input type="text" value={description} onChange={(e) => setDescription(e.target.value)} />
        </div>
        <div>
          <label>QB Item Ref</label>
          <input type="text" value={qbItemRef} onChange={(e) => setQbItemRef(e.target.value)} />
        </div>
      </div>

      <div style={{ marginTop: '10px' }}>
        <button
          className="button button-primary"
          disabled={saving}
          onClick={async () => {
            setSaving(true);
            setError(null);
            try {
              const res = await fetch(`${window.petSettings.apiUrl}/billing/exports/${exportId}/items`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-WP-Nonce': window.petSettings.nonce,
                },
                body: JSON.stringify({
                  sourceType, sourceId, quantity, unitPrice, description, qbItemRef,
                }),
              });
              const payload = await res.json().catch(() => null);
              if (!res.ok) throw new Error((payload && (payload.error || payload.message)) || 'Failed to add item');
              onItemAdded();
              setSourceId(0);
              setQuantity(1);
              setUnitPrice(0);
              setDescription('');
              setQbItemRef('');
              toast.success('Item added to export.');
            } catch (e) {
              setError(e instanceof Error ? e.message : 'Unknown error');
              toast.error(e instanceof Error ? e.message : 'Unknown error');
            } finally {
              setSaving(false);
            }
          }}
        >
          Add Item
        </button>
        {error && <span style={{ color: 'red', marginLeft: '10px' }}>Error: {error}</span>}
      </div>
    </div>
  );
};

const AddFromBillableTimePanel: React.FC<{
  exportRow: BillingExportRow;
  onItemsAdded: () => void;
}> = ({ exportRow, onItemsAdded }) => {
  const toast = useToast();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [defaultHourlyRate, setDefaultHourlyRate] = useState<number>(150);
  const [rows, setRows] = useState<Array<{
    entry: TimeEntryLite;
    customerId: number | null;
    selected: boolean;
  }>>([]);

  const parseToDateOnly = (value: string): string => {
    if (!value) return '';
    const normalized = value.includes('T') ? value : value.replace(' ', 'T');
    const dt = new Date(normalized);
    if (Number.isNaN(dt.getTime())) {
      return value.slice(0, 10);
    }
    return dt.toISOString().slice(0, 10);
  };

  const loadCandidates = async () => {
    setLoading(true);
    setError(null);
    try {
      const [ticketsRes, timeRes] = await Promise.all([
        fetch(`${window.petSettings.apiUrl}/tickets`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } }),
        fetch(`${window.petSettings.apiUrl}/time-entries`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } }),
      ]);
      if (!ticketsRes.ok || !timeRes.ok) {
        throw new Error('Failed to load tickets/time entries for assisted billing.');
      }

      const tickets: TicketLite[] = await ticketsRes.json();
      const timeEntries: TimeEntryLite[] = await timeRes.json();
      const ticketToCustomer = new Map<number, number>();
      tickets.forEach((t) => ticketToCustomer.set(Number(t.id), Number(t.customerId)));

      const filtered = timeEntries
        .filter((te) => Boolean(te.billable))
        .map((te) => ({
          entry: te,
          customerId: ticketToCustomer.get(Number(te.ticketId)) ?? null,
          selected: false,
        }))
        .filter((row) => row.customerId === Number(exportRow.customerId))
        .filter((row) => {
          const day = parseToDateOnly(row.entry.start);
          return day >= exportRow.periodStart && day <= exportRow.periodEnd;
        })
        .sort((a, b) => Number(a.entry.id) - Number(b.entry.id));

      setRows(filtered);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load billable time candidates.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCandidates();
  }, [exportRow.id]);

  const toggleAll = (checked: boolean) => {
    setRows((prev) => prev.map((r) => ({ ...r, selected: checked })));
  };

  const toggleOne = (id: number, checked: boolean) => {
    setRows((prev) => prev.map((r) => (r.entry.id === id ? { ...r, selected: checked } : r)));
  };

  const selectedCount = rows.filter((r) => r.selected).length;

  const addSelected = async () => {
    const selectedRows = rows.filter((r) => r.selected);
    if (selectedRows.length === 0) {
      setError('Select at least one billable time entry.');
      return;
    }

    setSaving(true);
    setError(null);
    try {
      for (const row of selectedRows) {
        const qtyHours = Number((Number(row.entry.duration || 0) / 60).toFixed(2));
        const description = `Time Entry #${row.entry.id} (Ticket #${row.entry.ticketId}) - ${row.entry.description || 'Billable time'}`;
        const response = await fetch(`${window.petSettings.apiUrl}/billing/exports/${exportRow.id}/items`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.petSettings.nonce,
          },
          body: JSON.stringify({
            sourceType: 'time_entry',
            sourceId: row.entry.id,
            quantity: qtyHours,
            unitPrice: defaultHourlyRate,
            description,
          }),
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok) {
          throw new Error((payload && (payload.error || payload.message)) || `Failed to add time entry #${row.entry.id}.`);
        }
      }
      await onItemsAdded();
      await loadCandidates();
      toast.success(`Added ${selectedRows.length} billable time entr${selectedRows.length === 1 ? 'y' : 'ies'}.`);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed while adding selected entries.');
      toast.error(e instanceof Error ? e.message : 'Failed while adding selected entries.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="pet-card" style={{ marginTop: '15px', padding: '10px' }}>
      <h4 style={{ marginTop: 0 }}>Add from Billable Time</h4>
      <div style={{ display: 'flex', gap: '10px', alignItems: 'center', marginBottom: '10px' }}>
        <label>Default Hourly Rate</label>
        <input
          type="number"
          step="0.01"
          min="0"
          value={defaultHourlyRate}
          onChange={(e) => setDefaultHourlyRate(Number(e.target.value))}
          style={{ width: '140px' }}
        />
        <button className="button" onClick={loadCandidates} disabled={loading}>Refresh candidates</button>
      </div>

      {loading ? (
        <LoadingState label="Loading billable time candidates…" />
      ) : rows.length === 0 ? (
        <EmptyState message="No billable time entries found for this export customer and period." />
      ) : (
        <>
          <div style={{ marginBottom: '8px', display: 'flex', gap: '12px', alignItems: 'center' }}>
            <label>
              <input
                type="checkbox"
                checked={selectedCount > 0 && selectedCount === rows.length}
                onChange={(e) => toggleAll(e.target.checked)}
              /> Select all
            </label>
            <span>{selectedCount} selected of {rows.length}</span>
          </div>
          <DataTable
            columns={[
              {
                key: 'selected',
                header: '',
                render: (_: any, row: any) => (
                  <input
                    type="checkbox"
                    checked={Boolean(row.selected)}
                    onChange={(e) => toggleOne(Number(row.entry.id), e.target.checked)}
                  />
                ),
              },
              { key: 'entry', header: 'Entry ID', render: (_: any, row: any) => String(row.entry.id) },
              { key: 'entry', header: 'Ticket', render: (_: any, row: any) => `#${row.entry.ticketId}` },
              { key: 'entry', header: 'Start', render: (_: any, row: any) => row.entry.start },
              { key: 'entry', header: 'Hours', render: (_: any, row: any) => (Number(row.entry.duration || 0) / 60).toFixed(2) },
              { key: 'entry', header: 'Description', render: (_: any, row: any) => row.entry.description || '-' },
            ]}
            data={rows as any}
            emptyMessage="No billable time candidates."
            compatibilityMode="wp"
          />
        </>
      )}

      <div style={{ marginTop: '10px' }}>
        <button className="button button-primary" onClick={addSelected} disabled={saving || rows.length === 0}>
          {saving ? 'Adding...' : `Add Selected (${selectedCount})`}
        </button>
      </div>

      {error && <div style={{ color: 'red', marginTop: '8px' }}>Error: {error}</div>}
    </div>
  );
};

const DispatchLog: React.FC<{ exportId: number }> = ({ exportId }) => {
  const [rows, setRows] = useState<Array<{
    id: number;
    status: string;
    attemptCount: number;
    nextAttemptAt: string | null;
    lastError: string | null;
    updatedAt: string;
  }>>([]);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    const run = async () => {
      setLoading(true);
      setErr(null);
      try {
        const res = await fetch(`${window.petSettings.apiUrl}/billing/exports/${exportId}/dispatch-log`, {
          headers: { 'X-WP-Nonce': window.petSettings.nonce },
        });
        if (!res.ok) throw new Error('Failed to load dispatch log');
        const data = await res.json();
        setRows(data);
      } catch (e) {
        setErr(e instanceof Error ? e.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    };
    run();
  }, [exportId]);

  return (
    <div className="pet-card" style={{ marginTop: '15px' }}>
      <h4 style={{ marginTop: 0 }}>Dispatch Log</h4>
      {loading ? (
        <LoadingState />
      ) : err ? (
        <ErrorState message={err} />
      ) : rows.length === 0 ? (
        <EmptyState message="No dispatch attempts yet." />
      ) : (
        <DataTable
          columns={[
            { key: 'id', header: 'ID', render: (v) => String(v) },
            { key: 'status', header: 'Status' },
            { key: 'attemptCount', header: 'Attempts', render: (v) => String(v) },
            { key: 'nextAttemptAt', header: 'Next Attempt' },
            { key: 'lastError', header: 'Last Error' },
            { key: 'updatedAt', header: 'Updated' },
          ]}
          data={rows}
          emptyMessage="No attempts recorded."
          compatibilityMode="wp"
        />
      )}
    </div>
  );
};
