import React, { useState, useEffect } from 'react';
import RoleKpis from './RoleKpis';

interface Skill {
  id: number;
  name: string;
  description: string;
}

interface TeamOption {
  id: number;
  name: string;
  status: string;
}

interface RoleTeamMapping {
  team_id: number;
  is_primary: boolean;
}

interface RoleFormProps {
  role: any | null; // Typed loosely for now, should be Role | null
  onSuccess: () => void;
  onCancel: () => void;
}

const RoleForm: React.FC<RoleFormProps> = ({ role, onSuccess, onCancel }) => {
  const [activeTab, setActiveTab] = useState<'details' | 'kpis'>('details');
  const [name, setName] = useState(role?.name || '');
  const [level, setLevel] = useState(role?.level || 'Junior');
  const [description, setDescription] = useState(role?.description || '');
  const [successCriteria, setSuccessCriteria] = useState(role?.success_criteria || '');
  const [baseInternalRate, setBaseInternalRate] = useState<string>(role?.base_internal_rate != null ? String(role.base_internal_rate) : '');
  const [availableSkills, setAvailableSkills] = useState<Skill[]>([]);
  const [selectedSkills, setSelectedSkills] = useState<{[key: number]: number}>({}); // skillId -> minProficiency
  const [availableTeams, setAvailableTeams] = useState<TeamOption[]>([]);
  const [selectedTeams, setSelectedTeams] = useState<{[key: number]: boolean}>({}); // teamId -> checked
  const [primaryTeamId, setPrimaryTeamId] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSkills();
    fetchTeams();
  }, []);

  useEffect(() => {
    if (role) {
      setName(role.name || '');
      setLevel(role.level || 'Junior');
      setDescription(role.description || '');
      setSuccessCriteria(role.success_criteria || '');
      setBaseInternalRate(role.base_internal_rate != null ? String(role.base_internal_rate) : '');
      
      // Parse required skills if they exist
      if (role.required_skills) {
        const skills: {[key: number]: number} = {};
        const skillsData = role.required_skills;
        
        Object.keys(skillsData).forEach(key => {
            const skillId = parseInt(key);
            const data = skillsData[key];
            if (data && typeof data === 'object' && data.min_proficiency_level) {
                skills[skillId] = data.min_proficiency_level;
            }
        });
        setSelectedSkills(skills);
      } else {
        setSelectedSkills({});
      }

      // Load existing role-team mappings
      if (role.teams && Array.isArray(role.teams)) {
        const teams: {[key: number]: boolean} = {};
        let primary: number | null = null;
        role.teams.forEach((t: RoleTeamMapping) => {
          teams[t.team_id] = true;
          if (t.is_primary) primary = t.team_id;
        });
        setSelectedTeams(teams);
        setPrimaryTeamId(primary);
      } else {
        setSelectedTeams({});
        setPrimaryTeamId(null);
      }
    } else {
      // Reset form for create mode
      setName('');
      setLevel('Junior');
      setDescription('');
      setSuccessCriteria('');
      setBaseInternalRate('');
      setSelectedSkills({});
      setSelectedTeams({});
      setPrimaryTeamId(null);
    }
  }, [role]);

  const fetchSkills = async () => {
    try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/skills`, {
            headers: {
                // @ts-ignore
                'X-WP-Nonce': window.petSettings.nonce,
            },
        });
        if (response.ok) {
            const data = await response.json();
            setAvailableSkills(data);
        }
    } catch (e) {
        console.error('Failed to fetch skills');
    }
  };

  const fetchTeams = async () => {
    try {
        // @ts-ignore
        const response = await fetch(`${window.petSettings.apiUrl}/teams`, {
            headers: {
                // @ts-ignore
                'X-WP-Nonce': window.petSettings.nonce,
            },
        });
        if (response.ok) {
            const data = await response.json();
            // Flatten nested teams and filter active
            const flatten = (nodes: any[]): TeamOption[] => {
                let flat: TeamOption[] = [];
                nodes.forEach((n: any) => {
                    flat.push({ id: n.id, name: n.name, status: n.status });
                    if (Array.isArray(n.children) && n.children.length > 0) {
                        flat = flat.concat(flatten(n.children));
                    }
                });
                return flat;
            };
            setAvailableTeams(flatten(data).filter(t => t.status === 'active'));
        }
    } catch (e) {
        console.error('Failed to fetch teams');
    }
  };

  const handleTeamToggle = (teamId: number) => {
    const newTeams = { ...selectedTeams };
    if (newTeams[teamId]) {
        delete newTeams[teamId];
        if (primaryTeamId === teamId) setPrimaryTeamId(null);
    } else {
        newTeams[teamId] = true;
    }
    setSelectedTeams(newTeams);
  };

  const handlePrimaryChange = (teamId: number) => {
    setPrimaryTeamId(teamId);
  };

  const saveRoleTeams = async (roleId: number) => {
    const teams = Object.keys(selectedTeams)
      .filter(id => selectedTeams[Number(id)])
      .map(id => ({
        team_id: Number(id),
        is_primary: Number(id) === primaryTeamId,
      }));

    // @ts-ignore
    const baseUrl = window.petSettings.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings.nonce;

    await fetch(`${baseUrl}/roles/${roleId}/teams`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
      body: JSON.stringify({ teams }),
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const payload = {
      name,
      level,
      description,
      success_criteria: successCriteria,
      base_internal_rate: baseInternalRate !== '' ? parseFloat(baseInternalRate) : null,
      required_skills: Object.entries(selectedSkills).reduce((acc, [skillId, level]) => {
        acc[parseInt(skillId)] = { min_proficiency_level: level, importance_weight: 1 };
        return acc;
      }, {} as any)
    };

    try {
      // @ts-ignore
      const baseUrl = window.petSettings.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings.nonce;
      
      const url = role ? `${baseUrl}/roles/${role.id}` : `${baseUrl}/roles`;

      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.error || 'Failed to save role');
      }

      const savedRole = await response.json();
      const roleId = role ? role.id : savedRole?.id;

      // Save department mappings if we have a role ID
      if (roleId) {
        await saveRoleTeams(roleId);
      }

      onSuccess();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleSkillToggle = (skillId: number) => {
      const newSkills = { ...selectedSkills };
      if (newSkills[skillId]) {
          delete newSkills[skillId];
      } else {
          newSkills[skillId] = 1; // Default level 1
      }
      setSelectedSkills(newSkills);
  };

  const handleSkillLevelChange = (skillId: number, level: number) => {
      setSelectedSkills({
          ...selectedSkills,
          [skillId]: level
      });
  };

  return (
    <div className="pet-card" style={{ padding: '20px', maxWidth: '800px' }}>
      <h3>{role ? 'Edit Role' : 'Create New Role'}</h3>
      
      {role && (
        <div className="nav-tab-wrapper" style={{ marginBottom: '20px' }}>
          <button 
            type="button"
            className={`nav-tab ${activeTab === 'details' ? 'nav-tab-active' : ''}`}
            onClick={() => setActiveTab('details')}
          >
            Details
          </button>
          <button 
            type="button"
            className={`nav-tab ${activeTab === 'kpis' ? 'nav-tab-active' : ''}`}
            onClick={() => setActiveTab('kpis')}
          >
            KPIs
          </button>
        </div>
      )}

      {activeTab === 'kpis' && role ? (
        <RoleKpis roleId={role.id} />
      ) : (
        <>
          {error && (
            <div className="notice notice-error inline" style={{ marginBottom: '20px' }}>
              <p>{error}</p>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Role Name</label>
              <input 
                type="text" 
                value={name} 
                onChange={e => setName(e.target.value)} 
                className="regular-text" 
                required 
                style={{ width: '100%' }}
              />
            </div>

            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Level</label>
              <select 
                value={level} 
                onChange={e => setLevel(e.target.value)}
                style={{ width: '100%' }}
              >
                <option value="Junior">Junior</option>
                <option value="Mid-Level">Mid-Level</option>
                <option value="Senior">Senior</option>
                <option value="Lead">Lead</option>
                <option value="Principal">Principal</option>
              </select>
            </div>

            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Description</label>
              <textarea 
                value={description} 
                onChange={e => setDescription(e.target.value)} 
                rows={4} 
                style={{ width: '100%' }}
              />
            </div>

            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Success Criteria</label>
              <textarea 
                value={successCriteria} 
                onChange={e => setSuccessCriteria(e.target.value)} 
                rows={4} 
                style={{ width: '100%' }}
                placeholder="What does success look like in this role?"
              />
            </div>

            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Base Internal Rate ($/hr)</label>
              <input 
                type="number" 
                step="0.01" 
                min="0" 
                value={baseInternalRate} 
                onChange={e => setBaseInternalRate(e.target.value)} 
                className="regular-text" 
                style={{ width: '100%' }}
                placeholder="Required for publishing"
              />
              <p style={{ margin: '4px 0 0', fontSize: '12px', color: '#666' }}>Internal cost rate used for margin calculations. Must be set before publishing.</p>
            </div>

            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '10px', fontWeight: 600 }}>Required Skills</label>
              <div style={{ border: '1px solid #ddd', padding: '10px', maxHeight: '200px', overflowY: 'auto' }}>
                {availableSkills.map(skill => (
                    <div key={skill.id} style={{ display: 'flex', alignItems: 'center', marginBottom: '8px' }}>
                        <label style={{ flex: 1, display: 'flex', alignItems: 'center' }}>
                            <input 
                                type="checkbox" 
                                checked={!!selectedSkills[skill.id]} 
                                onChange={() => handleSkillToggle(skill.id)}
                                style={{ marginRight: '8px' }}
                            />
                            {skill.name}
                        </label>
                        {selectedSkills[skill.id] && (
                            <select 
                                value={selectedSkills[skill.id]} 
                                onChange={(e) => handleSkillLevelChange(skill.id, parseInt(e.target.value))}
                                style={{ marginLeft: '10px', fontSize: '12px' }}
                            >
                                <option value={1}>Level 1 (Awareness)</option>
                                <option value={2}>Level 2 (Novice)</option>
                                <option value={3}>Level 3 (Competent)</option>
                                <option value={4}>Level 4 (Proficient)</option>
                                <option value={5}>Level 5 (Expert)</option>
                            </select>
                        )}
                    </div>
                ))}
                {availableSkills.length === 0 && <p style={{ color: '#666' }}>No skills defined yet.</p>}
              </div>
            </div>

            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '10px', fontWeight: 600 }}>Departments</label>
              <div style={{ border: '1px solid #ddd', padding: '10px', maxHeight: '200px', overflowY: 'auto' }}>
                {availableTeams.map(team => (
                    <div key={team.id} style={{ display: 'flex', alignItems: 'center', marginBottom: '8px' }}>
                        <label style={{ flex: 1, display: 'flex', alignItems: 'center' }}>
                            <input 
                                type="checkbox" 
                                checked={!!selectedTeams[team.id]} 
                                onChange={() => handleTeamToggle(team.id)}
                                style={{ marginRight: '8px' }}
                            />
                            {team.name}
                        </label>
                        {selectedTeams[team.id] && (
                            <label style={{ marginLeft: '10px', fontSize: '12px', display: 'flex', alignItems: 'center' }}>
                                <input 
                                    type="radio" 
                                    name="primaryTeam"
                                    checked={primaryTeamId === team.id}
                                    onChange={() => handlePrimaryChange(team.id)}
                                    style={{ marginRight: '4px' }}
                                />
                                Primary
                            </label>
                        )}
                    </div>
                ))}
                {availableTeams.length === 0 && <p style={{ color: '#666' }}>No departments defined yet.</p>}
              </div>
              <p style={{ margin: '4px 0 0', fontSize: '12px', color: '#666' }}>Select which departments supply this role. Mark one as primary.</p>
            </div>

            <div style={{ display: 'flex', gap: '10px', marginTop: '20px' }}>
              <button 
                type="submit" 
                className="button button-primary" 
                disabled={loading}
              >
                {loading ? 'Saving...' : 'Save Role'}
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
        </>
      )}
    </div>
  );
};

export default RoleForm;
