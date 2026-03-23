import React, { useEffect, useMemo, useState } from 'react';
import { ActivityLog, Certification, Employee, PersonCertification, Project, Role, Skill, Team, Ticket } from '../types';
import PageShell from './foundation/PageShell';
import Panel from './foundation/Panel';
import useToast from './foundation/useToast';

type Assignment = {
  id: number;
  employee_id: number;
  role_id: number;
  start_date: string;
  end_date: string | null;
  allocation_pct: number;
  status: string;
};

type EmployeeSkill = {
  id: number;
  employee_id: number;
  skill_id: number;
  self_rating: number;
  manager_rating: number;
  effective_date: string;
  skill_name?: string;
};

type AvailabilityState = 'available' | 'busy' | 'limited' | 'out';

const availabilityColors: Record<AvailabilityState, string> = {
  available: '#28a745',
  busy: '#0d6efd',
  limited: '#f59e0b',
  out: '#dc3545',
};

const MyProfile: React.FC = () => {
  const toast = useToast();
  const apiUrl = window.petSettings?.apiUrl ?? '';
  const nonce = window.petSettings?.nonce ?? '';
  const currentWpUserId = Number(window.petSettings?.currentUserId ?? 0);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [employees, setEmployees] = useState<Employee[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [skills, setSkills] = useState<Skill[]>([]);
  const [employeeSkills, setEmployeeSkills] = useState<EmployeeSkill[]>([]);
  const [certifications, setCertifications] = useState<Certification[]>([]);
  const [employeeCertifications, setEmployeeCertifications] = useState<PersonCertification[]>([]);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [workItems, setWorkItems] = useState<any[]>([]);
  const [activity, setActivity] = useState<ActivityLog[]>([]);

  const [editingIdentity, setEditingIdentity] = useState(false);
  const [editingAvailability, setEditingAvailability] = useState(false);
  const [savingIdentity, setSavingIdentity] = useState(false);
  const [savingAvailability, setSavingAvailability] = useState(false);
  const [lastAvailabilitySavedAt, setLastAvailabilitySavedAt] = useState<string | null>(null);

  const [identityDraft, setIdentityDraft] = useState({
    firstName: '',
    lastName: '',
    email: '',
    primaryRoleId: '',
    teamIds: [] as number[],
  });

  const [availabilityDraft, setAvailabilityDraft] = useState({
    state: 'available' as AvailabilityState,
    workPattern: '',
    nextAvailable: '',
    locationNote: '',
  });

  const [skillDraft, setSkillDraft] = useState({ skillId: '', selfRating: 3, managerRating: 3 });
  const [certDraft, setCertDraft] = useState({ certificationId: '', obtainedDate: '', expiryDate: '' });
  const [savingSkill, setSavingSkill] = useState(false);
  const [savingCert, setSavingCert] = useState(false);

  const me = useMemo(
    () => employees.find((employee) => Number(employee.wpUserId) === currentWpUserId) ?? null,
    [employees, currentWpUserId],
  );

  const activeAssignment = useMemo(
    () => assignments.find((assignment) => assignment.status === 'active') ?? null,
    [assignments],
  );

  const teamNameMap = useMemo(() => {
    const map = new Map<number, string>();
    const stack = [...teams];
    while (stack.length > 0) {
      const team = stack.shift()!;
      map.set(team.id, team.name);
      if (team.children?.length) {
        stack.push(...team.children);
      }
    }
    return map;
  }, [teams]);

  const roleNameMap = useMemo(() => {
    const map = new Map<number, string>();
    roles.forEach((role) => map.set(role.id, role.name));
    return map;
  }, [roles]);

  const enrichedSkills = useMemo(
    () => employeeSkills.map((entry) => ({
      ...entry,
      skill_name: skills.find((skill) => skill.id === entry.skill_id)?.name || `Skill #${entry.skill_id}`,
    })),
    [employeeSkills, skills],
  );

  const enrichedCertifications = useMemo(
    () => employeeCertifications.map((entry) => {
      const cert = certifications.find((candidate) => candidate.id === entry.certification_id);
      return {
        ...entry,
        certification_name: cert?.name || `Certification #${entry.certification_id}`,
      };
    }),
    [employeeCertifications, certifications],
  );

  const responsibilitySummary = useMemo(() => {
    const ticketSourceIds = new Set(
      workItems
        .filter((item) => String(item.sourceType || item.source_type || '').toLowerCase() === 'ticket')
        .map((item) => Number(item.sourceId || item.source_id))
        .filter((id) => !Number.isNaN(id) && id > 0),
    );
    const projectSourceIds = new Set(
      workItems
        .filter((item) => String(item.sourceType || item.source_type || '').toLowerCase() === 'project')
        .map((item) => Number(item.sourceId || item.source_id))
        .filter((id) => !Number.isNaN(id)),
    );
    const taskSourceIds = new Set(
      workItems
        .filter((item) => String(item.sourceType || item.source_type || '').toLowerCase() === 'task')
        .map((item) => Number(item.sourceId || item.source_id))
        .filter((id) => !Number.isNaN(id)),
    );
    const relatedTickets = ticketSourceIds.size > 0
      ? tickets.filter((ticket) => ticketSourceIds.has(Number(ticket.id)))
      : tickets;
    const projectIdsFromTickets = new Set(
      relatedTickets
        .map((ticket) => Number((ticket as any).projectId))
        .filter((id) => !Number.isNaN(id) && id > 0),
    );
    const relatedProjects = projects.filter((project) => projectSourceIds.has(project.id) || projectIdsFromTickets.has(project.id));
    const rankedTickets = [...relatedTickets].sort((a, b) => {
      const rank = (ticket: Ticket): number => {
        const priority = String(ticket.priority || '').toLowerCase();
        if (priority === 'critical') return 300;
        if (priority === 'high') return 220;
        if (priority === 'medium') return 140;
        if (priority === 'low') return 80;
        return 0;
      };
      return rank(b) - rank(a);
    });

    return {
      assignedTickets: relatedTickets.length,
      assignedProjects: relatedProjects.length,
      assignedTasks: taskSourceIds.size,
      openWorkItems: workItems.length,
      topTickets: rankedTickets.slice(0, 3),
      topProjects: relatedProjects.slice(0, 3),
    };
  }, [projects, tickets, workItems]);

  const availabilityState = useMemo<AvailabilityState>(() => {
    if (!me) return 'available';
    const value = String(me.malleableData?.availability_state || 'available').toLowerCase();
    if (value === 'busy' || value === 'limited' || value === 'out') return value;
    return 'available';
  }, [me]);

  const loadBaseData = async () => {
    const hdrs = { 'X-WP-Nonce': nonce };
    const [employeesRes, teamsRes, rolesRes, projectsRes] = await Promise.all([
      fetch(`${apiUrl}/employees`, { headers: hdrs }),
      fetch(`${apiUrl}/teams`, { headers: hdrs }),
      fetch(`${apiUrl}/roles`, { headers: hdrs }),
      fetch(`${apiUrl}/projects`, { headers: hdrs }).catch(() => null),
    ]);

    const employeesData = employeesRes.ok ? await employeesRes.json() : [];
    const teamsData = teamsRes.ok ? await teamsRes.json() : [];
    const rolesData = rolesRes.ok ? await rolesRes.json() : [];
    const projectsData = projectsRes?.ok ? await projectsRes.json() : [];

    setEmployees(Array.isArray(employeesData) ? employeesData : []);
    setTeams(Array.isArray(teamsData) ? teamsData : []);
    setRoles(Array.isArray(rolesData) ? rolesData : []);
    setProjects(Array.isArray(projectsData) ? projectsData : []);

    return Array.isArray(employeesData) ? employeesData : [];
  };

  const loadProfileData = async (employee: Employee) => {
    const hdrs = { 'X-WP-Nonce': nonce };
    const [assignRes, ticketRes, workRes, activityRes, skillsRes, empSkillsRes, certRes, empCertRes] = await Promise.all([
      fetch(`${apiUrl}/assignments?employee_id=${employee.id}`, { headers: hdrs }).catch(() => null),
      fetch(`${apiUrl}/tickets`, { headers: hdrs }).catch(() => null),
      fetch(`${apiUrl}/work/my-items`, { headers: hdrs }).catch(async () => fetch(`${apiUrl}/work-items?assigned_user_id=${encodeURIComponent(String(employee.wpUserId))}`, { headers: hdrs }).catch(() => null)),
      fetch(`${apiUrl}/activity?limit=10`, { headers: hdrs }).catch(() => null),
      fetch(`${apiUrl}/skills`, { headers: hdrs }).catch(() => null),
      fetch(`${apiUrl}/employees/${employee.id}/skills`, { headers: hdrs }).catch(() => null),
      fetch(`${apiUrl}/certifications`, { headers: hdrs }).catch(() => null),
      fetch(`${apiUrl}/employees/${employee.id}/certifications`, { headers: hdrs }).catch(() => null),
    ]);

    const assignData = assignRes?.ok ? await assignRes.json() : [];
    const ticketData = ticketRes?.ok ? await ticketRes.json() : [];
    const workData = workRes?.ok ? await workRes.json() : [];
    const activityData = activityRes?.ok ? await activityRes.json() : [];
    const skillsData = skillsRes?.ok ? await skillsRes.json() : [];
    const empSkillsData = empSkillsRes?.ok ? await empSkillsRes.json() : [];
    const certData = certRes?.ok ? await certRes.json() : [];
    const empCertData = empCertRes?.ok ? await empCertRes.json() : [];

    setAssignments(Array.isArray(assignData) ? assignData : []);
    setTickets(Array.isArray(ticketData) ? ticketData : []);
    setWorkItems(Array.isArray(workData) ? workData : []);
    setActivity(Array.isArray(activityData?.items) ? activityData.items : Array.isArray(activityData) ? activityData : []);
    setSkills(Array.isArray(skillsData) ? skillsData : []);
    setEmployeeSkills(Array.isArray(empSkillsData) ? empSkillsData : []);
    setCertifications(Array.isArray(certData) ? certData : []);
    setEmployeeCertifications(Array.isArray(empCertData) ? empCertData : []);
  };

  const refresh = async () => {
    try {
      setLoading(true);
      setError(null);
      const employeesData = await loadBaseData();
      const employee = employeesData.find((candidate: Employee) => Number(candidate.wpUserId) === currentWpUserId);
      if (!employee) {
        setError('No employee profile is linked to your user.');
        return;
      }
      await loadProfileData(employee);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load profile');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!apiUrl || !nonce) {
      setError('API settings missing');
      setLoading(false);
      return;
    }
    refresh();
  }, []);

  useEffect(() => {
    if (!me) return;
    setIdentityDraft({
      firstName: me.firstName,
      lastName: me.lastName,
      email: me.email,
      primaryRoleId: activeAssignment?.role_id ? String(activeAssignment.role_id) : '',
      teamIds: me.teamIds || [],
    });
    setAvailabilityDraft({
      state: availabilityState,
      workPattern: String(me.malleableData?.availability_pattern || ''),
      nextAvailable: String(me.malleableData?.next_available_note || ''),
      locationNote: String(me.malleableData?.location_note || ''),
    });
  }, [me?.id, activeAssignment?.id, availabilityState]);

  const saveEmployee = async (updated: Partial<Employee> & { malleableData?: Record<string, any> }) => {
    if (!me) return;
    const payload = {
      wpUserId: me.wpUserId,
      firstName: updated.firstName ?? me.firstName,
      lastName: updated.lastName ?? me.lastName,
      email: updated.email ?? me.email,
      status: updated.status ?? me.status ?? 'active',
      hireDate: updated.hireDate ?? me.hireDate ?? null,
      managerId: updated.managerId ?? me.managerId ?? null,
      teamIds: updated.teamIds ?? me.teamIds ?? [],
      malleableData: updated.malleableData ?? me.malleableData ?? {},
    };

    const response = await fetch(`${apiUrl}/employees/${me.id}`, {
      method: 'PUT',
      headers: {
        'X-WP-Nonce': nonce,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });
    if (!response.ok) {
      const text = await response.text();
      throw new Error(text || 'Failed to save profile changes');
    }
  };

  const saveIdentity = async () => {
    if (!me) return;
    try {
      setSavingIdentity(true);
      await saveEmployee({
        firstName: identityDraft.firstName,
        lastName: identityDraft.lastName,
        email: identityDraft.email,
        teamIds: identityDraft.teamIds,
      });

      const selectedRoleId = identityDraft.primaryRoleId ? Number(identityDraft.primaryRoleId) : null;
      const currentRoleId = activeAssignment?.role_id ?? null;
      if (selectedRoleId && selectedRoleId !== currentRoleId) {
        if (activeAssignment && activeAssignment.status === 'active') {
          await fetch(`${apiUrl}/assignments/${activeAssignment.id}/end`, {
            method: 'POST',
            headers: {
              'X-WP-Nonce': nonce,
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ end_date: new Date().toISOString().slice(0, 10) }),
          });
        }
        await fetch(`${apiUrl}/assignments`, {
          method: 'POST',
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            employee_id: me.id,
            role_id: selectedRoleId,
            start_date: new Date().toISOString().slice(0, 10),
            allocation_pct: 100,
          }),
        });
      }

      await refresh();
      setEditingIdentity(false);
      toast.success('Identity and role context saved');
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to save identity');
    } finally {
      setSavingIdentity(false);
    }
  };

  const saveAvailability = async () => {
    if (!me) return;
    try {
      setSavingAvailability(true);
      await saveEmployee({
        malleableData: {
          ...(me.malleableData || {}),
          availability_state: availabilityDraft.state,
          availability_pattern: availabilityDraft.workPattern,
          next_available_note: availabilityDraft.nextAvailable,
          location_note: availabilityDraft.locationNote,
        },
      });
      await refresh();
      setEditingAvailability(false);
      setLastAvailabilitySavedAt(new Date().toISOString());
      toast.success('Availability updated');
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to save availability');
    } finally {
      setSavingAvailability(false);
    }
  };

  const addSkill = async () => {
    if (!me || !skillDraft.skillId) return;
    try {
      setSavingSkill(true);
      const response = await fetch(`${apiUrl}/employees/${me.id}/skills`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': nonce,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          skill_id: Number(skillDraft.skillId),
          self_rating: skillDraft.selfRating,
          manager_rating: skillDraft.managerRating,
          effective_date: new Date().toISOString().slice(0, 10),
        }),
      });
      if (!response.ok) {
        throw new Error('Failed to save skill');
      }
      setSkillDraft({ skillId: '', selfRating: 3, managerRating: 3 });
      await refresh();
      toast.success('Capability updated');
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to add capability');
    } finally {
      setSavingSkill(false);
    }
  };

  const addCertification = async () => {
    if (!me || !certDraft.certificationId || !certDraft.obtainedDate) return;
    try {
      setSavingCert(true);
      const response = await fetch(`${apiUrl}/employees/${me.id}/certifications`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': nonce,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          certification_id: Number(certDraft.certificationId),
          obtained_date: certDraft.obtainedDate,
          expiry_date: certDraft.expiryDate || null,
        }),
      });
      if (!response.ok) {
        throw new Error('Failed to save certification');
      }
      setCertDraft({ certificationId: '', obtainedDate: '', expiryDate: '' });
      await refresh();
      toast.success('Certification added');
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to add certification');
    } finally {
      setSavingCert(false);
    }
  };

  const availabilityBadge = availabilityState.toUpperCase();
  const fullName = me ? `${me.firstName} ${me.lastName}`.trim() : 'Unknown User';
  const initials = fullName.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
  const teamLabels = (me?.teamIds || []).map((id) => teamNameMap.get(id) || `Team #${id}`);
  const primaryRoleLabel = activeAssignment?.role_id ? roleNameMap.get(activeAssignment.role_id) || `Role #${activeAssignment.role_id}` : 'Unassigned';

  if (loading) {
    return (
      <PageShell title="My Profile" subtitle="Loading your staff context…" className="pet-my-profile">
        <Panel><div className="pd-empty">Loading profile…</div></Panel>
      </PageShell>
    );
  }

  if (error || !me) {
    return (
      <PageShell title="My Profile" subtitle="Staff context unavailable" className="pet-my-profile">
        <Panel><div className="pd-error">{error || 'No employee found for this user.'}</div></Panel>
      </PageShell>
    );
  }

  return (
    <PageShell
      title="My Profile"
      subtitle="Identity, capability, availability, and operational context in one place."
      className="pet-my-profile"
      actions={<button type="button" className="button" onClick={refresh}>Refresh</button>}
      testId="my-profile-shell"
    >
      <Panel>
        <div className="pet-profile-hero">
          <div className="pet-profile-hero-avatar">
            {initials || 'ME'}
          </div>
          <div className="pet-profile-hero-main">
            <div className="pet-profile-hero-name">{fullName}</div>
            <div className="pet-profile-hero-subtitle">
              {primaryRoleLabel} · {teamLabels.length > 0 ? teamLabels.join(', ') : 'No team assigned'}
            </div>
          </div>
          <span
            className="pd-badge pet-profile-availability-badge"
            style={{ color: '#fff', background: availabilityColors[availabilityState], borderColor: availabilityColors[availabilityState] }}
          >
            {availabilityBadge}
          </span>
          <a className="button button-primary" href="/wp-admin/admin.php?page=pet-my-work">Open My Work</a>
        </div>
      </Panel>

      <Panel>
        <div className="pet-profile-section-header">
          <h3>Identity & Role</h3>
          {!editingIdentity && <button type="button" className="button" onClick={() => setEditingIdentity(true)}>Edit</button>}
        </div>
        {editingIdentity ? (
          <div className="pet-profile-edit-mode pet-profile-two-col-grid">
            <label>First Name<input value={identityDraft.firstName} onChange={(e) => setIdentityDraft((prev) => ({ ...prev, firstName: e.target.value }))} /></label>
            <label>Last Name<input value={identityDraft.lastName} onChange={(e) => setIdentityDraft((prev) => ({ ...prev, lastName: e.target.value }))} /></label>
            <label>Email<input type="email" value={identityDraft.email} onChange={(e) => setIdentityDraft((prev) => ({ ...prev, email: e.target.value }))} /></label>
            <label>Primary Role
              <select value={identityDraft.primaryRoleId} onChange={(e) => setIdentityDraft((prev) => ({ ...prev, primaryRoleId: e.target.value }))}>
                <option value="">Unassigned</option>
                {roles.map((role) => <option key={role.id} value={String(role.id)}>{role.name}</option>)}
              </select>
            </label>
            <label style={{ gridColumn: '1 / span 2' }}>Teams
              <select
                multiple
                value={identityDraft.teamIds.map(String)}
                onChange={(e) => {
                  const next = Array.from(e.target.selectedOptions).map((opt) => Number(opt.value));
                  setIdentityDraft((prev) => ({ ...prev, teamIds: next }));
                }}
                style={{ minHeight: 110 }}
              >
                {Array.from(teamNameMap.entries()).map(([id, name]) => <option key={id} value={String(id)}>{name}</option>)}
              </select>
            </label>
            <div className="pet-profile-edit-actions">
              <button type="button" className="button button-primary" disabled={savingIdentity} onClick={saveIdentity}>{savingIdentity ? 'Saving…' : 'Save'}</button>
              <button type="button" className="button" onClick={() => setEditingIdentity(false)}>Cancel</button>
            </div>
          </div>
        ) : (
          <div className="pet-profile-two-col-grid">
            <div><strong>Name</strong><div>{fullName}</div></div>
            <div><strong>Email</strong><div>{me.email}</div></div>
            <div><strong>Primary Role</strong><div>{primaryRoleLabel}</div></div>
            <div><strong>Teams</strong><div>{teamLabels.length ? teamLabels.join(', ') : 'No team assignment'}</div></div>
          </div>
        )}
      </Panel>

      <Panel>
        <div className="pet-profile-section-header">
          <h3>Teams / Capabilities</h3>
        </div>
        <div className="pet-profile-two-col-grid">
          <div>
            <div className="pet-profile-subheading">Capability Ratings</div>
            <div className="pet-profile-chip-wrap">
              {enrichedSkills.length > 0 ? enrichedSkills.map((skill) => (
                <span key={skill.id} className="pd-badge pet-profile-chip pet-profile-chip--capability">{skill.skill_name} ({Math.max(skill.self_rating, skill.manager_rating)}/5)</span>
              )) : <span className="pd-empty">No capabilities rated.</span>}
            </div>
            <div className="pet-profile-form-stack">
              <select value={skillDraft.skillId} onChange={(e) => setSkillDraft((prev) => ({ ...prev, skillId: e.target.value }))}>
                <option value="">Add capability…</option>
                {skills.map((skill) => <option key={skill.id} value={String(skill.id)}>{skill.name}</option>)}
              </select>
              <div className="pet-profile-inline-fields">
                <input type="number" min={0} max={5} value={skillDraft.selfRating} onChange={(e) => setSkillDraft((prev) => ({ ...prev, selfRating: Number(e.target.value) }))} />
                <input type="number" min={0} max={5} value={skillDraft.managerRating} onChange={(e) => setSkillDraft((prev) => ({ ...prev, managerRating: Number(e.target.value) }))} />
                <button type="button" className="button" disabled={savingSkill} onClick={addSkill}>{savingSkill ? 'Saving…' : 'Add'}</button>
              </div>
            </div>
          </div>
          <div>
            <div className="pet-profile-subheading">Certifications</div>
            <div className="pet-profile-chip-wrap">
              {enrichedCertifications.length > 0 ? enrichedCertifications.map((cert: any) => (
                <span key={cert.id} className="pd-badge pet-profile-chip">{cert.certification_name}</span>
              )) : <span className="pd-empty">No certifications assigned.</span>}
            </div>
            <div className="pet-profile-form-stack">
              <select value={certDraft.certificationId} onChange={(e) => setCertDraft((prev) => ({ ...prev, certificationId: e.target.value }))}>
                <option value="">Add certification…</option>
                {certifications.map((cert) => <option key={cert.id} value={String(cert.id)}>{cert.name}</option>)}
              </select>
              <div className="pet-profile-inline-fields">
                <input type="date" value={certDraft.obtainedDate} onChange={(e) => setCertDraft((prev) => ({ ...prev, obtainedDate: e.target.value }))} />
                <input type="date" value={certDraft.expiryDate} onChange={(e) => setCertDraft((prev) => ({ ...prev, expiryDate: e.target.value }))} />
                <button type="button" className="button" disabled={savingCert} onClick={addCertification}>{savingCert ? 'Saving…' : 'Add'}</button>
              </div>
            </div>
          </div>
        </div>
      </Panel>

      <Panel>
        <div className="pet-profile-section-header">
          <h3>Availability / Work Pattern</h3>
          {!editingAvailability && <button type="button" className="button" onClick={() => setEditingAvailability(true)}>Edit</button>}
        </div>
        {!editingAvailability && lastAvailabilitySavedAt && (
          <div className="pet-profile-save-note">
            Saved at {new Date(lastAvailabilitySavedAt).toLocaleTimeString()}
          </div>
        )}
        {editingAvailability ? (
          <div className="pet-profile-edit-mode pet-profile-two-col-grid">
            <label>Status
              <select value={availabilityDraft.state} onChange={(e) => setAvailabilityDraft((prev) => ({ ...prev, state: e.target.value as AvailabilityState }))}>
                <option value="available">Available</option>
                <option value="busy">Busy</option>
                <option value="limited">Limited</option>
                <option value="out">Out</option>
              </select>
            </label>
            <label>Work Pattern<input value={availabilityDraft.workPattern} onChange={(e) => setAvailabilityDraft((prev) => ({ ...prev, workPattern: e.target.value }))} placeholder="e.g., Mon-Fri 08:00-16:00" /></label>
            <label>Next Available<input value={availabilityDraft.nextAvailable} onChange={(e) => setAvailabilityDraft((prev) => ({ ...prev, nextAvailable: e.target.value }))} placeholder="e.g., After 14:00" /></label>
            <label>Location Note<input value={availabilityDraft.locationNote} onChange={(e) => setAvailabilityDraft((prev) => ({ ...prev, locationNote: e.target.value }))} placeholder="e.g., On-site Johannesburg" /></label>
            <div className="pet-profile-edit-actions">
              <button type="button" className="button button-primary" disabled={savingAvailability} onClick={saveAvailability}>{savingAvailability ? 'Saving…' : 'Save'}</button>
              <button type="button" className="button" onClick={() => setEditingAvailability(false)}>Cancel</button>
            </div>
          </div>
        ) : (
          <div className="pet-profile-two-col-grid">
            <div><strong>Status</strong><div>{availabilityDraft.state}</div></div>
            <div><strong>Work Pattern</strong><div>{availabilityDraft.workPattern || 'Not set'}</div></div>
            <div><strong>Next Available</strong><div>{availabilityDraft.nextAvailable || 'Not set'}</div></div>
            <div><strong>Location Note</strong><div>{availabilityDraft.locationNote || 'Not set'}</div></div>
          </div>
        )}
      </Panel>

      <Panel>
        <div className="pet-profile-section-header">
          <h3>Responsibilities & Current Work</h3>
        </div>
        <div className="pet-profile-stats-grid">
          <div className="pd-card pet-profile-stat-card"><strong>{responsibilitySummary.assignedTickets}</strong><div>Assigned Tickets</div></div>
          <div className="pd-card pet-profile-stat-card"><strong>{responsibilitySummary.assignedProjects}</strong><div>Assigned Projects</div></div>
          <div className="pd-card pet-profile-stat-card"><strong>{responsibilitySummary.assignedTasks}</strong><div>Assigned Tasks</div></div>
          <div className="pd-card pet-profile-stat-card"><strong>{responsibilitySummary.openWorkItems}</strong><div>Open Work Items</div></div>
        </div>
        <div className="pet-profile-two-col-grid">
          <div>
            <div className="pet-profile-subheading">Top Ticket Responsibilities</div>
            {responsibilitySummary.topTickets.length > 0 ? responsibilitySummary.topTickets.map((ticket) => (
              <a key={ticket.id} href={`/wp-admin/admin.php?page=pet-support#ticket=${ticket.id}`} className="pd-card pd-clickable pet-profile-linked-item">
                <div className="pet-profile-linked-item-title">{ticket.subject}</div>
                <div className="pet-profile-linked-item-meta">Ticket #{ticket.id} · {ticket.status}</div>
              </a>
            )) : <div className="pd-empty">No assigned tickets.</div>}
          </div>
          <div>
            <div className="pet-profile-subheading">Top Project Responsibilities</div>
            {responsibilitySummary.topProjects.length > 0 ? responsibilitySummary.topProjects.map((project) => (
              <a key={project.id} href={`/wp-admin/admin.php?page=pet-delivery#project=${project.id}`} className="pd-card pd-clickable pet-profile-linked-item">
                <div className="pet-profile-linked-item-title">{project.name}</div>
                <div className="pet-profile-linked-item-meta">Project #{project.id} · {project.state}</div>
              </a>
            )) : <div className="pd-empty">No assigned projects.</div>}
          </div>
        </div>
      </Panel>

      <Panel>
        <div className="pet-profile-section-header">
          <h3>Recent Activity / Context</h3>
        </div>
        {activity.length > 0 ? (
          <div className="pet-profile-form-stack">
            {activity.slice(0, 8).map((entry) => (
              <div key={entry.id} className="pd-card pet-profile-activity-row">
                <div className="pet-profile-linked-item-title">{entry.headline}</div>
                <div className="pet-profile-linked-item-meta">
                  {entry.reference_type ? `${entry.reference_type} #${entry.reference_id}` : 'General'} · {entry.occurred_at}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="pd-empty">No recent activity available.</div>
        )}
      </Panel>
    </PageShell>
  );
};

export default MyProfile;
