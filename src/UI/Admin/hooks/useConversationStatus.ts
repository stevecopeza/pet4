import { useEffect, useState, useRef, useCallback } from 'react';

export interface StatusSummary {
  status: 'red' | 'amber' | 'green' | 'blue' | 'none';
  unread_count: number;
  last_message_at: string | null;
  last_message_actor_id: number | null;
  conversation_state: string;
  child_discussion_count: number;
  child_worst_status: 'red' | 'amber' | 'green' | 'blue' | 'none';
}

/**
 * Fetches conversation status summaries for a list of context IDs.
 * Polls every 60 s while the tab is visible.
 * Returns a Map<contextId, StatusSummary>.
 */
const useConversationStatus = (
  contextType: string,
  contextIds: string[],
): { statuses: Map<string, StatusSummary>; refresh: () => void } => {
  const [statuses, setStatuses] = useState<Map<string, StatusSummary>>(new Map());
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const idsRef = useRef(contextIds);
  idsRef.current = contextIds;

  const doFetch = useCallback(async () => {
    const ids = idsRef.current;
    if (!ids.length || !contextType) return;
    try {
      const settings = (window as any).petSettings;
      const params = new URLSearchParams({
        context_type: contextType,
        context_ids: ids.join(','),
      });
      const res = await fetch(`${settings.apiUrl}/conversations/summary?${params}`, {
        headers: { 'X-WP-Nonce': settings.nonce },
      });
      if (!res.ok) return;
      const data: Record<string, StatusSummary> = await res.json();
      const map = new Map<string, StatusSummary>();
      for (const [key, val] of Object.entries(data)) {
        map.set(key, val);
      }
      setStatuses(map);
    } catch {
      // silent
    }
  }, [contextType]);

  // Initial fetch + poll
  useEffect(() => {
    if (!contextIds.length) return;

    doFetch();

    const startPoll = () => {
      if (timerRef.current) clearInterval(timerRef.current);
      timerRef.current = setInterval(() => {
        if (document.visibilityState === 'visible') {
          doFetch();
        }
      }, 60_000);
    };

    startPoll();

    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, [contextType, contextIds.join(','), doFetch]);

  return { statuses, refresh: doFetch };
};

export default useConversationStatus;
