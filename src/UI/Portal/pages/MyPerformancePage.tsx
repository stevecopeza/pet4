import React, { useCallback, useEffect, useState } from 'react';

// @ts-ignore
const apiUrl = (): string => (window.petSettings?.apiUrl ?? '') as string;
// @ts-ignore
const nonce  = (): string => (window.petSettings?.nonce  ?? '') as string;
const hdrs   = () => ({ 'X-WP-Nonce': nonce() });

interface Kpi {
  id: number;
  kpi_definition_id: number;
  kpi_name?: string | null;
  kpi_unit?: string | null;
  period_start: string;
  period_end: string;
  target_value: number;
  actual_value: number | null;
  score: number | null;
  status: string;
}

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
  displayName: string;
  skills: Skill[];
}

function scoreColor(score: number | null): string {
  if (score === null) return '#94a3b8';
  if (score >= 90) return '#10b981';
  if (score >= 70) return '#3b82f6';
  if (score >= 50) return '#f59e0b';
  return '#ef4444';
}

function ratingLabel(r: number): string {
  if (r <= 0) return '—';
  return ['', 'Beginner', 'Developing', 'Proficient', 'Advanced', 'Expert'][r] ?? `${r}`;
}

function ratingColor(r: number): string {
  if (r <= 1) return '#94a3b8';
  if (r === 2) return '#f59e0b';
  if (r === 3) return '#3b82f6';
  if (r === 4) return '#8b5cf6';
  return '#10b981';
}

function periodLabel(start: string, end: string): string {
  const s = new Date(start + 'T00:00:00').toLocaleDateString('en-ZA', { month: 'short', year: 'numeric' });
  const e = new Date(end   + 'T00:00:00').toLocaleDateString('en-ZA', { month: 'short', year: 'numeric' });
  return s === e ? s : `${s} – ${e}`;
}

const card: React.CSSProperties = {
  background: '#fff',
  border: '1px solid #e2e8f0',
  borderRadius: 12,
  padding: '20px 24px',
  boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
};

