import React, { useState } from 'react';
import { Calendar, WorkingWindow, Holiday } from '../types';

interface CalendarFormProps {
  initialData?: Calendar;
  onSave: (data: Partial<Calendar>) => void;
  onCancel: () => void;
}

const DAYS_OF_WEEK = [
  { id: 1, name: 'Monday' },
  { id: 2, name: 'Tuesday' },
  { id: 3, name: 'Wednesday' },
  { id: 4, name: 'Thursday' },
  { id: 5, name: 'Friday' },
  { id: 6, name: 'Saturday' },
  { id: 0, name: 'Sunday' },
];

const CalendarForm: React.FC<CalendarFormProps> = ({ initialData, onSave, onCancel }) => {
  const [name, setName] = useState(initialData?.name || '');
  const [timezone, setTimezone] = useState(initialData?.timezone || 'UTC');
  const [isDefault, setIsDefault] = useState(initialData?.is_default || false);
  const [workingWindows, setWorkingWindows] = useState<WorkingWindow[]>(initialData?.working_windows || []);
  const [holidays, setHolidays] = useState<Holiday[]>(initialData?.holidays || []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSave({
      name,
      timezone,
      is_default: isDefault,
      working_windows: workingWindows,
      holidays: holidays
    });
  };

  const updateWorkingWindow = (dayOfWeek: number, field: 'start_time' | 'end_time', value: string) => {
    setWorkingWindows(prev => {
      const existing = prev.find(w => w.day_of_week === dayOfWeek);
      if (existing) {
        if (!value && field === 'start_time') {
           // If clearing start time, remove the window (or handle appropriately)
           // For now let's just update
        }
        return prev.map(w => w.day_of_week === dayOfWeek ? { ...w, [field]: value } : w);
      } else {
        return [...prev, { 
          day_of_week: dayOfWeek, 
          start_time: field === 'start_time' ? value : '09:00',
          end_time: field === 'end_time' ? value : '17:00'
        }];
      }
    });
  };

  const getWorkingWindow = (dayOfWeek: number) => {
    return workingWindows.find(w => w.day_of_week === dayOfWeek) || { start_time: '', end_time: '' };
  };

  const addHoliday = () => {
    setHolidays([...holidays, { name: 'New Holiday', date: new Date().toISOString().split('T')[0], is_recurring: false }]);
  };

  const updateHoliday = (index: number, field: keyof Holiday, value: any) => {
    const newHolidays = [...holidays];
    newHolidays[index] = { ...newHolidays[index], [field]: value };
    setHolidays(newHolidays);
  };

  const removeHoliday = (index: number) => {
    setHolidays(holidays.filter((_, i) => i !== index));
  };

  return (
    <div className="pet-form-container" style={{ maxWidth: '800px', margin: '0 auto' }}>
      <h3>{initialData ? 'Edit Calendar' : 'New Calendar'}</h3>
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Name</label>
          <input 
            type="text" 
            value={name} 
            onChange={e => setName(e.target.value)} 
            required 
            style={{ width: '100%', padding: '8px', marginBottom: '15px' }}
          />
        </div>

        <div className="form-group">
          <label>Timezone</label>
          <select 
            value={timezone} 
            onChange={e => setTimezone(e.target.value)}
            style={{ width: '100%', padding: '8px', marginBottom: '15px' }}
          >
            <option value="UTC">UTC</option>
            <option value="Africa/Johannesburg">Africa/Johannesburg</option>
            <option value="Europe/London">Europe/London</option>
            <option value="America/New_York">America/New_York</option>
            {/* Add more as needed */}
          </select>
        </div>

        <div className="form-group">
          <label>
            <input 
              type="checkbox" 
              checked={isDefault} 
              onChange={e => setIsDefault(e.target.checked)} 
            />
            {' '}Is Default Calendar
          </label>
        </div>

        <div style={{ marginTop: '30px' }}>
          <h4>Working Windows</h4>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                <th style={{ textAlign: 'left' }}>Day</th>
                <th style={{ textAlign: 'left' }}>Start Time</th>
                <th style={{ textAlign: 'left' }}>End Time</th>
              </tr>
            </thead>
            <tbody>
              {DAYS_OF_WEEK.map(day => {
                const window = getWorkingWindow(day.id);
                return (
                  <tr key={day.id} style={{ borderBottom: '1px solid #eee' }}>
                    <td style={{ padding: '10px 0' }}>{day.name}</td>
                    <td>
                      <input 
                        type="time" 
                        value={window.start_time} 
                        onChange={e => updateWorkingWindow(day.id, 'start_time', e.target.value)}
                      />
                    </td>
                    <td>
                      <input 
                        type="time" 
                        value={window.end_time} 
                        onChange={e => updateWorkingWindow(day.id, 'end_time', e.target.value)}
                      />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div style={{ marginTop: '30px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h4>Holidays</h4>
            <button type="button" onClick={addHoliday} style={{ fontSize: '0.8em' }}>Add Holiday</button>
          </div>
          
          {holidays.map((holiday, index) => (
            <div key={index} style={{ display: 'flex', gap: '10px', marginBottom: '10px', alignItems: 'center' }}>
              <input 
                type="text" 
                value={holiday.name} 
                onChange={e => updateHoliday(index, 'name', e.target.value)}
                placeholder="Holiday Name"
                style={{ flex: 2 }}
              />
              <input 
                type="date" 
                value={holiday.date} 
                onChange={e => updateHoliday(index, 'date', e.target.value)}
                style={{ flex: 1 }}
              />
              <label style={{ display: 'flex', alignItems: 'center' }}>
                <input 
                  type="checkbox" 
                  checked={holiday.is_recurring} 
                  onChange={e => updateHoliday(index, 'is_recurring', e.target.checked)}
                />
                Recurring
              </label>
              <button 
                type="button" 
                onClick={() => removeHoliday(index)}
                style={{ color: 'red', border: 'none', background: 'none', cursor: 'pointer' }}
              >
                &times;
              </button>
            </div>
          ))}
        </div>

        <div style={{ marginTop: '30px', display: 'flex', gap: '10px' }}>
          <button 
            type="submit" 
            style={{ 
              background: '#007cba', 
              color: '#fff', 
              border: 'none', 
              padding: '10px 20px', 
              borderRadius: '4px',
              cursor: 'pointer' 
            }}
          >
            Save Calendar
          </button>
          <button 
            type="button" 
            onClick={onCancel}
            style={{ 
              background: '#f0f0f1', 
              color: '#000', 
              border: '1px solid #ccc', 
              padding: '10px 20px', 
              borderRadius: '4px',
              cursor: 'pointer' 
            }}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default CalendarForm;
