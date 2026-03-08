import { useEffect, useState } from 'react';
import { Employee, Team } from '../types';

export interface NameEntry {
  name: string;
  initials: string;
}

export interface NameMap {
  users: Map<number, NameEntry>;
  teams: Map<number, { name: string }>;
}

// Module-level cache — survives remounts, invalidated only on page reload
let cachedNameMap: NameMap | null = null;
let fetchPromise: Promise<NameMap> | null = null;

const useNameMap = (): { nameMap: NameMap | null; loading: boolean } => {
  const [nameMap, setNameMap] = useState<NameMap | null>(cachedNameMap);
  const [loading, setLoading] = useState(cachedNameMap === null);

  useEffect(() => {
    if (cachedNameMap) {
      setNameMap(cachedNameMap);
      setLoading(false);
      return;
    }

    if (!fetchPromise) {
      fetchPromise = fetchNameMap();
    }

    fetchPromise.then((map) => {
      cachedNameMap = map;
      setNameMap(map);
      setLoading(false);
    }).catch(() => {
      setLoading(false);
    });
  }, []);

  return { nameMap, loading };
};

async function fetchNameMap(): Promise<NameMap> {
  const settings = (window as any).petSettings;
  const headers = { 'X-WP-Nonce': settings.nonce };

  const [empRes, teamRes] = await Promise.all([
    fetch(`${settings.apiUrl}/employees`, { headers }),
    fetch(`${settings.apiUrl}/teams`, { headers }),
  ]);

  const employees: Employee[] = empRes.ok ? await empRes.json() : [];
  const teamsRaw: Team[] = teamRes.ok ? await teamRes.json() : [];

  const users = new Map<number, NameEntry>();
  for (const emp of employees) {
    const name =
      emp.displayName ||
      `${emp.firstName} ${emp.lastName}`.trim() ||
      `User #${emp.wpUserId}`;
    users.set(emp.wpUserId, { name, initials: getInitials(name) });
  }

  const teams = new Map<number, { name: string }>();
  for (const t of flattenTeams(teamsRaw)) {
    teams.set(t.id, { name: t.name });
  }

  return { users, teams };
}

function getInitials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

function flattenTeams(nodes: Team[]): Team[] {
  let flat: Team[] = [];
  for (const n of nodes) {
    flat.push(n);
    if (n.children?.length) flat = flat.concat(flattenTeams(n.children));
  }
  return flat;
}

export default useNameMap;
