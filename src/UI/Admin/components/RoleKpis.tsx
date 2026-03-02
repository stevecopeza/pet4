import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import { RoleKpi, KpiDefinition } from '../types';

interface RoleKpisProps {
  roleId: number;
}

const RoleKpis: React.FC<RoleKpisProps> = ({ roleId }) => {
  const [roleKpis, setRoleKpis] = useState<RoleKpi[]>([]);
  const [availableKpis, setAvailableKpis] = useState<KpiDefinition[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [newKpiData, setNewKpiData] = useState({
    kpi_definition_id: '',
    weight_percentage: 0,
    target_value: 0,
    measurement_frequency: 'monthly',
  });

  const fetchData = async () => {
    try {
      setLoading(true);
      // Fetch Role KPIs
      // @ts-ignore
      const kpisRes = await fetch(`${window.petSettings.apiUrl}/roles/${roleId}/kpis`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      // Fetch Available KPIs (Definitions)
      // @ts-ignore
      const defsRes = await fetch(`${window.petSettings.apiUrl}/kpi-definitions`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (kpisRes.ok && defsRes.ok) {
        const kpisData = await kpisRes.json();
        const defsData = await defsRes.json();

        // Enrich KPI data with names
        const enrichedKpis = kpisData.map((rk: RoleKpi) => {
          const def = defsData.find((d: KpiDefinition) => d.id === rk.kpi_definition_id);
          return {
            ...rk,
            kpi_name: def ? def.name : 'Unknown KPI',
            kpi_unit: def ? def.unit : '',
          };
        });

        setRoleKpis(enrichedKpis);
        setAvailableKpis(defsData);
      }
    } catch (err) {
      console.error('Failed to fetch role KPIs', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (roleId) {
      fetchData();
    }
  }, [roleId]);

  const handleAddKpi = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/roles/${roleId}/kpis`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify(newKpiData),
      });

      if (response.ok) {
        setShowAddForm(false);
        setNewKpiData({
          kpi_definition_id: '',
          weight_percentage: 0,
          target_value: 0,
          measurement_frequency: 'monthly',
        });
        fetchData();
      }
    } catch (err) {
      console.error('Failed to add KPI to role', err);
    }
  };

  const columns: Column<RoleKpi>[] = [
    { key: 'kpi_name', header: 'KPI Name' },
    { 
      key: 'weight_percentage', 
      header: 'Weight',
      render: (val) => `${val}%`
    },
    { 
      key: 'target_value', 
      header: 'Target',
      render: (val, item) => `${val} ${item.kpi_unit || ''}`
    },
    { key: 'measurement_frequency', header: 'Frequency' },
  ];

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h3>Assigned KPIs</h3>
        <button className="button button-primary" onClick={() => setShowAddForm(true)}>
          Assign KPI
        </button>
      </div>

      {showAddForm && (
        <div className="card" style={{ marginBottom: '20px' }}>
          <h4>Assign KPI to Role</h4>
          <form onSubmit={handleAddKpi}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>KPI</label>
                <select
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={newKpiData.kpi_definition_id}
                  onChange={(e) => setNewKpiData({ ...newKpiData, kpi_definition_id: e.target.value })}
                  required
                >
                  <option value="">Select KPI...</option>
                  {availableKpis.map(kpi => (
                    <option key={kpi.id} value={kpi.id}>{kpi.name} ({kpi.unit})</option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Weight (%)</label>
                <input
                  type="number"
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={newKpiData.weight_percentage}
                  onChange={(e) => setNewKpiData({ ...newKpiData, weight_percentage: Number(e.target.value) })}
                  required
                />
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Target Value</label>
                <input
                  type="number"
                  step="0.01"
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={newKpiData.target_value}
                  onChange={(e) => setNewKpiData({ ...newKpiData, target_value: Number(e.target.value) })}
                  required
                />
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Frequency</label>
                <select
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={newKpiData.measurement_frequency}
                  onChange={(e) => setNewKpiData({ ...newKpiData, measurement_frequency: e.target.value })}
                >
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                  <option value="yearly">Yearly</option>
                </select>
              </div>
            </div>
            <div style={{ marginTop: '10px' }}>
              <button type="submit" className="button button-primary">Save Assignment</button>
              <button 
                type="button" 
                className="button" 
                style={{ marginLeft: '10px' }}
                onClick={() => setShowAddForm(false)}
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      <DataTable
        data={roleKpis}
        columns={columns}
        loading={loading}
        emptyMessage="No KPIs assigned to this role."
      />
    </div>
  );
};

export default RoleKpis;
