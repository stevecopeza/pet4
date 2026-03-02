import React, { useState, useEffect } from 'react';
import { Employee, Skill } from '../types';
import { DataTable, Column } from './DataTable';

interface EmployeeSkillsProps {
  employee: Employee;
  reviewCycleId?: number;
}

interface EmployeeSkill {
  id: number;
  employee_id: number;
  skill_id: number;
  review_cycle_id?: number;
  self_rating: number;
  manager_rating: number;
  effective_date: string;
  created_at: string;
  skill_name?: string; // Enriched
}

const EmployeeSkills: React.FC<EmployeeSkillsProps> = ({ employee, reviewCycleId }) => {
  const [skills, setSkills] = useState<EmployeeSkill[]>([]);
  const [availableSkills, setAvailableSkills] = useState<Skill[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [newSkillId, setNewSkillId] = useState<string>('');
  const [selfRating, setSelfRating] = useState<number>(0);
  const [managerRating, setManagerRating] = useState<number>(0);
  const [effectiveDate, setEffectiveDate] = useState<string>(new Date().toISOString().split('T')[0]);

  // @ts-ignore
  const apiUrl = window.petSettings?.apiUrl;
  // @ts-ignore
  const nonce = window.petSettings?.nonce;

  useEffect(() => {
    fetchData();
  }, [employee.id, reviewCycleId]);

  const fetchData = async () => {
    setLoading(true);
    try {
      // Fetch Employee Skills
      let url = `${apiUrl}/employees/${employee.id}/skills`;
      if (reviewCycleId) {
        url += `?review_cycle_id=${reviewCycleId}`;
      }
      const skillsRes = await fetch(url, {
        headers: { 'X-WP-Nonce': nonce },
      });
      const skillsData = await skillsRes.json();

      // Fetch All Skills (for names and dropdown)
      const allSkillsRes = await fetch(`${apiUrl}/skills`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      const allSkillsData = await allSkillsRes.json();

      setAvailableSkills(allSkillsData);

      // Enrich employee skills with names
      const enrichedSkills = skillsData.map((es: any) => ({
        ...es,
        skill_name: allSkillsData.find((s: Skill) => s.id === es.skill_id)?.name || 'Unknown Skill',
      }));

      setSkills(enrichedSkills);
    } catch (err) {
      console.error('Failed to fetch skills data', err);
    } finally {
      setLoading(false);
    }
  };

  const handleAddSkill = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newSkillId) return;

    try {
      const response = await fetch(`${apiUrl}/employees/${employee.id}/skills`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          skill_id: parseInt(newSkillId),
          self_rating: selfRating,
          manager_rating: managerRating,
          effective_date: effectiveDate,
          review_cycle_id: reviewCycleId,
        }),
      });

      if (response.ok) {
        setShowAddForm(false);
        setNewSkillId('');
        setSelfRating(0);
        setManagerRating(0);
        fetchData();
      } else {
        alert('Failed to add skill rating');
      }
    } catch (err) {
      console.error(err);
      alert('Error adding skill rating');
    }
  };

  const columns: Column<EmployeeSkill>[] = [
    { key: 'skill_name', header: 'Skill' },
    { key: 'self_rating', header: 'Self Rating' },
    { key: 'manager_rating', header: 'Manager Rating' },
    { key: 'effective_date', header: 'Effective Date' },
  ];

  if (loading) return <div>Loading skills...</div>;

  return (
    <div className="employee-skills">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
        <h3>Skills & Ratings</h3>
        <button 
          className="button button-primary"
          onClick={() => setShowAddForm(!showAddForm)}
        >
          {showAddForm ? 'Cancel' : 'Rate Skill'}
        </button>
      </div>

      {showAddForm && (
        <div className="card" style={{ marginBottom: '20px', padding: '15px', background: '#f9f9f9', border: '1px solid #ddd' }}>
          <form onSubmit={handleAddSkill}>
            <table className="form-table">
              <tbody>
                <tr>
                  <th scope="row"><label>Skill</label></th>
                  <td>
                    <select 
                      value={newSkillId} 
                      onChange={(e) => setNewSkillId(e.target.value)}
                      required
                      className="regular-text"
                    >
                      <option value="">Select a skill...</option>
                      {availableSkills.map(skill => (
                        <option key={skill.id} value={skill.id}>{skill.name}</option>
                      ))}
                    </select>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Self Rating (0-5)</label></th>
                  <td>
                    <input 
                      type="number" 
                      min="0" 
                      max="5" 
                      value={selfRating} 
                      onChange={(e) => setSelfRating(parseInt(e.target.value))}
                      className="small-text"
                    />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Manager Rating (0-5)</label></th>
                  <td>
                    <input 
                      type="number" 
                      min="0" 
                      max="5" 
                      value={managerRating} 
                      onChange={(e) => setManagerRating(parseInt(e.target.value))}
                      className="small-text"
                    />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Effective Date</label></th>
                  <td>
                    <input 
                      type="date" 
                      value={effectiveDate} 
                      onChange={(e) => setEffectiveDate(e.target.value)}
                      required
                      className="regular-text"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
            <p className="submit">
              <button type="submit" className="button button-primary">Save Rating</button>
            </p>
          </form>
        </div>
      )}

      <DataTable 
        data={skills}
        columns={columns}
      />
    </div>
  );
};

export default EmployeeSkills;
