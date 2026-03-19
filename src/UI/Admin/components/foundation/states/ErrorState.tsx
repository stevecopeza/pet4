import React from 'react';

interface ErrorStateProps {
  message: string;
  onRetry?: () => void;
}

const ErrorState: React.FC<ErrorStateProps> = ({ message, onRetry }) => (
  <div className="pet-state pet-state-error" role="alert">
    <span>{message}</span>
    {onRetry && (
      <button type="button" className="button" onClick={onRetry}>
        Retry
      </button>
    )}
  </div>
);

export default ErrorState;
