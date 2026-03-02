import React, { useEffect, useState } from 'react';
import { SchemaDefinition } from '../types';
import SchemaEditor from './SchemaEditor';

const ENTITY_TYPES = [
  'customer',
  'project',
  'ticket',
  'article',
  'employee',
  'site',
  'contact'
];

const SchemaManagement = () => {
  const [selectedType, setSelectedType] = useState(ENTITY_TYPES[0]);
  const [schema, setSchema] = useState<SchemaDefinition | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mode, setMode] = useState<'view' | 'edit'>('view');

  const fetchSchema = async (type: string) => {
    setLoading(true);
    setError(null);
    setMode('view');
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/schemas/${type}`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch schemas');
      }

      const data: SchemaDefinition[] = await response.json();
      
      // Prioritize Draft, then Active, then Latest
      const draft = data.find(s => s.status === 'draft');
      const active = data.find(s => s.status === 'active');
      const latest = data[0]; // Assuming sorted by ID desc

      const current = draft || active || latest || null;
      setSchema(current);

      // If we found a draft, we can default to edit mode if desired, 
      // but let's stick to 'view' first to show status.
      if (draft) {
        // Optional: Indicate draft available
      }

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
      setSchema(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSchema(selectedType);
  }, [selectedType]);

  const handleCreateDraft = async () => {
    setLoading(true);
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/schemas/draft`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({
          entityType: selectedType,
          cloneFromActive: true // Always try to clone from active
        }),
      });

      if (!response.ok) {
        const errData = await response.json();
        throw new Error(errData.error || 'Failed to create draft');
      }

      const newDraft: SchemaDefinition = await response.json();
      setSchema(newDraft);
      setMode('edit');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Creation failed');
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateSchema = async (updatedSchema: SchemaDefinition) => {
    if (!updatedSchema.id) return;
    
    // Optimistic update
    setSchema(updatedSchema);

    try {
      const response = await fetch(`${window.petSettings.apiUrl}/schemas/${updatedSchema.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({ schema: updatedSchema.schema }),
      });

      if (!response.ok) {
        const errData = await response.json();
        throw new Error(errData.error || 'Failed to update schema');
      }
      
      const saved: SchemaDefinition = await response.json();
      setSchema(saved); // Update with server response
      
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Update failed');
      // Revert optimistic update? fetchSchema(selectedType);
    }
  };

  const handlePublishSchema = async (id: number) => {
    setLoading(true);
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/schemas/${id}/publish`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        const errData = await response.json();
        throw new Error(errData.error || 'Failed to publish');
      }

      const published: SchemaDefinition = await response.json();
      setSchema(published);
      setMode('view'); // Exit edit mode
      alert('Schema published successfully!');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Publish failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-schema-management">
      <div style={{ marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '15px' }}>
        <div>
          <label style={{ marginRight: '10px', fontWeight: 'bold' }}>Entity Type:</label>
          <select 
            value={selectedType} 
            onChange={(e) => setSelectedType(e.target.value)}
            disabled={loading}
            style={{ minWidth: '200px' }}
          >
            {ENTITY_TYPES.map(type => (
              <option key={type} value={type}>{type.charAt(0).toUpperCase() + type.slice(1)}</option>
            ))}
          </select>
        </div>
      </div>

      {loading && <div>Loading...</div>}
      
      {error && (
        <div className="notice notice-error inline" style={{ margin: '0 0 15px 0' }}>
          <p>{error}</p>
        </div>
      )}

      {!loading && (
        <>
          {/* Schema Status Header */}
          <div style={{ 
            marginBottom: '20px', padding: '15px', background: '#fff', 
            border: '1px solid #ccd0d4', borderLeft: `4px solid ${schema?.status === 'active' ? '#46b450' : (schema?.status === 'draft' ? '#ffb900' : '#ccc')}` 
          }}>
            {schema ? (
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <h3 style={{ margin: '0 0 5px 0' }}>
                    {schema.status === 'draft' ? 'Draft Schema' : (schema.status === 'active' ? 'Active Schema' : 'Historical Schema')} 
                    {' '}<span style={{ color: '#666', fontWeight: 'normal' }}>(Version {schema.version})</span>
                  </h3>
                  <div style={{ color: '#666', fontSize: '13px' }}>
                    {schema.publishedAt ? `Published: ${new Date(schema.publishedAt).toLocaleString()}` : 'Not published'}
                  </div>
                </div>
                
                <div>
                  {schema.status === 'active' && (
                    <button className="button button-primary" onClick={handleCreateDraft}>Create New Draft</button>
                  )}
                  {schema.status === 'draft' && mode === 'view' && (
                     <button className="button button-primary" onClick={() => setMode('edit')}>Edit Draft</button>
                  )}
                </div>
              </div>
            ) : (
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>No schema defined for this entity type.</div>
                <button className="button button-primary" onClick={handleCreateDraft}>Create Initial Schema</button>
              </div>
            )}
          </div>

          {/* Editor Area */}
          {schema && schema.status === 'draft' && mode === 'edit' && (
            <SchemaEditor 
              schema={schema}
              onUpdate={handleUpdateSchema}
              onPublish={handlePublishSchema}
            />
          )}
          
          {/* Read-only View (Active or Historical, or Draft in view mode) */}
          {schema && (mode === 'view' || schema.status !== 'draft') && (
             <div className="pet-card">
               <h4>Defined Fields</h4>
               {(!schema.schema || schema.schema.length === 0) ? (
                 <p>No fields defined.</p>
               ) : (
                 <table className="widefat striped">
                   <thead>
                     <tr>
                       <th>Label</th>
                       <th>Key</th>
                       <th>Type</th>
                       <th>Required</th>
                     </tr>
                   </thead>
                   <tbody>
                     {schema.schema.map((field, idx) => (
                       <tr key={idx}>
                         <td>{field.label}</td>
                         <td><code>{field.key}</code></td>
                         <td>{field.type}</td>
                         <td>{field.required ? 'Yes' : 'No'}</td>
                       </tr>
                     ))}
                   </tbody>
                 </table>
               )}
             </div>
          )}
        </>
      )}
    </div>
  );
};

export default SchemaManagement;
