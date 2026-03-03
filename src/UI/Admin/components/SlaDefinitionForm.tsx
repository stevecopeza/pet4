import React, { useState, useEffect } from 'react';
import { Sla, SlaTier, EscalationRule, Calendar, Role } from '../types';

interface SlaDefinitionFormProps {
  initialData?: Sla;
  onSave: (data: Partial<Sla>) => void;
  onCancel: () => void;
}

const emptyTier = (): SlaTier => ({
  priority: 1,
  label: '',
  calendar_id: 0,
  response_target_minutes: 60,
  resolution_target_minutes: 240,
  escalation_rules: [],
});

const SlaDefinitionForm: React.FC<SlaDefinitionFormProps> = ({ initialData, onSave, onCancel }) => {
  const [name, setName] = useState(initialData?.name || '');
  const [isTiered, setIsTiered] = useState(initialData?.is_tiered || false);
  const [tierTransitionCap, setTierTransitionCap] = useState(initialData?.tier_transition_cap_percent || 80);
  const [tiers, setTiers] = useState<SlaTier[]>(initialData?.tiers || []);
  const [responseTarget, setResponseTarget] = useState(initialData?.target_response_minutes || 60);
  const [resolutionTarget, setResolutionTarget] = useState(initialData?.target_resolution_minutes || 240);
  const [calendarId, setCalendarId] = useState(initialData?.calendar_id || 0);
  const [escalationRules, setEscalationRules] = useState<EscalationRule[]>(initialData?.escalation_rules || []);
  
  const [calendars, setCalendars] = useState<Calendar[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [calRes, roleRes] = await Promise.all([
          fetch(`${window.petSettings.apiUrl}/calendars`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } }),
          fetch(`${window.petSettings.apiUrl}/roles`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } })
        ]);

        if (calRes.ok) setCalendars(await calRes.json());
        if (roleRes.ok) setRoles(await roleRes.json());
      } catch (err) {
        console.error('Failed to fetch dependencies', err);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isTiered) {
      onSave({
        name,
        tiers,
        tier_transition_cap_percent: tierTransitionCap,
        escalation_rules: [],
      });
    } else {
      onSave({
        name,
        target_response_minutes: Number(responseTarget),
        target_resolution_minutes: Number(resolutionTarget),
        calendar_id: Number(calendarId),
        escalation_rules: escalationRules,
      });
    }
  };

  const addTier = () => {
    const nextPriority = tiers.length > 0 ? Math.max(...tiers.map(t => t.priority)) + 1 : 1;
    setTiers([...tiers, { ...emptyTier(), priority: nextPriority }]);
  };

  const updateTier = (index: number, field: keyof SlaTier, value: any) => {
    const updated = [...tiers];
    updated[index] = { ...updated[index], [field]: value };
    setTiers(updated);
  };

  const removeTier = (index: number) => {
    setTiers(tiers.filter((_, i) => i !== index));
  };

  const addRule = () => {
    setEscalationRules([...escalationRules, { percentage: 75, action: 'notify_manager', notify_role_id: undefined }]);
  };

  const updateRule = (index: number, field: keyof EscalationRule, value: any) => {
    const newRules = [...escalationRules];
    newRules[index] = { ...newRules[index], [field]: value };
    setEscalationRules(newRules);
  };

  const removeRule = (index: number) => {
    setEscalationRules(escalationRules.filter((_, i) => i !== index));
  };

  if (loading) return <div>Loading form dependencies...</div>;

  return (
    <div className="pet-form-container" style={{ maxWidth: '800px', margin: '0 auto' }}>
      <h3>{initialData ? 'Edit SLA Definition' : 'New SLA Definition'}</h3>
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Name</label>
          <input 
            type="text" 
            value={name} 
            onChange={e => setName(e.target.value)} 
            required 
            style={{ width: '100%', padding: '8px', marginBottom: '15px' }}
          />
        </div>

        <div className="form-group" style={{ marginBottom: '15px' }}>
          <label style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <input
              type="checkbox"
              checked={isTiered}
              onChange={e => setIsTiered(e.target.checked)}
            />
            Tiered SLA (multiple time-band tiers)
          </label>
        </div>

        {!isTiered && (
          <>
            <div style={{ display: 'flex', gap: '20px', marginBottom: '15px' }}>
              <div className="form-group" style={{ flex: 1 }}>
                <label>Response Target (Minutes)</label>
                <input 
                  type="number" 
                  value={responseTarget} 
                  onChange={e => setResponseTarget(Number(e.target.value))} 
                  required 
                  style={{ width: '100%', padding: '8px' }}
                />
              </div>
              <div className="form-group" style={{ flex: 1 }}>
                <label>Resolution Target (Minutes)</label>
                <input 
                  type="number" 
                  value={resolutionTarget} 
                  onChange={e => setResolutionTarget(Number(e.target.value))} 
                  required 
                  style={{ width: '100%', padding: '8px' }}
                />
              </div>
            </div>

            <div className="form-group">
              <label>Calendar</label>
              <select 
                value={calendarId} 
                onChange={e => setCalendarId(Number(e.target.value))}
                required
                style={{ width: '100%', padding: '8px', marginBottom: '15px' }}
              >
                <option value="">Select Calendar</option>
                {calendars.map(cal => (
                  <option key={cal.id} value={cal.id}>{cal.name}</option>
                ))}
              </select>
            </div>

            <div style={{ marginTop: '30px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <h4>Escalation Rules</h4>
                <button type="button" onClick={addRule} style={{ fontSize: '0.8em' }}>Add Rule</button>
              </div>
          
          {escalationRules.map((rule, index) => (
            <div key={index} style={{ display: 'flex', gap: '10px', marginBottom: '10px', alignItems: 'center', background: '#f9f9f9', padding: '10px', borderRadius: '4px' }}>
              <div style={{ flex: 1 }}>
                <label style={{ fontSize: '0.8em', display: 'block' }}>At % of SLA</label>
                <input 
                  type="number" 
                  value={rule.percentage} 
                  onChange={e => updateRule(index, 'percentage', Number(e.target.value))}
                  style={{ width: '100%' }}
                />
              </div>
              
              <div style={{ flex: 2 }}>
                <label style={{ fontSize: '0.8em', display: 'block' }}>Action</label>
                <select 
                  value={rule.action} 
                  onChange={e => updateRule(index, 'action', e.target.value)}
                  style={{ width: '100%' }}
                >
                  <option value="notify_manager">Notify Manager</option>
                  <option value="notify_role">Notify Role</option>
                  <option value="change_priority">Increase Priority</option>
                </select>
              </div>

              {rule.action === 'notify_role' && (
                <div style={{ flex: 2 }}>
                  <label style={{ fontSize: '0.8em', display: 'block' }}>Target Role</label>
                  <select 
                    value={rule.notify_role_id || ''} 
                    onChange={e => updateRule(index, 'notify_role_id', Number(e.target.value))}
                    style={{ width: '100%' }}
                  >
                    <option value="">Select Role</option>
                    {roles.map(role => (
                      <option key={role.id} value={role.id}>{role.name}</option>
                    ))}
                  </select>
                </div>
              )}

              <button 
                type="button" 
                onClick={() => removeRule(index)}
                style={{ color: 'red', border: 'none', background: 'none', cursor: 'pointer', alignSelf: 'flex-end', marginBottom: '5px' }}
              >
                &times;
              </button>
            </div>
          ))}
          {escalationRules.length === 0 && <p style={{ fontStyle: 'italic', color: '#666' }}>No escalation rules defined.</p>}
            </div>
          </>
        )}

        {isTiered && (
          <>
            <div className="form-group" style={{ marginBottom: '15px' }}>
              <label>Tier Transition Cap (%)</label>
              <input
                type="number"
                value={tierTransitionCap}
                onChange={e => setTierTransitionCap(Number(e.target.value))}
                min={1}
                max={99}
                style={{ width: '120px', padding: '8px' }}
              />
              <span style={{ marginLeft: '8px', color: '#666', fontSize: '0.85em' }}>
                Max carry-forward percentage when crossing tier boundaries
              </span>
            </div>

            <div style={{ marginTop: '20px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
                <h4 style={{ margin: 0 }}>Tiers</h4>
                <button type="button" onClick={addTier} style={{ fontSize: '0.8em' }}>Add Tier</button>
              </div>

              {tiers.map((tier, index) => (
                <div key={index} style={{ border: '1px solid #ddd', borderRadius: '6px', padding: '15px', marginBottom: '15px', background: '#fafafa' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
                    <strong>Tier {tier.priority}</strong>
                    <button
                      type="button"
                      onClick={() => removeTier(index)}
                      style={{ color: 'red', border: 'none', background: 'none', cursor: 'pointer' }}
                    >
                      &times; Remove
                    </button>
                  </div>

                  <div style={{ display: 'flex', gap: '10px', marginBottom: '10px' }}>
                    <div style={{ flex: 1 }}>
                      <label style={{ fontSize: '0.8em', display: 'block' }}>Label</label>
                      <input
                        type="text"
                        value={tier.label}
                        onChange={e => updateTier(index, 'label', e.target.value)}
                        placeholder="e.g. Office Hours"
                        style={{ width: '100%', padding: '6px' }}
                      />
                    </div>
                    <div style={{ flex: 1 }}>
                      <label style={{ fontSize: '0.8em', display: 'block' }}>Calendar</label>
                      <select
                        value={tier.calendar_id}
                        onChange={e => updateTier(index, 'calendar_id', Number(e.target.value))}
                        style={{ width: '100%', padding: '6px' }}
                      >
                        <option value={0}>Select Calendar</option>
                        {calendars.map(cal => (
                          <option key={cal.id} value={cal.id}>{cal.name}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '10px' }}>
                    <div style={{ flex: 1 }}>
                      <label style={{ fontSize: '0.8em', display: 'block' }}>Response Target (mins)</label>
                      <input
                        type="number"
                        value={tier.response_target_minutes}
                        onChange={e => updateTier(index, 'response_target_minutes', Number(e.target.value))}
                        style={{ width: '100%', padding: '6px' }}
                      />
                    </div>
                    <div style={{ flex: 1 }}>
                      <label style={{ fontSize: '0.8em', display: 'block' }}>Resolution Target (mins)</label>
                      <input
                        type="number"
                        value={tier.resolution_target_minutes}
                        onChange={e => updateTier(index, 'resolution_target_minutes', Number(e.target.value))}
                        style={{ width: '100%', padding: '6px' }}
                      />
                    </div>
                  </div>
                </div>
              ))}

              {tiers.length === 0 && <p style={{ fontStyle: 'italic', color: '#666' }}>No tiers defined. Add at least one tier.</p>}
            </div>
          </>
        )}

        <div style={{ marginTop: '30px', display: 'flex', gap: '10px' }}>
          <button 
            type="submit" 
            style={{ 
              background: '#007cba', 
              color: '#fff', 
              border: 'none', 
              padding: '10px 20px', 
              borderRadius: '4px',
              cursor: 'pointer' 
            }}
          >
            Save SLA
          </button>
          <button 
            type="button" 
            onClick={onCancel}
            style={{ 
              background: '#f0f0f1', 
              color: '#000', 
              border: '1px solid #ccc', 
              padding: '10px 20px', 
              borderRadius: '4px',
              cursor: 'pointer' 
            }}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default SlaDefinitionForm;
