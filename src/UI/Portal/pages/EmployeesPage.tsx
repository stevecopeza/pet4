/**
 * EmployeesPage — Portal-native employee management (v2)
 *
 * Implements the full-page detail pattern chosen in docs/42_staff_portal/03_ux_improvement_proposal.md.
 *
 * Routing (internal to the component; PortalApp routes #employees* here):
 *   #employees         → Employee directory list
 *   #employees/:id     → Full-page detail for employee :id (6 tabs)
 *
 * Tab structure for detail view:
 *   Identity · Organisation · Roles · Skills · Certifications · Reviews
 *
 * Capabilities:
 *   canEdit (pet_hr | pet_manager | manage_options): all tabs read/write
 *   canAssignRole (pet_manager | manage_options): can assign portal roles
 *   Read-only for users who have access but don't match above (currently none —
 *   the section is gated to HR/Manager at the PortalApp level).
 */
import React, { useState, useEffect, useCallback } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Employee {
  id: number;
  wpUserId: number;
  avatarUrl: string;
  firstName: string;
  lastName: string;
  displayName: string;
  email: string;
  status: string;
  hireDate: string | null;
  managerId: number | null;
  teamIds: number[];
  createdAt: string;
  archivedAt: string | null;
}

interface RoleAssignment {
  id: number;
  employee_id: number;
  role_id: number;
  role_name?: string;
  start_date: string;
  end_date: string | null;
  allocation_pct: number;
  status: string;
}

interface SkillEntry {
  id: number;
  skill_id: number;
  skill_name: string;
  self_rating: number;
  manager_rating: number;
  effective_date: string;
}

interface Skill {
  id: number;
  name: string;
}

interface CertEntry {
  id: number;
  certification_id: number;
  certification_name: string;
  issuing_body: string;
  obtained_date: string;
  expiry_date: string | null;
  evidence_url: string | null;
  status: string;
}

interface Certification {
  id: number;
  name: string;
  issuing_body: string;
}

interface ReviewEntry {
  id: number;
  employee_id: number;
  period_start: string;
  period_end: string;
  status: string;
  content: any;
  created_at: string;
}

const PORTAL_ROLES = [
  { value: '', label: 'No portal access' },
  { value: 'pet_sales', label: 'Sales' },
  { value: 'pet_hr', label: 'HR' },
  { value: 'pet_manager', label: 'Manager' },
] as const;

type DetailTab = 'identity' | 'organisation' | 'roles' | 'skills' | 'certifications' | 'reviews';

// ── API helpers ───────────────────────────────────────────────────────────────

