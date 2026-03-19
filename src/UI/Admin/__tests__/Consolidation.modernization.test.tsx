import fs from 'node:fs';
import path from 'node:path';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import Dialog from '../components/foundation/Dialog';
import { DataTable } from '../components/DataTable';

describe('Consolidation modernization regression guards', () => {
  it('does not leave direct alert()/confirm() calls in admin components', () => {
    const componentsDir = path.resolve(__dirname, '../components');
    const stack: string[] = [componentsDir];
    const offenders: string[] = [];

    while (stack.length > 0) {
      const current = stack.pop()!;
      const entries = fs.readdirSync(current, { withFileTypes: true });
      for (const entry of entries) {
        const fullPath = path.join(current, entry.name);
        if (entry.isDirectory()) {
          stack.push(fullPath);
          continue;
        }
        if (!entry.isFile() || (!entry.name.endsWith('.tsx') && !entry.name.endsWith('.ts'))) {
          continue;
        }
        const rel = path.relative(componentsDir, fullPath);
        if (rel === 'legacyDialogs.ts') {
          continue;
        }
        const source = fs.readFileSync(fullPath, 'utf8');
        if (/\balert\(/.test(source) || /\bconfirm\(/.test(source)) {
          offenders.push(rel);
        }
      }
    }

    expect(offenders).toEqual([]);
  });

  it('traps focus and restores previous focus in Dialog', () => {
    const before = document.createElement('button');
    before.textContent = 'Before';
    document.body.appendChild(before);
    before.focus();

    const onClose = vi.fn();
    const DialogHarness: React.FC = () => {
      const [open, setOpen] = React.useState(true);
      return (
        <Dialog
          open={open}
          title="Confirm change"
          description="Review and continue"
          onClose={() => {
            onClose();
            setOpen(false);
          }}
        >
          <button type="button">Cancel</button>
          <button type="button">Confirm</button>
        </Dialog>
      );
    };
    try {
      render(<DialogHarness />);

      const closeButton = screen.getByRole('button', { name: 'Close dialog' });
      const confirmButton = screen.getByRole('button', { name: 'Confirm' });
      const cancelButton = screen.getByRole('button', { name: 'Cancel' });

      expect(closeButton).toHaveFocus();
      closeButton.focus();
      fireEvent.keyDown(window, { key: 'Tab', shiftKey: true });
      expect(confirmButton).toHaveFocus();

      confirmButton.focus();
      fireEvent.keyDown(window, { key: 'Tab' });
      expect(closeButton).toHaveFocus();

      closeButton.focus();
      fireEvent.keyDown(window, { key: 'Tab' });
      expect(closeButton).toHaveFocus();
      expect(cancelButton).toBeInTheDocument();

      fireEvent.keyDown(window, { key: 'Escape' });
      expect(onClose).toHaveBeenCalledTimes(1);
      expect(before).toHaveFocus();
    } finally {
      document.body.removeChild(before);
    }
  });

  it('supports keyboard row activation in DataTable interactive rows', () => {
    const onRowClick = vi.fn();
    render(
      <DataTable
        columns={[{ key: 'name', header: 'Name' }]}
        data={[{ id: 1, name: 'Row One' }]}
        onRowClick={onRowClick}
      />
    );

    const rowButton = screen.getByRole('button', { name: /row one/i });
    rowButton.focus();
    fireEvent.keyDown(rowButton, { key: 'Enter' });
    fireEvent.keyDown(rowButton, { key: ' ' });

    expect(onRowClick).toHaveBeenCalledTimes(2);
  });
});
