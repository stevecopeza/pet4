import React, { useEffect, useState } from 'react';
import { Project, Task } from '../types';
import { DataTable, Column } from './DataTable';
import LogTimeModal from './LogTimeModal';
import useConversation from '../hooks/useConversation';
import { computeProjectHealth } from '../healthCompute';

interface ProjectDetailsProps {
  projectId: number;
  onBack: () => void;
}

const ProjectDetails: React.FC<ProjectDetailsProps> = ({ projectId, onBack }) => {
  const [project, setProject] = useState<Project | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const { openConversation } = useConversation();
  
  // Add Task Form State
  const [name, setName] = useState('');
  const [estimatedHours, setEstimatedHours] = useState(1);
  const [addingTask, setAddingTask] = useState(false);

  // Log Time State
  const [selectedTaskForLog, setSelectedTaskForLog] = useState<Task | null>(null);

  const fetchProject = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/projects/${projectId}`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch project details');
      }

      const data = await response.json();
      setProject(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProject();
  }, [projectId]);

  const handleAddTask = async (e: React.FormEvent) => {
    e.preventDefault();
    setAddingTask(true);
    
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/projects/${projectId}/tasks`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({
          name,
          estimatedHours,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Failed to add task');
      }

      // Reset form and refresh project
      setName('');
      setEstimatedHours(1);
      fetchProject();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error adding task');
    } finally {
      setAddingTask(false);
    }
  };

  const taskColumns: Column<Task>[] = [
    { key: 'name', header: 'Task Name' },
    { key: 'estimatedHours', header: 'Est. Hours' },
    { key: 'completed', header: 'Status', render: (_, item) => <span className={`pet-status-badge status-${item.completed ? 'completed' : 'pending'}`}>{item.completed ? 'Completed' : 'Pending'}</span> },
    {
      key: 'id',
      header: 'Actions',
      render: (_, item) => (
        <button 
          className="button button-small"
          onClick={() => setSelectedTaskForLog(item)}
        >
          Log Time
        </button>
      )
    }
  ];

  if (loading) return <div>Loading project details...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
  if (!project) return <div>Project not found</div>;

  const projHealth = project ? computeProjectHealth(project) : null;

  return (
    <div className={`pet-project-details ${projHealth?.className || ''}`}>
      <div style={{ marginBottom: '20px' }}>
        <button className="button" onClick={onBack}>&larr; Back to Projects</button>
        {project && (
          <button 
            className="button" 
            onClick={() => openConversation({
              contextType: 'project',
              contextId: String(project.id),
              subject: `Project: ${project.name}`,
              subjectKey: `project:${project.id}`,
            })}
            style={{ marginLeft: '10px' }}
          >
            Discuss
          </button>
        )}
      </div>

      <div className="card" style={{ padding: '20px', marginBottom: '20px', background: '#fff', border: '1px solid #ccd0d4' }}>
        <h2>
          {project.name}
          {projHealth && projHealth.reasons.map((r, i) => (
            <span key={i} className={`uhb-tag uhb-tag-${r.color}`}>{r.label}</span>
          ))}
        </h2>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
          <div>
            <p><strong>Customer ID:</strong> {project.customerId}</p>
            <p><strong>Total Sold Hours:</strong> {project.soldHours}</p>
          </div>
          <div>
            <p><strong>Total Tasks:</strong> {project.tasks.length}</p>
            <p><strong>Estimated Hours (Sum):</strong> {project.tasks.reduce((sum, task) => sum + task.estimatedHours, 0).toFixed(2)}</p>
          </div>
        </div>
      </div>

      <h3>Tasks</h3>
      <DataTable 
        columns={taskColumns} 
        data={project.tasks} 
        emptyMessage="No tasks yet." 
      />

      <div className="card" style={{ marginTop: '20px', padding: '20px', background: '#f0f0f1', border: '1px solid #ccd0d4' }}>
        <h4>Add Task</h4>
        <form onSubmit={handleAddTask} style={{ display: 'grid', gridTemplateColumns: '2fr 1fr auto', gap: '10px', alignItems: 'end' }}>
          <div>
            <label style={{ display: 'block', marginBottom: '5px' }}>Task Name</label>
            <input 
              type="text" 
              className="regular-text" 
              style={{ width: '100%' }}
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
            />
          </div>
          <div>
            <label style={{ display: 'block', marginBottom: '5px' }}>Est. Hours</label>
            <input 
              type="number" 
              step="0.1"
              min="0.1"
              style={{ width: '100%' }}
              value={estimatedHours}
              onChange={(e) => setEstimatedHours(parseFloat(e.target.value))}
              required
            />
          </div>
          <div>
            <button type="submit" className="button button-primary" disabled={addingTask}>
              {addingTask ? 'Adding...' : 'Add Task'}
            </button>
          </div>
        </form>
      </div>

      {selectedTaskForLog && null}
    </div>
  );
};

export default ProjectDetails;
