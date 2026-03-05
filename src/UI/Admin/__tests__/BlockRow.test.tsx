import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import BlockRow from '../components/BlockRow';
import type { BlockRowCallbacks } from '../components/BlockRow';
import type { QuoteBlock } from '../types';

const makeBlock = (overrides: Partial<QuoteBlock> = {}): QuoteBlock => ({
  id: 1,
  quoteId: 1,
  sectionId: 1,
  type: 'OnceOffSimpleServiceBlock',
  orderIndex: 0,
  componentId: null,
  priced: true,
  payload: {
    description: 'Setup service',
    quantity: 2,
    sellValue: 100,
    totalValue: 200,
    roleId: 1,
    ownerType: 'employee',
    owner: 'Alice Smith',
  },
  ...overrides,
});

const roles = [{ id: 1, name: 'Developer' }];

const makeCallbacks = (): BlockRowCallbacks => ({
  onEdit: vi.fn(),
  onDelete: vi.fn(),
  onDiscuss: vi.fn(),
});

const renderRow = (block: QuoteBlock, callbacks?: BlockRowCallbacks, extra?: Record<string, any>) => {
  const cbs = callbacks ?? makeCallbacks();
  return render(
    <table>
      <tbody>
        <BlockRow block={block} roles={roles} callbacks={cbs} {...extra} />
      </tbody>
    </table>
  );
};

describe('BlockRow', () => {
  it('renders service block description and icon', () => {
    renderRow(makeBlock());
    expect(screen.getByText('Setup service')).toBeInTheDocument();
    expect(screen.getByTitle('OnceOffSimpleServiceBlock')).toBeInTheDocument();
  });

  it('renders "No description" for empty description', () => {
    renderRow(makeBlock({ payload: {} }));
    expect(screen.getByText('No description')).toBeInTheDocument();
  });

  it('shows role badge', () => {
    renderRow(makeBlock());
    expect(screen.getByText('Developer')).toBeInTheDocument();
  });

  it('shows owner badge for employee', () => {
    renderRow(makeBlock());
    expect(screen.getByText('Alice Smith')).toBeInTheDocument();
  });

  it('shows quantity', () => {
    renderRow(makeBlock());
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('shows total value', () => {
    renderRow(makeBlock());
    expect(screen.getByText('$200.00')).toBeInTheDocument();
  });

  it('calls onEdit when row is clicked (not editing)', () => {
    const cbs = makeCallbacks();
    renderRow(makeBlock(), cbs);
    fireEvent.click(screen.getByText('Setup service'));
    expect(cbs.onEdit).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
  });

  it('does not call onEdit when isEditing=true', () => {
    const cbs = makeCallbacks();
    renderRow(makeBlock(), cbs, { isEditing: true });
    fireEvent.click(screen.getByText('Setup service'));
    expect(cbs.onEdit).not.toHaveBeenCalled();
  });

  it('renders project block with summary chip', () => {
    const block = makeBlock({
      type: 'OnceOffProjectBlock',
      payload: {
        description: 'Website Rebuild',
        phases: [{ units: [{ quantity: 10, unit: 'hours' }] }],
      },
    });
    renderRow(block, undefined, { projectSummary: '1 phase · 1 unit · 10h' });
    expect(screen.getByText('Website Rebuild')).toBeInTheDocument();
    expect(screen.getByText('1 phase · 1 unit · 10h')).toBeInTheDocument();
  });

  it('renders project block accordion triangle', () => {
    const toggleFn = vi.fn();
    const block = makeBlock({
      type: 'OnceOffProjectBlock',
      payload: { description: 'Proj' },
    });
    renderRow(block, undefined, { onToggleAccordion: toggleFn, isAccordionOpen: false });
    const triangle = screen.getByText('▸');
    fireEvent.click(triangle);
    expect(toggleFn).toHaveBeenCalled();
  });

  it('renders text block with truncated text', () => {
    const longText = 'A'.repeat(100);
    const block = makeBlock({
      type: 'TextBlock',
      payload: { text: longText },
    });
    renderRow(block);
    expect(screen.getByText(`${'A'.repeat(80)}…`)).toBeInTheDocument();
  });

  it('renders price adjustment block with amount as total', () => {
    const block = makeBlock({
      type: 'PriceAdjustmentBlock',
      payload: { description: 'Discount', amount: -500 },
    });
    renderRow(block);
    expect(screen.getByText('Discount')).toBeInTheDocument();
    // Amount is shown as total
    expect(screen.getByText('$-500.00')).toBeInTheDocument();
  });

  it('hides role/owner cells for text and adjustment blocks', () => {
    const block = makeBlock({
      type: 'TextBlock',
      payload: { text: 'Hello' },
    });
    renderRow(block);
    // Role badge should not render "No role" for text blocks
    // (the cell exists but renders nothing)
    expect(screen.queryByText('No role')).not.toBeInTheDocument();
  });

  it('renders hardware block', () => {
    const block = makeBlock({
      type: 'HardwareBlock',
      payload: { description: 'Server rack', quantity: 3, totalValue: 9000 },
    });
    renderRow(block);
    expect(screen.getByText('Server rack')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });
});
