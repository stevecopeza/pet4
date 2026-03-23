import React, { useCallback, useEffect, useMemo, useState } from 'react';

type ProbeMetricValue = {
  value: unknown;
  context?: Record<string, unknown> | null;
};

type WorkloadMetricValue = {
  query_count: number;
  execution_time_ms: number;
};

type Recommendation = {
  issue_key?: string;
  severity?: string;
  title?: string;
  message?: string;
  metric_key?: string;
  observed_value?: unknown;
};

type ProbeError = {
  metric_key: string;
  message: string;
  context?: Record<string, unknown> | null;
};

type PerformanceRun = {
  id: number;
  run_type: string;
  status: string;
  started_at: string | null;
  completed_at: string | null;
  duration_ms: number | null;
  selection?: {
    policy: string;
    fallback_to_failed: boolean;
  };
};

type PerformancePayload = {
  run: PerformanceRun | null;
  metrics: {
    probe: Record<string, ProbeMetricValue>;
    workload: Record<string, WorkloadMetricValue>;
    workload_other: Record<string, WorkloadMetricValue>;
    recommendations: Recommendation[];
    errors: ProbeError[];
  };
  counts: {
    probe: number;
    recommendations: number;
    errors: number;
  };
};

const WORKLOAD_CONTRACT_KEYS: Array<{ key: string; label: string }> = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'advisory.signals', label: 'Advisory Signals (Recent)' },
  { key: 'advisory.signals_work_item', label: 'Advisory Signals (Work Item)' },
  { key: 'advisory.reports_list', label: 'Advisory Reports List' },
  { key: 'advisory.reports_latest', label: 'Advisory Reports Latest' },
  { key: 'advisory.reports_get', label: 'Advisory Reports Get' },
  { key: 'advisory.reports_generate', label: 'Advisory Reports Generate' },
  { key: 'ticket.list', label: 'Ticket List' },
];

const METRIC_GROUPS: Array<{ title: string; keys: string[] }> = [
  { title: 'Environment', keys: ['environment.wp_version', 'environment.php_version', 'environment.mysql_version', 'environment.memory_limit', 'environment.max_execution_time'] },
  { title: 'PHP', keys: ['php.opcache_enabled', 'php.opcache_memory_used_bytes', 'php.opcache_memory_free_bytes', 'php.opcache_hit_rate'] },
  { title: 'Database', keys: ['database.connection_latency_ms', 'database.select_1_timing_ms', 'database.indexed_query_timing_ms', 'database.join_query_timing_ms'] },
  { title: 'Caching', keys: ['cache.backend', 'cache.available', 'cache.stats_available', 'cache.hits', 'cache.misses', 'cache.hit_rate'] },
  { title: 'Network', keys: ['network.db_ping_latency_ms', 'network.http_loopback_latency_ms', 'network.http_public_latency_ms'] },
];

