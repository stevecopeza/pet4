import React, { useState } from 'react';
import { Article, SchemaDefinition } from '../types';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';
import useConversation from '../hooks/useConversation';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

interface ArticleDetailsProps {
  article: Article;
  schema?: SchemaDefinition | null;
  onBack: () => void;
}

const ArticleDetails: React.FC<ArticleDetailsProps> = ({ article, schema, onBack }) => {
  const [isEditing, setIsEditing] = useState(false);
  const { openConversation } = useConversation();
  const [title, setTitle] = useState(article.title);
  const [content, setContent] = useState(article.content);
  const [category, setCategory] = useState(article.category);
  const [status, setStatus] = useState(article.status);
  const [malleableData, setMalleableData] = useState(article.malleableData || {});
  const [saving, setSaving] = useState(false);

  const handleMalleableChange = (key: string, value: any) => {
    setMalleableData(prev => ({
      ...prev,
      [key]: value
    }));
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      const response = await fetch(`${window.petSettings.apiUrl}/articles/${article.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
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
        throw new Error('Failed to update article');
      }

      setIsEditing(false);
      // In a real app we'd refresh the list or the object
    } catch (err) {
      legacyAlert('Failed to update article');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="pet-article-details">
      <div style={{ marginBottom: '20px' }}>
        <button className="button" onClick={onBack}>&larr; Back to Articles</button>
        {!isEditing && (
          <>
            <button className="button" onClick={() => setIsEditing(true)} style={{ marginLeft: '10px' }}>Edit</button>
            <button 
              className="button" 
              onClick={() => openConversation({
                contextType: 'knowledge_article',
                contextId: String(article.id),
                subject: `Article: ${article.title}`,
                subjectKey: `kb:${article.id}`,
              })}
              style={{ marginLeft: '10px' }}
            >
              Discuss
            </button>
          </>
        )}
        {isEditing && (
          <>
            <button className="button button-primary" onClick={handleSave} disabled={saving} style={{ marginLeft: '10px' }}>
              {saving ? 'Saving...' : 'Save Changes'}
            </button>
            <button className="button" onClick={() => setIsEditing(false)} disabled={saving} style={{ marginLeft: '10px' }}>Cancel</button>
          </>
        )}
      </div>


      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '20px' }}>
        <div style={{ width: '100%', paddingRight: '20px' }}>
          {isEditing ? (
            <input 
              type="text" 
              value={title} 
              onChange={(e) => setTitle(e.target.value)} 
              style={{ width: '100%', fontSize: '1.5em', marginBottom: '10px', display: 'block' }}
            />
          ) : (
            <h2 style={{ marginTop: 0 }}>{title}</h2>
          )}
          
          <div style={{ color: '#666', fontSize: '1.1em' }}>
            Category: {isEditing ? (
              <input 
                type="text" 
                value={category} 
                onChange={(e) => setCategory(e.target.value)} 
                style={{ width: '200px' }}
              />
            ) : category} &bull; Updated: {article.updatedAt || article.createdAt}
          </div>
        </div>
        <div>
          {isEditing ? (
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
            </select>
          ) : (
            <span className={`pet-status-badge status-${status}`}>{status}</span>
          )}
        </div>
      </div>

      <div className="pet-box" style={{ background: '#fff', padding: '30px', border: '1px solid #ccd0d4' }}>
        {isEditing ? (
          <textarea 
            value={content} 
            onChange={(e) => setContent(e.target.value)} 
            style={{ width: '100%', minHeight: '400px', fontSize: '1.1em', lineHeight: '1.6' }}
          />
        ) : (
          <div style={{ whiteSpace: 'pre-wrap', lineHeight: '1.6', fontSize: '1.1em' }}>
            {content}
          </div>
        )}
      </div>

      {schema && (
        <div style={{ marginTop: '20px', padding: '20px', background: '#f9f9f9', border: '1px solid #ddd' }}>
          <MalleableFieldsRenderer 
            schema={schema}
            values={malleableData}
            onChange={handleMalleableChange}
            readOnly={!isEditing}
          />
        </div>
      )}
      
      {!isEditing && (
        <div style={{ marginTop: '20px', textAlign: 'right' }}>
          <button className="button button-primary" onClick={() => setIsEditing(true)}>Edit Article</button>
        </div>
      )}
    </div>
  );
};

export default ArticleDetails;
