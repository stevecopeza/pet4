import React, { useEffect, useState } from 'react';
import { Team, Employee } from '../types';

interface TeamViewProps {
  team: Team;
  onClose: () => void;
  onEdit: () => void;
  allTeams: Team[]; // To resolve parent name
}

const TeamView: React.FC<TeamViewProps> = ({ team, onClose, onEdit, allTeams }) => {
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loadingEmployees, setLoadingEmployees] = useState(false);

  useEffect(() => {
    fetchEmployees();
  }, []);

  const fetchEmployees = async () => {
    try {
      setLoadingEmployees(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      
      const response = await fetch(`${apiUrl}/employees`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        setEmployees(data);
      }
    } catch (e) {
      console.error('Failed to fetch employees', e);
    } finally {
      setLoadingEmployees(false);
    }
  };

  const getEmployeeName = (id: number | null) => {
    if (!id) return '-';
    const emp = employees.find(e => e.id === id);
    return emp ? emp.displayName : `User #${id}`;
  };

  const getTeamName = (id: number | null) => {
    if (!id) return '-';
    
    // Helper to find team in tree
    const findTeam = (teams: Team[], targetId: number): Team | undefined => {
      for (const t of teams) {
        if (t.id === targetId) return t;
        if (t.children) {
          const found = findTeam(t.children, targetId);
          if (found) return found;
        }
      }
      return undefined;
    };

    const parent = findTeam(allTeams, id);
    return parent ? parent.name : `Team #${id}`;
  };

  const members = team.member_ids 
    ? employees.filter(e => team.member_ids.includes(e.id))
    : [];

  return (
    <div className="pet-form-container" style={{ background: '#fff', padding: '20px', marginBottom: '20px', border: '1px solid #c3c4c7', boxShadow: '0 1px 1px rgba(0,0,0,.04)' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', borderBottom: '1px solid #eee', paddingBottom: '10px' }}>
        <h3 style={{ margin: 0 }}>Team Details: {team.name}</h3>
        <div>
          <button type="button" className="button button-primary" onClick={onEdit} style={{ marginRight: '10px' }}>
            Edit Team
          </button>
          <button type="button" className="button" onClick={onClose}>
            Close
          </button>
        </div>
      </div>

      <table className="form-table" style={{ width: '100%', maxWidth: '800px' }}>
        <tbody>
          <tr>
            <th scope="row" style={{ width: '200px', textAlign: 'left', padding: '10px 0' }}>Team Name</th>
            <td style={{ padding: '10px 0' }}>
              <div style={{ display: 'flex', alignItems: 'center' }}>
                {team.visual?.type === 'icon' && team.visual.ref && (
                    team.visual.ref.startsWith('http') || team.visual.ref.startsWith('/') ? (
                        <img src={team.visual.ref} alt="" style={{ width: '24px', height: '24px', marginRight: '8px', borderRadius: '4px' }} />
                    ) : (
                        <span className={`dashicons ${team.visual.ref}`} style={{ marginRight: '8px', fontSize: '24px', width: '24px', height: '24px' }}></span>
                    )
                )}
                {team.visual?.type === 'color' && team.visual.ref && (
                    <span style={{ display: 'inline-block', width: '16px', height: '16px', backgroundColor: team.visual.ref, borderRadius: '50%', marginRight: '8px' }}></span>
                )}
                <strong>{team.name}</strong>
              </div>
            </td>
          </tr>

          <tr>
            <th scope="row" style={{ textAlign: 'left', padding: '10px 0' }}>Parent Team</th>
            <td style={{ padding: '10px 0' }}>{team.parent_team_id ? getTeamName(team.parent_team_id) : '(Root Team)'}</td>
          </tr>

          <tr>
            <th scope="row" style={{ textAlign: 'left', padding: '10px 0' }}>Manager</th>
            <td style={{ padding: '10px 0' }}>{loadingEmployees ? 'Loading...' : getEmployeeName(team.manager_id)}</td>
          </tr>

          <tr>
            <th scope="row" style={{ textAlign: 'left', padding: '10px 0' }}>Escalation Manager</th>
            <td style={{ padding: '10px 0' }}>{loadingEmployees ? 'Loading...' : getEmployeeName(team.escalation_manager_id)}</td>
          </tr>

          <tr>
            <th scope="row" style={{ textAlign: 'left', padding: '10px 0' }}>Status</th>
            <td style={{ padding: '10px 0' }}>
              <span className={`status-badge status-${team.status}`}>{team.status}</span>
            </td>
          </tr>

          <tr>
            <th scope="row" style={{ textAlign: 'left', padding: '10px 0', verticalAlign: 'top' }}>Team Members</th>
            <td style={{ padding: '10px 0' }}>
              {loadingEmployees ? 'Loading...' : (
                members.length > 0 ? (
                  <ul style={{ margin: 0, paddingLeft: '20px', listStyleType: 'disc' }}>
                    {members.map(member => (
                      <li key={member.id} style={{ marginBottom: '4px' }}>
                        {member.displayName} <span style={{ color: '#666', fontSize: '0.9em' }}>({member.jobTitle || 'No Title'})</span>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <span style={{ color: '#666', fontStyle: 'italic' }}>No members assigned to this team.</span>
                )
              )}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  );
};

export default TeamView;
