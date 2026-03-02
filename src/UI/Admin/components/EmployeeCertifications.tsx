import React, { useState, useEffect } from 'react';
import { DataTable, Column } from './DataTable';
import { Employee, PersonCertification, Certification } from '../types';

interface EmployeeCertificationsProps {
  employee: Employee;
}

const EmployeeCertifications: React.FC<EmployeeCertificationsProps> = ({ employee }) => {
  const [certifications, setCertifications] = useState<PersonCertification[]>([]);
  const [availableCertifications, setAvailableCertifications] = useState<Certification[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);

  // Form state
  const [newCertificationId, setNewCertificationId] = useState('');
  const [obtainedDate, setObtainedDate] = useState('');
  const [expiryDate, setExpiryDate] = useState('');
  const [evidenceUrl, setEvidenceUrl] = useState('');

  // @ts-ignore
  const apiUrl = window.petSettings?.apiUrl;
  // @ts-ignore
  const nonce = window.petSettings?.nonce;

  useEffect(() => {
    fetchData();
  }, [employee.id]);

  const fetchData = async () => {
    setLoading(true);
    try {
      await Promise.all([
        fetchCertifications(),
        fetchAvailableCertifications()
      ]);
    } catch (error) {
      console.error('Error fetching data:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchCertifications = async () => {
    const response = await fetch(`${apiUrl}/employees/${employee.id}/certifications`, {
      headers: { 'X-WP-Nonce': nonce }
    });
    if (response.ok) {
      const data = await response.json();
      setCertifications(data);
    }
  };

  const fetchAvailableCertifications = async () => {
    const response = await fetch(`${apiUrl}/certifications`, {
      headers: { 'X-WP-Nonce': nonce }
    });
    if (response.ok) {
      const data = await response.json();
      setAvailableCertifications(data);
    }
  };

  const handleAddCertification = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newCertificationId) return;

    try {
      const response = await fetch(`${apiUrl}/employees/${employee.id}/certifications`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          certification_id: parseInt(newCertificationId),
          obtained_date: obtainedDate,
          expiry_date: expiryDate || null,
          evidence_url: evidenceUrl || null,
        }),
      });

      if (response.ok) {
        setShowAddForm(false);
        setNewCertificationId('');
        setObtainedDate('');
        setExpiryDate('');
        setEvidenceUrl('');
        fetchCertifications(); // Refresh list
      } else {
        alert('Failed to assign certification');
      }
    } catch (err) {
      console.error(err);
      alert('Error assigning certification');
    }
  };

  // Enrich data for display
  const enrichedCertifications = certifications.map(cert => {
    const def = availableCertifications.find(c => c.id === cert.certification_id);
    return {
      ...cert,
      certification_name: def ? def.name : 'Unknown',
      issuing_body: def ? def.issuing_body : 'Unknown'
    };
  });

  const columns: Column<PersonCertification>[] = [
    { key: 'certification_name', header: 'Certification' },
    { key: 'issuing_body', header: 'Issuing Body' },
    { key: 'obtained_date', header: 'Obtained' },
    { key: 'expiry_date', header: 'Expires' },
    { 
        key: 'evidence_url', 
        header: 'Evidence', 
        render: (val) => val ? <a href={val as string} target="_blank" rel="noopener noreferrer">View</a> : '-' 
    },
    { key: 'status', header: 'Status' },
  ];

  return (
    <div className="employee-certifications">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
        <h4>Employee Certifications</h4>
        <button 
          type="button" 
          className="button button-secondary"
          onClick={() => setShowAddForm(!showAddForm)}
        >
          {showAddForm ? 'Cancel' : 'Add Certification'}
        </button>
      </div>

      {showAddForm && (
        <div style={{ padding: '15px', background: '#f0f0f1', marginBottom: '15px', border: '1px solid #ccd0d4' }}>
          <form onSubmit={handleAddCertification}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Certification</label>
                <select 
                  value={newCertificationId}
                  onChange={(e) => setNewCertificationId(e.target.value)}
                  required
                  style={{ width: '100%' }}
                >
                  <option value="">Select Certification...</option>
                  {availableCertifications.map(cert => (
                    <option key={cert.id} value={cert.id}>{cert.name} ({cert.issuing_body})</option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Obtained Date</label>
                <input 
                  type="date" 
                  value={obtainedDate}
                  onChange={(e) => setObtainedDate(e.target.value)}
                  required
                  style={{ width: '100%' }}
                />
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Expiry Date (Optional)</label>
                <input 
                  type="date" 
                  value={expiryDate}
                  onChange={(e) => setExpiryDate(e.target.value)}
                  style={{ width: '100%' }}
                />
              </div>
              <div>
                <label style={{ display: 'block', marginBottom: '5px' }}>Evidence URL (Optional)</label>
                <input 
                  type="url" 
                  value={evidenceUrl}
                  onChange={(e) => setEvidenceUrl(e.target.value)}
                  placeholder="https://..."
                  style={{ width: '100%' }}
                />
              </div>
            </div>
            <div style={{ marginTop: '10px' }}>
              <button type="submit" className="button button-primary">Save Certification</button>
            </div>
          </form>
        </div>
      )}

      <DataTable
        columns={columns}
        data={enrichedCertifications}
        loading={loading}
        emptyMessage="No certifications recorded."
      />
    </div>
  );
};

export default EmployeeCertifications;
