import React, { useState } from 'react';
import { Certification } from '../types';

interface CertificationFormProps {
  certification?: Certification | null;
  onSuccess: () => void;
  onCancel: () => void;
}

const CertificationForm: React.FC<CertificationFormProps> = ({ certification, onSuccess, onCancel }) => {
  const [name, setName] = useState(certification?.name || '');
  const [issuingBody, setIssuingBody] = useState(certification?.issuing_body || '');
  const [expiryMonths, setExpiryMonths] = useState(certification?.expiry_months?.toString() || '0');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      // @ts-ignore
      const url = certification 
        // @ts-ignore
        ? `${window.petSettings.apiUrl}/certifications/${certification.id}`
        // @ts-ignore
        : `${window.petSettings.apiUrl}/certifications`;
        
      const method = certification ? 'PUT' : 'POST';

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({
          name,
          issuing_body: issuingBody,
          expiry_months: parseInt(expiryMonths) || 0,
        }),
      });

      if (response.ok) {
        onSuccess();
      } else {
        const data = await response.json();
        setError(data.message || 'Failed to save certification');
      }
    } catch (err) {
      console.error('Error saving certification:', err);
      setError('An unexpected error occurred');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="pet-card" style={{ maxWidth: '600px' }}>
      <h3>{certification ? 'Edit Certification' : 'Add New Certification'}</h3>
      
      {error && <div className="notice notice-error inline"><p>{error}</p></div>}

      <form onSubmit={handleSubmit}>
        <table className="form-table">
          <tbody>
            <tr>
              <th scope="row"><label htmlFor="name">Certification Name</label></th>
              <td>
                <input
                  type="text"
                  id="name"
                  className="regular-text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                />
              </td>
            </tr>
            <tr>
              <th scope="row"><label htmlFor="issuingBody">Issuing Body</label></th>
              <td>
                <input
                  type="text"
                  id="issuingBody"
                  className="regular-text"
                  value={issuingBody}
                  onChange={(e) => setIssuingBody(e.target.value)}
                  required
                />
              </td>
            </tr>
            <tr>
              <th scope="row"><label htmlFor="expiryMonths">Validity Period (Months)</label></th>
              <td>
                <input
                  type="number"
                  id="expiryMonths"
                  className="small-text"
                  value={expiryMonths}
                  onChange={(e) => setExpiryMonths(e.target.value)}
                  min="0"
                />
                <p className="description">Enter 0 if the certification does not expire.</p>
              </td>
            </tr>
          </tbody>
        </table>

        <div className="submit" style={{ display: 'flex', gap: '10px', marginTop: '20px' }}>
          <button 
            type="submit" 
            className="button button-primary"
            disabled={submitting}
          >
            {submitting ? 'Saving...' : (certification ? 'Update Certification' : 'Add Certification')}
          </button>
          <button 
            type="button" 
            className="button"
            onClick={onCancel}
            disabled={submitting}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default CertificationForm;
