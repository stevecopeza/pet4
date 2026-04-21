import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const container = document.getElementById('pet-staff-app');
if (container) {
  createRoot(container).render(
    <React.StrictMode>
      <App view={(container.dataset.view ?? '') as string} />
    </React.StrictMode>
  );
}
