import React, { useCallback, useEffect, useRef, useState } from 'react';
import ConversationPanel from './ConversationPanel';
import { ConversationOpenParams } from './ConversationProvider';
import { Conversation, ConversationParticipant, Employee, Team } from '../types';
import useNameMap from '../hooks/useNameMap';
import '../styles/conversation-drawer.css';
import { legacyAlert, legacyConfirm } from './legacyDialogs';

interface ConversationDrawerProps {
  params: ConversationOpenParams | null;
  onClose: () => void;
}

const getSettings = () => (window as any).petSettings;

const ConversationDrawer: React.FC<ConversationDrawerProps> = ({ params, onClose }) => {
  const { nameMap } = useNameMap();
  const [convData, setConvData] = useState<Conversation | null>(null);
  const [refreshSignal, setRefreshSignal] = useState(0);
  const [showParticipants, setShowParticipants] = useState(false);
  const [participantSearch, setParticipantSearch] = useState('');
  const [addingParticipant, setAddingParticipant] = useState(false);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [participantSourceLoaded, setParticipantSourceLoaded] = useState(false);
  const popoverRef = useRef<HTMLDivElement>(null);

  // Reset state when params change
  useEffect(() => {
    setConvData(null);
    setRefreshSignal(0);
    setShowParticipants(false);
    setParticipantSearch('');
    setAddingParticipant(false);
  }, [params?.contextType, params?.contextId, params?.subjectKey]);

  useEffect(() => {
    if (!params) return;
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        if (showParticipants) setShowParticipants(false);
        else onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [params, onClose, showParticipants]);

  // Close popover on outside click
  useEffect(() => {
    if (!showParticipants) return;
    const handleClick = (e: MouseEvent) => {
      if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
        setShowParticipants(false);
      }
    };
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, [showParticipants]);

  const handleConversationChange = useCallback((conv: Conversation | null) => {
    setConvData(conv);
  }, []);

  const handleResolve = async () => {
    if (!convData) return;
    try {
      const res = await fetch(`${getSettings().apiUrl}/conversations/${convData.uuid}/resolve`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getSettings().nonce },
      });
      if (!res.ok) throw new Error('Failed to resolve');
      setRefreshSignal((s) => s + 1);
    } catch (e) {
      console.error(e);
    }
  };

  const handleReopen = async () => {
    if (!convData) return;
    try {
      const res = await fetch(`${getSettings().apiUrl}/conversations/${convData.uuid}/reopen`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getSettings().nonce },
      });
      if (!res.ok) throw new Error('Failed to reopen');
      setRefreshSignal((s) => s + 1);
    } catch (e) {
      console.error(e);
    }
  };

  // Lazy-load employee/team lists for add-participant search
  const loadParticipantSources = async () => {
    if (participantSourceLoaded) return;
    try {
      const headers = { 'X-WP-Nonce': getSettings().nonce };
      const [empRes, teamRes] = await Promise.all([
        fetch(`${getSettings().apiUrl}/employees`, { headers }),
        fetch(`${getSettings().apiUrl}/teams`, { headers }),
      ]);
      if (empRes.ok) setEmployees(await empRes.json());
      if (teamRes.ok) setTeams(await teamRes.json());
      setParticipantSourceLoaded(true);
    } catch (e) {
      console.error(e);
    }
  };

  const handleAddParticipant = async (type: string, id: number) => {
    if (!convData) return;
    setAddingParticipant(true);
    try {
      const res = await fetch(`${getSettings().apiUrl}/conversations/${convData.uuid}/participants/add`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getSettings().nonce },
        body: JSON.stringify({ participant_type: type, participant_id: id }),
      });
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.error || 'Failed to add participant');
      }
      setParticipantSearch('');
      setRefreshSignal((s) => s + 1);
    } catch (e) {
      legacyAlert(e instanceof Error ? e.message : 'Error adding participant');
    } finally {
      setAddingParticipant(false);
    }
  };

  const handleRemoveParticipant = async (type: string, id: number) => {
    if (!convData) return;
    try {
      const res = await fetch(`${getSettings().apiUrl}/conversations/${convData.uuid}/participants/remove`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getSettings().nonce },
        body: JSON.stringify({ participant_type: type, participant_id: id }),
      });
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.error || 'Failed to remove participant');
      }
      setRefreshSignal((s) => s + 1);
    } catch (e) {
      legacyAlert(e instanceof Error ? e.message : 'Error removing participant');
    }
  };

  if (!params) return null;

  const participants: ConversationParticipant[] = convData?.participants ?? [];
  const stateDot = convData?.state === 'resolved'
    ? { color: '#3b82f6', label: 'Resolved' }
    : convData
      ? { color: '#22c55e', label: 'Open' }
      : null;

  // Filter employee/team search results (exclude existing participants)
  const existingUserIds = new Set(participants.filter((p) => p.type === 'user').map((p) => p.id));
  const existingTeamIds = new Set(participants.filter((p) => p.type === 'team').map((p) => p.id));
  const searchLower = participantSearch.toLowerCase();
  const filteredEmployees = participantSearch.length >= 1
    ? employees.filter((e) => {
        if (existingUserIds.has(e.wpUserId)) return false;
        const name = (e.displayName || `${e.firstName} ${e.lastName}`).toLowerCase();
        return name.includes(searchLower);
      }).slice(0, 5)
    : [];
  const flatTeams = flattenTeamsHelper(teams);
  const filteredTeams = participantSearch.length >= 1
    ? flatTeams.filter((t) => {
        if (existingTeamIds.has(t.id)) return false;
        return t.name.toLowerCase().includes(searchLower);
      }).slice(0, 3)
    : [];

  return (
    <>
      <div className="pet-drawer-backdrop" onClick={onClose} />
      <aside className="pet-drawer" role="dialog" aria-label={`Conversation: ${params.subject}`}>
        <header className="pet-drawer-header">
          <div className="pet-drawer-header-left">
            {stateDot && (
              <span
                className="pet-drawer-status-dot"
                style={{ background: stateDot.color }}
                title={stateDot.label}
              />
            )}
            <h3 className="pet-drawer-title">{params.subject}</h3>
          </div>
          <div className="pet-drawer-header-actions">
            {/* Participant count */}
            {convData && (
              <button
                className="pet-drawer-participants-btn"
                onClick={() => { setShowParticipants((s) => !s); loadParticipantSources(); }}
                title="Participants"
              >
                {participants.length} 👤
              </button>
            )}
            {/* Resolve / Reopen */}
            {convData?.state === 'open' && (
              <button className="pet-drawer-action-btn" onClick={handleResolve} title="Resolve conversation">
                ✅ Resolve
              </button>
            )}
            {convData?.state === 'resolved' && (
              <button className="pet-drawer-action-btn" onClick={handleReopen} title="Reopen conversation">
                🔓 Reopen
              </button>
            )}
            <button className="pet-drawer-close" onClick={onClose} aria-label="Close">&times;</button>
          </div>
        </header>

        {/* Participant popover */}
        {showParticipants && (
          <div className="pet-drawer-popover" ref={popoverRef}>
            <div style={{ fontWeight: 600, marginBottom: '8px', fontSize: '13px' }}>Participants</div>
            {participants.map((p) => (
              <div key={`${p.type}-${p.id}`} className="pet-drawer-popover-row">
                <span className="pet-drawer-popover-type">{p.type === 'user' ? '👤' : p.type === 'team' ? '👥' : '📇'}</span>
                <span style={{ flex: 1 }}>{p.name}</span>
                {/* Don't allow removing last user participant */}
                {!(p.type === 'user' && participants.filter((pp) => pp.type === 'user').length <= 1) && (
                  <button
                    className="pet-drawer-popover-remove"
                    onClick={() => handleRemoveParticipant(p.type, p.id)}
                    title="Remove"
                  >
                    &times;
                  </button>
                )}
              </div>
            ))}
            <div style={{ borderTop: '1px solid #eee', marginTop: '8px', paddingTop: '8px' }}>
              <input
                type="text"
                placeholder="+ Add participant..."
                value={participantSearch}
                onChange={(e) => setParticipantSearch(e.target.value)}
                style={{ width: '100%', padding: '4px 8px', fontSize: '12px', border: '1px solid #ccc', borderRadius: '3px' }}
                disabled={addingParticipant}
              />
              {(filteredEmployees.length > 0 || filteredTeams.length > 0) && (
                <div style={{ marginTop: '4px', maxHeight: '150px', overflowY: 'auto' }}>
                  {filteredEmployees.map((emp) => (
                    <button
                      key={`emp-${emp.wpUserId}`}
                      className="pet-drawer-popover-add-item"
                      onClick={() => handleAddParticipant('user', emp.wpUserId)}
                      disabled={addingParticipant}
                    >
                      👤 {emp.displayName || `${emp.firstName} ${emp.lastName}`}
                    </button>
                  ))}
                  {filteredTeams.map((t) => (
                    <button
                      key={`team-${t.id}`}
                      className="pet-drawer-popover-add-item"
                      onClick={() => handleAddParticipant('team', t.id)}
                      disabled={addingParticipant}
                    >
                      👥 {t.name}
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        <div className="pet-drawer-body">
          <ConversationPanel
            uuid={params.uuid}
            contextType={params.contextType}
            contextId={params.contextId}
            contextVersion={params.contextVersion}
            defaultSubject={params.subject}
            subjectKey={params.subjectKey}
            nameMap={nameMap}
            onConversationChange={handleConversationChange}
            refreshSignal={refreshSignal}
          />
        </div>
      </aside>
    </>
  );
};

function flattenTeamsHelper(nodes: Team[]): Team[] {
  let flat: Team[] = [];
  for (const n of nodes) {
    flat.push(n);
    if (n.children?.length) flat = flat.concat(flattenTeamsHelper(n.children));
  }
  return flat;
}

export default ConversationDrawer;
