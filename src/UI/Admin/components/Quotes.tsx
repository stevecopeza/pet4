import React, { useEffect, useState, useRef } from 'react';
import { Quote, Customer } from '../types';
import { DataTable, Column } from './DataTable';
import QuoteForm from './QuoteForm';
import QuoteDetails, { computeQuoteTotals } from './QuoteDetails';
import { computeQuoteHealth } from '../healthCompute';

const Quotes = () => {
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingQuote, setEditingQuote] = useState<Quote | null>(null);
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

  const handleAddSuccess = (savedQuote?: Quote) => {
    setShowAddForm(false);
    setEditingQuote(null);
    
    if (savedQuote && savedQuote.id) {
      setSelectedQuoteId(savedQuote.id);
    } else {
      fetchQuotes();
    }
  };

  const handleEdit = (quote: Quote) => {
    setEditingQuote(quote);
    setShowAddForm(true);
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
    { 
      header: 'ID', 
      key: 'id',
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
            fontWeight: 'bold',
            fontSize: 'inherit'
          }}
        >
          {String(val)}
        </button>
      )
    },
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
    { header: 'Project / Quote Title', key: 'title' },
    { header: 'State', key: 'state' },
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
          onCancel={() => { setShowAddForm(false); setEditingQuote(null); }}
          initialData={editingQuote || undefined}
        />
      ) : (
        <>
          <div className="pet-header-actions" style={{ marginBottom: '20px', display: 'flex', gap: '10px' }}>
            <button 
              onClick={() => { setEditingQuote(null); setShowAddForm(true); }}
              className="button button-primary"
            >
              Start building quote
            </button>
            {selectedIds.length > 0 && (
              <button 
                onClick={handleBulkArchive}
                className="button button-secondary"
                style={{ color: '#b32d2e', borderColor: '#b32d2e' }}
              >
                Archive Selected ({selectedIds.length})
              </button>
            )}
          </div>

          {selectedIds.length > 0 && (
            <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
              <strong>{selectedIds.length} items selected</strong>
              <button className="button button-link-delete" style={{ color: '#a00', borderColor: '#a00' }} onClick={handleBulkArchive}>Archive Selected</button>
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
                <div className="pet-actions">
                  <button 
                    onClick={() => setSelectedQuoteId(quote.id)}
                    className="button button-small"
                    style={{ marginRight: '5px' }}
                  >
                    View
                  </button>
                  <button 
                    onClick={() => handleEdit(quote)}
                    className="button button-small"
                    style={{ marginRight: '5px' }}
                    disabled={quote.state !== 'draft'}
                  >
                    Edit
                  </button>
                  <button 
                    onClick={() => handleArchive(quote.id)}
                    className="button button-small button-link-delete"
                  >
                    Archive
                  </button>
                </div>
              )}
            />
          )}
        </>
      )}
    </div>
  );
};

export default Quotes;
