import React, { Suspense, lazy, useEffect, useState } from 'react';
import PortalShell from './PortalShell';
import { usePortalUser } from './hooks/usePortalUser';

// Lazy-load each section so the initial bundle stays small.
// Each import will resolve to a component in src/UI/Portal/pages/
const CustomersPage      = lazy(() => import('./pages/CustomersPage'));
const CatalogPage        = lazy(() => import('./pages/CatalogPage'));
const EmployeesPage      = lazy(() => import('./pages/EmployeesPage'));
const LeadsPage          = lazy(() => import('./pages/LeadsPage'));
const PipelinePage       = lazy(() => import('./pages/PipelinePage'));
const QuotesPage         = lazy(() => import('./pages/QuotesPage'));
const ApprovalsPage      = lazy(() => import('./pages/ApprovalsPage'));
const QuoteBuilderPage   = lazy(() => import('./pages/QuoteBuilderPage'));
const NotFoundPage       = lazy(() => import('./pages/NotFoundPage'));
// My Work section
const MyQueuePage        = lazy(() => import('./pages/MyQueuePage'));
const MyDeliverablesPage = lazy(() => import('./pages/MyDeliverablesPage'));
const CalendarPage       = lazy(() => import('./pages/CalendarPage'));
const LogTimePage        = lazy(() => import('./pages/LogTimePage'));
const ConversationsPage  = lazy(() => import('./pages/ConversationsPage'));
const ActivityPage       = lazy(() => import('./pages/ActivityPage'));
const KnowledgeBasePage  = lazy(() => import('./pages/KnowledgeBasePage'));
// Gap-fill pages
const MyProfilePage      = lazy(() => import('./pages/MyProfilePage'));
const MyTimePage         = lazy(() => import('./pages/MyTimePage'));
const MyPerformancePage  = lazy(() => import('./pages/MyPerformancePage'));
const ProjectsPage       = lazy(() => import('./pages/ProjectsPage'));
const EscalationsPage    = lazy(() => import('./pages/EscalationsPage'));
const AdvisoryPage       = lazy(() => import('./pages/AdvisoryPage'));
const SupportQueuePage   = lazy(() => import('./pages/SupportQueuePage'));

/** Minimal hash-based router — avoids needing react-router as a new dep. */
function useHash(): string {
  const [hash, setHash] = React.useState(() => window.location.hash || '#customers');
  React.useEffect(() => {
    const handler = () => setHash(window.location.hash || '#customers');
    window.addEventListener('hashchange', handler);
    return () => window.removeEventListener('hashchange', handler);
  }, []);
  return hash;
}

/**
 * Parse the quote ID from a builder hash, e.g. "#quote-builder-42" → 42
 */
