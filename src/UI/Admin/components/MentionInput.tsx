import React, { useEffect, useRef, useState } from 'react';
import { NameMap } from '../hooks/useNameMap';
import { Employee, Team } from '../types';

interface MentionInputProps {
  value: string;
  onChange: (value: string) => void;
  onSubmit: () => void;
  disabled?: boolean;
  placeholder?: string;
  nameMap?: NameMap | null;
}

interface MentionCandidate {
  type: 'user' | 'team';
  id: number;
  label: string;
}

// Module-level cache for employee/team data (shared with useNameMap fetch)
let cachedEmployees: Employee[] | null = null;
let cachedTeams: Team[] | null = null;
let sourceFetchPromise: Promise<void> | null = null;

async function ensureSources(): Promise<void> {
  if (cachedEmployees && cachedTeams) return;
  if (sourceFetchPromise) return sourceFetchPromise;

  sourceFetchPromise = (async () => {
    const settings = (window as any).petSettings;
    const headers = { 'X-WP-Nonce': settings.nonce };
    const [empRes, teamRes] = await Promise.all([
      fetch(`${settings.apiUrl}/employees`, { headers }),
      fetch(`${settings.apiUrl}/teams`, { headers }),
    ]);
    cachedEmployees = empRes.ok ? await empRes.json() : [];
    cachedTeams = teamRes.ok ? await teamRes.json() : [];
  })();

  return sourceFetchPromise;
}

function flattenTeams(nodes: Team[]): Team[] {
  let flat: Team[] = [];
  for (const n of nodes) {
    flat.push(n);
    if (n.children?.length) flat = flat.concat(flattenTeams(n.children));
  }
  return flat;
}

