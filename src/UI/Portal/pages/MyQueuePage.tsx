import React from 'react';
import TicketListPage from './TicketListPage';

const STATUS_TABS = [
  { label: 'All',         value: '' },
  { label: 'Open',        value: 'open' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Pending',     value: 'pending' },
];

const MyQueuePage: React.FC = () => (
  <TicketListPage
    title="My Queue"
    lifecycleOwner="support"
    statusTabs={STATUS_TABS}
    emptyMessage="No support tickets assigned to you."
  />
);

export default MyQueuePage;