const Performance = () => {
  const [payload, setPayload] = useState<PerformancePayload | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [running, setRunning] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const apiUrl: string = window.petSettings?.apiUrl ?? '';
  const nonce: string = window.petSettings?.nonce ?? '';

  const requestHeaders = useMemo(() => ({ 'X-WP-Nonce': nonce }), [nonce]);

  const loadLatest = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`${apiUrl}/performance/latest`, { headers: requestHeaders });
      if (!response.ok) {
        throw new Error(`Failed to load latest benchmark (${response.status})`);
      }
      const json = await response.json();
      setPayload(json as PerformancePayload);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load latest benchmark');
    } finally {
      setLoading(false);
    }
  }, [apiUrl, requestHeaders]);

  const runBenchmark = useCallback(async () => {
    setRunning(true);
    setError(null);
    try {
      const response = await fetch(`${apiUrl}/performance/run`, {
        method: 'POST',
        headers: requestHeaders,
      });
      if (!response.ok) {
        throw new Error(`Failed to run benchmark (${response.status})`);
      }
      const json = await response.json();
      setPayload(json as PerformancePayload);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to run benchmark');
    } finally {
      setRunning(false);
    }
  }, [apiUrl, requestHeaders]);

  useEffect(() => {
    loadLatest();
  }, [loadLatest]);

  const formatValue = (value: unknown): string => {
    if (value === null || value === undefined) {
      return 'n/a';
    }
    if (typeof value === 'boolean') {
      return value ? 'true' : 'false';
    }
    if (typeof value === 'number') {
      return Number.isFinite(value) ? String(value) : 'n/a';
    }
    if (typeof value === 'string') {
      return value;
    }
    return JSON.stringify(value);
  };

  const statusClassName = (status: string | null | undefined): string => {
    const normalized = (status ?? 'unknown').toLowerCase().replace(/[^a-z0-9]+/g, '_');
    return `pet-status-badge status-${normalized}`;
  };

  const severityClassName = (severity: string | null | undefined): string => {
    const normalized = (severity ?? 'unknown').toLowerCase().replace(/[^a-z0-9]+/g, '-');
    return `pet-performance-severity pet-performance-severity--${normalized}`;
  };

  const renderMetricRows = (title: string, keys: string[]) => {
    const probe = payload?.metrics?.probe ?? {};

    return (
      <section className="pet-panel pet-performance-section">
        <h2 className="pet-performance-section-title">{title}</h2>
        <table className="widefat striped pet-performance-table">
          <thead>
            <tr>
              <th>Metric</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            {keys.map((key) => {
              const metric = probe[key];
              return (
                <tr key={key}>
                  <td><code>{key}</code></td>
                  <td>{formatValue(metric?.value)}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </section>
    );
  };

  if (loading) {
    return (
      <div className="pet-state pet-state-loading pet-performance-loading">
        <span className="pet-state-spinner" aria-hidden="true" />
        <span>Loading performance benchmark data…</span>
      </div>
    );
  }

  return (
    <div className="pet-performance pet-page-shell">
      <div className="pet-panel pet-performance-hero">
        <div className="pet-performance-hero-header">
          <div className="pet-performance-hero-title">
            <h2>Performance Benchmark</h2>
            <p>Read-only benchmark snapshot and probe outputs.</p>
          </div>
          <button type="button" className="button button-primary pet-performance-run-button" onClick={runBenchmark} disabled={running}>
            {running ? 'Running Benchmark…' : 'Run Benchmark'}
          </button>
        </div>

        {error && <p className="pet-performance-error">{error}</p>}

        <div className="pet-performance-kpi-grid">
          <div className="pet-performance-kpi-card">
            <span className="pet-performance-kpi-label">Run Status</span>
            <strong className="pet-performance-kpi-value">
              <span className={statusClassName(payload?.run?.status)}>
                {payload?.run?.status ?? 'n/a'}
              </span>
            </strong>
          </div>
          <div className="pet-performance-kpi-card">
            <span className="pet-performance-kpi-label">Probe Metrics</span>
            <strong className="pet-performance-kpi-value">{payload?.counts?.probe ?? 0}</strong>
          </div>
          <div className="pet-performance-kpi-card">
            <span className="pet-performance-kpi-label">Recommendations</span>
            <strong className="pet-performance-kpi-value">{payload?.counts?.recommendations ?? 0}</strong>
          </div>
          <div className="pet-performance-kpi-card">
            <span className="pet-performance-kpi-label">Probe Errors</span>
            <strong className="pet-performance-kpi-value">{payload?.counts?.errors ?? 0}</strong>
          </div>
        </div>
      </div>

      <section className="pet-panel pet-performance-section">
        <h2 className="pet-performance-section-title">Environment</h2>
        <table className="widefat striped pet-performance-table">
          <thead>
            <tr>
              <th>Field</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>Run ID</td><td>{payload?.run?.id ?? 'n/a'}</td></tr>
            <tr><td>Run Type</td><td>{payload?.run?.run_type ?? 'n/a'}</td></tr>
            <tr>
              <td>Status</td>
              <td><span className={statusClassName(payload?.run?.status)}>{payload?.run?.status ?? 'n/a'}</span></td>
            </tr>
            <tr><td>Started</td><td>{payload?.run?.started_at ?? 'n/a'}</td></tr>
            <tr><td>Completed</td><td>{payload?.run?.completed_at ?? 'n/a'}</td></tr>
            <tr><td>Duration (ms)</td><td>{payload?.run?.duration_ms ?? 'n/a'}</td></tr>
            <tr><td>Latest Selection Policy</td><td>{payload?.run?.selection?.policy ?? 'current_request_or_unspecified'}</td></tr>
            <tr><td>Failed Fallback Used</td><td>{payload?.run?.selection ? (payload.run.selection.fallback_to_failed ? 'true' : 'false') : 'n/a'}</td></tr>
          </tbody>
        </table>
      </section>

      {METRIC_GROUPS.filter((group) => group.title !== 'Environment').map((group) => (
        <React.Fragment key={group.title}>
          {renderMetricRows(group.title, group.keys)}
        </React.Fragment>
      ))}

      <section className="pet-panel pet-performance-section">
        <h2 className="pet-performance-section-title">PET Workload</h2>
        <table className="widefat striped pet-performance-table">
          <thead>
            <tr>
              <th>Workload</th>
              <th>Query Count</th>
              <th>Execution Time (ms)</th>
            </tr>
          </thead>
          <tbody>
            {WORKLOAD_CONTRACT_KEYS.map((entry) => {
              const row = payload?.metrics?.workload?.[entry.key];
              return (
                <tr key={entry.key}>
                  <td className="pet-performance-workload-cell">{entry.label}<br /><code>{entry.key}</code></td>
                  <td>{row?.query_count ?? 0}</td>
                  <td>{row?.execution_time_ms ?? 0}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
        <p className="pet-performance-note">
          Non-contract workload keys are not promoted as first-class UI rows.
        </p>
      </section>

      <section className="pet-panel pet-performance-section">
        <h2 className="pet-performance-section-title">Recommendations</h2>
        {!payload?.metrics?.recommendations?.length ? (
          <p className="pet-performance-empty-state">No recommendations.</p>
        ) : (
          <table className="widefat striped pet-performance-table">
            <thead>
              <tr>
                <th>Issue</th>
                <th>Severity</th>
                <th>Title</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              {payload.metrics.recommendations.map((recommendation, index) => (
                <tr key={`${recommendation.issue_key ?? 'recommendation'}-${index}`}>
                  <td><code>{recommendation.issue_key ?? 'n/a'}</code></td>
                  <td>
                    <span className={severityClassName(recommendation.severity)}>
                      {recommendation.severity ?? 'n/a'}
                    </span>
                  </td>
                  <td>{recommendation.title ?? 'n/a'}</td>
                  <td>{recommendation.message ?? 'n/a'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      <section className="pet-panel pet-performance-section">
        <h2 className="pet-performance-section-title">Probe Errors</h2>
        {!payload?.metrics?.errors?.length ? (
          <p className="pet-performance-empty-state">No probe errors.</p>
        ) : (
          <table className="widefat striped pet-performance-table">
            <thead>
              <tr>
                <th>Metric Key</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              {payload.metrics.errors.map((probeError, index) => (
                <tr key={`${probeError.metric_key}-${index}`}>
                  <td><code>{probeError.metric_key}</code></td>
                  <td>{probeError.message}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
};

export default Performance;
