import React, { useEffect, useState } from 'react';
import { DashboardData } from '../types';
import { DataTable, Column } from './DataTable';
import { SkillHeatmapWidget } from './SkillHeatmapWidget';
import { KpiPerformanceWidget } from './KpiPerformanceWidget';
import { DemoWowPanel } from './DemoWowPanel';

const Dashboard = () => {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const settings = window.petSettings as any;
        const apiUrl = settings?.apiUrl;
        const nonce = settings?.nonce;
        
        if (!apiUrl || !nonce) {
             throw new Error('PET Settings not initialized');
        }

        const response = await fetch(`${apiUrl}/dashboard`, {
          headers: {
            'X-WP-Nonce': nonce,
          },
        });

        if (!response.ok) {
          throw new Error('Failed to fetch dashboard data');
        }

        const jsonData = await response.json();
        setData(jsonData);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An unknown error occurred');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) return <div>Loading dashboard...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
  if (!data) return <div>No data available</div>;

  const activityColumns: Column<typeof data.recentActivity[0]>[] = [
    { key: 'type', header: 'Type', render: (val) => <span style={{ fontWeight: 'bold' }}>{val}</span> },
    { key: 'message', header: 'Description' },
    { key: 'time', header: 'Time', render: (val) => <span style={{ color: '#666' }}>{val}</span> },
  ];

  return (
    <div className="pet-dashboard-grid">
      <div className="pet-card overview">
        <h2>Overview</h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px' }}>
          <div className="stat-box">
            <h3>Active Projects</h3>
            <p className="stat-value">{data.overview.activeProjects}</p>
          </div>
          <div className="stat-box">
            <h3>Pending Quotes</h3>
            <p className="stat-value">{data.overview.pendingQuotes}</p>
          </div>
          <div className="stat-box">
            <h3>Utilization</h3>
            <p className="stat-value">{data.overview.utilizationRate}%</p>
          </div>
          <div className="stat-box">
            <h3>Revenue (MTD)</h3>
            <p className="stat-value">${data.overview.revenueThisMonth.toLocaleString()}</p>
          </div>
        </div>
      </div>

      {data.demoWow && (
        <DemoWowPanel data={data.demoWow} />
      )}


      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginTop: '20px' }}>
          <SkillHeatmapWidget data={data.skillHeatmap || []} />
          <KpiPerformanceWidget data={data.kpiPerformance || []} />
      </div>

      <div className="pet-card activity" style={{ marginTop: '20px' }}>
        <h2>Recent Activity</h2>
        <DataTable 
          columns={activityColumns} 
          data={data.recentActivity} 
        />
      </div>
    </div>
  );
};

export default Dashboard;
