import React from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

interface SkillHeatmapWidgetProps {
    data: Array<{ skill_name: string; avg_rating: number }>;
}

export const SkillHeatmapWidget: React.FC<SkillHeatmapWidgetProps> = ({ data }) => {
    // Transform string numbers to floats if necessary
    const chartData = data.map(item => ({
        ...item,
        avg_rating: parseFloat(item.avg_rating as any)
    }));

    return (
        <div className="pet-card">
            <h3>Top Skills Proficiency</h3>
            <div style={{ height: 300 }}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={chartData} layout="vertical" margin={{ top: 5, right: 30, left: 40, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis type="number" domain={[0, 5]} />
                        <YAxis dataKey="skill_name" type="category" width={100} />
                        <Tooltip />
                        <Bar dataKey="avg_rating" fill="#8884d8" name="Avg Proficiency" />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
};