function parseBuilderHash(hash: string): number | null {
  const match = hash.match(/^#quote-builder-(\d+)$/);
  return match ? parseInt(match[1], 10) : null;
}

const Spinner: React.FC = () => (
  <div style={{ padding: 40, textAlign: 'center', color: '#6b7280' }}>Loading…</div>
);

const AccessDenied: React.FC = () => (
  <div style={{ padding: 40, textAlign: 'center', color: '#6b7280' }}>
    <h2 style={{ marginBottom: 8 }}>Access Denied</h2>
    <p>You don't have permission to view this section.</p>
  </div>
);

const PortalApp: React.FC = () => {
  const user = usePortalUser();
  const hash = useHash();
  const [unreadConversations, setUnreadConversations] = useState(0);

  // Fetch unread conversation count on mount (for sidebar badge)
  useEffect(() => {
    if (!user.hasPortalAccess) return;
    // @ts-ignore
    const apiUrl: string = window.petSettings?.apiUrl ?? '';
    // @ts-ignore
    const nonce: string  = window.petSettings?.nonce ?? '';
    if (!apiUrl) return;
    fetch(`${apiUrl}/conversations/unread-counts`, { headers: { 'X-WP-Nonce': nonce } })
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (data && typeof data === 'object') {
          const total = Object.values(data as Record<string, number>).reduce((a, b) => a + b, 0);
          setUnreadConversations(total);
        }
      })
      .catch(() => {});
  }, [user.hasPortalAccess]);

  if (!user.hasPortalAccess) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <div style={{ textAlign: 'center', padding: 40 }}>
          <h2 style={{ marginBottom: 8, color: '#111827' }}>Portal Access Required</h2>
          <p style={{ color: '#6b7280' }}>
            Your account doesn't have staff portal access.<br />
            Please contact your administrator.
          </p>
        </div>
      </div>
    );
  }

  const renderPage = () => {
    // Quote builder route: #quote-builder-{id}
    const builderId = parseBuilderHash(hash);
    if (builderId !== null) {
      return (user.isSales || user.isManager || user.isAdmin)
        ? <QuoteBuilderPage quoteId={builderId} />
        : <AccessDenied />;
    }

    // ── Gap-fill pages ─────────────────────────────────────────────
    if (hash === '#my-profile') return <MyProfilePage />;
    if (hash === '#my-time')    return <MyTimePage />;
    if (hash === '#my-performance') return <MyPerformancePage />;
    if (hash === '#projects')   return <ProjectsPage />;

    if (hash === '#escalations') {
      return (user.isManager || user.isAdmin)
        ? <EscalationsPage />
        : <AccessDenied />;
    }
    if (hash === '#advisory') {
      return (user.isManager || user.isAdmin)
        ? <AdvisoryPage />
        : <AccessDenied />;
    }
    if (hash === '#support') {
      return (user.isManager || user.isAdmin)
        ? <SupportQueuePage />
        : <AccessDenied />;
    }

    // Employees section — handles both #employees and #employees/:id (full-page detail)
    if (hash === '#employees' || hash.startsWith('#employees/')) {
      return (user.isHr || user.isManager || user.isAdmin)
        ? <EmployeesPage />
        : <AccessDenied />;
    }

    // Customers detail — #customers/:id
    if (hash.startsWith('#customers/')) {
      return (user.isSales || user.isHr || user.isManager || user.isAdmin)
        ? <CustomersPage />
        : <AccessDenied />;
    }

    // Leads detail — #leads/:id
    if (hash.startsWith('#leads/')) {
      return (user.isSales || user.isManager || user.isAdmin)
        ? <LeadsPage />
        : <AccessDenied />;
    }

    // Quotes detail — #quotes/:id
    if (hash.startsWith('#quotes/')) {
      return (user.isSales || user.isManager || user.isAdmin)
        ? <QuotesPage />
        : <AccessDenied />;
    }

    switch (hash) {
      case '#customers':
        return (user.isSales || user.isHr || user.isManager || user.isAdmin)
          ? <CustomersPage />
          : <AccessDenied />;

      case '#catalog':
        return (user.isSales || user.isHr || user.isManager || user.isAdmin)
          ? <CatalogPage />
          : <AccessDenied />;

      case '#leads':
        return (user.isSales || user.isManager || user.isAdmin)
          ? <LeadsPage />
          : <AccessDenied />;

      case '#pipeline':
        return (user.isSales || user.isManager || user.isAdmin)
          ? <PipelinePage />
          : <AccessDenied />;

      case '#quotes':
        return (user.isSales || user.isManager || user.isAdmin)
          ? <QuotesPage />
          : <AccessDenied />;

      case '#approvals':
        return (user.isManager || user.isAdmin)
          ? <ApprovalsPage />
          : <AccessDenied />;

      // ── My Work section — visible to all portal users ──────────────
      case '#my-queue':
        return <MyQueuePage />;

      case '#my-deliverables':
        return <MyDeliverablesPage />;

      case '#calendar':
        return <CalendarPage />;

      case '#log-time':
        return <LogTimePage />;

      case '#activity':
        return <ActivityPage />;

      case '#knowledge-base':
        return <KnowledgeBasePage />;

      default:
        // Conversations: #conversations or #conversations/{uuid}
        if (hash === '#conversations' || hash.startsWith('#conversations/')) {
          const uuid = hash.startsWith('#conversations/') ? hash.slice('#conversations/'.length) : null;
          return <ConversationsPage activeUuid={uuid} onUnreadChange={setUnreadConversations} />;
        }
        return <NotFoundPage />;
    }
  };

  const isBuilderMode = parseBuilderHash(hash) !== null;

  return (
    <PortalShell user={user} activeHash={hash} builderMode={isBuilderMode} unreadConversations={unreadConversations}>
      <Suspense fallback={<Spinner />}>
        {renderPage()}
      </Suspense>
    </PortalShell>
  );
};

export default PortalApp;
