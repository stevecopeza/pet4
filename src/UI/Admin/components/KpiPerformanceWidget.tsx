import React from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

interface KpiPerformanceWidgetProps {
    data: Array<{ kpi_name: string; avg_score: number }>;
}

export const KpiPerformanceWidget: React.FC<KpiPerformanceWidgetProps> = ({ data }) => {
     // Transform string numbers to floats if necessary
     const chartData = data.map(item => ({
        ...item,
        avg_score: parseFloat(item.avg_score as any)
    }));

    return (
        <div className="pet-card">
            <h3>Avg KPI Performance (Top 10)</h3>
            <div style={{ height: 300 }}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={chartData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="kpi_name" />
                        <YAxis />
                        <Tooltip />
                        <Legend />
                        <Bar dataKey="avg_score" fill="#82ca9d" name="Avg Score" />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
};
