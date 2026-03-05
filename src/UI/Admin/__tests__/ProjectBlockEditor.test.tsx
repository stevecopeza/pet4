import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ProjectBlockEditor from '../components/ProjectBlockEditor';
import type { ProjectDraft } from '../components/ProjectBlockEditor';

const catalogItems = [
  { id: 10, name: 'Dev Hours', unit_price: 100, unit_cost: 60, type: 'service' },
];
const roles = [{ id: 1, name: 'Engineer' }];

const emptyDraft: ProjectDraft = { description: '', phases: [] };

const draftWithPhases: ProjectDraft = {
  description: 'Website Rebuild',
  phases: [
    {
      id: 'p1',
      name: 'Discovery',
      units: [
        {
          id: 'u1', title: 'Requirements', description: 'Requirements',
          catalogItemId: null, roleId: null, ownerType: '', ownerId: null,
          teamId: null, owner: '', team: '', quantity: 10, unit: 'hours',
          unitPrice: 100, totalValue: 1000, price_override: false,
        },
      ],
    },
    {
      id: 'p2',
      name: 'Build',
      units: [],
    },
  ],
};

const renderEditor = (draft: ProjectDraft = emptyDraft, extra: Record<string, any> = {}) => {
  const onDraftChange = extra.onDraftChange ?? vi.fn();
  const onRoleChange = extra.onRoleChange ?? vi.fn();

  return {
    onDraftChange,
    onRoleChange,
    ...render(
      <ProjectBlockEditor
        draft={draft}
        onDraftChange={onDraftChange}
        roles={roles}
        employees={[]}
        teams={[]}
        catalogItems={catalogItems}
        ownerOptionsCache={{}}
        rateCards={[]}
        onRoleChange={onRoleChange}
      />
    ),
  };
};

describe('ProjectBlockEditor', () => {
  it('renders phase name inputs', () => {
    renderEditor(draftWithPhases);
    expect(screen.getByDisplayValue('Discovery')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Build')).toBeInTheDocument();
  });

  it('shows empty state when no phases', () => {
    renderEditor(emptyDraft);
    expect(screen.getByText(/No phases yet/)).toBeInTheDocument();
  });

  it('renders phase headers', () => {
    renderEditor(draftWithPhases);
    expect(screen.getByDisplayValue('Discovery')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Build')).toBeInTheDocument();
  });

  it('shows phase unit count and total', () => {
    renderEditor(draftWithPhases);
    // Discovery: 1 unit in header badge
    expect(screen.getByText(/1 unit/)).toBeInTheDocument();
    // $1000.00 appears in the phase totals row and the unit row
    const matches = screen.getAllByText(/\$1,?000\.00/);
    expect(matches.length).toBeGreaterThanOrEqual(1);
  });

  it('shows empty unit message for phase with no units', () => {
    renderEditor(draftWithPhases);
    expect(screen.getByText(/No units\. Click/)).toBeInTheDocument();
  });

  it('calls onDraftChange when + Add Phase is clicked', () => {
    const { onDraftChange } = renderEditor(emptyDraft);
    fireEvent.click(screen.getByText('+ Add Phase'));
    expect(onDraftChange).toHaveBeenCalledWith(
      expect.objectContaining({
        phases: expect.arrayContaining([
          expect.objectContaining({ name: 'Phase 1' }),
        ]),
      })
    );
  });

  it('calls onDraftChange to delete a phase', () => {
    const { onDraftChange } = renderEditor(draftWithPhases);
    // Find the delete buttons (✕) in phase headers
    const deleteButtons = screen.getAllByTitle('Delete phase');
    fireEvent.click(deleteButtons[0]); // Delete Discovery
    expect(onDraftChange).toHaveBeenCalledWith(
      expect.objectContaining({
        phases: expect.arrayContaining([
          expect.objectContaining({ name: 'Build' }),
        ]),
      })
    );
  });

  it('renders phase toolbar buttons', () => {
    renderEditor(draftWithPhases);
    // Move up/down and delete buttons exist for each phase
    expect(screen.getAllByTitle('Move up').length).toBe(2);
    expect(screen.getAllByTitle('Move down').length).toBe(2);
    expect(screen.getAllByTitle('Delete phase').length).toBe(2);
  });

  it('calls onDraftChange when + Add Unit is clicked', () => {
    const { onDraftChange } = renderEditor(draftWithPhases);
    const addUnitButtons = screen.getAllByText('+ Add Unit');
    fireEvent.click(addUnitButtons[0]); // Add unit to Discovery
    expect(onDraftChange).toHaveBeenCalledWith(
      expect.objectContaining({
        phases: expect.arrayContaining([
          expect.objectContaining({
            name: 'Discovery',
            units: expect.arrayContaining([
              expect.objectContaining({ description: 'Requirements' }),
              expect.objectContaining({ description: '' }), // new empty unit
            ]),
          }),
        ]),
      })
    );
  });

  it('calls onDraftChange when phase name changes', async () => {
    const { onDraftChange } = renderEditor(draftWithPhases);
    const phaseInput = screen.getByDisplayValue('Discovery');
    await userEvent.clear(phaseInput);
    await userEvent.type(phaseInput, 'Research');
    expect(onDraftChange).toHaveBeenCalledWith(
      expect.objectContaining({
        phases: expect.arrayContaining([
          expect.objectContaining({ name: expect.any(String) }),
        ]),
      })
    );
  });

  it('renders unit read-mode data', () => {
    renderEditor(draftWithPhases);
    expect(screen.getByText('Requirements')).toBeInTheDocument();
    // quantity "10" may appear multiple times (unit row + phase totals)
    const tens = screen.getAllByText('10');
    expect(tens.length).toBeGreaterThanOrEqual(1);
  });

  it('supports phase reorder buttons', () => {
    const { onDraftChange } = renderEditor(draftWithPhases);
    // Move Build up (second phase → first)
    const moveUpButtons = screen.getAllByTitle('Move up');
    fireEvent.click(moveUpButtons[1]); // second phase move-up
    expect(onDraftChange).toHaveBeenCalledWith(
      expect.objectContaining({
        phases: expect.arrayContaining([
          expect.objectContaining({ name: 'Build' }),
          expect.objectContaining({ name: 'Discovery' }),
        ]),
      })
    );
  });
});
