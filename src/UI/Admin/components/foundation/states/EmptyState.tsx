import React from 'react';

interface EmptyStateProps {
  message: string;
}

const EmptyState: React.FC<EmptyStateProps> = ({ message }) => (
  <div className="pet-state pet-state-empty">
    <span>{message}</span>
  </div>
);

export default EmptyState;
