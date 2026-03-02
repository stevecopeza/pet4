import React, { useEffect, useState } from 'react';
import { DataTable, Column } from './DataTable';
import { Employee } from '../types';
import EmployeeSkills from './EmployeeSkills';
import PersonKpis from './PersonKpis';

interface PerformanceReview {
  id: number;
  employee_id: number;
  reviewer_id: number;
  period_start: string;
  period_end: string;
  status: string;
  content: any;
  created_at: string;
  updated_at: string;
}

interface EmployeeReviewsProps {
  employee: Employee;
}

const EmployeeReviews: React.FC<EmployeeReviewsProps> = ({ employee }) => {
  const [reviews, setReviews] = useState<PerformanceReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [createParams, setCreateParams] = useState({
    period_start: new Date().toISOString().split('T')[0],
    period_end: new Date(new Date().setMonth(new Date().getMonth() + 3)).toISOString().split('T')[0],
  });

  // Edit/View state
  const [selectedReview, setSelectedReview] = useState<PerformanceReview | null>(null);
  const [activeReviewTab, setActiveReviewTab] = useState<'details' | 'skills' | 'kpis'>('details');
  const [reviewContent, setReviewContent] = useState<string>(''); // For now, simple text content
  const [reviewStatus, setReviewStatus] = useState<string>('');

  const fetchReviews = async () => {
    try {
      setLoading(true);
      // @ts-ignore
      const url = `${window.petSettings.apiUrl}/performance-reviews?employee_id=${employee.id}`;
      // @ts-ignore
      const response = await fetch(url, {
        headers: {
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        setReviews(data);
      }
    } catch (err) {
      console.error('Failed to fetch reviews', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (employee.id) {
      fetchReviews();
    }
  }, [employee.id]);

  const handleCreateReview = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/performance-reviews`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({
          employee_id: employee.id,
          ...createParams,
        }),
      });

      if (response.ok) {
        setShowCreateForm(false);
        fetchReviews();
      }
    } catch (err) {
      console.error('Failed to create review', err);
    }
  };

  const handleUpdateReview = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedReview) return;

    try {
      // @ts-ignore
      const response = await fetch(`${window.petSettings.apiUrl}/performance-reviews/${selectedReview.id}`, {
        method: 'POST', // or PUT/PATCH
        headers: {
          'Content-Type': 'application/json',
          // @ts-ignore
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({
          content: { notes: reviewContent },
          status: reviewStatus,
        }),
      });

      if (response.ok) {
        setSelectedReview(null);
        fetchReviews();
      }
    } catch (err) {
      console.error('Failed to update review', err);
    }
  };

  const openReview = (review: PerformanceReview) => {
    setSelectedReview(review);
    setActiveReviewTab('details');
    setReviewContent(review.content?.notes || '');
    setReviewStatus(review.status);
  };

  const columns: Column<PerformanceReview>[] = [
    { header: 'ID', key: 'id' },
    { header: 'Period Start', key: 'period_start' },
    { header: 'Period End', key: 'period_end' },
    { header: 'Status', key: 'status' },
    { header: 'Updated At', key: 'updated_at' },
    {
      header: 'Actions',
      key: 'id',
      render: (_, review) => (
        <button
          onClick={() => openReview(review)}
          className="button button-small"
        >
          Open
        </button>
      ),
    },
  ];

  if (loading && !reviews.length) return <p>Loading reviews...</p>;

  return (
    <div className="pet-employee-reviews">
      <div className="pet-actions" style={{ marginBottom: '1rem' }}>
        <button
          className="button button-secondary"
          onClick={() => setShowCreateForm(!showCreateForm)}
        >
          {showCreateForm ? 'Cancel' : 'Start New Review'}
        </button>
      </div>

      {showCreateForm && (
        <div className="pet-form-panel" style={{ padding: '15px', background: '#f9f9f9', border: '1px solid #ddd', marginBottom: '15px' }}>
          <h4>New Performance Review</h4>
          <form onSubmit={handleCreateReview}>
            <div className="form-field">
              <label>Period Start</label>
              <input
                type="date"
                value={createParams.period_start}
                onChange={(e) => setCreateParams({ ...createParams, period_start: e.target.value })}
                required
              />
            </div>
            <div className="form-field">
              <label>Period End</label>
              <input
                type="date"
                value={createParams.period_end}
                onChange={(e) => setCreateParams({ ...createParams, period_end: e.target.value })}
                required
              />
            </div>
            <button type="submit" className="button button-primary">Create</button>
          </form>
        </div>
      )}

      {selectedReview ? (
        <div className="pet-review-detail" style={{ padding: '15px', background: '#fff', border: '1px solid #ccc' }}>
          <h4>Review #{selectedReview.id} ({selectedReview.period_start} to {selectedReview.period_end})</h4>
          
          <div className="nav-tab-wrapper" style={{ marginBottom: '20px' }}>
            <button 
              type="button"
              className={`nav-tab ${activeReviewTab === 'details' ? 'nav-tab-active' : ''}`}
              onClick={() => setActiveReviewTab('details')}
            >
              Details
            </button>
            <button 
              type="button"
              className={`nav-tab ${activeReviewTab === 'skills' ? 'nav-tab-active' : ''}`}
              onClick={() => setActiveReviewTab('skills')}
            >
              Skills
            </button>
            <button 
              type="button"
              className={`nav-tab ${activeReviewTab === 'kpis' ? 'nav-tab-active' : ''}`}
              onClick={() => setActiveReviewTab('kpis')}
            >
              KPIs
            </button>
          </div>

          {activeReviewTab === 'details' && (
            <form onSubmit={handleUpdateReview}>
              <div className="form-field">
                <label>Notes / Feedback</label>
                <textarea
                  value={reviewContent}
                  onChange={(e) => setReviewContent(e.target.value)}
                  rows={10}
                  style={{ width: '100%' }}
                />
              </div>
              <div className="form-field">
                <label>Status</label>
                <select value={reviewStatus} onChange={(e) => setReviewStatus(e.target.value)}>
                  <option value="draft">Draft</option>
                  <option value="submitted">Submitted</option>
                  <option value="completed">Completed</option>
                </select>
              </div>
              <button type="submit" className="button button-primary">Save Review</button>
              <button 
                type="button" 
                className="button" 
                style={{ marginLeft: '10px' }}
                onClick={() => setSelectedReview(null)}
              >
                Close
              </button>
            </form>
          )}

          {activeReviewTab === 'skills' && (
            <EmployeeSkills 
              employee={employee} 
              reviewCycleId={selectedReview.id} 
            />
          )}

          {activeReviewTab === 'kpis' && (
            <PersonKpis 
              employee={employee}
              periodStart={selectedReview.period_start}
              periodEnd={selectedReview.period_end}
            />
          )}
        </div>
      ) : (
        <DataTable 
          data={reviews}
          columns={columns}
          loading={loading}
          emptyMessage="No performance reviews found."
        />
      )}
    </div>
  );
};

export default EmployeeReviews;
