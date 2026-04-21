import React from 'react';

const NotFoundPage: React.FC = () => (
  <div style={{ padding: 60, textAlign: 'center', color: '#6b7280' }}>
    <div style={{ fontSize: 18, fontWeight: 600, color: '#111827', marginBottom: 8 }}>Page not found</div>
    <p>Use the sidebar to navigate to a section.</p>
    <a href="#customers" style={{ color: '#2563eb', fontWeight: 600, marginTop: 16, display: 'inline-block' }}>
      Go to Customers →
    </a>
  </div>
);

export default NotFoundPage;
