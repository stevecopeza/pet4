import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import PriceCell from '../components/PriceCell';
import RoleBadge from '../components/RoleBadge';
import OwnerBadge from '../components/OwnerBadge';

// ---------------------------------------------------------------------------
// PriceCell
// ---------------------------------------------------------------------------
describe('PriceCell', () => {
  it('renders dash for null amount', () => {
    render(<PriceCell amount={null} />);
    expect(screen.getByText('–')).toBeInTheDocument();
  });

  it('renders formatted dollar amount', () => {
    render(<PriceCell amount={123.4} />);
    expect(screen.getByText('$123.40')).toBeInTheDocument();
  });

  it('shows override indicator when isOverride=true and amount > 0', () => {
    render(<PriceCell amount={50} isOverride />);
    expect(screen.getByTitle('Manual override')).toBeInTheDocument();
  });

  it('shows derived indicator when isOverride=false and amount > 0', () => {
    render(<PriceCell amount={50} isOverride={false} />);
    expect(screen.getByTitle('Derived from rate card')).toBeInTheDocument();
  });

  it('renders unit suffix when provided', () => {
    render(<PriceCell amount={100} unit="hours" />);
    expect(screen.getByText('/hours')).toBeInTheDocument();
  });

  it('applies bold style when bold prop set', () => {
    const { container } = render(<PriceCell amount={99} bold />);
    const span = container.querySelector('span');
    expect(span?.style.fontWeight).toBe('600');
  });
});

// ---------------------------------------------------------------------------
// RoleBadge
// ---------------------------------------------------------------------------
describe('RoleBadge', () => {
  it('shows "No role" when roleName is null', () => {
    render(<RoleBadge roleName={null} />);
    expect(screen.getByText('No role')).toBeInTheDocument();
  });

  it('shows role name when provided', () => {
    render(<RoleBadge roleName="Senior Developer" />);
    expect(screen.getByText('Senior Developer')).toBeInTheDocument();
  });

  it('uses muted style for unset role', () => {
    const { container } = render(<RoleBadge roleName={undefined} />);
    const span = container.querySelector('span')!;
    expect(span.style.backgroundColor).toBe('rgb(240, 240, 240)');
  });
});

// ---------------------------------------------------------------------------
// OwnerBadge
// ---------------------------------------------------------------------------
describe('OwnerBadge', () => {
  it('shows "Unassigned" when no owner', () => {
    render(<OwnerBadge ownerName={null} ownerType="" />);
    expect(screen.getByText('Unassigned')).toBeInTheDocument();
  });

  it('shows employee name with green style', () => {
    const { container } = render(<OwnerBadge ownerName="Jane Doe" ownerType="employee" />);
    expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    const span = container.querySelector('span')!;
    expect(span.style.backgroundColor).toBe('rgb(232, 245, 233)');
  });

  it('shows team name with amber style', () => {
    const { container } = render(<OwnerBadge ownerName="DevOps" ownerType="team" />);
    expect(screen.getByText('DevOps')).toBeInTheDocument();
    const span = container.querySelector('span')!;
    expect(span.style.backgroundColor).toBe('rgb(254, 247, 224)');
  });
});
