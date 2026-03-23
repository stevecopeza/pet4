import React from 'react';
import ErrorBoundary from './components/ErrorBoundary';
import ConversationProvider from './components/ConversationProvider';
import Dashboard from './components/Dashboard';
import Dashboards from './components/Dashboards';
import MyWork from './components/MyWork';
import MyProfile from './components/MyProfile';
import Projects from './components/Projects';
import Commercial from './components/Commercial';
import TimeEntries from './components/TimeEntries';
import StaffTimeCapture from './components/StaffTimeCapture';
import Customers from './components/Customers';
import Employees from './components/Employees';
import Support from './components/Support';
import Knowledge from './components/Knowledge';
import Activity from './components/Activity';
import Settings from './components/Settings';
import Roles from './components/Roles';
import WorkItems from './components/WorkItems';
import Finance from './components/Finance';
import Conversations from './components/Conversations';
import Approvals from './components/Approvals';
import EscalationRules from './components/EscalationRules';
import Escalations from './components/Escalations';
import PulsewayRmm from './components/PulsewayRmm';
import Advisory from './components/Advisory';
import Performance from './components/Performance';
import ToastProvider from './components/foundation/ToastProvider';
import { GlobalConfirmationHost } from './components/foundation/confirmationService';

declare global {
  interface Window {
    petSettings: {
      currentPage?: string;
      apiUrl: string;
      nonce: string;
      currentUserId?: number;
    };
  }
}

const App = () => {
  const currentPage = window.petSettings?.currentPage || 'pet-dashboard';

  const getPageTitle = (slug: string) => {
    switch (slug) {
      case 'pet-dashboard': return 'Overview';
      case 'pet-dashboards': return 'Dashboards';
      case 'pet-my-work': return 'My Work';
      case 'pet-my-profile': return 'My Profile';
      case 'pet-crm': return 'Customers';
      case 'pet-quotes-sales': return 'Quotes & Sales';
      case 'pet-delivery': return 'Delivery';
      case 'pet-time': return 'Time';
      case 'pet-time-capture': return 'Staff Time Capture';
      case 'pet-support': return 'Support';
      case 'pet-knowledge': return 'Knowledge';
      case 'pet-people': return 'Staff';
      case 'pet-roles': return 'Roles & Capabilities';
      case 'pet-activity': return 'Activity';
      case 'pet-settings': return 'Settings';
      case 'pet-finance': return 'Finance';
      case 'pet-advisory': return 'Advisory';
      case 'pet-performance': return 'Performance';
      case 'pet-conversations': return 'Conversations';
      case 'pet-approvals': return 'Approvals';
      case 'pet-escalations': return 'Escalations';
      case 'pet-escalation-rules': return 'Escalation Rules';
      case 'pet-pulseway': return 'Pulseway RMM';
      default: return 'PET';
    }
  };

  const isDashboardsPage = currentPage === 'pet-dashboards';

  const renderContent = () => {
    switch (currentPage) {
      case 'pet-dashboard':
        return <Dashboard />;
      case 'pet-dashboards':
        return <Dashboards />;
      case 'pet-my-work':
        return <MyWork />;
      case 'pet-my-profile':
        return <MyProfile />;
      case 'pet-work':
        return <WorkItems />;
      case 'pet-delivery':
        return <Projects />;
      case 'pet-quotes-sales':
        return <Commercial />;
      case 'pet-time':
        return <TimeEntries />;
      case 'pet-time-capture':
        return <StaffTimeCapture />;
      case 'pet-crm':
        return <Customers />;
      case 'pet-people':
        return <Employees />;
      case 'pet-roles':
        return <Roles />;
      case 'pet-support':
        return <Support />;
      case 'pet-advisory':
        return <Advisory />;
      case 'pet-performance':
        return <Performance />;
      case 'pet-conversations':
        return <Conversations />;
      case 'pet-approvals':
        return <Approvals />;
      case 'pet-knowledge':
        return <Knowledge />;
      case 'pet-activity':
        return <Activity />;
      case 'pet-settings':
        return <Settings />;
      case 'pet-finance':
        return <Finance />;
      case 'pet-escalations':
        return <Escalations />;
      case 'pet-escalation-rules':
        return <EscalationRules />;
      case 'pet-pulseway':
        return <PulsewayRmm />;
      default:
        return (
          <div className="pet-card" style={{ padding: '40px', textAlign: 'center', color: '#666' }}>
            <h2 style={{ marginTop: 0 }}>Coming Soon</h2>
            <p>The {getPageTitle(currentPage)} module is currently under development.</p>
          </div>
        );
    }
  };

  // Dashboards page renders full-screen without WP wrapper
  if (isDashboardsPage) {
    return (
      <ToastProvider>
        <ErrorBoundary>
          <ConversationProvider>
            {renderContent()}
            <GlobalConfirmationHost />
          </ConversationProvider>
        </ErrorBoundary>
      </ToastProvider>
    );
  }

  return (
    <ToastProvider>
      <ErrorBoundary>
        <ConversationProvider>
          <div className="pet-admin-dashboard" style={{ padding: '20px', background: '#fff', marginTop: '20px' }}>
            <header style={{ marginBottom: '30px', borderBottom: '1px solid #eee', paddingBottom: '20px' }}>
              <h1 style={{ margin: 0 }}>PET - {getPageTitle(currentPage)}</h1>
              <p style={{ margin: '10px 0 0', color: '#666' }}>Welcome to the PET (Plan. Execute. Track).</p>
            </header>
            
            <main>
              {renderContent()}
            </main>
            <GlobalConfirmationHost />
          </div>
        </ConversationProvider>
      </ErrorBoundary>
    </ToastProvider>
  );
};

export default App;
