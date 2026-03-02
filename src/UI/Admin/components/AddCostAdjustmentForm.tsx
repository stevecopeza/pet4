import React, { useState } from 'react';

interface AddCostAdjustmentFormProps {
  onSuccess: () => void;
  onCancel: () => void;
  quoteId: number;
}

const AddCostAdjustmentForm: React.FC<AddCostAdjustmentFormProps> = ({ onSuccess, onCancel, quoteId }) => {
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState<string>('0');
  const [reason, setReason] = useState('');
  const [approvedBy, setApprovedBy] = useState('');
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const payload = {
        description,
        amount: parseFloat(amount),
        reason,
        approvedBy
      };

      const response = await fetch(`${apiUrl}/quotes/${quoteId}/adjustments`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to add cost adjustment');
      }

      onSuccess();
    } catch (err) {
      console.error('AddCostAdjustmentForm: Error', err);
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {error && <div className="notice notice-error inline"><p>{error}</p></div>}
      
      <div style={{ marginBottom: '15px' }}>
        <label htmlFor="adj-description" style={{ display: 'block', marginBottom: '5px' }}>Description</label>
        <input 
          id="adj-description"
          type="text" 
          value={description} 
          onChange={(e) => setDescription(e.target.value)}
          style={{ width: '100%' }}
          required
          placeholder="e.g. Rush fee, Discount, Correction"
        />
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label htmlFor="adj-amount" style={{ display: 'block', marginBottom: '5px' }}>Amount (Negative for credit/discount)</label>
        <input 
          id="adj-amount"
          type="number" 
          value={amount} 
          onChange={(e) => setAmount(e.target.value)}
          style={{ width: '100%' }}
          step="0.01"
          required
        />
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label htmlFor="adj-reason" style={{ display: 'block', marginBottom: '5px' }}>Reason</label>
        <textarea 
          id="adj-reason"
          value={reason} 
          onChange={(e) => setReason(e.target.value)}
          style={{ width: '100%', minHeight: '60px' }}
          required
          placeholder="Why is this adjustment being made?"
        />
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label htmlFor="adj-approved-by" style={{ display: 'block', marginBottom: '5px' }}>Approved By</label>
        <input 
          id="adj-approved-by"
          type="text" 
          value={approvedBy} 
          onChange={(e) => setApprovedBy(e.target.value)}
          style={{ width: '100%' }}
          required
          placeholder="Name of person authorizing this"
        />
      </div>

      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
        <button type="button" className="button" onClick={onCancel} disabled={loading}>Cancel</button>
        <button type="submit" className="button button-primary" disabled={loading}>
          {loading ? 'Adding...' : 'Add Adjustment'}
        </button>
      </div>
    </form>
  );
};

export default AddCostAdjustmentForm;
