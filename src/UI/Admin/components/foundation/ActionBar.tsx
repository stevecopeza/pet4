import React from 'react';

interface ActionBarProps {
  children: React.ReactNode;
  className?: string;
  testId?: string;
}

const ActionBar: React.FC<ActionBarProps> = ({ children, className, testId }) => {
  return (
    <div className={`pet-action-bar ${className || ''}`.trim()} data-testid={testId}>
      {children}
    </div>
  );
};

export default ActionBar;
