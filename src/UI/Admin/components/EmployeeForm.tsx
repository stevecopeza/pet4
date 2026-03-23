import React, { useState, useEffect } from 'react';
import MalleableFieldsRenderer from './MalleableFieldsRenderer';
import EmployeeSkills from './EmployeeSkills';
import EmployeeRoles from './EmployeeRoles';
import EmployeeCertifications from './EmployeeCertifications';
import PersonKpis from './PersonKpis';
import EmployeeReviews from './EmployeeReviews';
import { SchemaDefinition, Employee, Team } from '../types';
export type EmployeeFormTab = 'details' | 'roles' | 'skills' | 'certifications' | 'kpis' | 'reviews';
export type EmployeeFormDetailsFocus = 'all' | 'identity' | 'org';

interface EmployeeFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  initialData?: Employee;
  hideTabNavigation?: boolean;
  forcedTab?: EmployeeFormTab;
  onTabChange?: (tab: EmployeeFormTab) => void;
  detailsFocus?: EmployeeFormDetailsFocus;
  roleAssignmentsEditable?: boolean;
  onRoleAssignmentsChanged?: () => void;
}

const EmployeeForm: React.FC<EmployeeFormProps> = ({
  onSuccess,
  onCancel,
  initialData,
  hideTabNavigation = false,
  forcedTab,
  onTabChange,
  detailsFocus = 'all',
  roleAssignmentsEditable = false,
  onRoleAssignmentsChanged,
}) => {
  const isEditMode = !!initialData;
  const [activeTab, setActiveTab] = useState<EmployeeFormTab>('details');
  const [wpUserId, setWpUserId] = useState(initialData?.wpUserId?.toString() || '');
  const [availableUsers, setAvailableUsers] = useState<any[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [firstName, setFirstName] = useState(initialData?.firstName || '');
  const [lastName, setLastName] = useState(initialData?.lastName || '');
  const [email, setEmail] = useState(initialData?.email || '');
  const [status, setStatus] = useState(initialData?.status || 'active');
  const [hireDate, setHireDate] = useState(initialData?.hireDate || '');
  const [managerId, setManagerId] = useState(initialData?.managerId?.toString() || '');
  const [teamIds, setTeamIds] = useState<number[]>(initialData?.teamIds || []);
  const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
  const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fetchError, setFetchError] = useState<string | null>(null);
  const currentTab = forcedTab || activeTab;
  const showIdentityFields = currentTab === 'details' && (detailsFocus === 'all' || detailsFocus === 'identity');
  const showOrgFields = currentTab === 'details' && (detailsFocus === 'all' || detailsFocus === 'org');
  const showAdditionalInfo = currentTab === 'details' && (detailsFocus === 'all' || detailsFocus === 'org');

  const handleTabChange = (tab: EmployeeFormTab) => {
    if (!forcedTab) {
      setActiveTab(tab);
    }
    if (onTabChange) {
      onTabChange(tab);
    }
  };

  useEffect(() => {
    const fetchSchema = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;
        
        const response = await fetch(`${apiUrl}/schemas/employee?status=active`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          if (Array.isArray(data) && data.length > 0) {
            setActiveSchema(data[0]);
          }
        } else {
             setFetchError(`Fetch failed: ${response.status} ${response.statusText}`);
        }
      } catch (err) {
        console.error('Failed to fetch schema', err);
        setFetchError(err instanceof Error ? err.message : 'Unknown fetch error');
      }
    };

    const fetchAvailableUsers = async () => {
      // Only fetch available users in Add mode, or if we want to allow changing user (which we don't for now)
      if (isEditMode) return;

      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;
        
        const response = await fetch(`${apiUrl}/employees/available-users`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setAvailableUsers(data);
        }
      } catch (err) {
        console.error('Failed to fetch available users', err);
      }
    };

    const fetchEmployees = async () => {
      try {
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
      } catch (err) {
        console.error('Failed to fetch employees for manager list', err);
      }
    };

    const fetchTeams = async () => {
      try {
        // @ts-ignore
        const apiUrl = window.petSettings?.apiUrl;
        // @ts-ignore
        const nonce = window.petSettings?.nonce;
        
        const response = await fetch(`${apiUrl}/teams`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setTeams(data);
        }
      } catch (err) {
        console.error('Failed to fetch teams', err);
      }
    };

    fetchSchema();
    fetchAvailableUsers();
    fetchEmployees();
    fetchTeams();
  }, [isEditMode]);

  const handleMalleableChange = (key: string, value: any) => {
    setMalleableData(prev => ({
      ...prev,
      [key]: value
    }));
  };

  const handleUserSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const selectedId = e.target.value;
    setWpUserId(selectedId);
    
    const user = availableUsers.find(u => u.ID == selectedId);
    if (user) {
        setEmail(user.user_email || '');
    }
  };

  const flattenTeams = (teams: Team[]): Team[] => {
    let flat: Team[] = [];
    teams.forEach(team => {
      flat.push(team);
      // @ts-ignore
      if (team.children) {
        // @ts-ignore
        flat = [...flat, ...flattenTeams(team.children)];
      }
    });
    return flat;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const url = isEditMode 
        ? `${apiUrl}/employees/${initialData.id}`
        : `${apiUrl}/employees`;
      
      const method = isEditMode ? 'POST' : 'POST'; // Note: WP REST API often uses POST for updates if _method=PUT is not supported, but standard is PUT.
      // Actually, WP REST API supports PUT. But let's check my Controller registration.
      // I registered WP_REST_Server::EDITABLE which maps to POST, PUT, PATCH.
      // So fetch method should be 'PUT' for update.

      const response = await fetch(url, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ 
          wpUserId: parseInt(wpUserId, 10), 
          firstName, 
          lastName, 
          email,
          status,
          hireDate: hireDate || null,
          managerId: managerId ? parseInt(managerId, 10) : null,
          malleableData,
          teamIds
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || `Failed to ${isEditMode ? 'update' : 'create'} employee`);
      }

      if (!isEditMode) {
        setWpUserId('');
        setFirstName('');
        setLastName('');
        setEmail('');
        setStatus('active');
        setHireDate('');
        setManagerId('');
        setTeamIds([]);
        setMalleableData({});
      }
      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="pet-form-container" style={{ padding: '20px', background: '#f9f9f9', border: '1px solid #ddd', marginBottom: '20px' }}>
      <h3>{isEditMode ? 'Edit Employee' : 'Add New Employee'}</h3>

      {isEditMode && !hideTabNavigation && (
        <div className="nav-tab-wrapper" style={{ marginBottom: '20px' }}>
          <button 
            type="button"
            className={`nav-tab ${currentTab === 'details' ? 'nav-tab-active' : ''}`}
            onClick={() => handleTabChange('details')}
          >
            Details
          </button>
          <button 
            type="button"
            className={`nav-tab ${currentTab === 'roles' ? 'nav-tab-active' : ''}`}
            onClick={() => handleTabChange('roles')}
          >
            Roles
          </button>
          <button 
            type="button"
            className={`nav-tab ${currentTab === 'skills' ? 'nav-tab-active' : ''}`}
            onClick={() => handleTabChange('skills')}
          >
            Skills
          </button>
          <button 
            type="button"
            className={`nav-tab ${currentTab === 'certifications' ? 'nav-tab-active' : ''}`}
            onClick={() => handleTabChange('certifications')}
          >
            Certifications
          </button>
          <button 
            type="button"
            className={`nav-tab ${currentTab === 'kpis' ? 'nav-tab-active' : ''}`}
            onClick={() => handleTabChange('kpis')}
          >
            KPIs
          </button>
          <button 
            type="button"
            className={`nav-tab ${currentTab === 'reviews' ? 'nav-tab-active' : ''}`}
            onClick={() => handleTabChange('reviews')}
          >
            Reviews
          </button>
        </div>
      )}

      {currentTab === 'roles' && initialData ? (
        <EmployeeRoles
          employee={initialData}
          allowAssignments={roleAssignmentsEditable}
          onAssignmentsChanged={onRoleAssignmentsChanged}
        />
      ) : currentTab === 'skills' && initialData ? (
        <EmployeeSkills employee={initialData} />
      ) : currentTab === 'certifications' && initialData ? (
        <EmployeeCertifications employee={initialData} />
      ) : currentTab === 'kpis' && initialData ? (
        <PersonKpis employee={initialData} />
      ) : currentTab === 'reviews' && initialData ? (
        <EmployeeReviews employee={initialData} />
      ) : (
        <>
          {fetchError && <div style={{ color: 'orange', marginBottom: '10px' }}>Schema Error: {fetchError}</div>}
          {error && <div style={{ color: 'red', marginBottom: '10px' }}>{error}</div>}
          
          <form onSubmit={handleSubmit}>
            {currentTab === 'details' && detailsFocus !== 'all' && (
              <p style={{ marginTop: 0, color: '#4f6178', fontWeight: 600 }}>
                {detailsFocus === 'identity' ? 'Identity Details' : 'Org Placement Details'}
              </p>
            )}

            {showIdentityFields && (
              <>
                <div style={{ marginBottom: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>WP User ID:</label>
                  {isEditMode ? (
                    <input 
                      type="text" 
                      value={wpUserId} 
                      disabled 
                      style={{ width: '100%', padding: '8px', background: '#eee', border: '1px solid #ddd', borderRadius: '4px' }} 
                    />
                  ) : (
                    <select 
                      value={wpUserId} 
                      onChange={handleUserSelect}
                      required
                      style={{ width: '100%', padding: '8px', border: '1px solid #ddd', borderRadius: '4px' }}
                    >
                      <option value="">-- Select a User --</option>
                      {availableUsers.map(user => (
                        <option key={user.ID} value={user.ID}>
                          {user.user_login} ({user.display_name})
                        </option>
                      ))}
                    </select>
                  )}
                </div>
                <div style={{ marginBottom: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px' }}>First Name:</label>
                  <input 
                    type="text" 
                    value={firstName} 
                    onChange={(e) => setFirstName(e.target.value)} 
                    required 
                    style={{ width: '100%', maxWidth: '400px' }}
                  />
                </div>
                <div style={{ marginBottom: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px' }}>Last Name:</label>
                  <input 
                    type="text" 
                    value={lastName} 
                    onChange={(e) => setLastName(e.target.value)} 
                    required 
                    style={{ width: '100%', maxWidth: '400px' }}
                  />
                </div>
                <div style={{ marginBottom: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px' }}>Email:</label>
                  <input 
                    type="email" 
                    value={email} 
                    onChange={(e) => setEmail(e.target.value)} 
                    required 
                    style={{ width: '100%', maxWidth: '400px' }}
                  />
                </div>
                <div style={{ marginBottom: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px' }}>Status:</label>
                  <select 
                    value={status} 
                    onChange={(e) => setStatus(e.target.value)} 
                    required 
                    style={{ width: '100%', maxWidth: '400px', padding: '8px', border: '1px solid #ddd', borderRadius: '4px' }}
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="terminated">Terminated</option>
                  </select>
                </div>
              </>
            )}

            {showOrgFields && (
              <>
                <div style={{ marginBottom: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px' }}>Hire Date:</label>
                  <input 
                    type="date" 
                    value={hireDate} 
                    onChange={(e) => setHireDate(e.target.value)} 
                    style={{ width: '100%', maxWidth: '400px' }}
                  />
                </div>
                <div style={{ marginBottom: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px' }}>Manager:</label>
                  <select 
                    value={managerId} 
                    onChange={(e) => setManagerId(e.target.value)} 
                    style={{ width: '100%', maxWidth: '400px', padding: '8px', border: '1px solid #ddd', borderRadius: '4px' }}
                  >
                    <option value="">-- No Manager --</option>
                    {employees
                      .filter(emp => !initialData || emp.id !== initialData.id)
                      .map(emp => (
                        <option key={emp.id} value={emp.id}>
                          {emp.firstName} {emp.lastName}
                        </option>
                      ))
                    }
                  </select>
                </div>
                <div style={{ marginBottom: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px' }}>Teams (Member):</label>
                  <select 
                    multiple
                    value={teamIds.map(String)} 
                    onChange={(e) => {
                      const selectedOptions = Array.from(e.target.selectedOptions, option => parseInt(option.value));
                      setTeamIds(selectedOptions);
                    }} 
                    style={{ width: '100%', maxWidth: '400px', padding: '8px', border: '1px solid #ddd', borderRadius: '4px', height: '150px' }}
                  >
                    {flattenTeams(teams).map(team => (
                      <option key={team.id} value={team.id}>
                        {team.name}
                      </option>
                    ))}
                  </select>
                  <p className="description" style={{ fontSize: '12px', color: '#666', marginTop: '4px' }}>Hold Ctrl (Windows) or Cmd (Mac) to select multiple teams.</p>
                </div>
              </>
            )}

            {activeSchema && showAdditionalInfo && (
          <div style={{ marginBottom: '20px', padding: '15px', background: '#fff', border: '1px solid #eee' }}>
            <h4 style={{ marginTop: 0 }}>Additional Information</h4>
            <MalleableFieldsRenderer 
              schema={activeSchema}
              values={malleableData}
              onChange={handleMalleableChange}
            />
          </div>
        )}

        <div style={{ display: 'flex', gap: '10px' }}>
          <button type="submit" className="button button-primary" disabled={loading}>
            {loading ? 'Saving...' : (isEditMode ? 'Update Employee' : 'Create Employee')}
          </button>
          <button type="button" className="button" onClick={onCancel} disabled={loading}>
            Cancel
          </button>
        </div>
      </form>
      </>
      )}
    </div>
  );
};

export default EmployeeForm;
