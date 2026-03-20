import React from 'react';

interface DemoWowData {
    escalationRules: {
        enabledCount: number;
        totalCount: number;
    };
    slaRisk: {
        warningCount: number;
        breachedCount: number;
    };
    workload: {
        unassignedTicketsCount: number;
    };
    actions: {
        escalationRulesUrl: string;
        helpdeskUrl: string;
        advisoryUrl?: string;
    };
}

interface DemoWowPanelProps {
    data: DemoWowData;
}

export const DemoWowPanel: React.FC<DemoWowPanelProps> = ({ data }) => {
    return (
        <div className="pet-card demo-wow-panel" style={{ marginTop: '20px', borderLeft: '4px solid #0073aa' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
                <h2 style={{ margin: 0 }}>Operational Health</h2>
                <div className="actions">
                    <a href={data.actions.escalationRulesUrl} className="button button-secondary" style={{ marginRight: '10px' }}>
                        Manage Rules
                    </a>
                    <a href={data.actions.helpdeskUrl} className="button button-primary">
                        Helpdesk
                    </a>
                    {data.actions.advisoryUrl && (
                        <a href={data.actions.advisoryUrl} className="button" style={{ marginLeft: '10px' }}>
                            Advisory
                        </a>
                    )}
                </div>
            </div>
            
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px' }}>
                <div className="stat-box">
                    <h3>Escalation Rules</h3>
                    <p className="stat-value">
                        {data.escalationRules.enabledCount} <span style={{ fontSize: '0.5em', color: '#666' }}>/ {data.escalationRules.totalCount} Active</span>
                    </p>
                </div>
                
                <div className="stat-box">
                    <h3>SLA Risk</h3>
                    <div style={{ display: 'flex', gap: '15px', alignItems: 'baseline', marginTop: '5px' }}>
                        <span style={{ color: '#dba617', fontWeight: 'bold' }}>
                            {data.slaRisk.warningCount} Warning
                        </span>
                        <span style={{ color: '#d63638', fontWeight: 'bold' }}>
                            {data.slaRisk.breachedCount} Breached
                        </span>
                    </div>
                </div>
                
                <div className="stat-box">
                    <h3>Unassigned Tickets</h3>
                    <p className="stat-value" style={{ color: data.workload.unassignedTicketsCount > 0 ? '#d63638' : 'inherit' }}>
                        {data.workload.unassignedTicketsCount}
                    </p>
                </div>
            </div>
        </div>
    );
};
