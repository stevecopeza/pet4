import React, { useEffect, useState } from 'react';
import { Article } from '../types';
import { DataTable, Column } from './DataTable';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import ArticleForm from './ArticleForm';
import ArticleDetails from './ArticleDetails';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';

const Knowledge = () => {
  const [articles, setArticles] = useState<Article[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingArticle, setEditingArticle] = useState<Article | null>(null);
  const [viewingArticle, setViewingArticle] = useState<Article | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [archiveBusy, setArchiveBusy] = useState(false);
  const [pendingArchiveId, setPendingArchiveId] = useState<number | null>(null);
  const [confirmBulkArchive, setConfirmBulkArchive] = useState(false);
  const toast = useToast();

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
    setArchiveBusy(true);

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
      setSelectedIds(prev => prev.filter(sid => sid !== id));
      toast.success('Article archived');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Failed to archive');
    } finally {
      setArchiveBusy(false);
      setPendingArchiveId(null);
    }
  };

  const handleBulkArchive = async () => {
    setArchiveBusy(true);

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    let failedCount = 0;
    try {
      // Process sequentially to avoid overwhelming server
      for (const id of selectedIds) {
        try {
          const response = await fetch(`${apiUrl}/articles/${id}`, {
            method: 'DELETE',
            headers: {
              'X-WP-Nonce': nonce,
            },
          });
          if (!response.ok) {
            failedCount += 1;
          }
        } catch (e) {
          console.error(`Failed to archive ${id}`, e);
          failedCount += 1;
        }
      }
      
      const successCount = selectedIds.length - failedCount;
      setSelectedIds([]);
      fetchArticles();
      if (failedCount > 0) {
        toast.error(`Archived ${successCount} articles; ${failedCount} failed.`);
      } else {
        toast.success(`Archived ${successCount} articles.`);
      }
    } finally {
      setArchiveBusy(false);
      setConfirmBulkArchive(false);
    }
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
              <button className="button" onClick={() => setConfirmBulkArchive(true)}>Archive Selected</button>
            </div>
          )}

          <DataTable 
            columns={columns} 
            data={articles} 
            loading={loading}
            error={error}
            onRetry={fetchArticles}
            emptyMessage="No articles found." 
            compatibilityMode="wp"
            selection={{
              selectedIds,
              onSelectionChange: setSelectedIds
            }}
            actions={(item) => {
              const items: KebabMenuItem[] = [
                { type: 'action', label: 'View', onClick: () => setViewingArticle(item) },
                { type: 'action', label: 'Edit', onClick: () => setEditingArticle(item), disabled: item.status === 'archived', disabledReason: 'Archived articles cannot be edited' },
              ];
              if (item.status !== 'archived') {
                items.push({ type: 'action', label: 'Archive', onClick: () => setPendingArchiveId(item.id), danger: true });
              }
              return <KebabMenu items={items} />;
            }}
          />

          <ConfirmationDialog
            open={pendingArchiveId !== null}
            title="Archive article?"
            description="This action will archive the selected article."
            confirmLabel="Archive"
            busy={archiveBusy}
            onCancel={() => setPendingArchiveId(null)}
            onConfirm={() => {
              if (pendingArchiveId !== null) {
                handleArchive(pendingArchiveId);
              }
            }}
          />

          <ConfirmationDialog
            open={confirmBulkArchive}
            title="Archive selected articles?"
            description={`This action will archive ${selectedIds.length} selected articles.`}
            confirmLabel="Archive selected"
            busy={archiveBusy}
            onCancel={() => setConfirmBulkArchive(false)}
            onConfirm={handleBulkArchive}
          />
        </>
      )}
    </div>
  );
};

export default Knowledge;
