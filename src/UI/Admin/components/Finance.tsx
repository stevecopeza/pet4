import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';

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

const Finance: React.FC = () => {
  const [exportsData, setExportsData] = useState<BillingExportRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedExport, setSelectedExport] = useState<BillingExportRow | null>(null);
  const [items, setItems] = useState<BillingExportItemRow[]>([]);
  const [itemsLoading, setItemsLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    const run = async () => {
      try {
        const res = await fetch(`${window.petSettings.apiUrl}/billing/exports`, {
          headers: { 'X-WP-Nonce': window.petSettings.nonce },
        });
        if (!res.ok) throw new Error('Failed to fetch billing exports');
        const data = await res.json();
        setExportsData(data);
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

  if (loading) return <div>Loading...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-card">
      <h2 style={{ marginTop: 0 }}>Billing Exports</h2>
      <DataTable 
        columns={columns} 
        data={exportsData} 
        emptyMessage="No billing exports yet."
        actions={(row) => (
          <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
            <button 
              className="button button-small" 
              onClick={async () => {
                setSelectedExport(row);
                setItemsLoading(true);
                setActionError(null);
                try {
                  const res = await fetch(`${window.petSettings.apiUrl}/billing/exports/${row.id}/items`, {
                    headers: { 'X-WP-Nonce': window.petSettings.nonce },
                  });
                  if (!res.ok) throw new Error('Failed to load items');
                  const data = await res.json();
                  setItems(data);
                } catch (e) {
                  setActionError(e instanceof Error ? e.message : 'Unknown error');
                } finally {
                  setItemsLoading(false);
                }
              }}
            >
              View
            </button>
            <button 
              className="button button-primary button-small" 
              disabled={row.status !== 'draft'}
              onClick={async () => {
                setActionError(null);
                try {
                  const res = await fetch(`${window.petSettings.apiUrl}/billing/exports/${row.id}/queue`, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': window.petSettings.nonce },
                  });
                  if (!res.ok) throw new Error('Failed to queue export');
                  await res.json();
                  setExportsData(exportsData.map(e => e.id === row.id ? { ...e, status: 'queued' } : e));
                } catch (e) {
                  setActionError(e instanceof Error ? e.message : 'Unknown error');
                }
              }}
            >
              Queue
            </button>
          </div>
        )}
      />

      {selectedExport && (
        <div className="pet-card" style={{ marginTop: '20px' }}>
          <h3 style={{ marginTop: 0 }}>Export #{selectedExport.id} Items</h3>
          {itemsLoading ? (
            <div>Loading items...</div>
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
            />
          )}

          <div style={{ marginTop: '15px' }}>
            <AddItemForm 
              exportId={selectedExport.id}
              onItemAdded={async () => {
                const res = await fetch(`${window.petSettings.apiUrl}/billing/exports/${selectedExport.id}/items`, {
                  headers: { 'X-WP-Nonce': window.petSettings.nonce },
                });
                const data = await res.json();
                setItems(data);
              }}
            />
          </div>

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
        const data = await res.json();
        setRows(data);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    };
    run();
  }, []);
  if (loading) return <div>Loading...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
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
        const data = await res.json();
        setRows(data);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    };
    run();
  }, []);
  if (loading) return <div>Loading...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
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
    />
  );
};

export default Finance;

type AddItemFormProps = {
  exportId: number;
  onItemAdded: () => void;
};

const AddItemForm: React.FC<AddItemFormProps> = ({ exportId, onItemAdded }) => {
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
                  'X-WP-Nonce': window.petSettings.nonce 
                },
                body: JSON.stringify({
                  sourceType, sourceId, quantity, unitPrice, description, qbItemRef
                }),
              });
              if (!res.ok) throw new Error('Failed to add item');
              await res.json();
              onItemAdded();
              setSourceId(0);
              setQuantity(1);
              setUnitPrice(0);
              setDescription('');
              setQbItemRef('');
            } catch (e) {
              setError(e instanceof Error ? e.message : 'Unknown error');
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
        <div>Loading...</div>
      ) : err ? (
        <div style={{ color: 'red' }}>Error: {err}</div>
      ) : rows.length === 0 ? (
        <div>No dispatch attempts yet.</div>
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
        />
      )}
    </div>
  );
};
