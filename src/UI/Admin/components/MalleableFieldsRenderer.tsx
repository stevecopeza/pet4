import React from 'react';
import { SchemaDefinition } from '../types';

interface MalleableFieldsRendererProps {
  schema: SchemaDefinition | null;
  values: Record<string, any>;
  onChange: (key: string, value: any) => void;
  readOnly?: boolean;
}

const MalleableFieldsRenderer: React.FC<MalleableFieldsRendererProps> = ({ 
  schema, 
  values, 
  onChange,
  readOnly = false
}) => {
  const fields = schema ? (schema.schema || schema.fields || []) : [];

  if (!schema || fields.length === 0) {
    return null;
  }

  return (
    <div className="pet-malleable-fields">
      <h4 style={{ margin: '20px 0 15px', borderBottom: '1px solid #eee', paddingBottom: '10px' }}>
        Additional Details
      </h4>
      
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '15px' }}>
        {fields.map((field) => (
          <div key={field.key} style={{ marginBottom: '10px' }}>
            <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>
              {field.label}
              {field.required && <span style={{ color: 'red', marginLeft: '3px' }}>*</span>}
            </label>
            
            {field.type === 'textarea' ? (
              <textarea
                value={values[field.key] || ''}
                onChange={(e) => onChange(field.key, e.target.value)}
                disabled={readOnly}
                required={field.required}
                style={{ width: '100%', minHeight: '80px' }}
              />
            ) : field.type === 'select' ? (
              <select
                value={values[field.key] || ''}
                onChange={(e) => onChange(field.key, e.target.value)}
                disabled={readOnly}
                required={field.required}
                style={{ width: '100%' }}
              >
                <option value="">-- Select --</option>
                {(field.options || []).map((opt) => (
                  <option key={opt} value={opt}>{opt}</option>
                ))}
              </select>
            ) : field.type === 'boolean' ? (
              <select
                value={values[field.key] === true ? 'true' : (values[field.key] === false ? 'false' : '')}
                onChange={(e) => onChange(field.key, e.target.value === 'true')}
                disabled={readOnly}
                required={field.required}
                style={{ width: '100%' }}
              >
                <option value="">-- Select --</option>
                <option value="true">Yes</option>
                <option value="false">No</option>
              </select>
            ) : field.type === 'date' ? (
              <input
                type="date"
                value={values[field.key] || ''}
                onChange={(e) => onChange(field.key, e.target.value)}
                disabled={readOnly}
                required={field.required}
                style={{ width: '100%' }}
              />
            ) : (
              <input
                type={field.type === 'number' ? 'number' : 'text'}
                value={values[field.key] || ''}
                onChange={(e) => onChange(field.key, e.target.value)}
                disabled={readOnly}
                required={field.required}
                style={{ width: '100%' }}
              />
            )}
          </div>
        ))}
      </div>
    </div>
  );
};

export default MalleableFieldsRenderer;
