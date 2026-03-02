import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';

interface DecisionSummary {
  id: string; // Mapped from uuid
  uuid: string;
  decision_type: string;
  conversation_id: string;
  state: string;
  payload: any;
  requested_at: string;
  requester_id: number;
}

const Approvals = () => {
  const [decisions, setDecisions] = useState<DecisionSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchDecisions = async () => {
      try {
        setLoading(true);
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        if (!apiUrl || !nonce) {
          setError('API settings missing');
          return;
        }

        const response = await fetch(`${apiUrl}/decisions/pending`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch pending approvals');
        }

        const data = await response.json();
        const mappedData = data.map((item: any) => ({
            ...item,
            id: item.uuid
        }));
        setDecisions(mappedData);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An unknown error occurred');
      } finally {
        setLoading(false);
      }
    };

    fetchDecisions();
  }, []);

  const handleRespond = async (uuid: string, responseType: 'approved' | 'rejected') => {
    if (!confirm(`Are you sure you want to ${responseType} this request?`)) return;

    try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;

        const res = await fetch(`${apiUrl}/decisions/${uuid}/respond`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({ response: responseType, comment: '' }),
        });

        if (!res.ok) {
            const err = await res.json();
            throw new Error(err.error || 'Failed to respond');
        }

        // Refresh list
        setDecisions(decisions.filter(d => d.uuid !== uuid));

    } catch (err) {
        alert(err instanceof Error ? err.message : 'Error responding');
    }
  };

  const columns: Column<DecisionSummary>[] = [
    { key: 'decision_type', header: 'Type', render: (val) => <span style={{ fontWeight: 'bold' }}>{val}</span> },
    { key: 'payload', header: 'Details', render: (val) => (
        <pre style={{ margin: 0, fontSize: '11px', maxHeight: '100px', overflow: 'auto' }}>
            {JSON.stringify(val, null, 2)}
        </pre>
    )},
    { key: 'requested_at', header: 'Requested', render: (val) => new Date(val).toLocaleString() },
    { key: 'uuid', header: 'Actions', render: (_val, item) => (
        <div style={{ display: 'flex', gap: '5px' }}>
            <button 
                className="button button-small" 
                style={{ borderColor: '#46b450', color: '#46b450' }}
                onClick={() => handleRespond(item.uuid, 'approved')}
            >
                Approve
            </button>
            <button 
                className="button button-small" 
                style={{ borderColor: '#dc3232', color: '#dc3232' }}
                onClick={() => handleRespond(item.uuid, 'rejected')}
            >
                Reject
            </button>
        </div>
    )},
  ];

  if (loading) return <div>Loading approvals...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-approvals">
      <h2>Pending Approvals</h2>
      <p>Decisions requiring your action.</p>
      
      <DataTable 
        columns={columns} 
        data={decisions} 
        emptyMessage="No pending approvals found." 
      />
    </div>
  );
};

export default Approvals;
