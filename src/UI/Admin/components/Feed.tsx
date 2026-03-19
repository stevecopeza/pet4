import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import { FeedEvent, Announcement } from '../types';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

const Feed = () => {
  const [events, setEvents] = useState<FeedEvent[]>([]);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchFeed = async () => {
    try {
      setLoading(true);
      const apiUrl = (window as any).petSettings.apiUrl;
      const nonce = (window as any).petSettings.nonce;

      const [eventsRes, annRes] = await Promise.all([
        fetch(`${apiUrl}/feed`, { headers: { 'X-WP-Nonce': nonce } }),
        fetch(`${apiUrl}/announcements`, { headers: { 'X-WP-Nonce': nonce } }),
      ]);

      if (!eventsRes.ok) throw new Error('Failed to fetch feed events');
      if (!annRes.ok) throw new Error('Failed to fetch announcements');

      const eventsData = await eventsRes.json();
      const annData = await annRes.json();
      
      setEvents(Array.isArray(eventsData) ? eventsData : []);
      setAnnouncements(Array.isArray(annData) ? annData : []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFeed();
  }, []);

  const reactToEvent = async (eventId: string, reactionType: string) => {
    try {
      const apiUrl = (window as any).petSettings.apiUrl;
      const nonce = (window as any).petSettings.nonce;
      const res = await fetch(`${apiUrl}/feed/${eventId}/react`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ reactionType }),
      });
      if (!res.ok) throw new Error('Failed to react to event');
      fetchFeed();
    } catch (err) {
      legacyAlert(err instanceof Error ? err.message : 'Failed to react');
    }
  };

  const acknowledgeAnnouncement = async (announcementId: string) => {
    try {
      const apiUrl = (window as any).petSettings.apiUrl;
      const nonce = (window as any).petSettings.nonce;
      const res = await fetch(`${apiUrl}/announcements/${announcementId}/ack`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({}),
      });
      if (!res.ok) throw new Error('Failed to acknowledge announcement');
      fetchFeed();
    } catch (err) {
      legacyAlert(err instanceof Error ? err.message : 'Failed to acknowledge');
    }
  };

  const scopeBadge = (scope: string, ref?: string | null) => (
    <span style={{ textTransform: 'uppercase', fontSize: '11px', fontWeight: 'bold', padding: '2px 6px', background: '#eef', borderRadius: '3px' }}>
      {scope}{ref ? `:${ref}` : ''}
    </span>
  );

  const eventColumns: Column<FeedEvent>[] = [
    { key: 'createdAt', header: 'Date/Time', render: (val) => String(val) },
    { key: 'classification', header: 'Class', render: (val) => <span style={{ textTransform: 'uppercase', fontSize: '11px', fontWeight: 'bold', padding: '2px 6px', background: '#eee', borderRadius: '3px' }}>{String(val)}</span> },
    { key: 'title', header: 'Title', render: (val) => String(val) },
    { key: 'summary', header: 'Summary', render: (val) => String(val) },
    { key: 'audienceScope', header: 'Scope', render: (val, item) => (scopeBadge(String(val), String((item as FeedEvent).audienceReferenceId || '')) as React.ReactNode) },
    { key: 'pinned', header: 'Pinned', render: (val) => (val ? 'Yes' : '-') },
    {
      key: 'id',
      header: 'Actions',
      render: (_, item) => (
        <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
          <button className="button button-small" onClick={() => reactToEvent(item.id, 'acknowledged')}>Ack</button>
          <button className="button button-small" onClick={() => reactToEvent(item.id, 'win')}>Win</button>
          <button className="button button-small" onClick={() => reactToEvent(item.id, 'concern')}>Concern</button>
          <button className="button button-small" onClick={() => reactToEvent(item.id, 'suggestion')}>Suggest</button>
        </div>
      ),
    },
  ];

  const annColumns: Column<Announcement>[] = [
    { key: 'createdAt', header: 'Date/Time', render: (val) => String(val) },
    { key: 'priorityLevel', header: 'Priority', render: (val) => <span className={`pet-priority-badge priority-${val}`}>{val}</span> },
    { key: 'title', header: 'Title', render: (val) => String(val) },
    { key: 'body', header: 'Body', render: (val) => String(val) },
    { key: 'audienceScope', header: 'Scope', render: (val, item) => (scopeBadge(String(val), String((item as Announcement).audienceReferenceId || '')) as React.ReactNode) },
    { key: 'pinned', header: 'Pinned', render: (val) => (val ? 'Yes' : '-') },
    { key: 'acknowledgementRequired', header: 'Ack Req', render: (val) => (val ? 'Yes' : '-') },
    {
      key: 'id',
      header: 'Actions',
      render: (_, item) => (
        <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
          <button className="button button-small" onClick={() => acknowledgeAnnouncement(item.id)}>Acknowledge</button>
        </div>
      ),
    },
  ];

  if (loading) return <div>Loading feed...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;

  return (
    <div className="pet-feed">
      <h2>Operational Feed</h2>
      <p>Relevant events and announcements.</p>

      <div style={{ marginBottom: '25px' }}>
        <h3 style={{ marginBottom: '10px' }}>Announcements</h3>
        <DataTable columns={annColumns} data={announcements} emptyMessage="No announcements." />
      </div>

      <div>
        <h3 style={{ marginBottom: '10px' }}>Events</h3>
        <DataTable columns={eventColumns} data={events} emptyMessage="No events." />
      </div>
    </div>
  );
};

export default Feed;
