import { describe, expect, it } from 'vitest';
import { buildSupportStatusOptions } from '../components/Support';
import { resolveTicketFormStatusOptions } from '../components/TicketForm';
import { resolveReturnQueueId } from '../components/WorkItems';

describe('hardening boundary helpers', () => {
  it('buildSupportStatusOptions returns API-authoritative values', () => {
    const map = new Map([
      ['new', { value: 'new', label: 'New' }],
      ['closed', { value: 'closed', label: 'Closed' }],
    ]);

    const options = buildSupportStatusOptions(map);
    expect(options).toEqual([
      { value: 'new', label: 'New' },
      { value: 'closed', label: 'Closed' },
    ]);
  });

  it('resolveTicketFormStatusOptions prefers API options and avoids hardcoded fallback', () => {
    const options = resolveTicketFormStatusOptions(
      [
        { value: 'planned', label: 'Planned' },
        { value: 'in_progress', label: 'In Progress' },
      ],
      'new'
    );

    expect(options).toEqual([
      { value: 'planned', label: 'Planned' },
      { value: 'in_progress', label: 'In Progress' },
    ]);
  });

  it('resolveTicketFormStatusOptions falls back to current status only', () => {
    const options = resolveTicketFormStatusOptions([], 'pending');
    expect(options).toEqual([{ value: 'pending', label: 'pending' }]);
  });

  it('resolveReturnQueueId uses item team assignment when present', () => {
    const queueId = resolveReturnQueueId(
      {
        assigned_team_id: '7',
      } as any,
      'support:user:99'
    );
    expect(queueId).toBe('7');
  });

  it('resolveReturnQueueId uses selected team queue context when item has no team', () => {
    const queueId = resolveReturnQueueId(
      {
        assigned_team_id: null,
      } as any,
      'support:team:12'
    );
    expect(queueId).toBe('12');
  });

  it('resolveReturnQueueId returns null when no queue context exists', () => {
    const queueId = resolveReturnQueueId(
      {
        assigned_team_id: null,
      } as any,
      'support:user:34'
    );
    expect(queueId).toBeNull();
  });
});