const MyPerformancePage: React.FC = () => {
  const [profile, setProfile]   = useState<Profile | null>(null);
  const [kpis, setKpis]         = useState<Kpi[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [profileRes, kpisRes] = await Promise.all([
        fetch(`${apiUrl()}/staff/profile`, { headers: hdrs() }),
        fetch(`${apiUrl()}/staff/profile/kpis`, { headers: hdrs() }),
      ]);
      if (!profileRes.ok) {
        const b = await profileRes.json().catch(() => ({}));
        throw new Error(b.error ?? `HTTP ${profileRes.status}`);
      }
      setProfile(await profileRes.json());
      if (kpisRes.ok) setKpis(await kpisRes.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  // Group KPIs by period
  const kpisByPeriod = kpis.reduce((map, kpi) => {
    const key = `${kpi.period_start}__${kpi.period_end}`;
    const existing = map.get(key) ?? [];
    existing.push(kpi);
    map.set(key, existing);
    return map;
  }, new Map<string, Kpi[]>());

  const periodKeys = [...kpisByPeriod.keys()].sort((a, b) => b.localeCompare(a)); // newest first

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '24px 20px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>My Performance</h1>
        <button onClick={load} style={{ padding: '6px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: '#f8fafc', fontSize: 12, fontWeight: 600, color: '#475569', cursor: 'pointer' }}>↻ Refresh</button>
      </div>

      {loading && <div style={{ padding: '60px 0', textAlign: 'center', color: '#94a3b8' }}>Loading…</div>}

      {error && (
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', borderRadius: 10, padding: '14px 18px', fontSize: 13, marginBottom: 16 }}>
          {error === 'No PET employee mapping exists for this user.'
            ? 'Your user account is not linked to an employee record. Please contact your administrator.'
            : error}
        </div>
      )}

      {!loading && !error && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>

          {/* Skills */}
          <div style={card}>
            <div style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: '#64748b', marginBottom: 12 }}>Skills</div>
            {(!profile?.skills || profile.skills.length === 0) ? (
              <div style={{ color: '#94a3b8', fontSize: 13, textAlign: 'center', padding: '16px 0' }}>No skill ratings recorded yet.</div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 10 }}>
                {profile.skills.map(s => (
                  <div key={s.id} style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '10px 14px' }}>
                    <div style={{ fontSize: 13, fontWeight: 600, color: '#1e293b', marginBottom: 6 }}>{s.skill_name}</div>
                    <div style={{ display: 'flex', gap: 10, fontSize: 11, color: '#64748b' }}>
                      <span>Self: <strong style={{ color: ratingColor(s.self_rating) }}>{ratingLabel(s.self_rating)}</strong></span>
                      <span>Mgr: <strong style={{ color: ratingColor(s.manager_rating) }}>{ratingLabel(s.manager_rating)}</strong></span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* KPIs by period */}
          {periodKeys.length === 0 && (
            <div style={{ ...card, textAlign: 'center', color: '#94a3b8', fontSize: 14, padding: '40px' }}>
              No KPI records found. Your manager will add them when review periods are set up.
            </div>
          )}

          {periodKeys.map(key => {
            const [start, end] = key.split('__');
            const periodKpis = kpisByPeriod.get(key) ?? [];
            const avgScore = periodKpis.filter(k => k.score !== null).map(k => k.score as number);
            const avg = avgScore.length ? Math.round(avgScore.reduce((a, b) => a + b, 0) / avgScore.length) : null;

            return (
              <div key={key} style={card}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                  <div style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: '#64748b' }}>
                    KPIs — {periodLabel(start, end)}
                  </div>
                  {avg !== null && (
                    <div style={{
                      padding: '4px 14px', borderRadius: 20, fontSize: 13, fontWeight: 700,
                      background: `${scoreColor(avg)}22`, color: scoreColor(avg),
                    }}>
                      Avg score: {avg}%
                    </div>
                  )}
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                  {periodKpis.map(kpi => {
                    const unit = kpi.kpi_unit ?? '';
                    const pct = kpi.actual_value !== null && kpi.target_value > 0
                      ? Math.min(100, Math.round((kpi.actual_value / kpi.target_value) * 100))
                      : null;

                    return (
                      <div key={kpi.id} style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '12px 16px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 }}>
                          <div style={{ fontSize: 13, fontWeight: 600, color: '#1e293b' }}>
                            {kpi.kpi_name ?? `KPI #${kpi.kpi_definition_id}`}
                          </div>
                          <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexShrink: 0 }}>
                            {kpi.score !== null && (
                              <span style={{ fontSize: 13, fontWeight: 700, color: scoreColor(kpi.score) }}>
                                {Math.round(kpi.score)}%
                              </span>
                            )}
                            {kpi.score === null && <span style={{ fontSize: 12, color: '#94a3b8' }}>Pending</span>}
                          </div>
                        </div>
                        <div style={{ display: 'flex', gap: 16, fontSize: 12, color: '#64748b', marginBottom: pct !== null ? 8 : 0 }}>
                          <span>Target: <strong style={{ color: '#1e293b' }}>{kpi.target_value}{unit ? ` ${unit}` : ''}</strong></span>
                          <span>Actual: <strong style={{ color: '#1e293b' }}>{kpi.actual_value !== null ? `${kpi.actual_value}${unit ? ` ${unit}` : ''}` : '—'}</strong></span>
                        </div>
                        {pct !== null && (
                          <div style={{ background: '#e2e8f0', borderRadius: 4, height: 6, overflow: 'hidden' }}>
                            <div style={{ height: '100%', width: `${pct}%`, background: scoreColor(kpi.score), borderRadius: 4, transition: 'width 0.3s' }} />
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          })}

        </div>
      )}
    </div>
  );
};

export default MyPerformancePage;
