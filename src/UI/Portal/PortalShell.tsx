import React from 'react';
import { PortalUser } from './hooks/usePortalUser';
import './portal.css';

interface NavItem {
  hash: string;
  label: string;
  icon: React.ReactNode;
  badge?: number;
  requiresCap: (user: PortalUser) => boolean;
}

const IconCustomers = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
    <circle cx="9" cy="7" r="4"/>
    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
  </svg>
);
const IconCatalog = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
  </svg>
);
const IconEmployees = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <rect x="2" y="7" width="20" height="14" rx="2"/>
    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
  </svg>
);
const IconLeads = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="8" r="4"/>
    <path d="M20 21a8 8 0 1 0-16 0"/>
  </svg>
);
const IconQuotes = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
    <polyline points="14 2 14 8 20 8"/>
    <line x1="16" y1="13" x2="8" y2="13"/>
    <line x1="16" y1="17" x2="8" y2="17"/>
    <polyline points="10 9 9 9 8 9"/>
  </svg>
);
const IconApprovals = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M9 11l3 3L22 4"/>
    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
  </svg>
);
const IconMyQueue = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
  </svg>
);
const IconDeliverables = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
  </svg>
);
const IconCalendar = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
  </svg>
);
const IconLogTime = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
  </svg>
);
const IconConversations = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
  </svg>
);
const IconActivity = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
  </svg>
);
const IconKnowledgeBase = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
  </svg>
);
const IconProfile = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="8" r="4"/>
    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
  </svg>
);
const IconClock = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="10"/>
    <polyline points="12 6 12 12 16 14"/>
    <line x1="8" y1="18" x2="16" y2="18"/>
  </svg>
);
const IconPerformance = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <line x1="18" y1="20" x2="18" y2="10"/>
    <line x1="12" y1="20" x2="12" y2="4"/>
    <line x1="6" y1="20" x2="6" y2="14"/>
  </svg>
);
const IconProjects = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
  </svg>
);
const IconEscalation = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
  </svg>
);
const IconAdvisory = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
  </svg>
);
const IconSupport = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    <line x1="9" y1="10" x2="15" y2="10"/>
  </svg>
);
const IconSettings = () => (
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="3"/>
    <path d="M19.07 4.93A10 10 0 1 0 4.93 19.07"/>
  </svg>
);
const IconSignOut = () => (
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
    <polyline points="16 17 21 12 16 7"/>
    <line x1="21" y1="12" x2="9" y2="12"/>
  </svg>
);
const LogoIcon = () => (
  <svg width="28" height="28" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"
    style={{ background: '#2563eb', borderRadius: 7, padding: 5 }}>
    <rect x="2" y="2" width="5.5" height="5.5" rx="1.2" fill="white"/>
    <rect x="10.5" y="2" width="5.5" height="5.5" rx="1.2" fill="white" opacity="0.7"/>
    <rect x="2" y="10.5" width="5.5" height="5.5" rx="1.2" fill="white" opacity="0.7"/>
    <rect x="10.5" y="10.5" width="5.5" height="5.5" rx="1.2" fill="white" opacity="0.5"/>
  </svg>
);

const NAV_COMMERCIAL: NavItem[] = [
  {
    hash: '#customers',
    label: 'Customers',
    icon: <IconCustomers />,
    requiresCap: (u) => u.isSales || u.isHr || u.isManager || u.isAdmin,
  },
  {
    hash: '#catalog',
    label: 'Catalog',
    icon: <IconCatalog />,
    requiresCap: (u) => u.isSales || u.isHr || u.isManager || u.isAdmin,
  },
  {
    hash: '#leads',
    label: 'Leads',
    icon: <IconLeads />,
    requiresCap: (u) => u.isSales || u.isManager || u.isAdmin,
  },
  {
    hash: '#quotes',
    label: 'Quotes',
    icon: <IconQuotes />,
    requiresCap: (u) => u.isSales || u.isManager || u.isAdmin,
  },
  {
    hash: '#approvals',
    label: 'Approvals',
    icon: <IconApprovals />,
    requiresCap: (u) => u.isManager || u.isAdmin,
  },
];

const NAV_PEOPLE: NavItem[] = [
  {
    hash: '#employees',
    label: 'Employees',
    icon: <IconEmployees />,
    requiresCap: (u) => u.isHr || u.isManager || u.isAdmin,
  },
];

