import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import KpiDefinitionForm from './KpiDefinitionForm';

interface KpiDefinition {
  id: number;
  name: string;
  description: string;
  default_frequency: string;
  unit: string;
  created_at: string;
}

const KpiDefinitions = () => {
  const [definitions, setDefinitions] = useState<KpiDefinition[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingDefinition, setEditingDefinition] = useState<KpiDefinition | null>(null);

  const fetchDefinitions = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/kpi-definitions`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        setDefinitions(data);
      }
    } catch (err) {
      console.error('Failed to fetch KPI definitions', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDefinitions();
  }, []);

  const columns: Column<KpiDefinition>[] = [
    { 
      key: 'name', 
      header: 'KPI Name',
      render: (val, def) => (
        <button
          type="button"
          onClick={() => {
            setEditingDefinition(def);
            setShowAddForm(true);
          }}
          style={{
            background: 'none',
            border: 'none',
            color: '#2271b1',
            cursor: 'pointer',
            padding: 0,
            textAlign: 'left',
            fontWeight: 'bold',
            fontSize: 'inherit',
          }}
        >
          {String(val)}
        </button>
      )
    },
    { key: 'description', header: 'Description' },
    { key: 'unit', header: 'Unit' },
    { key: 'default_frequency', header: 'Default Frequency' },
    { 
      key: 'created_at', 
      header: 'Created',
      render: (val) => new Date(val).toLocaleDateString()
    },
  ];

  if (showAddForm) {
    return (
      <div>
        <div style={{ marginBottom: '20px' }}>
          <button 
            className="button" 
            onClick={() => {
              setShowAddForm(false);
              setEditingDefinition(null);
            }}
          >
            &larr; Back to KPI Library
          </button>
        </div>
        <KpiDefinitionForm 
          definition={editingDefinition}
          onSuccess={() => {
            setShowAddForm(false);
            setEditingDefinition(null);
            fetchDefinitions();
          }} 
          onCancel={() => {
            setShowAddForm(false);
            setEditingDefinition(null);
          }} 
        />
      </div>
    );
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h3>KPI Library</h3>
        <button 
          className="button button-primary" 
          onClick={() => {
            setEditingDefinition(null);
            setShowAddForm(true);
          }}
        >
          Add KPI Definition
        </button>
      </div>

      <DataTable
        data={definitions}
        columns={columns}
        loading={loading}
        emptyMessage="No KPI definitions found."
        actions={(item) => (
          <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
            <button
              className="button button-small"
              onClick={() => {
                setEditingDefinition(item);
                setShowAddForm(true);
              }}
            >
              Edit
            </button>
          </div>
        )}
      />
    </div>
  );
};

export default KpiDefinitions;
