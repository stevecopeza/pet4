import React, { useCallback, useEffect, useState } from 'react';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface Article {
  id: number;
  title: string;
  content: string;
  category: string;
  status: string;
  createdAt: string;
  updatedAt: string;
}

function excerpt(content: string, max = 140): string {
  const stripped = content.replace(/<[^>]+>/g, '').trim();
  return stripped.length > max ? stripped.slice(0, max) + '…' : stripped;
}

const KnowledgeBasePage: React.FC = () => {
  const [articles, setArticles]   = useState<Article[]>([]);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);
  const [search, setSearch]       = useState('');
  const [selected, setSelected]   = useState<Article | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${apiUrl()}/articles`, { headers: hdrs() });
      if (!res.ok) throw new Error(`Failed to load articles (${res.status})`);
      const data: Article[] = await res.json();
      setArticles(data.filter(a => a.status !== 'archived'));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const q = search.toLowerCase().trim();
  const filtered = q
    ? articles.filter(a => a.title.toLowerCase().includes(q) || a.category.toLowerCase().includes(q) || a.content.toLowerCase().includes(q))
    : articles;

  // Group by category
  const groups: Record<string, Article[]> = {};
  for (const a of filtered) {
    const cat = a.category ? (a.category.charAt(0).toUpperCase() + a.category.slice(1)) : 'General';
    if (!groups[cat]) groups[cat] = [];
    groups[cat].push(a);
  }
  const sortedCategories = Object.keys(groups).sort();

  if (selected) {
    return (
      <div style={{ maxWidth: 760, margin: '0 auto', padding: '24px 20px' }}>
        <button
          onClick={() => setSelected(null)}
          style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'none', border: 'none', cursor: 'pointer', fontSize: 14, color: '#64748b', marginBottom: 20, padding: 0 }}
        >
          ← Back to Knowledge Base
        </button>
        <div style={{ fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 6 }}>
          {selected.category}
        </div>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: '0 0 20px' }}>{selected.title}</h1>
        <div
          style={{ fontSize: 15, lineHeight: 1.75, color: '#334155' }}
          dangerouslySetInnerHTML={{ __html: selected.content }}
        />
      </div>
    );
  }

  return (
    <div style={{ maxWidth: 860, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20, gap: 16 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>Knowledge Base</h1>
        <div style={{ fontSize: 13, color: '#64748b' }}>{articles.length} article{articles.length !== 1 ? 's' : ''}</div>
      </div>

      {/* search */}
      <div style={{ marginBottom: 24 }}>
        <input
          type="text"
          placeholder="Search articles…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          style={{ width: '100%', padding: '10px 14px', border: '1px solid #cbd5e1', borderRadius: 8, fontSize: 14, fontFamily: 'inherit', color: '#1e293b', outline: 'none', boxSizing: 'border-box' }}
        />
      </div>

      {error && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 8, padding: '10px 14px', fontSize: 13, marginBottom: 16 }}>{error}</div>
      )}

      {loading && <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b', fontSize: 14 }}>Loading…</div>}

      {!loading && filtered.length === 0 && (
        <div style={{ textAlign: 'center', padding: '60px 0', color: '#94a3b8', fontSize: 14 }}>
          {q ? `No articles matching "${search}".` : 'No articles yet.'}
        </div>
      )}

      {!loading && sortedCategories.map(cat => (
        <div key={cat} style={{ marginBottom: 28 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
            <span style={{ fontSize: 13, fontWeight: 700, color: '#334155', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{cat}</span>
            <span style={{ fontSize: 11, fontWeight: 700, background: '#f1f5f9', color: '#64748b', padding: '1px 8px', borderRadius: 8 }}>{groups[cat].length}</span>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 10 }}>
            {groups[cat].map(article => (
              <button
                key={article.id}
                onClick={() => setSelected(article)}
                style={{
                  background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10, padding: '14px 16px',
                  textAlign: 'left', cursor: 'pointer', transition: 'box-shadow 0.12s',
                  boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
                }}
                onMouseEnter={e => (e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)')}
                onMouseLeave={e => (e.currentTarget.style.boxShadow = '0 1px 3px rgba(0,0,0,0.04)')}
              >
                <div style={{ fontSize: 14, fontWeight: 600, color: '#1e293b', marginBottom: 6 }}>{article.title}</div>
                <div style={{ fontSize: 12, color: '#94a3b8', lineHeight: 1.5 }}>{excerpt(article.content)}</div>
              </button>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
};

export default KnowledgeBasePage;
