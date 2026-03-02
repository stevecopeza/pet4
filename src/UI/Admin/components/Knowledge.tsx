import React, { useEffect, useState } from 'react';
import { Article } from '../types';
import { DataTable, Column } from './DataTable';
import ArticleForm from './ArticleForm';
import ArticleDetails from './ArticleDetails';

const Knowledge = () => {
  const [articles, setArticles] = useState<Article[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingArticle, setEditingArticle] = useState<Article | null>(null);
  const [viewingArticle, setViewingArticle] = useState<Article | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);

  const fetchSchema = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/schemas/article?status=active`, {
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

  const fetchArticles = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/articles`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch articles');
      }

      const data = await response.json();
      setArticles(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchArticles();
    fetchSchema();
  }, []);

  const handleFormSuccess = () => {
    setShowForm(false);
    setEditingArticle(null);
    fetchArticles();
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this article?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/articles/${id}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to archive article');
      }

      fetchArticles();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} articles?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially to avoid overwhelming server
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/articles/${id}`, {
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
    fetchArticles();
  };

  const columns: Column<Article>[] = [
    { key: 'id', header: 'ID' },
    { key: 'title', header: 'Title', render: (val: any) => <strong>{String(val)}</strong> },
    { key: 'category', header: 'Category', render: (val: any) => <span style={{ textTransform: 'capitalize' }}>{String(val)}</span> },
    { key: 'status', header: 'Status', render: (val: any) => <span className={`pet-status-badge status-${val}`}>{String(val)}</span> },
    ...(activeSchema?.fields || activeSchema?.schema || []).map((field: any) => ({
      key: field.key as keyof Article,
      header: field.label,
      render: (_: any, item: Article) => {
        const value = item.malleableData?.[field.key];
        return value !== undefined && value !== null ? String(value) : '-';
      }
    })),
    { key: 'createdAt', header: 'Created' },
    { key: 'updatedAt', header: 'Last Updated', render: (val) => val || '-' },
  ];

  if (viewingArticle) {
    return <ArticleDetails article={viewingArticle} schema={activeSchema} onBack={() => setViewingArticle(null)} />;
  }

  if (loading && !articles.length) return <div>Loading knowledge base...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  const isFormVisible = showForm || !!editingArticle;

  return (
    <div className="pet-knowledge">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2>Knowledge Base</h2>
        {!isFormVisible && (
          <button className="button button-primary" onClick={() => setShowForm(true)}>
            Create New Article
          </button>
        )}
      </div>

      {isFormVisible && (
        <ArticleForm 
          onSuccess={handleFormSuccess} 
          onCancel={() => { setShowForm(false); setEditingArticle(null); }} 
          initialData={editingArticle || undefined}
        />
      )}

      {!isFormVisible && (
        <>
          {selectedIds.length > 0 && (
            <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
              <strong>{selectedIds.length} items selected</strong>
              <button className="button" onClick={handleBulkArchive}>Archive Selected</button>
            </div>
          )}

          <DataTable 
            columns={columns} 
            data={articles} 
            emptyMessage="No articles found." 
            selection={{
              selectedIds,
              onSelectionChange: setSelectedIds
            }}
            actions={(item) => (
              <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
                <button 
                  className="button button-small" 
                  onClick={() => setViewingArticle(item)}
                >
                  View
                </button>
                <button 
                  className="button button-small"
                  onClick={() => setEditingArticle(item)}
                  disabled={item.status === 'archived'}
                >
                  Edit
                </button>
                {item.status !== 'archived' && (
                  <button 
                    className="button button-small button-link-delete"
                    style={{ color: '#a00', borderColor: '#a00' }}
                    onClick={() => handleArchive(item.id)}
                  >
                    Archive
                  </button>
                )}
              </div>
            )}
          />
        </>
      )}
    </div>
  );
};

export default Knowledge;
