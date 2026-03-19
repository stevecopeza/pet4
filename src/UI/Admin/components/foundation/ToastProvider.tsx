import React from 'react';
import { registerToastApi } from './toastService';

type ToastTone = 'success' | 'error' | 'info';

type ToastItem = {
  id: number;
  message: string;
  tone: ToastTone;
};

type ToastApi = {
  success: (message: string) => void;
  error: (message: string) => void;
  info: (message: string) => void;
  dismiss: (id: number) => void;
};

const ToastContext = React.createContext<ToastApi | null>(null);

export const useToastContext = (): ToastApi => {
  const ctx = React.useContext(ToastContext);
  if (!ctx) {
    throw new Error('useToast must be used inside ToastProvider');
  }
  return ctx;
};

interface ToastProviderProps {
  children: React.ReactNode;
}

const AUTO_DISMISS_MS = 4000;

const ToastProvider: React.FC<ToastProviderProps> = ({ children }) => {
  const [toasts, setToasts] = React.useState<ToastItem[]>([]);
  const idRef = React.useRef(1);

  const dismiss = React.useCallback((id: number) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const push = React.useCallback((message: string, tone: ToastTone) => {
    const id = idRef.current++;
    setToasts((prev) => [...prev, { id, message, tone }]);
    window.setTimeout(() => {
      dismiss(id);
    }, AUTO_DISMISS_MS);
  }, [dismiss]);

  const api = React.useMemo<ToastApi>(() => ({
    success: (message: string) => push(message, 'success'),
    error: (message: string) => push(message, 'error'),
    info: (message: string) => push(message, 'info'),
    dismiss,
  }), [dismiss, push]);

  React.useEffect(() => {
    registerToastApi(api);
    return () => registerToastApi(null);
  }, [api]);

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div className="pet-toast-viewport" aria-live="polite" aria-atomic="true">
        {toasts.map((toast) => (
          <div key={toast.id} className={`pet-toast pet-toast-${toast.tone}`} role="status">
            <span>{toast.message}</span>
            <button
              type="button"
              className="pet-toast-dismiss"
              onClick={() => dismiss(toast.id)}
              aria-label="Dismiss notification"
            >
              ×
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
};

export default ToastProvider;
