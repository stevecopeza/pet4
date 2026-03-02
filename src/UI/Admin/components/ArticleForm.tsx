import React, { useState, useEffect } from 'react';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';
import { SchemaDefinition, Article } from '../types';

interface ArticleFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  initialData?: Article;
}

const ArticleForm: React.FC<ArticleFormProps> = ({ onSuccess, onCancel, initialData }) => {
  const isEditMode = !!initialData;
  const [title, setTitle] = useState(initialData?.title || '');
  const [content, setContent] = useState(initialData?.content || '');
  const [category, setCategory] = useState(initialData?.category || 'general');
  const [status, setStatus] = useState(initialData?.status || 'draft');
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
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

    fetchSchema();
  }, []);

  const handleMalleableChange = (key: string, value: any) => {
    setMalleableData(prev => ({
      ...prev,
      [key]: value
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const url = isEditMode 
        ? `${apiUrl}/articles/${initialData.id}`
        : `${apiUrl}/articles`;
      
      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ 
          title,
          content,
          category,
          status,
          malleableData
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || `Failed to ${isEditMode ? 'update' : 'create'} article`);
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-form-container" style={{ padding: '20px', background: '#f9f9f9', border: '1px solid #ddd', marginBottom: '20px' }}>
      <h3>{isEditMode ? 'Edit Article' : 'Create New Article'}</h3>
      {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Title:</label>
          <input 
            type="text" 
            value={title} 
            onChange={(e) => setTitle(e.target.value)} 
            required 
            style={{ width: '100%', maxWidth: '400px' }}
          />
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Category:</label>
          <select 
            value={category} 
            onChange={(e) => setCategory(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="general">General</option>
            <option value="technical">Technical</option>
            <option value="process">Process</option>
            <option value="troubleshooting">Troubleshooting</option>
          </select>
        </div>

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Status:</label>
          <select 
            value={status} 
            onChange={(e) => setStatus(e.target.value)}
            style={{ width: '100%', maxWidth: '400px' }}
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </select>
        </div>

        {activeSchema && (
          <div style={{ marginBottom: '20px', padding: '15px', background: '#fff', border: '1px solid #eee' }}>
            <h4 style={{ marginTop: 0, marginBottom: '15px' }}>Additional Information</h4>
            <MalleableFieldsRenderer 
              schema={activeSchema}
              values={malleableData}
              onChange={handleMalleableChange}
            />
          </div>
        )}

        <div style={{ marginBottom: '10px' }}>
          <label style={{ display: 'block', marginBottom: '5px' }}>Content:</label>
          <textarea 
            value={content} 
            onChange={(e) => setContent(e.target.value)} 
            required 
            rows={10}
            style={{ width: '100%', maxWidth: '600px' }}
          />
        </div>

        <div style={{ marginTop: '15px' }}>
          <button 
            type="submit" 
            disabled={loading}
            className="button button-primary"
            style={{ marginRight: '10px' }}
          >
            {loading ? 'Saving...' : (isEditMode ? 'Update Article' : 'Create Article')}
          </button>
          <button 
            type="button" 
            onClick={onCancel}
            className="button"
            disabled={loading}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default ArticleForm;
