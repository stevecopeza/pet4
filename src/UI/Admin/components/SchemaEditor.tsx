import React, { useState } from 'react';
import { SchemaDefinition } from '../types';

interface SchemaEditorProps {
  schema: SchemaDefinition;
  onUpdate: (updatedSchema: SchemaDefinition) => void;
  onPublish: (id: number) => void;
}

const SchemaEditor: React.FC<SchemaEditorProps> = ({ schema, onUpdate, onPublish }) => {
  const [editingField, setEditingField] = useState<any | null>(null);
  const [isAdding, setIsAdding] = useState(false);
  const [showConfirmPublish, setShowConfirmPublish] = useState(false);

  const handlePublish = () => {
    onPublish(schema.id);
    setShowConfirmPublish(false);
  };

  const fields = schema.schema || [];

  return (
    <div className="pet-schema-editor">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h3>Draft Schema (Version {schema.version})</h3>
        <div style={{ display: 'flex', gap: '10px' }}>
          <button 
            className="button button-primary"
            onClick={() => setShowConfirmPublish(true)}
            style={{ backgroundColor: '#d63638', borderColor: '#d63638' }}
          >
            Publish Schema
          </button>
        </div>
      </div>

      {showConfirmPublish && (
        <div style={{ 
          background: '#fff', border: '1px solid #d63638', borderLeft: '4px solid #d63638', 
          padding: '15px', marginBottom: '20px', boxShadow: '0 1px 1px rgba(0,0,0,0.04)' 
        }}>
          <h4 style={{ margin: '0 0 10px', color: '#d63638' }}>⚠️ Irreversible Action</h4>
          <p>Publishing this schema will make it the <strong>Active</strong> schema for all new records.</p>
          <p>This action cannot be undone. Are you sure?</p>
          <div style={{ marginTop: '15px', display: 'flex', gap: '10px' }}>
            <button className="button button-primary" onClick={handlePublish}>Yes, Publish Now</button>
            <button className="button" onClick={() => setShowConfirmPublish(false)}>Cancel</button>
          </div>
        </div>
      )}

      <div className="pet-card">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
          <h4 style={{ margin: 0 }}>Fields</h4>
          <button className="button" onClick={() => setIsAdding(true)}>Add Field</button>
        </div>

        {fields.length === 0 ? (
          <p style={{ color: '#666', fontStyle: 'italic' }}>No fields defined yet.</p>
        ) : (
          <table className="widefat striped">
            <thead>
              <tr>
                <th>Label</th>
                <th>Key</th>
                <th>Type</th>
                <th>Required</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {fields.map((field, idx) => (
                <tr key={idx}>
                  <td>{field.label}</td>
                  <td><code>{field.key}</code></td>
                  <td>{field.type}</td>
                  <td>{field.required ? 'Yes' : 'No'}</td>
                  <td>
                    <button className="button-link" onClick={() => setEditingField({ ...field, originalIndex: idx })}>Edit</button>
                    {' | '}
                    <button className="button-link delete" style={{ color: '#a00' }} onClick={() => {
                      const newFields = [...fields];
                      newFields.splice(idx, 1);
                      onUpdate({ ...schema, schema: newFields });
                    }}>Remove</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Field Editor Modal (Simplified inline for now) */}
      {(isAdding || editingField) && (
        <FieldForm 
          initialData={editingField}
          onSave={(fieldData) => {
            const newFields = [...fields];
            if (editingField) {
              newFields[editingField.originalIndex] = fieldData;
            } else {
              newFields.push(fieldData);
            }
            onUpdate({ ...schema, schema: newFields });
            setIsAdding(false);
            setEditingField(null);
          }}
          onCancel={() => {
            setIsAdding(false);
            setEditingField(null);
          }}
        />
      )}
    </div>
  );
};

const FieldForm = ({ initialData, onSave, onCancel }: { initialData?: any, onSave: (data: any) => void, onCancel: () => void }) => {
  const [formData, setFormData] = useState(initialData || {
    label: '',
    key: '',
    type: 'text',
    required: false,
    options: []
  });

  const handleChange = (field: string, value: any) => {
    setFormData((prev: any) => {
      const updates: any = { [field]: value };
      // Auto-generate key from label if key is empty or untouched (simple heuristic)
      if (field === 'label' && (!prev.key || prev.key === prev.label.toLowerCase().replace(/[^a-z0-9]/g, '_'))) {
         updates.key = value.toLowerCase().replace(/[^a-z0-9]/g, '_');
      }
      return { ...prev, ...updates };
    });
  };

  return (
    <div style={{
      position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
      background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center',
      zIndex: 10000
    }}>
      <div style={{ background: '#fff', padding: '20px', width: '400px', borderRadius: '4px', boxShadow: '0 4px 12px rgba(0,0,0,0.15)' }}>
        <h3>{initialData ? 'Edit Field' : 'Add New Field'}</h3>
        
        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Label</label>
          <input 
            type="text" 
            value={formData.label} 
            onChange={(e) => handleChange('label', e.target.value)}
            style={{ width: '100%' }}
          />
        </div>

        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Key (Internal Name)</label>
          <input 
            type="text" 
            value={formData.key} 
            onChange={(e) => handleChange('key', e.target.value)}
            style={{ width: '100%' }}
            disabled={!!initialData} // Lock key on edit
          />
        </div>

        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Type</label>
          <select 
            value={formData.type} 
            onChange={(e) => handleChange('type', e.target.value)}
            style={{ width: '100%' }}
            disabled={!!initialData} // Lock type on edit as per docs
          >
            <option value="text">Text</option>
            <option value="textarea">Text Area</option>
            <option value="number">Number</option>
            <option value="boolean">Boolean (Yes/No)</option>
            <option value="date">Date</option>
            <option value="datetime">Date & Time</option>
            <option value="select">Select (Dropdown)</option>
            <option value="multiselect">Multi-Select</option>
            <option value="email">Email</option>
            <option value="url">URL</option>
          </select>
        </div>

        {(formData.type === 'select' || formData.type === 'multiselect') && (
          <div style={{ marginBottom: '15px' }}>
            <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Options (comma separated)</label>
            <input 
              type="text" 
              value={Array.isArray(formData.options) ? formData.options.join(', ') : ''} 
              onChange={(e) => handleChange('options', e.target.value.split(',').map((s: string) => s.trim()).filter(Boolean))}
              style={{ width: '100%' }}
              placeholder="Option 1, Option 2, Option 3"
            />
          </div>
        )}

        <div style={{ marginBottom: '20px' }}>
          <label>
            <input 
              type="checkbox" 
              checked={formData.required} 
              onChange={(e) => handleChange('required', e.target.checked)}
            />
            {' '}Required Field
          </label>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
          <button className="button" onClick={onCancel}>Cancel</button>
          <button className="button button-primary" onClick={() => onSave(formData)}>Save Field</button>
        </div>
      </div>
    </div>
  );
};

export default SchemaEditor;
