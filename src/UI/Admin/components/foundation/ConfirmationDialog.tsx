import React from 'react';
import Dialog from './Dialog';

interface ConfirmationDialogProps {
  open: boolean;
  title: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
  busy?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

const ConfirmationDialog: React.FC<ConfirmationDialogProps> = ({
  open,
  title,
  description,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  busy = false,
  onConfirm,
  onCancel,
}) => {
  return (
    <Dialog open={open} title={title} description={description} onClose={onCancel}>
      <div className="pet-dialog-actions">
        <button type="button" className="button" onClick={onCancel} disabled={busy}>
          {cancelLabel}
        </button>
        <button type="button" className="button button-primary" onClick={onConfirm} disabled={busy}>
          {busy ? 'Working…' : confirmLabel}
        </button>
      </div>
    </Dialog>
  );
};

export default ConfirmationDialog;
