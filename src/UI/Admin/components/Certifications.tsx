import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import CertificationForm from './CertificationForm';

interface Certification {
  id: number;
  name: string;
  issuing_body: string;
  expiry_months: number;
  status: string;
}

const Certifications = () => {
  const [certifications, setCertifications] = useState<Certification[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingCertification, setEditingCertification] = useState<Certification | null>(null);

  const fetchCertifications = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/certifications`, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        setCertifications(data);
      }
    } catch (err) {
      console.error('Failed to fetch certifications', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCertifications();
  }, []);

  const openCertification = (cert: Certification) => {
    setEditingCertification(cert);
    setShowAddForm(true);
  };

  const columns: Column<Certification>[] = [
    { 
      key: 'name', 
      header: 'Certification Name',
      render: (val, cert) => (
        <button
          type="button"
          onClick={() => openCertification(cert)}
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
    { key: 'issuing_body', header: 'Issuing Body' },
    { 
      key: 'expiry_months', 
      header: 'Validity',
      render: (val) => val === 0 ? 'No Expiry' : `${val} Months`
    },
    { key: 'status', header: 'Status' },
  ];

  if (showAddForm) {
    return (
      <div>
        <div style={{ marginBottom: '20px' }}>
          <button 
            className="button" 
            onClick={() => {
              setShowAddForm(false);
              setEditingCertification(null);
            }}
          >
            &larr; Back to Certifications
          </button>
        </div>
        <CertificationForm 
          certification={editingCertification}
          onSuccess={() => {
            setShowAddForm(false);
            setEditingCertification(null);
            fetchCertifications();
          }} 
          onCancel={() => {
            setShowAddForm(false);
            setEditingCertification(null);
          }} 
        />
      </div>
    );
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h3>Certifications Library</h3>
        <button 
          className="button button-primary" 
          onClick={() => {
            setEditingCertification(null);
            setShowAddForm(true);
          }}
        >
          Add Certification
        </button>
      </div>

      <DataTable
        data={certifications}
        columns={columns}
        loading={loading}
        emptyMessage="No certifications defined yet."
        actions={(item) => (
          <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end' }}>
            <button
              className="button button-small"
              onClick={() => openCertification(item)}
            >
              Edit
            </button>
          </div>
        )}
      />
    </div>
  );
};

export default Certifications;
