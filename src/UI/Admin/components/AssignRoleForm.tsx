import React, { useState, useEffect } from 'react';

interface Employee {
    id: number;
    display_name: string;
}

interface Role {
    id: number;
    name: string;
}

interface AssignRoleFormProps {
    onSuccess: () => void;
    onCancel: () => void;
}

const AssignRoleForm: React.FC<AssignRoleFormProps> = ({ onSuccess, onCancel }) => {
    const [employeeId, setEmployeeId] = useState<number | ''>('');
    const [roleId, setRoleId] = useState<number | ''>('');
    const [startDate, setStartDate] = useState(new Date().toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState('');
    const [allocationPct, setAllocationPct] = useState(100);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [employees, setEmployees] = useState<Employee[]>([]);
    const [roles, setRoles] = useState<Role[]>([]);

    useEffect(() => {
        const fetchLookups = async () => {
            try {
                // @ts-ignore
                const [empRes, roleRes] = await Promise.all([
                    // @ts-ignore
                    fetch(`${window.petSettings.apiUrl}/employees`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } }),
                    // @ts-ignore
                    fetch(`${window.petSettings.apiUrl}/roles`, { headers: { 'X-WP-Nonce': window.petSettings.nonce } })
                ]);

                if (empRes.ok) setEmployees(await empRes.json());
                if (roleRes.ok) setRoles(await roleRes.json());
            } catch (e) {
                console.error('Failed to fetch lookups', e);
            }
        };
        fetchLookups();
    }, []);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            // @ts-ignore
            const response = await fetch(`${window.petSettings.apiUrl}/assignments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // @ts-ignore
                    'X-WP-Nonce': window.petSettings.nonce,
                },
                body: JSON.stringify({
                    employee_id: employeeId,
                    role_id: roleId,
                    start_date: startDate,
                    end_date: endDate || null,
                    allocation_pct: allocationPct
                }),
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Failed to assign role');
            }

            onSuccess();
        } catch (err: any) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="pet-form" style={{ maxWidth: '500px', background: '#fff', padding: '20px', border: '1px solid #ccd0d4', boxShadow: '0 1px 1px rgba(0,0,0,.04)' }}>
            <h3 style={{ marginTop: 0 }}>Assign Role to Person</h3>
            
            {error && <div className="notice notice-error"><p>{error}</p></div>}
            
            <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Person</label>
                <select 
                    value={employeeId} 
                    onChange={(e) => setEmployeeId(Number(e.target.value))}
                    required
                    style={{ width: '100%', maxWidth: '100%' }}
                >
                    <option value="">Select Person...</option>
                    {employees.map(e => (
                        <option key={e.id} value={e.id}>{e.display_name}</option>
                    ))}
                </select>
            </div>

            <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Role</label>
                <select 
                    value={roleId} 
                    onChange={(e) => setRoleId(Number(e.target.value))}
                    required
                    style={{ width: '100%', maxWidth: '100%' }}
                >
                    <option value="">Select Role...</option>
                    {roles.map(r => (
                        <option key={r.id} value={r.id}>{r.name}</option>
                    ))}
                </select>
            </div>

            <div style={{ display: 'flex', gap: '15px', marginBottom: '15px' }}>
                <div style={{ flex: 1 }}>
                    <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Start Date</label>
                    <input 
                        type="date" 
                        value={startDate} 
                        onChange={(e) => setStartDate(e.target.value)}
                        required
                        style={{ width: '100%' }}
                    />
                </div>
                <div style={{ flex: 1 }}>
                    <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>End Date (Optional)</label>
                    <input 
                        type="date" 
                        value={endDate} 
                        onChange={(e) => setEndDate(e.target.value)}
                        style={{ width: '100%' }}
                    />
                </div>
            </div>

            <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Allocation %</label>
                <input 
                    type="number" 
                    value={allocationPct} 
                    onChange={(e) => setAllocationPct(Number(e.target.value))}
                    min="1"
                    max="100"
                    required
                    style={{ width: '100px' }}
                />
            </div>

            <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
                <button type="submit" className="button button-primary" disabled={loading}>
                    {loading ? 'Assigning...' : 'Assign Role'}
                </button>
                <button type="button" className="button" onClick={onCancel} disabled={loading}>
                    Cancel
                </button>
            </div>
        </form>
    );
};

export default AssignRoleForm;
