import React from 'react';

interface PriceCellProps {
  amount: number | null | undefined;
  isOverride?: boolean;
  unit?: string | null;
  bold?: boolean;
}

const PriceCell: React.FC<PriceCellProps> = ({
  amount,
  isOverride = false,
  unit,
  bold = false,
}) => {
  if (amount == null) {
    return <span style={{ color: '#999' }}>–</span>;
  }

  const formatted = `$${amount.toFixed(2)}`;
  const suffix = unit ? `/${unit}` : '';

  return (
    <span
      style={{
        fontWeight: bold ? 600 : 'normal',
        whiteSpace: 'nowrap',
        display: 'inline-flex',
        alignItems: 'center',
        gap: '4px',
      }}
    >
      {formatted}
      {suffix && (
        <span style={{ fontSize: '11px', color: '#666' }}>{suffix}</span>
      )}
      {amount > 0 && (
        <span
          title={isOverride ? 'Manual override' : 'Derived from rate card'}
          style={{ fontSize: '11px', opacity: 0.6, cursor: 'help' }}
        >
          {isOverride ? '✎' : '🔗'}
        </span>
      )}
    </span>
  );
};

export default PriceCell;
