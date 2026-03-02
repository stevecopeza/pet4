import React, { useEffect, useState } from 'react';
import { WorkItem } from '../types';
import { DataTable, Column } from './DataTable';

const WorkItems = () => {
  const [items, setItems] = useState<WorkItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<'my' | 'unassigned' | 'all'>('my');

  // @ts-ignore
  const currentUserId = window.petSettings?.currentUserId;

  const fetchItems = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings.nonce;

      let url = `${apiUrl}/work-items`;
      
      if (filter === 'my' && currentUserId) {
        url += `?assigned_user_id=${currentUserId}`;
      } else if (filter === 'unassigned') {
        // We might need a department ID here, but for now let's just ask for unassigned generally if the API supports it
        // The controller expects department_id AND unassigned=1. 
        // For now, let's just fetch active items if 'all' or 'unassigned' without dept.
        // Or maybe we should add a 'my-department' filter later.
        // Let's stick to 'active' for 'all' and 'my' for 'my'.
        // If filter is 'unassigned', we need to know the user's department.
        // Since we don't have that easily, let's just support 'my' and 'all' (active) for now.
      }
      
      // Re-logic:
      // If 'my', pass assigned_user_id.
      // If 'all', pass nothing (defaults to active).
      
      if (filter === 'my' && currentUserId) {
         url = `${apiUrl}/work-items?assigned_user_id=${currentUserId}`;
      } else {
         url = `${apiUrl}/work-items`;
      }

      const response = await fetch(url, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch work items');
      }

      const data = await response.json();
      setItems(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchItems();
  }, [filter]);

  const handleAssign = async (id: string) => {
    if (!currentUserId) return;
    if (!confirm('Assign this item to yourself?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings.nonce;

      const response = await fetch(`${apiUrl}/work-items/${id}/assign`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          assigned_user_id: currentUserId
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to assign item');
      }

      fetchItems();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to assign');
    }
  };

  const columns: Column<WorkItem>[] = [
    { key: 'id', header: 'ID' },
    { key: 'source_type', header: 'Type' },
    { 
      key: 'priority_score', 
      header: 'Priority',
      render: (value) => (
        <span style={{ 
          fontWeight: 'bold', 
          color: (value as number) > 80 ? '#d63638' : ((value as number) > 50 ? '#d46f15' : 'inherit') 
        }}>
          {Number(value).toFixed(1)}
        </span>
      )
    },
    { 
      key: 'sla_time_remaining', 
      header: 'SLA Clock',
      render: (value) => {
        if (value === null) return '-';
        const minutes = value as number;
        const color = minutes < 0 ? '#d63638' : (minutes < 60 ? '#d46f15' : 'green');
        return <span style={{ color }}>{minutes} min</span>;
      }
    },
    { key: 'status', header: 'Status' },
    {
      key: 'id', // Placeholder key for actions
      header: 'Actions',
      render: (_, item) => (
        <div style={{ display: 'flex', gap: '8px' }}>
            {!item.assigned_user_id && (
                <button 
                    className="button button-small"
                    onClick={() => handleAssign(item.id)}
                >
                    Pick Up
                </button>
            )}
            {/* Future: Add Prioritize / Open Source actions */}
        </div>
      )
    }
  ];

  if (loading && items.length === 0) return <div>Loading...</div>;
  if (error) return <div className="notice notice-error"><p>{error}</p></div>;

  return (
    <div className="wrap">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2 style={{ margin: 0 }}>My Work</h2>
        <div style={{ display: 'flex', gap: '10px' }}>
            <select 
                value={filter} 
                onChange={(e) => setFilter(e.target.value as any)}
                style={{ height: '30px' }}
            >
                <option value="my">My Items</option>
                <option value="all">All Active</option>
            </select>
            <button className="button" onClick={fetchItems}>Refresh</button>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={items}
        emptyMessage="No work items found."
      />
    </div>
  );
};

export default WorkItems;
