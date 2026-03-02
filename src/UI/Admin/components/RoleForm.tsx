import React, { useState, useEffect } from 'react';
import RoleKpis from './RoleKpis';

interface Skill {
  id: number;
  name: string;
  description: string;
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
  const [availableSkills, setAvailableSkills] = useState<Skill[]>([]);
  const [selectedSkills, setSelectedSkills] = useState<{[key: number]: number}>({}); // skillId -> minProficiency
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSkills();
  }, []);

  useEffect(() => {
    if (role) {
      setName(role.name || '');
      setLevel(role.level || 'Junior');
      setDescription(role.description || '');
      setSuccessCriteria(role.success_criteria || '');
      
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
    } else {
      // Reset form for create mode
      setName('');
      setLevel('Junior');
      setDescription('');
      setSuccessCriteria('');
      setSelectedSkills({});
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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const payload = {
      name,
      level,
      description,
      success_criteria: successCriteria,
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
        method: 'POST', // Using POST for both create and update (update uses /roles/{id})
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
