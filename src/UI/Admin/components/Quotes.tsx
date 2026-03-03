import React, { useEffect, useState } from 'react';
import { Quote } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import QuoteForm from './QuoteForm';
import QuoteDetails, { computeQuoteTotals } from './QuoteDetails';
import { computeQuoteHealth } from '../healthCompute';

interface QuotesProps {
  initialQuoteId?: number | null;
  onInitialQuoteConsumed?: () => void;
}

const Quotes: React.FC<QuotesProps> = ({ initialQuoteId, onInitialQuoteConsumed }) => {
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [selectedQuoteId, setSelectedQuoteId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [customers, setCustomers] = useState<Customer[]>([]);

  const fetchSchema = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/schemas/quote?status=active`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        if (Array.isArray(data) && data.length > 0) {
          setActiveSchema(data[0]);
        }
      }
    } catch (err) {
      console.error('Failed to fetch schema', err);
    }
  };

  const fetchQuotes = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/quotes`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch quotes');
      }

      const data = await response.json();
      console.log('Quotes fetch response:', data);
      setQuotes(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const fetchCustomers = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/customers`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch customers');
      }

      const data = await response.json();
      setCustomers(data);
    } catch (err) {
      console.error('Failed to fetch customers for quotes list', err);
    }
  };

  useEffect(() => {
    fetchQuotes();
    fetchSchema();
    fetchCustomers();
  }, []);

  useEffect(() => {
    if (initialQuoteId) {
      setSelectedQuoteId(initialQuoteId);
      if (onInitialQuoteConsumed) {
        onInitialQuoteConsumed();
      }
    }
  }, [initialQuoteId]);

  const handleAddSuccess = (savedQuote?: Quote) => {
    setShowAddForm(false);
    
    if (savedQuote && savedQuote.id) {
      setSelectedQuoteId(savedQuote.id);
    } else {
      fetchQuotes();
    }
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this quote?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/quotes/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive quote');
      }

      fetchQuotes();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} quotes?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/quotes/${id}`, {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': nonce,
          },
        });
      } catch (e) {
        console.error(`Failed to archive ${id}`, e);
      }
    }
    
    setSelectedIds([]);
    fetchQuotes();
  };

  const handleBackFromDetails = () => {
    setSelectedQuoteId(null);
    fetchQuotes();
  };

  const columns: Column<Quote>[] = [
    { header: 'ID', key: 'id' },
    { 
      header: 'Customer', 
      key: 'customerId',
      render: (_: any, item: Quote) => {
        const match = customers.find((c) => c.id === item.customerId);
        if (match) {
          return match.name;
        }
        return item.customerId ? `Customer #${item.customerId}` : '-';
      }
    },
    {
      header: 'Title',
      key: 'title',
      render: (val: any, item: Quote) => (
        <button
          type="button"
          onClick={() => setSelectedQuoteId(item.id)}
          style={{
            background: 'none',
            border: 'none',
            color: '#2271b1',
            cursor: 'pointer',
            padding: 0,
            textAlign: 'left',
            fontWeight: 500,
            fontSize: 'inherit',
          }}
        >
          {String(val) || '(untitled)'}
        </button>
      )
    },
    { header: 'State', key: 'state', render: (val: any) => {
      const state = val as string;
      return <span className={`pet-status-badge status-${state}`}>{state}</span>;
    }},
    { header: 'Version', key: 'version' },
    { header: 'Currency', key: 'currency' },
    { 
      header: 'Total Value', 
      key: 'totalValue',
      render: (val: any, item: Quote) => {
        const hasBlocks = Array.isArray((item as any).blocks) && (item as any).blocks.length > 0;
        const hasSections = Array.isArray((item as any).sections) && (item as any).sections.length > 0;

        if (hasBlocks || hasSections) {
          try {
            const { quoteTotal } = computeQuoteTotals(item);
            return `$${quoteTotal.toFixed(2)}`;
          } catch (e) {
            // Fallback to stored value if computation fails
          }
        }

        if (typeof val === 'number' && val > 0) {
          return `$${Number(val).toFixed(2)}`;
        }

        return '-';
      }
    },
    { 
      header: 'Accepted At', 
      key: 'acceptedAt',
      render: (val: any) => val ? new Date(val).toLocaleDateString() : '-'
    },
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Quote,
      header: field.label,
      render: (_: any, item: Quote) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
  ];

  if (selectedQuoteId) {
    return (
      <QuoteDetails 
        quoteId={selectedQuoteId} 
        onBack={handleBackFromDetails} 
      />
    );
  }

  return (
    <div className="pet-quotes">

      {showAddForm ? (
        <QuoteForm 
          onSuccess={handleAddSuccess} 
          onCancel={() => setShowAddForm(false)}
        />
      ) : (
        <>
          <div className="pet-page-header">
            <h2>Quotes</h2>
            <button
              onClick={() => setShowAddForm(true)}
              className="button button-primary"
            >
              Start building quote
            </button>
          </div>

          {selectedIds.length > 0 && (
            <div className="pet-bulk-bar">
              <strong>{selectedIds.length} selected</strong>
              <button className="button button-small button-link-delete" onClick={handleBulkArchive}>
                Archive Selected
              </button>
            </div>
          )}

          {error && <div className="notice notice-error"><p>{error}</p></div>}
          
          {loading ? (
            <div>Loading quotes...</div>
          ) : (
            <DataTable 
              columns={columns} 
              data={quotes} 
              emptyMessage="No quotes found. Create a new quote to get started." 
              selection={{
                selectedIds,
                onSelectionChange: setSelectedIds
              }}
              rowClassName={(q) => {
                const qa = q as any;
                return computeQuoteHealth({
                  state: q.state,
                  totalValue: q.totalValue,
                  createdAt: qa.createdAt || new Date().toISOString(),
                  updatedAt: qa.updatedAt || null,
                }).className;
              }}
              actions={(quote) => (
                <KebabMenu items={[
                  { type: 'action', label: 'Open', onClick: () => setSelectedQuoteId(quote.id) },
                  { type: 'action', label: 'Archive', onClick: () => handleArchive(quote.id), danger: true },
                ]} />
              )}
            />
          )}
        </>
      )}
    </div>
  );
};

export default Quotes;
