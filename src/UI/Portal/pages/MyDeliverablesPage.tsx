import React from 'react';
import TicketListPage from './TicketListPage';

const STATUS_TABS = [
  { label: 'All',         value: '' },
  { label: 'Planned',     value: 'planned' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Blocked',     value: 'blocked' },
];

const MyDeliverablesPage: React.FC = () => (
  <TicketListPage
    title="My Deliverables"
    lifecycleOwner="project"
    statusTabs={STATUS_TABS}
    emptyMessage="No project work assigned to you."
  />
);

export default MyDeliverablesPage;