function apiBase(): string {
  return (window as any).petSettings?.apiUrl ?? '/wp-json/pet/v1';
}
function apiHeaders(): HeadersInit {
  return {
    'Content-Type': 'application/json',
    'X-WP-Nonce': (window as any).petSettings?.nonce ?? '',
  };
}
async function apiFetch<T>(path: string, opts: RequestInit = {}): Promise<T> {
  const res = await fetch(`${apiBase()}${path}`, {
    ...opts,
    headers: { ...apiHeaders(), ...(opts.headers ?? {}) },
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error((body as any).message ?? `API error ${res.status}`);
  }
  return res.json() as Promise<T>;
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** Parse #employees/123 → 123, #employees → null */
function parseEmployeeHash(hash: string): number | null {
  const m = hash.match(/^#employees\/(\d+)$/);
  return m ? parseInt(m[1], 10) : null;
}

// ── Shared primitives ─────────────────────────────────────────────────────────

const inputStyle: React.CSSProperties = {
  width: '100%', padding: '8px 12px', border: '1px solid #e5e7eb',
  borderRadius: 8, fontSize: 13, outline: 'none', boxSizing: 'border-box',
  fontFamily: 'inherit', background: '#fff',
};

function FormField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#374151', marginBottom: 5 }}>
        {label}
      </label>
      {children}
    </div>
  );
}

function EmployeeAvatar({ url, name, size = 36 }: { url: string; name: string; size?: number }) {
  const [errored, setErrored] = useState(false);
  const initials = name.trim().split(/\s+/).map(w => w[0] ?? '').join('').slice(0, 2).toUpperCase();
  if (errored) {
    return (
      <span style={{
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        width: size, height: size, borderRadius: '50%', background: '#6366f1',
        color: '#fff', fontWeight: 700, fontSize: size * 0.35, flexShrink: 0,
      }}>{initials}</span>
    );
  }
  return (
    <img src={url} alt={name} onError={() => setErrored(true)}
      style={{ width: size, height: size, borderRadius: '50%', flexShrink: 0, objectFit: 'cover' }} />
  );
}

function StatusBadge({ status }: { status: string }) {
  return <span className={`portal-badge portal-badge-${status}`}>{status}</span>;
}

function StarRating({ value, max = 5 }: { value: number; max?: number }) {
  return (
    <span className="portal-rating">
      {Array.from({ length: max }, (_, i) => (
        <span key={i} className={`portal-rating-star${i < value ? ' filled' : ''}`}>★</span>
      ))}
    </span>
  );
}

function TabEmpty({ message }: { message: string }) {
  return (
    <div className="portal-empty" style={{ paddingTop: 40 }}>
      <div className="portal-empty-title">{message}</div>
    </div>
  );
}

function TabError({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <div className="portal-empty" style={{ paddingTop: 40 }}>
      <div className="portal-empty-title">Failed to load</div>
      <div className="portal-empty-subtitle">{message}</div>
      <button className="portal-btn portal-btn-ghost" onClick={onRetry}>Retry</button>
    </div>
  );
}

// ── Tab: Identity ─────────────────────────────────────────────────────────────

interface IdentityTabProps {
  employee: Employee;
  employees: Employee[];
  canEdit: boolean;
  onSaved: (updated: Employee) => void;
  onArchived: () => void;
}

function IdentityTab({ employee, employees, canEdit, onSaved, onArchived }: IdentityTabProps) {
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({
    firstName: employee.firstName,
    lastName: employee.lastName,
    email: employee.email,
    hireDate: employee.hireDate ?? '',
    status: employee.status,
    managerId: employee.managerId ? String(employee.managerId) : '',
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const manager = employees.find(e => e.id === employee.managerId);

  const handleSave = async () => {
    if (!form.firstName.trim() || !form.lastName.trim() || !form.email.trim()) {
      setError('First name, last name and email are required.');
      return;
    }
    try {
      setSaving(true);
      setError(null);
      const updated = await apiFetch<Employee>(`/employees/${employee.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          ...form,
          wpUserId: employee.wpUserId,
          managerId: form.managerId ? parseInt(form.managerId) : null,
        }),
      });
      setEditing(false);
      onSaved(updated ?? { ...employee, ...form });
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  const handleArchive = async () => {
    if (!confirm(`Archive ${employee.displayName}? This will remove their portal access.`)) return;
    try {
      await apiFetch(`/employees/${employee.id}`, { method: 'DELETE' });
      onArchived();
    } catch (e: any) {
      alert(`Archive failed: ${e.message}`);
    }
  };

  if (editing) {
    return (
      <div style={{ maxWidth: 560 }}>
        {error && (
          <div className="portal-banner portal-banner-amber" style={{ marginBottom: 16 }}>
            <div className="portal-banner-text">{error}</div>
          </div>
        )}
        <div className="portal-section-card">
          <div className="portal-section-card-header">
            <span className="portal-section-card-title">Basic Information</span>
          </div>
          <div className="portal-section-card-body" style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <FormField label="First Name *">
                <input type="text" value={form.firstName}
                  onChange={e => setForm(f => ({ ...f, firstName: e.target.value }))} style={inputStyle} />
              </FormField>
              <FormField label="Last Name *">
                <input type="text" value={form.lastName}
                  onChange={e => setForm(f => ({ ...f, lastName: e.target.value }))} style={inputStyle} />
              </FormField>
            </div>
            <FormField label="Email *">
              <input type="email" value={form.email}
                onChange={e => setForm(f => ({ ...f, email: e.target.value }))} style={inputStyle} />
            </FormField>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <FormField label="Hire Date">
                <input type="date" value={form.hireDate}
                  onChange={e => setForm(f => ({ ...f, hireDate: e.target.value }))} style={inputStyle} />
              </FormField>
              <FormField label="Status">
                <select value={form.status} onChange={e => setForm(f => ({ ...f, status: e.target.value }))} style={inputStyle}>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </FormField>
            </div>
            <FormField label="Manager">
              <select value={form.managerId} onChange={e => setForm(f => ({ ...f, managerId: e.target.value }))} style={inputStyle}>
                <option value="">— No manager —</option>
                {employees.filter(e => e.id !== employee.id).map(emp => (
                  <option key={emp.id} value={emp.id}>{emp.displayName}</option>
                ))}
              </select>
            </FormField>
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
          <button className="portal-btn portal-btn-primary" onClick={handleSave} disabled={saving} style={{ justifyContent: 'center' }}>
            {saving ? 'Saving…' : 'Save Changes'}
          </button>
          <button className="portal-btn portal-btn-ghost" onClick={() => setEditing(false)} disabled={saving}>Cancel</button>
        </div>
      </div>
    );
  }

  return (
    <div style={{ maxWidth: 560 }}>
      <div className="portal-section-card">
        <div className="portal-section-card-header">
          <span className="portal-section-card-title">Basic Information</span>
          {canEdit && (
            <button className="portal-btn portal-btn-ghost portal-btn-sm" onClick={() => setEditing(true)}>
              Edit
            </button>
          )}
        </div>
        <div className="portal-section-card-body">
          <div className="portal-info-grid">
            <span className="portal-info-label">Full name</span>
            <span className="portal-info-value">{employee.displayName}</span>
            <span className="portal-info-label">Email</span>
            <span className="portal-info-value">{employee.email}</span>
            <span className="portal-info-label">Status</span>
            <span className="portal-info-value"><StatusBadge status={employee.status} /></span>
            <span className="portal-info-label">Hire date</span>
            <span className="portal-info-value">{fmtDate(employee.hireDate)}</span>
            <span className="portal-info-label">Manager</span>
            <span className="portal-info-value">{manager ? manager.displayName : '—'}</span>
            <span className="portal-info-label">Member since</span>
            <span className="portal-info-value">{fmtDate(employee.createdAt)}</span>
          </div>
        </div>
      </div>

      {canEdit && employee.status !== 'archived' && (
        <button
          className="portal-btn portal-btn-ghost"
          onClick={handleArchive}
          style={{ color: '#dc2626', borderColor: '#fecaca', marginTop: 8 }}
        >
          Archive Employee
        </button>
      )}
    </div>
  );
}

// ── Tab: Organisation ─────────────────────────────────────────────────────────

function OrganisationTab({ employee, employees }: { employee: Employee; employees: Employee[] }) {
  const manager = employees.find(e => e.id === employee.managerId);
  const directReports = employees.filter(e => e.managerId === employee.id && e.status === 'active');

  return (
    <div style={{ maxWidth: 560, display: 'flex', flexDirection: 'column', gap: 16 }}>
      <div className="portal-section-card">
        <div className="portal-section-card-header">
          <span className="portal-section-card-title">Reporting Line</span>
        </div>
        <div className="portal-section-card-body">
          <div className="portal-info-grid">
            <span className="portal-info-label">Reports to</span>
            <span className="portal-info-value">
              {manager ? (
                <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <EmployeeAvatar url={manager.avatarUrl} name={manager.displayName} size={24} />
                  {manager.displayName}
                </span>
              ) : '— No manager assigned'}
            </span>
          </div>
        </div>
      </div>

      {directReports.length > 0 && (
        <div className="portal-section-card">
          <div className="portal-section-card-header">
            <span className="portal-section-card-title">Direct Reports ({directReports.length})</span>
          </div>
          <div style={{ padding: '8px 0' }}>
            {directReports.map(rep => (
              <div key={rep.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 16px' }}>
                <EmployeeAvatar url={rep.avatarUrl} name={rep.displayName} size={30} />
                <div>
                  <div style={{ fontSize: 13, fontWeight: 600 }}>{rep.displayName}</div>
                  <div style={{ fontSize: 12, color: '#9ca3af' }}>{rep.email}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {employee.teamIds.length > 0 && (
        <div className="portal-section-card">
          <div className="portal-section-card-header">
            <span className="portal-section-card-title">Team Memberships</span>
          </div>
          <div className="portal-section-card-body">
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              {employee.teamIds.map(tid => (
                <span key={tid} className="portal-badge portal-badge-active" style={{ fontSize: 12 }}>
                  Team #{tid}
                </span>
              ))}
            </div>
            <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 8 }}>Team names shown as IDs — team name resolution coming soon.</div>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Tab: Roles ────────────────────────────────────────────────────────────────

function RolesTab({ employee, canEdit }: { employee: Employee; canEdit: boolean }) {
  const [assignments, setAssignments] = useState<RoleAssignment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await apiFetch<RoleAssignment[]>(`/assignments?employee_id=${employee.id}`);
      // Fetch role names
      const roles = await apiFetch<{ id: number; name: string }[]>('/roles').catch(() => []);
      const roleMap = new Map(roles.map(r => [r.id, r.name]));
      setAssignments(data.map(a => ({ ...a, role_name: roleMap.get(a.role_id) ?? `Role #${a.role_id}` })));
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [employee.id]);

  useEffect(() => { load(); }, [load]);

  if (loading) return <div style={{ padding: 24, color: '#9ca3af', fontSize: 13 }}>Loading roles…</div>;
  if (error) return <TabError message={error} onRetry={load} />;
  if (assignments.length === 0) return <TabEmpty message="No role assignments" />;

  return (
    <div style={{ maxWidth: 700 }}>
      <div className="portal-data-list">
        <div className="portal-data-list-row portal-data-list-header"
          style={{ gridTemplateColumns: '2fr 1fr 1fr 80px 90px' }}>
          <span>Role</span><span>Start</span><span>End</span><span>Alloc%</span><span>Status</span>
        </div>
        {assignments.map(a => (
          <div key={a.id} className="portal-data-list-row"
            style={{ gridTemplateColumns: '2fr 1fr 1fr 80px 90px' }}>
            <span style={{ fontWeight: 600 }}>{a.role_name}</span>
            <span style={{ color: '#6b7280' }}>{fmtDate(a.start_date)}</span>
            <span style={{ color: '#6b7280' }}>{a.end_date ? fmtDate(a.end_date) : 'Ongoing'}</span>
            <span>{a.allocation_pct}%</span>
            <span><StatusBadge status={a.status} /></span>
          </div>
        ))}
      </div>
      {canEdit && (
        <div style={{ marginTop: 12, fontSize: 12, color: '#9ca3af' }}>
          To assign roles, use the Roles section in the admin panel.
        </div>
      )}
    </div>
  );
}

// ── Tab: Skills ───────────────────────────────────────────────────────────────

function SkillsTab({ employee, canEdit }: { employee: Employee; canEdit: boolean }) {
  const [skills, setSkills] = useState<SkillEntry[]>([]);
  const [allSkills, setAllSkills] = useState<Skill[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ skillId: '', selfRating: 0, managerRating: 0, effectiveDate: new Date().toISOString().split('T')[0] });
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [skillData, allSkillData] = await Promise.all([
        apiFetch<any[]>(`/employees/${employee.id}/skills`),
        apiFetch<Skill[]>('/skills').catch(() => [] as Skill[]),
      ]);
      setAllSkills(allSkillData);
      const map = new Map(allSkillData.map(s => [s.id, s.name]));
      setSkills(skillData.map(s => ({ ...s, skill_name: map.get(s.skill_id) ?? `Skill #${s.skill_id}` })));
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [employee.id]);

  useEffect(() => { load(); }, [load]);

  const handleAdd = async () => {
    if (!form.skillId) return;
    try {
      setSaving(true);
      await apiFetch(`/employees/${employee.id}/skills`, {
        method: 'POST',
        body: JSON.stringify({
          skill_id: parseInt(form.skillId),
          self_rating: form.selfRating,
          manager_rating: form.managerRating,
          effective_date: form.effectiveDate,
        }),
      });
      setShowForm(false);
      setForm({ skillId: '', selfRating: 0, managerRating: 0, effectiveDate: new Date().toISOString().split('T')[0] });
      await load();
    } catch (e: any) {
      alert(`Failed to add skill: ${e.message}`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div style={{ padding: 24, color: '#9ca3af', fontSize: 13 }}>Loading skills…</div>;
  if (error) return <TabError message={error} onRetry={load} />;

  return (
    <div style={{ maxWidth: 700 }}>
      {canEdit && (
        <div style={{ marginBottom: 16 }}>
          <button className="portal-btn portal-btn-ghost portal-btn-sm" onClick={() => setShowForm(v => !v)}>
            {showForm ? 'Cancel' : '+ Rate Skill'}
          </button>
        </div>
      )}

      {showForm && (
        <div className="portal-section-card" style={{ marginBottom: 16 }}>
          <div className="portal-section-card-header">
            <span className="portal-section-card-title">Add Skill Rating</span>
          </div>
          <div className="portal-section-card-body" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <FormField label="Skill">
                <select value={form.skillId} onChange={e => setForm(f => ({ ...f, skillId: e.target.value }))} style={inputStyle}>
                  <option value="">Select skill…</option>
                  {allSkills.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
              </FormField>
              <FormField label="Effective Date">
                <input type="date" value={form.effectiveDate} onChange={e => setForm(f => ({ ...f, effectiveDate: e.target.value }))} style={inputStyle} />
              </FormField>
              <FormField label="Self Rating (0–5)">
                <input type="number" min={0} max={5} value={form.selfRating} onChange={e => setForm(f => ({ ...f, selfRating: parseInt(e.target.value) }))} style={inputStyle} />
              </FormField>
              <FormField label="Manager Rating (0–5)">
                <input type="number" min={0} max={5} value={form.managerRating} onChange={e => setForm(f => ({ ...f, managerRating: parseInt(e.target.value) }))} style={inputStyle} />
              </FormField>
            </div>
            <button className="portal-btn portal-btn-primary" onClick={handleAdd} disabled={saving} style={{ justifyContent: 'center', alignSelf: 'flex-start' }}>
              {saving ? 'Saving…' : 'Save Rating'}
            </button>
          </div>
        </div>
      )}

      {skills.length === 0 ? (
        <TabEmpty message="No skill ratings recorded" />
      ) : (
        <div className="portal-data-list">
          <div className="portal-data-list-row portal-data-list-header"
            style={{ gridTemplateColumns: '2fr 1fr 1fr 1fr' }}>
            <span>Skill</span><span>Self</span><span>Manager</span><span>Effective</span>
          </div>
          {skills.map(s => (
            <div key={s.id} className="portal-data-list-row" style={{ gridTemplateColumns: '2fr 1fr 1fr 1fr', alignItems: 'center' }}>
              <span style={{ fontWeight: 600 }}>{s.skill_name}</span>
              <StarRating value={s.self_rating} />
              <StarRating value={s.manager_rating} />
              <span style={{ color: '#6b7280' }}>{fmtDate(s.effective_date)}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ── Tab: Certifications ───────────────────────────────────────────────────────

function CertificationsTab({ employee, canEdit }: { employee: Employee; canEdit: boolean }) {
  const [certs, setCerts] = useState<CertEntry[]>([]);
  const [allCerts, setAllCerts] = useState<Certification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ certId: '', obtainedDate: '', expiryDate: '', evidenceUrl: '' });
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [certData, allCertData] = await Promise.all([
        apiFetch<any[]>(`/employees/${employee.id}/certifications`),
        apiFetch<Certification[]>('/certifications').catch(() => [] as Certification[]),
      ]);
      setAllCerts(allCertData);
      const map = new Map(allCertData.map(c => [c.id, c]));
      setCerts(certData.map(c => {
        const def = map.get(c.certification_id);
        return { ...c, certification_name: def?.name ?? `Cert #${c.certification_id}`, issuing_body: def?.issuing_body ?? '—' };
      }));
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [employee.id]);

  useEffect(() => { load(); }, [load]);

  const handleAdd = async () => {
    if (!form.certId || !form.obtainedDate) return;
    try {
      setSaving(true);
      await apiFetch(`/employees/${employee.id}/certifications`, {
        method: 'POST',
        body: JSON.stringify({
          certification_id: parseInt(form.certId),
          obtained_date: form.obtainedDate,
          expiry_date: form.expiryDate || null,
          evidence_url: form.evidenceUrl || null,
        }),
      });
      setShowForm(false);
      setForm({ certId: '', obtainedDate: '', expiryDate: '', evidenceUrl: '' });
      await load();
    } catch (e: any) {
      alert(`Failed to add certification: ${e.message}`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div style={{ padding: 24, color: '#9ca3af', fontSize: 13 }}>Loading certifications…</div>;
  if (error) return <TabError message={error} onRetry={load} />;

  return (
    <div style={{ maxWidth: 750 }}>
      {canEdit && (
        <div style={{ marginBottom: 16 }}>
          <button className="portal-btn portal-btn-ghost portal-btn-sm" onClick={() => setShowForm(v => !v)}>
            {showForm ? 'Cancel' : '+ Add Certification'}
          </button>
        </div>
      )}

      {showForm && (
        <div className="portal-section-card" style={{ marginBottom: 16 }}>
          <div className="portal-section-card-header">
            <span className="portal-section-card-title">Add Certification</span>
          </div>
          <div className="portal-section-card-body" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <FormField label="Certification *">
                <select value={form.certId} onChange={e => setForm(f => ({ ...f, certId: e.target.value }))} style={inputStyle}>
                  <option value="">Select certification…</option>
                  {allCerts.map(c => <option key={c.id} value={c.id}>{c.name} ({c.issuing_body})</option>)}
                </select>
              </FormField>
              <FormField label="Obtained Date *">
                <input type="date" value={form.obtainedDate} onChange={e => setForm(f => ({ ...f, obtainedDate: e.target.value }))} style={inputStyle} />
              </FormField>
              <FormField label="Expiry Date">
                <input type="date" value={form.expiryDate} onChange={e => setForm(f => ({ ...f, expiryDate: e.target.value }))} style={inputStyle} />
              </FormField>
              <FormField label="Evidence URL">
                <input type="url" value={form.evidenceUrl} onChange={e => setForm(f => ({ ...f, evidenceUrl: e.target.value }))} placeholder="https://…" style={inputStyle} />
              </FormField>
            </div>
            <button className="portal-btn portal-btn-primary" onClick={handleAdd} disabled={saving || !form.certId || !form.obtainedDate} style={{ justifyContent: 'center', alignSelf: 'flex-start' }}>
              {saving ? 'Saving…' : 'Save Certification'}
            </button>
          </div>
        </div>
      )}

      {certs.length === 0 ? (
        <TabEmpty message="No certifications recorded" />
      ) : (
        <div className="portal-data-list">
          <div className="portal-data-list-row portal-data-list-header"
            style={{ gridTemplateColumns: '2fr 1.5fr 1fr 1fr 80px' }}>
            <span>Certification</span><span>Issuing Body</span><span>Obtained</span><span>Expires</span><span>Status</span>
          </div>
          {certs.map(c => (
            <div key={c.id} className="portal-data-list-row" style={{ gridTemplateColumns: '2fr 1.5fr 1fr 1fr 80px', alignItems: 'center' }}>
              <span style={{ fontWeight: 600 }}>
                {c.evidence_url
                  ? <a href={c.evidence_url} target="_blank" rel="noopener noreferrer" style={{ color: '#2563eb' }}>{c.certification_name}</a>
                  : c.certification_name}
              </span>
              <span style={{ color: '#6b7280' }}>{c.issuing_body}</span>
              <span style={{ color: '#6b7280' }}>{fmtDate(c.obtained_date)}</span>
              <span style={{ color: '#6b7280' }}>{fmtDate(c.expiry_date)}</span>
              <StatusBadge status={c.status} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ── Tab: Reviews ──────────────────────────────────────────────────────────────

function ReviewsTab({ employee, canEdit }: { employee: Employee; canEdit: boolean }) {
  const [reviews, setReviews] = useState<ReviewEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    period_start: new Date().toISOString().split('T')[0],
    period_end: new Date(new Date().setMonth(new Date().getMonth() + 3)).toISOString().split('T')[0],
  });
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await apiFetch<ReviewEntry[]>(`/performance-reviews?employee_id=${employee.id}`);
      setReviews(data);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [employee.id]);

  useEffect(() => { load(); }, [load]);

  const handleCreate = async () => {
    try {
      setSaving(true);
      await apiFetch('/performance-reviews', {
        method: 'POST',
        body: JSON.stringify({ employee_id: employee.id, ...form }),
      });
      setShowForm(false);
      await load();
    } catch (e: any) {
      alert(`Failed to create review: ${e.message}`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div style={{ padding: 24, color: '#9ca3af', fontSize: 13 }}>Loading reviews…</div>;
  if (error) return <TabError message={error} onRetry={load} />;

  return (
    <div style={{ maxWidth: 700 }}>
      {canEdit && (
        <div style={{ marginBottom: 16 }}>
          <button className="portal-btn portal-btn-ghost portal-btn-sm" onClick={() => setShowForm(v => !v)}>
            {showForm ? 'Cancel' : '+ Start Review'}
          </button>
        </div>
      )}

      {showForm && (
        <div className="portal-section-card" style={{ marginBottom: 16 }}>
          <div className="portal-section-card-header">
            <span className="portal-section-card-title">New Performance Review</span>
          </div>
          <div className="portal-section-card-body" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <FormField label="Period Start">
                <input type="date" value={form.period_start} onChange={e => setForm(f => ({ ...f, period_start: e.target.value }))} style={inputStyle} />
              </FormField>
              <FormField label="Period End">
                <input type="date" value={form.period_end} onChange={e => setForm(f => ({ ...f, period_end: e.target.value }))} style={inputStyle} />
              </FormField>
            </div>
            <button className="portal-btn portal-btn-primary" onClick={handleCreate} disabled={saving} style={{ justifyContent: 'center', alignSelf: 'flex-start' }}>
              {saving ? 'Creating…' : 'Create Review'}
            </button>
          </div>
        </div>
      )}

      {reviews.length === 0 ? (
        <TabEmpty message="No performance reviews" />
      ) : (
        <div className="portal-data-list">
          <div className="portal-data-list-row portal-data-list-header"
            style={{ gridTemplateColumns: '1fr 1fr 100px' }}>
            <span>Period</span><span>Created</span><span>Status</span>
          </div>
          {reviews.map(r => (
            <div key={r.id} className="portal-data-list-row" style={{ gridTemplateColumns: '1fr 1fr 100px', alignItems: 'center' }}>
              <span style={{ fontWeight: 600 }}>{fmtDate(r.period_start)} → {fmtDate(r.period_end)}</span>
              <span style={{ color: '#6b7280' }}>{fmtDate(r.created_at)}</span>
              <StatusBadge status={r.status} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ── ProvisionModal ────────────────────────────────────────────────────────────

interface ProvisionModalProps {
  employees: Employee[];
  canAssignRole: boolean;
  onSaved: () => void;
  onClose: () => void;
}

function ProvisionModal({ employees, canAssignRole, onSaved, onClose }: ProvisionModalProps) {
  const [form, setForm] = useState({ firstName: '', lastName: '', email: '', hireDate: '', status: 'active', portalRole: '', managerId: '' });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<{ isNewUser: boolean } | null>(null);

  const handleSave = async () => {
    if (!form.firstName.trim() || !form.lastName.trim() || !form.email.trim()) {
      setError('First name, last name and email are required.');
      return;
    }
    try {
      setSaving(true);
      setError(null);
      const res = await apiFetch<{ isNewUser: boolean; wpUserId: number }>('/employees/provision', {
        method: 'POST',
        body: JSON.stringify({ ...form, managerId: form.managerId ? parseInt(form.managerId) : null }),
      });
      setResult(res);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="portal-modal-overlay" onClick={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="portal-modal">
        <div className="portal-modal-header">
          <span className="portal-modal-title">{result ? 'Employee Provisioned' : 'New Employee'}</span>
          <button className="portal-modal-close" onClick={onClose} aria-label="Close">×</button>
        </div>
        <div className="portal-modal-body">
          {result ? (
            <div style={{ textAlign: 'center', padding: '16px 0' }}>
              <div style={{ fontSize: 40, marginBottom: 12 }}>✅</div>
              <div style={{ fontWeight: 700, fontSize: 15 }}>Employee provisioned!</div>
              <div style={{ fontSize: 13, color: '#6b7280', marginTop: 6 }}>
                {result.isNewUser
                  ? 'A WordPress account was created and a password reset email has been sent.'
                  : 'Linked to an existing WordPress account.'}
              </div>
              <button className="portal-btn portal-btn-primary" onClick={onSaved} style={{ marginTop: 20, justifyContent: 'center' }}>
                Done
              </button>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
              {error && (
                <div className="portal-banner portal-banner-amber">
                  <div className="portal-banner-text">{error}</div>
                </div>
              )}
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                <FormField label="First Name *">
                  <input type="text" value={form.firstName} onChange={e => setForm(f => ({ ...f, firstName: e.target.value }))} placeholder="Jane" style={inputStyle} />
                </FormField>
                <FormField label="Last Name *">
                  <input type="text" value={form.lastName} onChange={e => setForm(f => ({ ...f, lastName: e.target.value }))} placeholder="Smith" style={inputStyle} />
                </FormField>
              </div>
              <FormField label="Email *">
                <input type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} placeholder="jane.smith@company.com" style={inputStyle} />
              </FormField>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                <FormField label="Hire Date">
                  <input type="date" value={form.hireDate} onChange={e => setForm(f => ({ ...f, hireDate: e.target.value }))} style={inputStyle} />
                </FormField>
                <FormField label="Status">
                  <select value={form.status} onChange={e => setForm(f => ({ ...f, status: e.target.value }))} style={inputStyle}>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </FormField>
              </div>
              <FormField label="Manager">
                <select value={form.managerId} onChange={e => setForm(f => ({ ...f, managerId: e.target.value }))} style={inputStyle}>
                  <option value="">— No manager —</option>
                  {employees.map(emp => <option key={emp.id} value={emp.id}>{emp.displayName}</option>)}
                </select>
              </FormField>
              {canAssignRole && (
                <FormField label="Portal Role">
                  <select value={form.portalRole} onChange={e => setForm(f => ({ ...f, portalRole: e.target.value }))} style={inputStyle}>
                    {PORTAL_ROLES.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                  </select>
                  <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 4 }}>
                    Creates a WordPress login with a password reset email.
                  </div>
                </FormField>
              )}
              <div style={{ display: 'flex', gap: 8, paddingTop: 4 }}>
                <button className="portal-btn portal-btn-primary" onClick={handleSave} disabled={saving} style={{ flex: 1, justifyContent: 'center' }}>
                  {saving ? 'Provisioning…' : 'Provision Employee'}
                </button>
                <button className="portal-btn portal-btn-ghost" onClick={onClose} disabled={saving}>Cancel</button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// ── EmployeeDetail ────────────────────────────────────────────────────────────

interface EmployeeDetailProps {
  employeeId: number;
  employees: Employee[];
  canEdit: boolean;
  canAssignRole: boolean;
  onBack: () => void;
  onEmployeeChanged: () => void;
}

const TABS: { key: DetailTab; label: string }[] = [
  { key: 'identity',       label: 'Identity' },
  { key: 'organisation',   label: 'Organisation' },
  { key: 'roles',          label: 'Roles' },
  { key: 'skills',         label: 'Skills' },
  { key: 'certifications', label: 'Certifications' },
  { key: 'reviews',        label: 'Reviews' },
];

function EmployeeDetail({ employeeId, employees, canEdit, canAssignRole, onBack, onEmployeeChanged }: EmployeeDetailProps) {
  const [activeTab, setActiveTab] = useState<DetailTab>('identity');
  const [employee, setEmployee] = useState<Employee | null>(
    employees.find(e => e.id === employeeId) ?? null
  );
  const [loading, setLoading] = useState(!employee);

  // If employee isn't in the local list, fetch directly
  useEffect(() => {
    if (!employees.find(e => e.id === employeeId)) {
      apiFetch<Employee[]>('/employees')
        .then(list => {
          const found = list.find(e => e.id === employeeId);
          if (found) setEmployee(found);
        })
        .catch(() => {})
        .finally(() => setLoading(false));
    } else {
      setEmployee(employees.find(e => e.id === employeeId) ?? null);
    }
  }, [employeeId, employees]);

  if (loading) {
    return (
      <div style={{ padding: 32, color: '#9ca3af', fontSize: 13 }}>Loading employee…</div>
    );
  }

  if (!employee) {
    return (
      <div className="portal-empty">
        <div className="portal-empty-title">Employee not found</div>
        <button className="portal-btn portal-btn-ghost" onClick={onBack}>← Back to Employees</button>
      </div>
    );
  }

  const handleSaved = (updated: Employee) => {
    setEmployee(updated);
    onEmployeeChanged();
  };

  const handleArchived = () => {
    onEmployeeChanged();
    onBack();
  };

  return (
    <div>
      {/* Detail header */}
      <div className="portal-detail-header">
        <button className="portal-detail-back" onClick={onBack}>
          ← Employees
        </button>
        <div className="portal-detail-identity">
          <EmployeeAvatar url={employee.avatarUrl} name={employee.displayName} size={44} />
          <div>
            <div className="portal-detail-name">{employee.displayName}</div>
            <div className="portal-detail-meta">
              {employee.email}
              {' · '}
              <StatusBadge status={employee.status} />
            </div>
          </div>
        </div>
      </div>

      {/* Tab bar */}
      <div className="portal-tab-bar">
        {TABS.map(t => (
          <button
            key={t.key}
            className={`portal-tab${activeTab === t.key ? ' active' : ''}`}
            onClick={() => setActiveTab(t.key)}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Tab content */}
      {activeTab === 'identity' && (
        <IdentityTab employee={employee} employees={employees} canEdit={canEdit} onSaved={handleSaved} onArchived={handleArchived} />
      )}
      {activeTab === 'organisation' && (
        <OrganisationTab employee={employee} employees={employees} />
      )}
      {activeTab === 'roles' && (
        <RolesTab employee={employee} canEdit={canAssignRole} />
      )}
      {activeTab === 'skills' && (
        <SkillsTab employee={employee} canEdit={canEdit} />
      )}
      {activeTab === 'certifications' && (
        <CertificationsTab employee={employee} canEdit={canEdit} />
      )}
      {activeTab === 'reviews' && (
        <ReviewsTab employee={employee} canEdit={canEdit} />
      )}
    </div>
  );
}

// ── EmployeeList ──────────────────────────────────────────────────────────────

type FilterMode = 'all' | 'active' | 'archived';

interface EmployeeListProps {
  employees: Employee[];
  loading: boolean;
  error: string | null;
  filter: FilterMode;
  setFilter: (f: FilterMode) => void;
  search: string;
  setSearch: (s: string) => void;
  canEdit: boolean;
  canAssignRole: boolean;
  onProvision: () => void;
  onSelect: (emp: Employee) => void;
  onRetry: () => void;
}

function EmployeeList({ employees, loading, error, filter, setFilter, search, setSearch, canEdit, canAssignRole, onProvision, onSelect, onRetry }: EmployeeListProps) {
  const filtered = employees.filter(emp => {
    if (filter === 'active'   && emp.status !== 'active')   return false;
    if (filter === 'archived' && emp.status !== 'archived') return false;
    if (search) {
      const q = search.toLowerCase();
      return emp.displayName.toLowerCase().includes(q) || emp.email.toLowerCase().includes(q);
    }
    return true;
  });

  const kpiActive   = employees.filter(e => e.status === 'active').length;
  const kpiInactive = employees.filter(e => e.status !== 'active').length;

  return (
    <>
      <div className="portal-page-header">
        <div>
          <div className="portal-page-title">Employees</div>
          <div className="portal-page-subtitle">Staff records and portal access management</div>
        </div>
        {canEdit && (
          <button className="portal-btn portal-btn-primary" onClick={onProvision}>
            + New Employee
          </button>
        )}
      </div>

      <div className="portal-kpi-strip" style={{ gridTemplateColumns: 'repeat(3, 1fr)' }}>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Total</div>
          <div className="portal-kpi-value">{employees.length}</div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Active</div>
          <div className="portal-kpi-value" style={{ color: '#16a34a' }}>{kpiActive}</div>
        </div>
        <div className="portal-kpi-card">
          <div className="portal-kpi-label">Archived</div>
          <div className="portal-kpi-value" style={{ color: '#9ca3af' }}>{kpiInactive}</div>
        </div>
      </div>

      <div className="portal-filters-row">
        {(['all', 'active', 'archived'] as FilterMode[]).map(f => (
          <button key={f} className={`portal-filter-tab${filter === f ? ' active' : ''}`} onClick={() => setFilter(f)}>
            {f.charAt(0).toUpperCase() + f.slice(1)}
          </button>
        ))}
        <div className="portal-filter-spacer" />
        <input
          type="search"
          placeholder="Search staff…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          style={{ padding: '6px 12px', borderRadius: 8, border: '1px solid #e5e7eb', fontSize: 13, outline: 'none', width: 200, fontFamily: 'inherit' }}
        />
      </div>

      {loading ? (
        <div className="portal-card">
          {[1, 2, 3, 4].map(i => (
            <div key={i} style={{ padding: '14px 16px', borderBottom: '1px solid #e5e7eb', display: 'flex', gap: 14, alignItems: 'center' }}>
              <div className="portal-skeleton" style={{ width: 36, height: 36, borderRadius: '50%', flexShrink: 0 }} />
              <div style={{ flex: 1 }}>
                <div className="portal-skeleton" style={{ height: 14, width: '35%', marginBottom: 6, borderRadius: 3 }} />
                <div className="portal-skeleton" style={{ height: 12, width: '22%', borderRadius: 3 }} />
              </div>
              <div className="portal-skeleton" style={{ height: 22, width: 60, borderRadius: 20 }} />
            </div>
          ))}
        </div>
      ) : error ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">Failed to load employees</div>
            <div className="portal-empty-subtitle">{error}</div>
            <button className="portal-btn portal-btn-ghost" onClick={onRetry}>Retry</button>
          </div>
        </div>
      ) : filtered.length === 0 ? (
        <div className="portal-card">
          <div className="portal-empty">
            <div className="portal-empty-title">{search ? 'No matching staff' : 'No employees yet'}</div>
            <div className="portal-empty-subtitle">
              {search ? 'Try a different search.' : canEdit ? 'Add your first employee to get started.' : 'No staff records found.'}
            </div>
            {!search && canEdit && (
              <button className="portal-btn portal-btn-primary" onClick={onProvision}>+ New Employee</button>
            )}
          </div>
        </div>
      ) : (
        <div className="portal-card">
          <table>
            <thead>
              <tr>
                <th style={{ width: 46 }} />
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Hired</th>
                <th style={{ width: 80 }} />
              </tr>
            </thead>
            <tbody>
              {filtered.map(emp => (
                <tr key={emp.id} onClick={() => onSelect(emp)} style={{ cursor: 'pointer' }}>
                  <td><EmployeeAvatar url={emp.avatarUrl} name={emp.displayName} size={36} /></td>
                  <td><div style={{ fontWeight: 600 }}>{emp.displayName}</div></td>
                  <td style={{ color: '#6b7280' }}>{emp.email}</td>
                  <td><StatusBadge status={emp.status} /></td>
                  <td style={{ color: '#6b7280', whiteSpace: 'nowrap' }}>{fmtDate(emp.hireDate)}</td>
                  <td style={{ textAlign: 'right' }} onClick={e => e.stopPropagation()}>
                    <button className="portal-btn portal-btn-ghost portal-btn-sm" onClick={() => onSelect(emp)}>
                      View →
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}

// ── Main page ─────────────────────────────────────────────────────────────────

const EmployeesPage: React.FC = () => {
  const user = usePortalUser();
  const canEdit       = user.isHr || user.isManager || user.isAdmin;
  const canAssignRole = user.isManager || user.isAdmin;

  // Internal hash-based sub-routing
  const [hash, setHash] = useState(() => window.location.hash);
  useEffect(() => {
    const handler = () => setHash(window.location.hash);
    window.addEventListener('hashchange', handler);
    return () => window.removeEventListener('hashchange', handler);
  }, []);

  const employeeId = parseEmployeeHash(hash);

  // Employee list — loaded at page level so detail view can use it without re-fetching
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<FilterMode>('active');
  const [search, setSearch] = useState('');
  const [showProvision, setShowProvision] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      setEmployees(await apiFetch<Employee[]>('/employees'));
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const goToDetail = (emp: Employee) => {
    window.location.hash = `#employees/${emp.id}`;
  };
  const goToList = () => {
    window.location.hash = '#employees';
  };

  // Detail view
  if (employeeId !== null) {
    return (
      <EmployeeDetail
        employeeId={employeeId}
        employees={employees}
        canEdit={canEdit}
        canAssignRole={canAssignRole}
        onBack={goToList}
        onEmployeeChanged={load}
      />
    );
  }

  // List view
  return (
    <>
      {showProvision && (
        <ProvisionModal
          employees={employees}
          canAssignRole={canAssignRole}
          onSaved={() => { load(); setShowProvision(false); }}
          onClose={() => setShowProvision(false)}
        />
      )}
      <EmployeeList
        employees={employees}
        loading={loading}
        error={error}
        filter={filter}
        setFilter={setFilter}
        search={search}
        setSearch={setSearch}
        canEdit={canEdit}
        canAssignRole={canAssignRole}
        onProvision={() => setShowProvision(true)}
        onSelect={goToDetail}
        onRetry={load}
      />
    </>
  );
};

export default EmployeesPage;