const MentionInput: React.FC<MentionInputProps> = ({
  value,
  onChange,
  onSubmit,
  disabled,
  placeholder,
  nameMap,
}) => {
  const inputRef = useRef<HTMLInputElement>(null);
  const [showDropdown, setShowDropdown] = useState(false);
  const [candidates, setCandidates] = useState<MentionCandidate[]>([]);
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [mentionQuery, setMentionQuery] = useState('');
  const [mentionStart, setMentionStart] = useState(-1);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Detect @ trigger
  useEffect(() => {
    const input = inputRef.current;
    if (!input) return;

    const cursorPos = input.selectionStart ?? value.length;
    // Look backwards from cursor for @
    const textBefore = value.substring(0, cursorPos);
    const atMatch = textBefore.match(/@([^\s@]*)$/);

    if (atMatch) {
      const query = atMatch[1];
      setMentionQuery(query);
      setMentionStart(cursorPos - query.length - 1); // position of @
      if (query.length >= 1) {
        searchCandidates(query);
        setShowDropdown(true);
      } else {
        // Just typed @, show all
        searchCandidates('');
        setShowDropdown(true);
      }
    } else {
      setShowDropdown(false);
      setMentionQuery('');
      setMentionStart(-1);
    }
  }, [value]);

  const searchCandidates = async (query: string) => {
    await ensureSources();
    const q = query.toLowerCase();
    const results: MentionCandidate[] = [];

    // Users
    for (const emp of cachedEmployees || []) {
      const name = emp.displayName || `${emp.firstName} ${emp.lastName}`.trim();
      if (!q || name.toLowerCase().includes(q)) {
        results.push({ type: 'user', id: emp.wpUserId, label: name });
      }
      if (results.length >= 8) break;
    }

    // Teams
    if (results.length < 8) {
      for (const t of flattenTeams(cachedTeams || [])) {
        if (!q || t.name.toLowerCase().includes(q)) {
          results.push({ type: 'team', id: t.id, label: t.name });
        }
        if (results.length >= 8) break;
      }
    }

    setCandidates(results);
    setSelectedIndex(0);
  };

  const insertMention = (candidate: MentionCandidate) => {
    if (mentionStart < 0) return;

    const cursorPos = inputRef.current?.selectionStart ?? value.length;
    const before = value.substring(0, mentionStart);
    const after = value.substring(cursorPos);
    const token = `@[${candidate.type}:${candidate.id}]`;
    const newValue = `${before}${token} ${after}`;

    onChange(newValue);
    setShowDropdown(false);
    setMentionQuery('');
    setMentionStart(-1);

    // Restore focus
    setTimeout(() => {
      const input = inputRef.current;
      if (input) {
        const newPos = before.length + token.length + 1;
        input.focus();
        input.setSelectionRange(newPos, newPos);
      }
    }, 0);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (showDropdown && candidates.length > 0) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setSelectedIndex((i) => Math.min(i + 1, candidates.length - 1));
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        setSelectedIndex((i) => Math.max(i - 1, 0));
        return;
      }
      if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault();
        insertMention(candidates[selectedIndex]);
        return;
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        setShowDropdown(false);
        return;
      }
    }

    if (e.key === 'Enter' && !showDropdown) {
      onSubmit();
    }
  };

  // Render display value with pills (visual only — the actual value has tokens)
  const renderDisplayValue = (): string => {
    if (!nameMap) return value;
    return value.replace(/@\[(user|team):(\d+)\]/g, (_, type, id) => {
      const numId = Number(id);
      if (type === 'user') {
        return `@${nameMap.users.get(numId)?.name ?? `User #${numId}`}`;
      }
      return `@${nameMap.teams.get(numId)?.name ?? `Team #${numId}`}`;
    });
  };

  return (
    <div style={{ position: 'relative', flex: 1 }}>
      <input
        ref={inputRef}
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={handleKeyDown}
        placeholder={placeholder || 'Type a message... (@ to mention)'}
        style={{ width: '100%', padding: '8px' }}
        disabled={disabled}
        autoComplete="off"
      />

      {showDropdown && candidates.length > 0 && (
        <div
          ref={dropdownRef}
          className="pet-mention-dropdown"
          style={{
            position: 'absolute',
            bottom: '100%',
            left: 0,
            right: 0,
            background: '#fff',
            border: '1px solid #ccc',
            borderRadius: '4px',
            boxShadow: '0 -2px 8px rgba(0,0,0,0.1)',
            maxHeight: '200px',
            overflowY: 'auto',
            zIndex: 20,
            marginBottom: '4px',
          }}
        >
          {candidates.map((c, i) => (
            <button
              key={`${c.type}-${c.id}`}
              type="button"
              onMouseDown={(e) => {
                e.preventDefault(); // prevent input blur
                insertMention(c);
              }}
              style={{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                padding: '6px 10px',
                border: 'none',
                background: i === selectedIndex ? '#e8f0fe' : 'transparent',
                cursor: 'pointer',
                fontSize: '13px',
              }}
            >
              <span style={{ marginRight: '6px' }}>
                {c.type === 'user' ? '\ud83d\udc64' : '\ud83d\udc65'}
              </span>
              {c.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
};

/** Parse @[type:id] tokens from a message body into mentions array for the API */
export function parseMentionTokens(body: string): { type: string; id: number }[] {
  const mentions: { type: string; id: number }[] = [];
  const regex = /@\[(user|team):(\d+)\]/g;
  let match;
  while ((match = regex.exec(body)) !== null) {
    mentions.push({ type: match[1], id: Number(match[2]) });
  }
  return mentions;
}

/** Render a message body with mention tokens replaced by styled pills */
export function renderMentionPills(
  body: string,
  nameMap?: NameMap | null,
): React.ReactNode[] {
  if (!body) return [body];
  const parts: React.ReactNode[] = [];
  const regex = /@\[(user|team):(\d+)\]/g;
  let lastIndex = 0;
  let match;

  while ((match = regex.exec(body)) !== null) {
    // Text before match
    if (match.index > lastIndex) {
      parts.push(body.substring(lastIndex, match.index));
    }

    const type = match[1];
    const id = Number(match[2]);
    let name: string;

    if (nameMap && type === 'user') {
      name = nameMap.users.get(id)?.name ?? `User #${id}`;
    } else if (nameMap && type === 'team') {
      name = nameMap.teams.get(id)?.name ?? `Team #${id}`;
    } else {
      name = `${type === 'user' ? 'User' : 'Team'} #${id}`;
    }

    parts.push(
      <span
        key={`mention-${match.index}`}
        className="pet-mention-pill"
        style={{
          background: '#e8f0fe',
          color: '#1a73e8',
          borderRadius: '3px',
          padding: '0 4px',
          fontWeight: 500,
          fontSize: '0.95em',
        }}
      >
        @{name}
      </span>,
    );

    lastIndex = match.index + match[0].length;
  }

  // Remaining text
  if (lastIndex < body.length) {
    parts.push(body.substring(lastIndex));
  }

  return parts.length > 0 ? parts : [body];
}

export default MentionInput;
