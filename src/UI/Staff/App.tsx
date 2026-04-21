import React from 'react';
import TimeCapturePage from './pages/TimeCapturePage';
import StaffApprovalsPage from './pages/StaffApprovalsPage';
import './staff.css';

interface Props {
  view: string;
}

export default function App({ view }: Props) {
  if (view === 'time') return <TimeCapturePage />;
  if (view === 'approvals') return <StaffApprovalsPage />;
  return (
    <div className="staff-error">
      Unknown view: <code>{view}</code>
    </div>
  );
}
