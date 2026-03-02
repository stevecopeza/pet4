import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import ConversationPanel from './ConversationPanel';

interface ConversationSummary {
  id: string; // Mapped from uuid
  uuid: string;
  context_type: string;
  context_id: string;
  subject: string;
  state: string;
  created_at: string;
}

const Conversations = () => {
  const [conversations, setConversations] = useState<ConversationSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedConversationId, setSelectedConversationId] = useState<string | null>(null);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (id) {
        setSelectedConversationId(id);
    }

    const handlePopState = () => {
        const params = new URLSearchParams(window.location.search);
        const id = params.get('id');
        setSelectedConversationId(id);
    };

    window.addEventListener('popstate', handlePopState);

    const fetchConversations = async () => {
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

        const response = await fetch(`${apiUrl}/conversations/me?limit=50`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch conversations');
        }

        const data = await response.json();
        const mappedData = data.map((item: any) => ({
            ...item,
            id: item.uuid
        }));
        setConversations(mappedData);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An unknown error occurred');
      } finally {
        setLoading(false);
      }
    };

    fetchConversations();

    return () => {
        window.removeEventListener('popstate', handlePopState);
    };
  }, []);

  const updateUrl = (id: string | null) => {
      const url = new URL(window.location.href);
      if (id) {
          url.searchParams.set('id', id);
      } else {
          url.searchParams.delete('id');
      }
      window.history.pushState({}, '', url.toString());
  };

  const columns: Column<ConversationSummary>[] = [
    { key: 'subject', header: 'Subject', render: (val, item) => (
      <a href={`admin.php?page=pet-conversations&id=${item.uuid}`} onClick={(e) => {
          e.preventDefault();
          setSelectedConversationId(item.uuid);
          updateUrl(item.uuid);
      }} style={{ fontWeight: 'bold', cursor: 'pointer' }}>{val}</a>
    )},
    { key: 'context_type', header: 'Context', render: (val, item) => `${val} #${item.context_id}` },
    { key: 'state', header: 'Status', render: (val) => (
      <span className={`pet-status-badge status-${val}`}>{val}</span>
    )},
    { key: 'created_at', header: 'Created', render: (val) => new Date(val).toLocaleString() },
  ];

  if (selectedConversationId) {
    return (
      <div className="pet-conversations-detail">
        <div style={{ marginBottom: '15px' }}>
            <button 
                className="button" 
                onClick={() => {
                    setSelectedConversationId(null);
                    updateUrl(null);
                }}
            >
                &larr; Back to list
            </button>
        </div>
        <ConversationPanel uuid={selectedConversationId} />
      </div>
    );
  }

  if (loading) return <div>Loading conversations...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-conversations">
      <h2>My Conversations</h2>
      <p>Recent conversations you are involved in.</p>
      
      <DataTable 
        columns={columns} 
        data={conversations} 
        emptyMessage="No conversations found." 
      />
    </div>
  );
};

export default Conversations;
