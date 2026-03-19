import React from 'react';
import ConfirmationDialog from './ConfirmationDialog';

type ConfirmationRequest = {
  title: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
  resolve: (confirmed: boolean) => void;
};

type ConfirmationOptions = {
  title?: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
};

let listeners: Array<(request: ConfirmationRequest) => void> = [];

const emitConfirmationRequest = (request: ConfirmationRequest) => {
  listeners.forEach((listener) => listener(request));
};

export const subscribeToConfirmations = (listener: (request: ConfirmationRequest) => void) => {
  listeners.push(listener);
  return () => {
    listeners = listeners.filter((l) => l !== listener);
  };
};

export const requestConfirmation = ({
  title = 'Confirm action',
  description,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
}: ConfirmationOptions): Promise<boolean> => new Promise((resolve) => {
  emitConfirmationRequest({
    title,
    description,
    confirmLabel,
    cancelLabel,
    resolve,
  });
});

export const confirmText = (description: string, options?: Omit<ConfirmationOptions, 'description'>): Promise<boolean> =>
  requestConfirmation({
    description,
    ...options,
  });

export const GlobalConfirmationHost: React.FC = () => {
  const [request, setRequest] = React.useState<ConfirmationRequest | null>(null);

  React.useEffect(() => subscribeToConfirmations((nextRequest) => {
    setRequest(nextRequest);
  }), []);

  const handleClose = (confirmed: boolean) => {
    setRequest((current) => {
      if (current) {
        current.resolve(confirmed);
      }
      return null;
    });
  };

  return (
    <ConfirmationDialog
      open={request !== null}
      title={request?.title ?? 'Confirm action'}
      description={request?.description ?? ''}
      confirmLabel={request?.confirmLabel ?? 'Confirm'}
      cancelLabel={request?.cancelLabel ?? 'Cancel'}
      onCancel={() => handleClose(false)}
      onConfirm={() => handleClose(true)}
    />
  );
};

export default requestConfirmation;
