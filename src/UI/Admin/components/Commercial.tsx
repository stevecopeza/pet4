import React, { useState, useRef, useEffect } from 'react';
import Leads from './Leads';
import Quotes from './Quotes';
import Catalog from './Catalog';

const Commercial = () => {
  const [activeTab, setActiveTab] = useState<'leads' | 'quotes' | 'catalog'>('leads');
  const instanceId = useRef(Math.random().toString(36).substr(2, 5)).current;

  useEffect(() => {
    console.log(`[Commercial:${instanceId}] Mounted`);
    return () => console.log(`[Commercial:${instanceId}] Unmounting`);
  }, []);

  console.log(`[Commercial:${instanceId}] Render. activeTab=${activeTab}`);

  return (
    <div data-instance-id={instanceId}>
      <div style={{background: 'cyan', padding: 5}}>DEBUG: Commercial:{instanceId} activeTab={activeTab}</div>
      <div className="pet-tabs" style={{ display: 'flex', borderBottom: '1px solid #ccc', marginBottom: '20px' }}>
        <button
          className={`pet-tab ${activeTab === 'leads' ? 'active' : ''}`}
          onClick={() => setActiveTab('leads')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'leads' ? '#fff' : '#f0f0f0',
            borderBottom: activeTab === 'leads' ? '2px solid #2271b1' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'leads' ? 'bold' : 'normal'
          }}
        >
          Leads
        </button>
        <button
          className={`pet-tab ${activeTab === 'quotes' ? 'active' : ''}`}
          onClick={() => setActiveTab('quotes')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'quotes' ? '#fff' : '#f0f0f0',
            borderBottom: activeTab === 'quotes' ? '2px solid #2271b1' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'quotes' ? 'bold' : 'normal'
          }}
        >
          Quotes
        </button>
        <button
          className={`pet-tab ${activeTab === 'catalog' ? 'active' : ''}`}
          onClick={() => setActiveTab('catalog')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'catalog' ? '#fff' : '#f0f0f0',
            borderBottom: activeTab === 'catalog' ? '2px solid #2271b1' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'catalog' ? 'bold' : 'normal'
          }}
        >
          Catalog
        </button>
      </div>

      {activeTab === 'leads' && <Leads />}
      {activeTab === 'quotes' && <Quotes />}
      {activeTab === 'catalog' && <Catalog />}
    </div>
  );
};

export default Commercial;
