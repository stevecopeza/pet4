import React, { useState, useEffect } from 'react';

interface Capability {
    id: number;
    name: string;
}

interface SkillFormProps {
    skill?: {
        id: number;
        name: string;
        description: string;
        capability_id: number;
    } | null;
    onSuccess: () => void;
    onCancel: () => void;
}

const SkillForm: React.FC<SkillFormProps> = ({ skill, onSuccess, onCancel }) => {
    const [name, setName] = useState(skill?.name || '');
    const [capabilityId, setCapabilityId] = useState<number | ''>(skill?.capability_id || '');
    const [description, setDescription] = useState(skill?.description || '');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [capabilities, setCapabilities] = useState<Capability[]>([]);

    useEffect(() => {
        const fetchCapabilities = async () => {
            try {
                // @ts-ignore
                const response = await fetch(`${window.petSettings.apiUrl}/capabilities`, {
                    headers: {
                        // @ts-ignore
                        'X-WP-Nonce': window.petSettings.nonce,
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    setCapabilities(data);
                }
            } catch (err) {
                console.error('Failed to fetch capabilities', err);
            }
        };

        fetchCapabilities();
    }, []);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            // @ts-ignore
            const baseUrl = window.petSettings.apiUrl;
            // @ts-ignore
            const nonce = window.petSettings.nonce;

            const url = skill ? `${baseUrl}/skills/${skill.id}` : `${baseUrl}/skills`;
            const method = skill ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    // @ts-ignore
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify({
                    name,
                    capability_id: capabilityId,
                    description
                }),
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Failed to create skill');
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
            <h3 style={{ marginTop: 0 }}>{skill ? 'Edit Skill' : 'Define New Skill'}</h3>
            
            {error && <div className="notice notice-error"><p>{error}</p></div>}
            
            <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Skill Name</label>
                <input 
                    type="text" 
                    value={name} 
                    onChange={(e) => setName(e.target.value)}
                    required
                    style={{ width: '100%' }}
                />
            </div>

            <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Capability</label>
                <select 
                    value={capabilityId} 
                    onChange={(e) => setCapabilityId(Number(e.target.value))}
                    required
                    style={{ width: '100%', maxWidth: '100%' }}
                >
                    <option value="">Select Capability...</option>
                    {capabilities.map(c => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                </select>
            </div>

            <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Description</label>
                <textarea 
                    value={description} 
                    onChange={(e) => setDescription(e.target.value)}
                    required
                    rows={4}
                    style={{ width: '100%' }}
                />
            </div>

            <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
                <button type="submit" className="button button-primary" disabled={loading}>
                    {loading ? 'Saving...' : (skill ? 'Save Skill' : 'Create Skill')}
                </button>
                <button type="button" className="button" onClick={onCancel} disabled={loading}>
                    Cancel
                </button>
            </div>
        </form>
    );
};

export default SkillForm;
