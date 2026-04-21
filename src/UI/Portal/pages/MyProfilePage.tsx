import React, { useCallback, useEffect, useState } from 'react';
import { usePortalUser } from '../hooks/usePortalUser';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface Skill {
  id: number;
  skill_id: number;
  skill_name: string;
  self_rating: number;
  manager_rating: number;
  effective_date: string;
}

interface Profile {
  id: number;
  wpUserId: number;
  firstName: string;
  lastName: string;
  displayName: string;
  email: string;
  status: string;
  hireDate: string | null;
  managerId: number | null;
  teamIds: number[];
  malleableData: Record<string, any> | null;
  skills: Skill[];
}

function ratingLabel(r: number): string {
  if (r <= 0) return '—';
  const labels = ['', 'Beginner', 'Developing', 'Proficient', 'Advanced', 'Expert'];
  return labels[r] ?? `${r}`;
}

function ratingColor(r: number): string {
  if (r <= 1) return '#94a3b8';
  if (r === 2) return '#f59e0b';
  if (r === 3) return '#3b82f6';
  if (r === 4) return '#8b5cf6';
  return '#10b981';
}

const card: React.CSSProperties = {
  background: '#fff',
  border: '1px solid #e2e8f0',
  borderRadius: 12,
  padding: '20px 24px',
  boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
};

const sectionLabel: React.CSSProperties = {
  fontSize: 11,
  fontWeight: 700,
  textTransform: 'uppercase',
  letterSpacing: '0.07em',
  color: '#64748b',
  marginBottom: 12,
};

const field: React.CSSProperties = {
  display: 'grid',
  gridTemplateColumns: '140px 1fr',
  gap: 4,
  padding: '8px 0',
  borderBottom: '1px solid #f1f5f9',
};

const fieldLabel: React.CSSProperties = {
  fontSize: 12,
  color: '#64748b',
  fontWeight: 600,
};

const fieldValue: React.CSSProperties = {
  fontSize: 13,
  color: '#1e293b',
};

const MyProfilePage: React.FC = () => {
  const user = usePortalUser();
  const [profile, setProfile] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${apiUrl()}/staff/profile`, { headers: hdrs() });
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.error ?? `HTTP ${res.status}`);
      }
      setProfile(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const jobTitle = profile?.malleableData?.['jobTitle'] ?? profile?.malleableData?.['job_title'] ?? null;

  return (
    <div style={{ maxWidth: 860, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>My Profile</h1>
        <button
          onClick={load}
          style={{ padding: '6px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#f8fafc', fontSize: 12, fontWeight: 600, color: '#475569', cursor: 'pointer' }}
        >
          ↻ Refresh
        </button>
      </div>

      {loading && (
        <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8' }}>Loading profile…</div>
      )}

      {error && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 10, padding: '14px 18px', fontSize: 13, marginBottom: 16 }}>
          {error === 'No PET employee mapping exists for this user.'
            ? 'Your user account is not linked to an employee record yet. Please ask your administrator to set this up.'
            : error}
        </div>
      )}

      {!loading && profile && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>

          {/* Identity card */}
          <div style={{ ...card, gridColumn: '1 / -1', display: 'flex', alignItems: 'center', gap: 20 }}>
            <div style={{
              width: 64, height: 64, borderRadius: '50%',
              background: '#2563eb', color: '#fff',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 24, fontWeight: 700, flexShrink: 0,
            }}>
              {user.initials}
            </div>
            <div>
              <div style={{ fontSize: 20, fontWeight: 700, color: '#0f172a' }}>{profile.displayName}</div>
              {jobTitle && <div style={{ fontSize: 14, color: '#64748b', marginTop: 2 }}>{jobTitle}</div>}
              <div style={{ fontSize: 13, color: '#94a3b8', marginTop: 2 }}>{profile.email}</div>
            </div>
            <div style={{ marginLeft: 'auto' }}>
              <span style={{
                display: 'inline-block', padding: '4px 12px', borderRadius: 20,
                fontSize: 12, fontWeight: 700,
                background: profile.status === 'active' ? '#f0fdf4' : '#fef2f2',
                color: profile.status === 'active' ? '#16a34a' : '#dc2626',
              }}>
                {profile.status}
              </span>
            </div>
          </div>

          {/* Details */}
          <div style={card}>
            <div style={sectionLabel}>Details</div>
            <div style={{ ...field, borderTop: '1px solid #f1f5f9' }}>
              <span style={fieldLabel}>Employee ID</span>
              <span style={fieldValue}>#{profile.id}</span>
            </div>
            <div style={field}>
              <span style={fieldLabel}>Email</span>
              <span style={fieldValue}>{profile.email}</span>
            </div>
            {jobTitle && (
              <div style={field}>
                <span style={fieldLabel}>Job Title</span>
                <span style={fieldValue}>{jobTitle}</span>
              </div>
            )}
            <div style={field}>
              <span style={fieldLabel}>Hire Date</span>
              <span style={fieldValue}>{profile.hireDate ?? '—'}</span>
            </div>
            <div style={{ ...field, borderBottom: 'none' }}>
              <span style={fieldLabel}>Status</span>
              <span style={fieldValue}>{profile.status}</span>
            </div>
          </div>

          {/* Malleable data extras */}
          {profile.malleableData && Object.keys(profile.malleableData).filter(k => !['jobTitle', 'job_title'].includes(k)).length > 0 && (
            <div style={card}>
              <div style={sectionLabel}>Additional Info</div>
              {Object.entries(profile.malleableData)
                .filter(([k]) => !['jobTitle', 'job_title'].includes(k))
                .map(([k, v], i, arr) => (
                  <div key={k} style={{ ...field, borderBottom: i < arr.length - 1 ? '1px solid #f1f5f9' : 'none', borderTop: i === 0 ? '1px solid #f1f5f9' : 'none' }}>
                    <span style={fieldLabel}>{k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</span>
                    <span style={fieldValue}>{String(v)}</span>
                  </div>
                ))
              }
            </div>
          )}

          {/* Skills */}
          <div style={{ ...card, gridColumn: '1 / -1' }}>
            <div style={sectionLabel}>Skills & Ratings</div>
            {profile.skills.length === 0 ? (
              <div style={{ color: '#94a3b8', fontSize: 13, padding: '16px 0', textAlign: 'center' }}>
                No skill ratings recorded yet.
              </div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 10, marginTop: 4 }}>
                {profile.skills.map(s => (
                  <div key={s.id} style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '10px 14px' }}>
                    <div style={{ fontSize: 13, fontWeight: 600, color: '#1e293b', marginBottom: 6 }}>{s.skill_name}</div>
                    <div style={{ display: 'flex', gap: 12, fontSize: 11, color: '#64748b' }}>
                      <span>Self: <strong style={{ color: ratingColor(s.self_rating) }}>{ratingLabel(s.self_rating)}</strong></span>
                      <span>Manager: <strong style={{ color: ratingColor(s.manager_rating) }}>{ratingLabel(s.manager_rating)}</strong></span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

        </div>
      )}
    </div>
  );
};

export default MyProfilePage;
