import React, { useState, useEffect } from 'react';
import { Team, Employee } from '../types';

interface TeamFormProps {
  initialData?: Team;
  onSuccess: () => void;
  onCancel: () => void;
  teams: Team[]; // Pass all teams for parent selection
}

const TeamForm: React.FC<TeamFormProps> = ({ initialData, onSuccess, onCancel, teams }) => {
  const [formData, setFormData] = useState({
    name: initialData?.name || '',
    parent_team_id: initialData?.parent_team_id || '',
    manager_id: initialData?.manager_id || '',
    escalation_manager_id: initialData?.escalation_manager_id || '',
    status: initialData?.status || 'active',
    visual: {
      type: initialData?.visual?.type || 'color',
      ref: initialData?.visual?.ref || '#333333',
    },
    member_ids: initialData?.member_ids || [],
  });

  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchEmployees();
  }, []);

  const fetchEmployees = async () => {
    try {
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/employees`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      if (response.ok) {
        const data = await response.json();
        setEmployees(data);
      }
    } catch (e) {
      console.error('Failed to fetch employees', e);
    }
  };

  const flattenTeams = (teams: Team[], excludeId?: number): Team[] => {
    let flat: Team[] = [];
    teams.forEach(team => {
      if (team.id === excludeId) return;
      flat.push(team);
      if (team.children) {
        flat = [...flat, ...flattenTeams(team.children, excludeId)];
      }
    });
    return flat;
  };

  const availableTeams = flattenTeams(teams, initialData?.id);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const url = initialData 
        ? `${apiUrl}/teams/${initialData.id}`
        : `${apiUrl}/teams`;

      const method = initialData ? 'POST' : 'POST'; // Update uses POST/PUT, REST usually PUT but WP often POST

      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(formData),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to save team');
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-form-container" style={{ background: '#f0f0f1', padding: '20px', marginBottom: '20px', border: '1px solid #c3c4c7' }}>
      <h3>{initialData ? 'Edit Team' : 'Add New Team'}</h3>
      
      {error && <div className="notice notice-error"><p>{error}</p></div>}

      <form onSubmit={handleSubmit}>
        <table className="form-table">
          <tbody>
            <tr>
              <th scope="row"><label htmlFor="name">Team Name</label></th>
              <td>
                <input 
                  type="text" 
                  id="name" 
                  name="name" 
                  value={formData.name}
                  onChange={(e) => setFormData({...formData, name: e.target.value})}
                  className="regular-text"
                  required
                />
              </td>
            </tr>

            <tr>
              <th scope="row"><label htmlFor="parent_team_id">Parent Team</label></th>
              <td>
                <select 
                  id="parent_team_id" 
                  name="parent_team_id" 
                  value={formData.parent_team_id}
                  onChange={(e) => setFormData({...formData, parent_team_id: e.target.value})}
                >
                  <option value="">(None - Root Team)</option>
                  {availableTeams.map(team => (
                    <option key={team.id} value={team.id}>{team.name}</option>
                  ))}
                </select>
              </td>
            </tr>

            <tr>
              <th scope="row"><label htmlFor="manager_id">Manager</label></th>
              <td>
                <select 
                  id="manager_id" 
                  name="manager_id" 
                  value={formData.manager_id}
                  onChange={(e) => setFormData({...formData, manager_id: e.target.value})}
                >
                  <option value="">Select Manager</option>
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.displayName}</option>
                  ))}
                </select>
              </td>
            </tr>

            <tr>
              <th scope="row"><label htmlFor="escalation_manager_id">Escalation Manager</label></th>
              <td>
                <select 
                  id="escalation_manager_id" 
                  name="escalation_manager_id" 
                  value={formData.escalation_manager_id}
                  onChange={(e) => setFormData({...formData, escalation_manager_id: e.target.value})}
                >
                  <option value="">(None)</option>
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.displayName}</option>
                  ))}
                </select>
                <p className="description">Person to escalate tickets to if SLA breached.</p>
              </td>
            </tr>

            <tr>
              <th scope="row"><label htmlFor="member_ids">Team Members</label></th>
              <td>
                <select 
                  id="member_ids" 
                  name="member_ids" 
                  multiple
                  value={formData.member_ids.map(String)}
                  onChange={(e) => {
                    const selectedOptions = Array.from(e.target.selectedOptions, option => parseInt(option.value));
                    setFormData({...formData, member_ids: selectedOptions});
                  }}
                  style={{ height: '150px', width: '100%' }}
                >
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.displayName}</option>
                  ))}
                </select>
                <p className="description">Hold Ctrl (Windows) or Cmd (Mac) to select multiple members.</p>
              </td>
            </tr>

            <tr>
              <th scope="row">Visual Identity</th>
              <td>
                <fieldset>
                  <label style={{ marginRight: '15px' }}>
                    <input 
                      type="radio" 
                      name="visualType" 
                      value="color"
                      checked={formData.visual.type === 'color'}
                      onChange={() => setFormData({...formData, visual: {...formData.visual, type: 'color'}})}
                    /> Color
                  </label>
                  <label>
                    <input 
                      type="radio" 
                      name="visualType" 
                      value="icon"
                      checked={formData.visual.type === 'icon'}
                      onChange={() => setFormData({...formData, visual: {...formData.visual, type: 'icon'}})}
                    /> Icon
                  </label>
                  
                  <br /><br />
                  
                  {formData.visual.type === 'color' ? (
                    <input 
                      type="color" 
                      value={formData.visual.ref || '#000000'}
                      onChange={(e) => setFormData({...formData, visual: {...formData.visual, ref: e.target.value}})}
                    />
                  ) : (
                    <div>
                      {formData.visual.ref && (
                        <div style={{ marginBottom: '10px' }}>
                          {formData.visual.ref.startsWith('http') || formData.visual.ref.startsWith('/') ? (
                             <img src={formData.visual.ref} alt="Team Icon" style={{ maxWidth: '50px', maxHeight: '50px', display: 'block', border: '1px solid #ddd', padding: '4px', background: '#fff' }} />
                          ) : (
                             <span className={`dashicons ${formData.visual.ref}`} style={{ fontSize: '30px', height: '30px', width: '30px' }}></span>
                          )}
                        </div>
                      )}
                      <button 
                        type="button" 
                        className="button" 
                        onClick={() => {
                          // @ts-ignore
                          if (typeof wp === 'undefined' || !wp.media) {
                            alert('WordPress Media Library is not available.');
                            return;
                          }
                      
                          // @ts-ignore
                          const frame = wp.media({
                            title: 'Select Team Icon',
                            button: {
                              text: 'Use this icon'
                            },
                            multiple: false
                          });
                      
                          frame.on('select', () => {
                            // @ts-ignore
                            const attachment = frame.state().get('selection').first().toJSON();
                            setFormData({
                              ...formData,
                              visual: {
                                ...formData.visual,
                                type: 'icon',
                                ref: attachment.url
                              }
                            });
                          });
                      
                          frame.open();
                        }}
                      >
                        {formData.visual.ref ? 'Change Icon' : 'Select Icon'}
                      </button>
                      
                      {formData.visual.ref && (
                        <button 
                          type="button" 
                          className="button button-link-delete" 
                          style={{ marginLeft: '10px', color: '#a00' }}
                          onClick={() => setFormData({...formData, visual: {...formData.visual, ref: ''}})}
                        >
                          Remove
                        </button>
                      )}
                      <input type="hidden" value={formData.visual.ref || ''} />
                    </div>
                  )}
                </fieldset>
              </td>
            </tr>

            <tr>
              <th scope="row"><label htmlFor="status">Status</label></th>
              <td>
                <select 
                  id="status" 
                  name="status" 
                  value={formData.status}
                  onChange={(e) => setFormData({...formData, status: e.target.value})}
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>

        <p className="submit">
          <button type="submit" className="button button-primary" disabled={loading}>
            {loading ? 'Saving...' : 'Save Team'}
          </button>
          <button type="button" className="button" onClick={onCancel} style={{ marginLeft: '10px' }}>
            Cancel
          </button>
        </p>
      </form>
    </div>
  );
};

export default TeamForm;
