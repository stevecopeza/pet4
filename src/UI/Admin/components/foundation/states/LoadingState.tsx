import React from 'react';

interface LoadingStateProps {
  label?: string;
}

const LoadingState: React.FC<LoadingStateProps> = ({ label = 'Loading…' }) => (
  <div className="pet-state pet-state-loading" role="status" aria-live="polite">
    <div className="pet-state-spinner" aria-hidden="true" />
    <span>{label}</span>
  </div>
);

export default LoadingState;
