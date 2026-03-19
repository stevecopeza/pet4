import React, { useEffect, useState } from 'react';
import { ActivityLog } from '../types';
import Feed from './Feed';
import EventStreamViewer from './EventStreamViewer';
import { DataTable, Column } from './DataTable';
import LoadingState from './foundation/states/LoadingState';
import ErrorState from './foundation/states/ErrorState';

const Activity = () => {
  const [logs, setLogs] = useState<ActivityLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchLogs = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/activity?limit=100`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch activity logs');
      }

      const data = await response.json();
      // Handle both array and paginated response format
      if (data && Array.isArray(data.items)) {
        setLogs(data.items);
      } else if (Array.isArray(data)) {
        setLogs(data);
      } else {
        console.warn('Activity: Unexpected response format', data);
        setLogs([]);
      }
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {

    fetchLogs();
  }, []);

  const columns: Column<ActivityLog>[] = [
    { key: 'occurred_at', header: 'Date/Time', render: (val) => val },
    { key: 'event_type', header: 'Type', render: (val) => <span style={{ textTransform: 'uppercase', fontSize: '11px', fontWeight: 'bold', padding: '2px 6px', background: '#eee', borderRadius: '3px' }}>{val}</span> },
    { key: 'headline', header: 'Description', render: (val) => val },
    { key: 'actor_display_name', header: 'User', render: (val) => val || '-' },
    { 
      key: 'reference_type', 
      header: 'Related Entity', 
      render: (val, item) => val ? `${val} #${item.reference_id}` : '-' 
    },
  ];

  if (loading && !logs.length) return <LoadingState label="Loading activity feed…" />;
  if (error && !logs.length) return <ErrorState message={error} onRetry={fetchLogs} />;

  return (
    <div className="pet-activity">
      <Feed />
      <h2 style={{ marginTop: '30px' }}>Activity Log</h2>
      <p>Recent system activity.</p>
      
      <DataTable 
        columns={columns} 
        data={logs} 
        loading={loading}
        error={error}
        onRetry={fetchLogs}
        emptyMessage="No activity recorded yet." 
        compatibilityMode="wp"
      />

      <h2 style={{ marginTop: '30px' }}>Event Stream</h2>
      <p>Immutable domain event stream (read-only).</p>
      <EventStreamViewer />
    </div>
  );
};

export default Activity;
