import React, { useEffect, useRef, useState } from 'react';

export type KebabMenuItem =
  | { type: 'action'; label: string; onClick: () => void; danger?: boolean; hasNotification?: boolean; disabled?: boolean; disabledReason?: string }
  | { type: 'toggle'; label: string; checked: boolean; onChange: () => void }
  | { type: 'divider' };

interface KebabMenuProps {
  items: KebabMenuItem[];
  /** Show a notification dot on the trigger itself */
  hasNotification?: boolean;
  /** Use light (white) style for dark backgrounds */
  light?: boolean;
}

const KebabMenu: React.FC<KebabMenuProps> = ({ items, hasNotification, light }) => {
  const [open, setOpen] = useState(false);
  const wrapRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;

    const handleClickOutside = (e: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };

    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [open]);

  return (
    <div className="pet-kebab-wrap" ref={wrapRef}>
      <button
        type="button"
        className={`pet-kebab-trigger${light ? ' pet-kebab-trigger--light' : ''}`}
        onClick={(e) => { e.stopPropagation(); setOpen(!open); }}
        aria-label="Actions"
      >
        ⋯
        {hasNotification && <span className="pet-notification-dot pet-notification-dot--trigger" />}
      </button>
      {open && (
        <div className="pet-kebab-menu">
          {items.map((item, i) => {
            if (item.type === 'divider') {
              return <div key={i} className="pet-kebab-divider" />;
            }
            if (item.type === 'toggle') {
              return (
                <label key={i} className="pet-kebab-toggle">
                  <input
                    type="checkbox"
                    checked={item.checked}
                    onChange={() => { item.onChange(); }}
                  />
                  {item.label}
                </label>
              );
            }
            // action
            return (
              <button
                key={i}
                type="button"
                className={`pet-kebab-item${item.danger ? ' pet-kebab-item--danger' : ''}`}
                disabled={item.disabled}
                title={item.disabled ? item.disabledReason : undefined}
                onClick={(e) => {
                  e.stopPropagation();
                  if (!item.disabled) {
                    item.onClick();
                    setOpen(false);
                  }
                }}
              >
                {item.label}
                {item.hasNotification && <span className="pet-notification-dot" />}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default KebabMenu;
