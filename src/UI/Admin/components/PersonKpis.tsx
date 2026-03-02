import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import { PersonKpi, KpiDefinition, Employee } from '../types';

interface PersonKpisProps {
  employee: Employee;
  periodStart?: string;
  periodEnd?: string;
}

const PersonKpis: React.FC<PersonKpisProps> = ({ employee, periodStart, periodEnd }) => {
  const [personKpis, setPersonKpis] = useState<PersonKpi[]>([]);
  const [loading, setLoading] = useState(true);
  const [showGenerateForm, setShowGenerateForm] = useState(false);
  const [generateParams, setGenerateParams] = useState({
    role_id: '', // Need to fetch available roles or get from employee assignment
    period_start: periodStart || new Date().toISOString().split('T')[0],
    period_end: periodEnd || new Date(new Date().setMonth(new Date().getMonth() + 1)).toISOString().split('T')[0],
  });
  
  // For editing
  const [editingKpi, setEditingKpi] = useState<PersonKpi | null>(null);
  const [editData, setEditData] = useState({
    actual_value: 0,
    score: 0,
  });

  const fetchData = async () => {
    try {
      setLoading(true);
      // Fetch Person KPIs
      let url = `${window.petSettings.apiUrl}/employees/${employee.id}/kpis`;
      if (periodStart && periodEnd) {
        url += `?period_start=${periodStart}&period_end=${periodEnd}`;
      }
      // @ts-ignore
      const kpisRes = await fetch(url, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      // Fetch Definitions for names
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

        // Enrich
        const enrichedKpis = kpisData.map((pk: PersonKpi) => {
          const def = defsData.find((d: KpiDefinition) => d.id === pk.kpi_definition_id);
          return {
            ...pk,
            kpi_name: def ? def.name : 'Unknown KPI',
            kpi_unit: def ? def.unit : '',
          };
        });

        setPersonKpis(enrichedKpis);
      }
    } catch (err) {
      console.error('Failed to fetch person KPIs', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (employee.id) {
      fetchData();
    }
  }, [employee.id, periodStart, periodEnd]);

  const handleGenerateKpis = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/employees/${employee.id}/kpis/generate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify(generateParams),
      });

      if (response.ok) {
        setShowGenerateForm(false);
        fetchData();
      }
    } catch (err) {
      console.error('Failed to generate KPIs', err);
    }
  };

  const handleUpdateKpi = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingKpi) return;

    try {
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/kpis/${editingKpi.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify(editData),
      });

      if (response.ok) {
        setEditingKpi(null);
        fetchData();
      }
    } catch (err) {
      console.error('Failed to update KPI', err);
    }
  };

  const columns: Column<PersonKpi>[] = [
    { key: 'kpi_name', header: 'KPI Name' },
    { 
      key: 'period_start', 
      header: 'Period',
      render: (val, item) => `${item.period_start} to ${item.period_end}`
    },
    { 
      key: 'target_value', 
      header: 'Target',
      render: (val, item) => `${val} ${item.kpi_unit || ''}`
    },
    { 
      key: 'actual_value', 
      header: 'Actual',
      render: (val, item) => val !== null ? `${val} ${item.kpi_unit || ''}` : '-'
    },
    { 
      key: 'score', 
      header: 'Score',
      render: (val) => val !== null ? `${val}%` : '-'
    },
    {
      key: 'id',
      header: 'Actions',
      render: (_, item) => (
        <button 
          className="button button-small"
          onClick={() => {
            setEditingKpi(item);
            setEditData({
              actual_value: item.actual_value || 0,
              score: item.score || 0,
            });
          }}
        >
          Update
        </button>
      )
    }
  ];

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h3>Performance KPIs</h3>
        <button className="button button-primary" onClick={() => setShowGenerateForm(true)}>
          Generate KPIs
        </button>
      </div>

      {showGenerateForm && (
        <div className="card" style={{ marginBottom: '20px' }}>
          <h4>Generate KPIs from Role</h4>
          <form onSubmit={handleGenerateKpis}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px' }}>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Role ID</label>
                <input
                  type="number"
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={generateParams.role_id}
                  onChange={(e) => setGenerateParams({ ...generateParams, role_id: e.target.value })}
                  placeholder="Enter Role ID"
                  required
                />
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Start Date</label>
                <input
                  type="date"
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={generateParams.period_start}
                  onChange={(e) => setGenerateParams({ ...generateParams, period_start: e.target.value })}
                  required
                />
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>End Date</label>
                <input
                  type="date"
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={generateParams.period_end}
                  onChange={(e) => setGenerateParams({ ...generateParams, period_end: e.target.value })}
                  required
                />
              </div>
            </div>
            <div style={{ marginTop: '10px' }}>
              <button type="submit" className="button button-primary">Generate</button>
              <button 
                type="button" 
                className="button" 
                style={{ marginLeft: '10px' }}
                onClick={() => setShowGenerateForm(false)}
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {editingKpi && (
        <div className="card" style={{ marginBottom: '20px', borderLeft: '4px solid #007cba' }}>
          <h4>Update Result: {editingKpi.kpi_name}</h4>
          <form onSubmit={handleUpdateKpi}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Actual Value</label>
                <input
                  type="number"
                  step="0.01"
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={editData.actual_value}
                  onChange={(e) => setEditData({ ...editData, actual_value: Number(e.target.value) })}
                  required
                />
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Score (%)</label>
                <input
                  type="number"
                  step="0.01"
                  className="regular-text"
                  style={{ width: '100%' }}
                  value={editData.score}
                  onChange={(e) => setEditData({ ...editData, score: Number(e.target.value) })}
                  required
                />
              </div>
            </div>
            <div style={{ marginTop: '10px' }}>
              <button type="submit" className="button button-primary">Save Result</button>
              <button 
                type="button" 
                className="button" 
                style={{ marginLeft: '10px' }}
                onClick={() => setEditingKpi(null)}
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      <DataTable
        data={personKpis}
        columns={columns}
        loading={loading}
        emptyMessage="No KPIs recorded for this employee."
      />
    </div>
  );
};

export default PersonKpis;
