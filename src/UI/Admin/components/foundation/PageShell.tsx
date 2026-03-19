import React from 'react';

interface PageShellProps {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
  testId?: string;
}

const PageShell: React.FC<PageShellProps> = ({ title, subtitle, actions, children, className, testId }) => {
  return (
    <section className={`pet-page-shell ${className || ''}`.trim()} data-testid={testId}>
      <header className="pet-page-shell-header">
        <div className="pet-page-shell-title-group">
          <h2>{title}</h2>
          {subtitle ? <p className="pet-page-shell-subtitle">{subtitle}</p> : null}
        </div>
        {actions ? <div className="pet-page-shell-actions">{actions}</div> : null}
      </header>
      <div className="pet-page-shell-content">
        {children}
      </div>
    </section>
  );
};

export default PageShell;
