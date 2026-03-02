import React, { useEffect, useState } from 'react';
import { Setting } from '../types';
import Calendars from './Calendars';
import { DataTable, Column } from './DataTable';
import SchemaManagement from './SchemaManagement';
import SlaDefinitions from './SlaDefinitions';

interface PetSettingsWindow extends Window {
  petSettings: {
    apiUrl: string;
    nonce: string;
  };
  URL: typeof URL;
}

declare const window: PetSettingsWindow;

// Extended interface for UI that includes id
interface SettingWithId extends Setting {
  id: string;
}

const Settings = () => {
  const [activeTab, setActiveTab] = useState<'general' | 'schemas' | 'calendars' | 'slas' | 'logs'>('general');
  const [settings, setSettings] = useState<SettingWithId[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Logs state
  const [logType, setLogType] = useState<'pet' | 'wp'>('pet');
  const [logs, setLogs] = useState<string[]>([]);
  const [logsLoading, setLogsLoading] = useState(false);

  const fetchSettings = async () => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/settings`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch settings');
      }

      const data: Setting[] = await response.json();
      // Add id property required by DataTable
      setSettings(data.map(s => ({ ...s, id: s.key })));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const fetchLogs = async () => {
    setLogsLoading(true);
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/logs?type=${logType}`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch logs');
      }

      const data = await response.json();
      setLogs(data.logs || []);
    } catch (err) {
      console.error('Error fetching logs:', err);
      setLogs(['Error fetching logs.']);
    } finally {
      setLogsLoading(false);
    }
  };

  useEffect(() => {
    fetchSettings();
  }, []);

  useEffect(() => {
    if (activeTab === 'logs') {
      fetchLogs();
    }
  }, [activeTab, logType]);

  const handleValueChange = async (key: string, newValue: string) => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/settings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({ key, value: newValue }),
      });

      if (!response.ok) {
        throw new Error('Failed to update setting');
      }
      
      // Update local state
      setSettings(prev => prev.map(s => s.key === key ? { ...s, value: newValue } : s));

    } catch (err) {
      alert(err instanceof Error ? err.message : 'Update failed');
    }
  };

  const handleDownloadLogs = async () => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/logs/download`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to generate log report');
      }

      const data = await response.json();
      const content = data.content;
      const filename = data.filename;

      const blob = new Blob([content], { type: 'text/plain' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch (err) {
      alert('Failed to download logs');
    }
  };

  const columns: Column<SettingWithId>[] = [
    { key: 'key', header: 'Key', render: (val) => <strong>{val as string}</strong> },
    { 
      key: 'value', 
      header: 'Value', 
      render: (val, item) => {
        if (item.type === 'boolean') {
          return (
            <input 
              type="checkbox" 
              checked={val === 'true'} 
              onChange={(e) => {
                const newValue = e.target.checked ? 'true' : 'false';
                handleValueChange(item.key, newValue);
              }}
            />
          );
        }
        return (
          <input 
            type="text" 
            defaultValue={val as string} 
            onBlur={(e) => {
              if (e.target.value !== val) {
                handleValueChange(item.key, e.target.value);
              }
            }}
            style={{ width: '100%', maxWidth: '300px' }}
          />
        );
      }
    },
    { key: 'description', header: 'Description', render: (val) => <em style={{ color: '#666' }}>{val as string}</em> },
    { key: 'updatedAt', header: 'Last Updated', render: (val) => val as string || '-' },
  ];

  return (
    <div className="pet-settings">
      <div style={{ marginBottom: '20px', borderBottom: '1px solid #ddd' }}>
        <button
          onClick={() => setActiveTab('general')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'general' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'general' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'general' ? 'bold' : 'normal',
            color: activeTab === 'general' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          General Settings
        </button>
        <button
          onClick={() => setActiveTab('schemas')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'schemas' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'schemas' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'schemas' ? 'bold' : 'normal',
            color: activeTab === 'schemas' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          Schemas & Malleable Fields
        </button>
        <button
          onClick={() => setActiveTab('calendars')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'calendars' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'calendars' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'calendars' ? 'bold' : 'normal',
            color: activeTab === 'calendars' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          Calendars
        </button>
        <button
          onClick={() => setActiveTab('slas')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'slas' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'slas' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'slas' ? 'bold' : 'normal',
            color: activeTab === 'slas' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          SLA Definitions
        </button>
        <button
          onClick={() => setActiveTab('logs')}
          style={{
            padding: '10px 20px',
            border: 'none',
            background: activeTab === 'logs' ? '#fff' : 'transparent',
            borderBottom: activeTab === 'logs' ? '2px solid #007cba' : 'none',
            cursor: 'pointer',
            fontWeight: activeTab === 'logs' ? 'bold' : 'normal',
            color: activeTab === 'logs' ? '#000' : '#555',
            fontSize: '14px'
          }}
        >
          Logs
        </button>
      </div>

      {activeTab === 'general' && (
        <>
          <h2>System Settings</h2>
          <p>Configure global plugin settings.</p>
          
          {loading && <div>Loading settings...</div>}
          {error && <div style={{ color: 'red' }}>Error: {error}</div>}
          
          {!loading && !error && (
            <DataTable 
              columns={columns} 
              data={settings} 
              emptyMessage="No settings defined." 
            />
          )}
          
          <div style={{ marginTop: '20px', padding: '15px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
            <h3>Note</h3>
            <p>These settings are stored in the database and affect plugin behavior globally.</p>
          </div>

          <div style={{ marginTop: '20px', padding: '15px', background: '#fff', border: '1px solid #ccd0d4' }}>
            <h3>Demo Installer</h3>
            <p>Seed announcements and feed events for demo purposes.</p>
            <button
              className="button button-primary"
              onClick={async () => {
                try {
                  const res = await fetch(`${window.petSettings.apiUrl}/system/run-demo`, {
                    method: 'POST',
                    headers: {
                      'X-WP-Nonce': window.petSettings.nonce,
                    },
                  });
                  if (!res.ok) {
                    throw new Error('Failed to run demo installer');
                  }
                  const json = await res.json();
                  alert(`Demo data created: ${json.announcements} announcements, ${json.events} events`);
                } catch (err) {
                  alert(err instanceof Error ? err.message : 'Failed to run demo installer');
                }
              }}
            >
              Run Demo Installer
            </button>
          </div>
        </>
      )}

      {activeTab === 'schemas' && <SchemaManagement />}
      {activeTab === 'calendars' && <Calendars />}
      {activeTab === 'slas' && <SlaDefinitions />}

      {activeTab === 'logs' && (
        <div className="pet-logs-viewer">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
            <div>
              <select 
                value={logType} 
                onChange={(e) => setLogType(e.target.value as 'pet' | 'wp')}
                style={{ marginRight: '10px', padding: '5px' }}
              >
                <option value="pet">PET System Logs</option>
                <option value="wp">WP Debug Log</option>
              </select>
              <button className="button" onClick={fetchLogs} disabled={logsLoading}>
                {logsLoading ? 'Refreshing...' : 'Refresh Logs'}
              </button>
            </div>
            <button className="button button-primary" onClick={handleDownloadLogs}>
              Download Diagnostic Report
            </button>
          </div>

          <div style={{ 
            background: '#0c0d0e', 
            color: '#e0e0e0', 
            padding: '15px', 
            borderRadius: '4px', 
            height: '500px', 
            overflowY: 'auto',
            fontFamily: 'monospace',
            fontSize: '12px',
            whiteSpace: 'pre-wrap'
          }}>
            {logs.length === 0 ? (
              <div style={{ color: '#888', fontStyle: 'italic' }}>No logs found.</div>
            ) : (
              logs.map((line, i) => (
                <div key={i} style={{ borderBottom: '1px solid #333', padding: '2px 0' }}>{line}</div>
              ))
            )}
          </div>
          <p className="description">Showing last 200 entries.</p>
        </div>
      )}
    </div>
  );
};

export default Settings;
