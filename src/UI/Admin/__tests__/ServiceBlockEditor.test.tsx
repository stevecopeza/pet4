import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ServiceBlockEditor from '../components/ServiceBlockEditor';
import type { ServiceBlockDraft } from '../components/ServiceBlockEditor';

const baseDraft: ServiceBlockDraft = {
  description: 'Consulting',
  catalogItemId: null,
  roleId: null,
  ownerType: '',
  ownerId: null,
  teamId: null,
  owner: '',
  team: '',
  quantity: 1,
  unit: 'hours',
  sellValue: 150,
  price_override: false,
};

const catalogItems = [
  { id: 10, name: 'Tier 1 Support', unit_price: 50, unit_cost: 30, type: 'service' },
  { id: 11, name: 'Server', unit_price: 2000, unit_cost: 1500, type: 'product' },
];

const roles = [{ id: 1, name: 'Engineer' }];

const renderEditor = (overrides: Partial<ServiceBlockDraft> = {}, extra: Record<string, any> = {}) => {
  const draft = { ...baseDraft, ...overrides };
  const onDraftChange = extra.onDraftChange ?? vi.fn();
  const onSave = extra.onSave ?? vi.fn();
  const onCancel = extra.onCancel ?? vi.fn();
  const onRoleChange = extra.onRoleChange ?? vi.fn();

  return {
    onDraftChange,
    onSave,
    onCancel,
    onRoleChange,
    ...render(
      <table>
        <tbody>
          <ServiceBlockEditor
            draft={draft}
            onDraftChange={onDraftChange}
            onSave={onSave}
            onCancel={onCancel}
            saving={extra.saving ?? false}
            serverError={extra.serverError ?? null}
            catalogItems={catalogItems}
            roles={roles}
            employees={[]}
            teams={[]}
            ownerOptions={null}
            onRoleChange={onRoleChange}
          />
        </tbody>
      </table>
    ),
  };
};

describe('ServiceBlockEditor', () => {
  it('renders description input with current value', () => {
    renderEditor();
    const input = screen.getByPlaceholderText('Description') as HTMLInputElement;
    expect(input.value).toBe('Consulting');
  });

  it('renders quantity input', () => {
    renderEditor({ quantity: 5 });
    const inputs = screen.getAllByRole('spinbutton');
    const qtyInput = inputs.find((el) => (el as HTMLInputElement).value === '5');
    expect(qtyInput).toBeTruthy();
  });

  it('computes and displays total (qty × price)', () => {
    renderEditor({ quantity: 3, sellValue: 100 });
    expect(screen.getByText('$300.00')).toBeInTheDocument();
  });

  it('calls onDraftChange when description changes', async () => {
    const { onDraftChange } = renderEditor();
    const input = screen.getByPlaceholderText('Description');
    await userEvent.clear(input);
    await userEvent.type(input, 'X');
    expect(onDraftChange).toHaveBeenCalledWith('description', expect.any(String));
  });

  it('filters catalog to services only', () => {
    renderEditor();
    const options = screen.getAllByRole('option');
    const serviceOption = options.find((o) => o.textContent === 'Tier 1 Support');
    const productOption = options.find((o) => o.textContent === 'Server');
    expect(serviceOption).toBeTruthy();
    expect(productOption).toBeUndefined();
  });

  it('calls onDraftChange with catalog item details on selection', async () => {
    const { onDraftChange } = renderEditor();
    const catalogSelect = screen.getByDisplayValue('Catalog: Select service…');
    await userEvent.selectOptions(catalogSelect, '10');
    // Should call with catalogItemId, description, sellValue, price_override
    expect(onDraftChange).toHaveBeenCalledWith('catalogItemId', 10);
    expect(onDraftChange).toHaveBeenCalledWith('description', 'Tier 1 Support');
    expect(onDraftChange).toHaveBeenCalledWith('sellValue', 50);
    expect(onDraftChange).toHaveBeenCalledWith('price_override', false);
  });

  it('marks price_override=true when user edits sell value', async () => {
    const { onDraftChange } = renderEditor();
    const priceInputs = screen.getAllByRole('spinbutton');
    // The price input has value "150" (sellValue from draft)
    const priceInput = priceInputs.find((el) => (el as HTMLInputElement).value === '150');
    expect(priceInput).toBeTruthy();
    fireEvent.change(priceInput!, { target: { value: '200' } });
    expect(onDraftChange).toHaveBeenCalledWith('sellValue', '200');
    expect(onDraftChange).toHaveBeenCalledWith('price_override', true);
  });

  it('shows "auto" label when not overridden', () => {
    renderEditor({ price_override: false });
    expect(screen.getByText('auto')).toBeInTheDocument();
  });

  it('shows "Reset to rate card" link when overridden', () => {
    renderEditor({ price_override: true });
    expect(screen.getByText('Reset to rate card')).toBeInTheDocument();
  });

  it('disables save button when there are validation errors', () => {
    renderEditor({ description: '', quantity: 0 });
    const saveBtn = screen.getByTitle(/Fix errors/i);
    expect(saveBtn).toBeDisabled();
  });

  it('enables save button for valid draft', () => {
    renderEditor();
    // Find the ✓ button
    const saveBtn = screen.getByTitle('Save');
    expect(saveBtn).not.toBeDisabled();
  });

  it('calls onSave on Enter key press (valid draft)', () => {
    const { onSave } = renderEditor();
    const input = screen.getByPlaceholderText('Description');
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(onSave).toHaveBeenCalled();
  });

  it('calls onCancel on Escape key press', () => {
    const { onCancel } = renderEditor();
    const input = screen.getByPlaceholderText('Description');
    fireEvent.keyDown(input, { key: 'Escape' });
    expect(onCancel).toHaveBeenCalled();
  });

  it('disables save button while saving', () => {
    renderEditor({}, { saving: true });
    const buttons = screen.getAllByRole('button');
    const saveBtn = buttons.find((b) => b.textContent === '…');
    expect(saveBtn).toBeDisabled();
  });

  it('shows server error banner when provided', () => {
    renderEditor({}, { serverError: 'Network timeout' });
    expect(screen.getByText(/Network timeout/)).toBeInTheDocument();
  });

  it('shows row error summary when multiple errors', () => {
    renderEditor({ description: '', quantity: 0, sellValue: -1 });
    expect(screen.getByText(/Fix \d+ errors? before saving/)).toBeInTheDocument();
  });

  it('renders unit dropdown with correct options', () => {
    renderEditor();
    expect(screen.getByDisplayValue('hours')).toBeInTheDocument();
    expect(screen.getByText('days')).toBeInTheDocument();
    expect(screen.getByText('licenses')).toBeInTheDocument();
    expect(screen.getByText('months')).toBeInTheDocument();
  });

  it('calls onRoleChange when role is selected', async () => {
    const { onRoleChange, onDraftChange } = renderEditor();
    const roleSelect = screen.getByDisplayValue('No role');
    await userEvent.selectOptions(roleSelect, '1');
    expect(onDraftChange).toHaveBeenCalledWith('roleId', 1);
    expect(onRoleChange).toHaveBeenCalledWith(1);
  });
});
