import toast from './foundation/toastService';

export const legacyAlert = (message: unknown): void => {
  if (typeof message === 'string') {
    toast.error(message);
    return;
  }
  toast.error(String(message ?? 'Action failed'));
};

export const legacyConfirm = (message: unknown): boolean => {
  const confirmFn = window['confirm'];
  return confirmFn(String(message ?? 'Are you sure?'));
};
