import React, { useState } from 'react';
import Leads from './Leads';
import Quotes from './Quotes';
import Catalog from './Catalog';

const tabs = [
  { key: 'leads', label: 'Leads' },
  { key: 'quotes', label: 'Quotes' },
  { key: 'catalog', label: 'Catalog' },
] as const;

type TabKey = typeof tabs[number]['key'];

const Commercial = () => {
  const [activeTab, setActiveTab] = useState<TabKey>('leads');
  const [pendingQuoteId, setPendingQuoteId] = useState<number | null>(null);

  return (
    <div>
      <div className="pet-commercial-tabs">
        {tabs.map(({ key, label }) => (
          <button
            key={key}
            className={`pet-tab ${activeTab === key ? 'active' : ''}`}
            onClick={() => setActiveTab(key)}
          >
            {label}
          </button>
        ))}
      </div>

      {activeTab === 'leads' && (
        <Leads onNavigateToQuote={(quoteId) => { setPendingQuoteId(quoteId); setActiveTab('quotes'); }} />
      )}
      {activeTab === 'quotes' && (
        <Quotes initialQuoteId={pendingQuoteId} onInitialQuoteConsumed={() => setPendingQuoteId(null)} />
      )}
      {activeTab === 'catalog' && <Catalog />}
    </div>
  );
};

export default Commercial;
