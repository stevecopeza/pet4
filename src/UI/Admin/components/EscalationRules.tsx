import React, { useState, useEffect } from 'react';

interface EscalationRule {
  id: number;
  threshold_percent: number;
  action: string;
  criteria_json: string;
  is_enabled: boolean;
}

const EscalationRules: React.FC = () => {
  const [rules, setRules] = useState<EscalationRule[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [currentRule, setCurrentRule] = useState<Partial<EscalationRule>>({});
  const [saving, setSaving] = useState(false);

  // API Helper
  const apiFetch = async (path: string, options: RequestInit = {}) => {
    const nonce = (window as any).petSettings?.nonce;
    const headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...options.headers,
    };
    const response = await fetch(`${(window as any).petSettings?.apiUrl}${path}`, {
      ...options,
      headers,
    });
    
    if (response.status === 403 || response.status === 404) {
      throw new Error(`API Error: ${response.status} ${response.statusText}`);
    }

    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new Error(data.message || 'An error occurred');
    }
    return response.json();
  };

  const fetchRules = async () => {
    try {
      setLoading(true);
      const data = await apiFetch('/escalation-rules');
      setRules(data);
      setError(null);
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRules();
  }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      // Basic JSON validation
      try {
        JSON.parse(currentRule.criteria_json || '{}');
      } catch (e) {
        throw new Error('Invalid JSON in Criteria');
      }

      if (currentRule.id) {
        await apiFetch(`/escalation-rules/${currentRule.id}`, {
          method: 'PATCH',
          body: JSON.stringify(currentRule),
        });
      } else {
        await apiFetch('/escalation-rules', {
          method: 'POST',
          body: JSON.stringify(currentRule),
        });
      }
      setIsModalOpen(false);
      fetchRules();
    } catch (err: any) {
      alert(err.message);
    } finally {
      setSaving(false);
    }
  };

  const handleToggle = async (rule: EscalationRule) => {
    try {
      await apiFetch(`/escalation-rules/${rule.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ is_enabled: !rule.is_enabled }),
      });
      fetchRules();
    } catch (err: any) {
      alert(err.message);
    }
  };

  const openModal = (rule?: EscalationRule) => {
    if (rule) {
      setCurrentRule({ ...rule });
    } else {
      setCurrentRule({
        threshold_percent: 75,
        action: 'notify_manager',
        criteria_json: '{}',
        is_enabled: true
      });
    }
    setIsModalOpen(true);
  };

  if (loading) return <div className="wrap">Loading...</div>;

  if (error) {
    return (
      <div className="wrap">
        <h1>Escalation Rules</h1>
        <div className="notice notice-error"><p>{error}</p></div>
      </div>
    );
  }

  return (
    <div className="wrap">
      <h1 className="wp-heading-inline">Escalation Rules</h1>
      <button className="page-title-action" onClick={() => openModal()}>Add New</button>
      <hr className="wp-header-end" />

      <table className="wp-list-table widefat fixed striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Threshold %</th>
            <th>Action</th>
            <th>Criteria (JSON)</th>
            <th>Enabled</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {rules.map(rule => (
            <tr key={rule.id}>
              <td>{rule.id}</td>
              <td>{rule.threshold_percent}%</td>
              <td>{rule.action}</td>
              <td><pre style={{ margin: 0, maxWidth: '300px', overflow: 'auto' }}>{rule.criteria_json}</pre></td>
              <td>
                <label>
                  <input 
                    type="checkbox" 
                    checked={rule.is_enabled} 
                    onChange={() => handleToggle(rule)}
                  />
                  {rule.is_enabled ? ' Active' : ' Disabled'}
                </label>
              </td>
              <td>
                <button className="button button-small" onClick={() => openModal(rule)}>Edit</button>
              </td>
            </tr>
          ))}
          {rules.length === 0 && (
            <tr><td colSpan={6}>No escalation rules found.</td></tr>
          )}
        </tbody>
      </table>

      {isModalOpen && (
        <div className="pet-modal-overlay" style={{
          position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
          background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999
        }}>
          <div className="pet-modal-content" style={{
            background: '#fff', padding: '20px', borderRadius: '4px', width: '500px', maxWidth: '90%'
          }}>
            <h2>{currentRule.id ? 'Edit Rule' : 'New Rule'}</h2>
            <form onSubmit={handleSave}>
              <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px' }}>Threshold %</label>
                <input 
                  type="number" 
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={currentRule.threshold_percent} 
                  onChange={e => setCurrentRule({...currentRule, threshold_percent: parseInt(e.target.value)})}
                  required 
                  min="0" max="100"
                />
              </div>
              <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px' }}>Action</label>
                <input 
                  type="text" 
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={currentRule.action} 
                  onChange={e => setCurrentRule({...currentRule, action: e.target.value})}
                  required 
                />
              </div>
              <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px' }}>Criteria JSON</label>
                <textarea 
                  className="large-text code"
                  rows={5}
                  style={{ width: '100%' }}
                  value={currentRule.criteria_json} 
                  onChange={e => setCurrentRule({...currentRule, criteria_json: e.target.value})}
                  required 
                />
              </div>
              <div style={{ marginBottom: '15px' }}>
                <label>
                  <input 
                    type="checkbox" 
                    checked={currentRule.is_enabled} 
                    onChange={e => setCurrentRule({...currentRule, is_enabled: e.target.checked})}
                  /> Enable this rule
                </label>
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                <button type="button" className="button" onClick={() => setIsModalOpen(false)}>Cancel</button>
                <button type="submit" className="button button-primary" disabled={saving}>
                  {saving ? 'Saving...' : 'Save Rule'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default EscalationRules;