const NAV_MY_WORK: NavItem[] = [
  {
    hash: '#my-profile',
    label: 'My Profile',
    icon: <IconProfile />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#my-queue',
    label: 'My Queue',
    icon: <IconMyQueue />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#my-deliverables',
    label: 'My Deliverables',
    icon: <IconDeliverables />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#projects',
    label: 'My Projects',
    icon: <IconProjects />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#calendar',
    label: 'Calendar',
    icon: <IconCalendar />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#log-time',
    label: 'Log Time',
    icon: <IconLogTime />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#my-time',
    label: 'Time History',
    icon: <IconClock />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#my-performance',
    label: 'My Performance',
    icon: <IconPerformance />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#conversations',
    label: 'Conversations',
    icon: <IconConversations />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#activity',
    label: 'Activity',
    icon: <IconActivity />,
    requiresCap: (u) => u.hasPortalAccess,
  },
  {
    hash: '#knowledge-base',
    label: 'Knowledge Base',
    icon: <IconKnowledgeBase />,
    requiresCap: (u) => u.hasPortalAccess,
  },
];

const NAV_MANAGEMENT: NavItem[] = [
  {
    hash: '#support',
    label: 'Support Queue',
    icon: <IconSupport />,
    requiresCap: (u) => u.isManager || u.isAdmin,
  },
  {
    hash: '#escalations',
    label: 'Escalations',
    icon: <IconEscalation />,
    requiresCap: (u) => u.isManager || u.isAdmin,
  },
  {
    hash: '#advisory',
    label: 'Advisory',
    icon: <IconAdvisory />,
    requiresCap: (u) => u.isManager || u.isAdmin,
  },
];

interface PortalShellProps {
  user: PortalUser;
  activeHash: string;
  children: React.ReactNode;
  /** Optional approval queue count to show on Approvals badge */
  pendingApprovals?: number;
  /** Unread conversation count for My Work badge */
  unreadConversations?: number;
  /**
   * When true, removes the padding from portal-main and sets overflow:hidden
   * so the child (e.g. QuoteBuilderPage) can manage its own scrolling regions.
   */
  builderMode?: boolean;
}

const PortalShell: React.FC<PortalShellProps> = ({
  user,
  activeHash,
  children,
  pendingApprovals,
  unreadConversations,
  builderMode,
}) => {
  // @ts-ignore
  const logoutUrl: string = (window.petSettings?.logoutUrl) ?? '/wp-login.php?action=logout';

  const roleLabel = user.isAdmin
    ? 'Administrator'
    : user.isManager
    ? 'Manager'
    : user.isHr
    ? 'HR Staff'
    : user.isSales
    ? 'Sales'
    : 'Staff';

  const renderNavItem = (item: NavItem) => {
    if (!item.requiresCap(user)) return null;
    const isActive = activeHash === item.hash
      || (item.hash === '#conversations' && activeHash.startsWith('#conversations'))
      || (item.hash === '#projects' && activeHash.startsWith('#projects'));
    const badge =
      item.hash === '#approvals' ? pendingApprovals :
      item.hash === '#conversations' ? unreadConversations :
      undefined;

    return (
      <a
        key={item.hash}
        href={item.hash}
        className={`portal-nav-item${isActive ? ' active' : ''}`}
      >
        <span className="portal-nav-icon">{item.icon}</span>
        {item.label}
        {badge != null && badge > 0 && (
          <span className="portal-nav-badge">{badge}</span>
        )}
      </a>
    );
  };

  return (
    <>
      {/* Header */}
      <header className="portal-header">
        <a className="portal-logo" href="#customers">
          <LogoIcon />
          PET Portal
        </a>
        <div className="portal-header-spacer" />
        <div className="portal-header-user">
          <div className="portal-avatar">{user.initials}</div>
          <div>
            <div className="portal-user-name">{user.displayName}</div>
            <div className="portal-user-role">{roleLabel}</div>
          </div>
        </div>
      </header>

      {/* Sidebar */}
      <nav className="portal-sidebar">
        <div className="portal-nav-section-label">Commercial</div>
        {NAV_COMMERCIAL.map(renderNavItem)}

        {NAV_PEOPLE.some((item) => item.requiresCap(user)) && (
          <>
            <div className="portal-nav-section-label">People</div>
            {NAV_PEOPLE.map(renderNavItem)}
          </>
        )}

        {NAV_MY_WORK.some((item) => item.requiresCap(user)) && (
          <>
            <div className="portal-nav-section-label">My Work</div>
            {NAV_MY_WORK.map(renderNavItem)}
          </>
        )}

        {NAV_MANAGEMENT.some((item) => item.requiresCap(user)) && (
          <>
            <div className="portal-nav-section-label">Management</div>
            {NAV_MANAGEMENT.map(renderNavItem)}
          </>
        )}

        <div className="portal-sidebar-footer">
          <a className="portal-sidebar-footer-item" href={logoutUrl}>
            <IconSignOut />
            Sign out
          </a>
        </div>
      </nav>

      {/* Main content */}
      <main
        className="portal-main"
        style={builderMode ? { padding: 0, overflow: 'hidden' } : undefined}
      >
        {children}
      </main>
    </>
  );
};

export default PortalShell;
