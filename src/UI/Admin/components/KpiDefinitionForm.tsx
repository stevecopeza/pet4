import React, { useState } from 'react';
import { KpiDefinition } from '../types';

interface KpiDefinitionFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  definition?: KpiDefinition | null;
}

const KpiDefinitionForm: React.FC<KpiDefinitionFormProps> = ({ onSuccess, onCancel, definition }) => {
  const [formData, setFormData] = useState({
    name: definition?.name || '',
    description: definition?.description || '',
    default_frequency: definition?.default_frequency || 'monthly',
    unit: definition?.unit || '%',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const url = definition 
        // @ts-ignore
        ? `${window.petSettings.apiUrl}/kpi-definitions/${definition.id}`
        // @ts-ignore
        : `${window.petSettings.apiUrl}/kpi-definitions`;

      const method = definition ? 'PUT' : 'POST';

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify(formData),
      });

      if (!response.ok) {
        throw new Error('Failed to save KPI definition');
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="card" style={{ maxWidth: '600px' }}>
      <h3>{definition ? 'Edit KPI Definition' : 'New KPI Definition'}</h3>
      
      {error && (
        <div className="notice notice-error inline">
          <p>{error}</p>
        </div>
      )}

      <form onSubmit={handleSubmit}>
        <table className="form-table">
          <tbody>
            <tr>
              <th scope="row">
                <label htmlFor="name">Name</label>
              </th>
              <td>
                <input
                  type="text"
                  id="name"
                  className="regular-text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  required
                />
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label htmlFor="description">Description</label>
              </th>
              <td>
                <textarea
                  id="description"
                  className="large-text"
                  rows={3}
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  required
                />
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label htmlFor="unit">Unit</label>
              </th>
              <td>
                <input
                  type="text"
                  id="unit"
                  className="regular-text"
                  value={formData.unit}
                  onChange={(e) => setFormData({ ...formData, unit: e.target.value })}
                  placeholder="%, hours, count, etc."
                />
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label htmlFor="default_frequency">Default Frequency</label>
              </th>
              <td>
                <select
                  id="default_frequency"
                  value={formData.default_frequency}
                  onChange={(e) => setFormData({ ...formData, default_frequency: e.target.value })}
                >
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                  <option value="yearly">Yearly</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>

        <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
          <button 
            type="submit" 
            className="button button-primary"
            disabled={loading}
          >
            {loading ? 'Saving...' : 'Save Definition'}
          </button>
          <button 
            type="button" 
            className="button"
            onClick={onCancel}
            disabled={loading}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default KpiDefinitionForm;
