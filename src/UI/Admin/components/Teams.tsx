import React, { useEffect, useState } from 'react';
import { Team, Employee } from '../types';
import { DataTable, Column } from './DataTable';
import TeamForm from './TeamForm';
import TeamView from './TeamView';

interface FlatTeam extends Team {
  depth: number;
}

const Teams = () => {
  const [teams, setTeams] = useState<Team[]>([]);
  const [flatTeams, setFlatTeams] = useState<FlatTeam[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingTeam, setEditingTeam] = useState<Team | null>(null);
  const [viewingTeam, setViewingTeam] = useState<Team | null>(null);
  const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);

  const fetchTeams = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/teams`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch teams');
      }

      const data = await response.json();
      setTeams(data);
      setFlatTeams(flattenTree(data));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
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

      if (!response.ok) {
        throw new Error('Failed to fetch employees');
      }

      const data = await response.json();
      setEmployees(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('Failed to fetch employees for Teams view', err);
    }
  };

  const flattenTree = (nodes: Team[], depth = 0): FlatTeam[] => {
    let flat: FlatTeam[] = [];
    nodes.forEach(node => {
      flat.push({ ...node, depth });
      if (node.children && node.children.length > 0) {
        flat = [...flat, ...flattenTree(node.children, depth + 1)];
      }
    });
    return flat;
  };

  useEffect(() => {
    fetchTeams();
    fetchEmployees();
  }, []);

  const findEmployeeById = (id: number): Employee | undefined => {
    return employees.find(e => e.id === id);
  };

  const getUserEditUrl = (wpUserId: number): string => {
    // @ts-ignore
    const ajaxUrl = typeof window !== 'undefined' ? window.ajaxurl : undefined;
    let base = '/wp-admin/';
    if (ajaxUrl && typeof ajaxUrl === 'string') {
      const idx = ajaxUrl.indexOf('admin-ajax.php');
      if (idx !== -1) {
        base = ajaxUrl.substring(0, idx);
      }
    }
    if (!base.endsWith('/')) {
      base += '/';
    }
    return `${base}user-edit.php?user_id=${wpUserId}`;
  };

  const handleFormSuccess = () => {
    setShowAddForm(false);
    setEditingTeam(null);
    setViewingTeam(null);
    fetchTeams();
  };

  const handleEdit = (team: Team) => {
    setViewingTeam(null);
    setEditingTeam(team);
    setShowAddForm(true);
  };

  const handleView = (team: Team) => {
    setEditingTeam(null);
    setShowAddForm(false);
    setViewingTeam(team);
  };

  const handleArchive = async (id: number) => {
    if (!confirm('Are you sure you want to archive this team?')) return;

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/teams/${id}/archive`, {
        method: 'POST', // Using POST for explicit archive action
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to archive team');
      }

      fetchTeams();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to archive');
    }
  };

  const handleBulkArchive = async () => {
    if (!confirm(`Are you sure you want to archive ${selectedIds.length} teams?`)) return;

    // @ts-ignore
    const apiUrl = window.petSettings?.apiUrl;
    // @ts-ignore
    const nonce = window.petSettings?.nonce;

    // Process sequentially
    for (const id of selectedIds) {
      try {
        await fetch(`${apiUrl}/teams/${id}/archive`, {
          method: 'POST',
          headers: {
            'X-WP-Nonce': nonce,
          },
        });
      } catch (e) {
        console.error(`Failed to archive ${id}`, e);
      }
    }
    
    setSelectedIds([]);
    fetchTeams();
  };

  const columns: Column<FlatTeam>[] = [
    { 
      key: 'name', 
      header: 'Team Name',
      width: '40%',
      render: (val: any, item: FlatTeam) => (
        <span style={{ paddingLeft: `${item.depth * 20}px`, whiteSpace: 'nowrap' }}>
          {item.depth > 0 && <span className="dashicons dashicons-marker" style={{ fontSize: '14px', color: '#ccc', marginRight: '5px' }}></span>}
          {item.visual?.type === 'icon' && item.visual.ref && (
            item.visual.ref.startsWith('http') || item.visual.ref.startsWith('/') ? (
              <img src={item.visual.ref} alt="" style={{ width: '20px', height: '20px', marginRight: '5px', verticalAlign: 'middle', borderRadius: '4px' }} />
            ) : (
              <span className={`dashicons ${item.visual.ref}`} style={{ marginRight: '5px', color: '#666' }}></span>
            )
          )}
          {item.visual?.type === 'color' && item.visual.ref && (
            <span style={{ display: 'inline-block', width: '10px', height: '10px', backgroundColor: item.visual.ref, borderRadius: '50%', marginRight: '5px' }}></span>
          )}
          <a
            href="#"
            onClick={(e) => { e.preventDefault(); handleView(item); }}
            style={{ fontWeight: 'bold', textDecoration: 'none', color: '#2271b1', cursor: 'pointer', whiteSpace: 'nowrap' }}
            className="row-title"
          >
            {String(val).replace(/\s+/g, '\u00a0')}
          </a>
        </span>
      )
    },
    { 
      key: 'manager_id', 
      header: 'Manager',
      width: '30%',
      render: (val: any, item: FlatTeam) => {
        if (!val) {
          return '-';
        }
        const manager = findEmployeeById(Number(val));
        if (!manager) {
          return `User #${val}`;
        }
        const fullName = `${manager.firstName} ${manager.lastName}`.trim() || manager.email || `User #${manager.wpUserId}`;
        const href = getUserEditUrl(manager.wpUserId);
        return (
          <a
            href={href}
            style={{ color: '#2271b1', textDecoration: 'none', cursor: 'pointer' }}
          >
            {fullName}
          </a>
        );
      }
    },
    {
      key: 'member_ids',
      header: 'Members',
      width: '10%',
      render: (_: any, item: FlatTeam) => {
        const count = Array.isArray(item.member_ids) ? item.member_ids.length : 0;
        return count;
      }
    },
    {
      key: 'id',
      header: 'Online now',
      width: '10%',
      render: () => '0'
    },
  ];

  return (
    <div className="pet-teams-container">
      {loading && !teams.length ? <div>Loading teams...</div> :
      error ? <div style={{ color: 'red' }}>Error: {error}</div> :
      <>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
          <h2>Teams</h2>
          {!showAddForm && (
            <button className="button button-primary" onClick={() => setShowAddForm(true)}>
              Add New Team
            </button>
          )}
        </div>

        {viewingTeam && (
          <TeamView
            team={viewingTeam}
            onClose={() => setViewingTeam(null)}
            onEdit={() => handleEdit(viewingTeam)}
            allTeams={teams}
          />
        )}

        {showAddForm && (
          <TeamForm 
            onSuccess={handleFormSuccess} 
            onCancel={() => { setShowAddForm(false); setEditingTeam(null); }} 
            initialData={editingTeam || undefined}
            teams={teams}
          />
        )}

        {selectedIds.length > 0 && (
          <div style={{ padding: '10px', background: '#e5f5fa', border: '1px solid #b5e1ef', marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '15px' }}>
            <strong>{selectedIds.length} items selected</strong>
            <button className="button button-link-delete" style={{ color: '#a00', borderColor: '#a00' }} onClick={handleBulkArchive}>Archive Selected</button>
          </div>
        )}

        <DataTable 
          columns={columns} 
          data={flatTeams} 
          emptyMessage="No teams found."
          selection={{
            selectedIds,
            onSelectionChange: setSelectedIds
          }}
          actions={(item) => (
            <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
              <button 
                className={`button button-small`}
                onClick={() => handleEdit(item)}
              >
                Edit
              </button>
              <button 
                className={`button button-small button-link-delete`}
                style={{ color: '#a00', borderColor: '#a00' }}
                onClick={() => handleArchive(item.id)}
              >
                Archive
              </button>
            </div>
          )}
        />
      </>
      }
    </div>
  );
};

export default Teams;
