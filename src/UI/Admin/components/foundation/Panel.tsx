import React from 'react';

interface PanelProps {
  children: React.ReactNode;
  className?: string;
  testId?: string;
}

const Panel: React.FC<PanelProps> = ({ children, className, testId }) => {
  return (
    <section className={`pet-panel ${className || ''}`.trim()} data-testid={testId}>
      {children}
    </section>
  );
};

export default Panel;
