import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';

type EventRow = {
  id: number;
  eventUuid: string;
  occurredAt: string;
  recordedAt: string;
  aggregateType: string;
  aggregateId: number;
  aggregateVersion: number;
  eventType: string;
  eventSchemaVersion: number;
  actorType?: string | null;
  actorId?: number | null;
  correlationId?: string | null;
  causationId?: string | null;
  payloadJson: string;
  metadataJson?: string | null;
};

const EventStreamViewer: React.FC = () => {
  const [events, setEvents] = useState<EventRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchEvents = async () => {
      try {
        const response = await fetch(`${window.petSettings.apiUrl}/event-stream?limit=50`, {
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
          },
        });
        if (!response.ok) throw new Error('Failed to fetch event stream');
        const data = await response.json();
        setEvents(Array.isArray(data) ? data : []);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    };
    fetchEvents();
  }, []);

  const columns: Column<EventRow>[] = [
    { key: 'id', header: 'ID', render: (val) => String(val) },
    { key: 'occurredAt', header: 'Occurred', render: (val) => val },
    { key: 'eventType', header: 'Event', render: (val) => <span style={{ fontFamily: 'monospace' }}>{val}</span> },
    { key: 'aggregateType', header: 'Aggregate', render: (val, row) => `${val} #${row.aggregateId}` },
    { key: 'aggregateVersion', header: 'Version', render: (val) => String(val) },
    { key: 'actorType', header: 'Actor', render: (val, row) => val ? `${val}${row.actorId ? ' #' + row.actorId : ''}` : '-' },
    { key: 'correlationId', header: 'Correlation', render: (val) => val || '-' },
    { key: 'causationId', header: 'Causation', render: (val) => val || '-' },
    { key: 'eventUuid', header: 'UUID', render: (val) => <span style={{ fontFamily: 'monospace', fontSize: '11px' }}>{val}</span> },
  ];

  if (loading) return <div>Loading event stream...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-card" style={{ marginTop: '10px' }}>
      <DataTable columns={columns} data={events} emptyMessage="No events recorded yet." />
    </div>
  );
};

export default EventStreamViewer;
