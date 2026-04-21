import React from 'react';
import { createRoot } from 'react-dom/client';
import PortalApp from './PortalApp';

const container = document.getElementById('pet-portal-root');
if (container) {
  createRoot(container).render(
    <React.StrictMode>
      <PortalApp />
    </React.StrictMode>
  );
}
