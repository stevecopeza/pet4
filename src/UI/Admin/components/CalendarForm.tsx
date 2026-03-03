import React, { useState } from 'react';
import { Calendar, WorkingWindow, Holiday } from '../types';

interface CalendarFormProps {
  initialData?: Calendar;
  onSuccess: () => void;
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

const ALL_DAY_WINDOWS: WorkingWindow[] = DAYS_OF_WEEK.map(d => ({
  day_of_week: d.id,
  start_time: '00:00',
  end_time: '23:59',
}));

const CalendarForm: React.FC<CalendarFormProps> = ({ initialData, onSuccess, onCancel }) => {
  const [name, setName] = useState(initialData?.name || '');
  const [timezone, setTimezone] = useState(initialData?.timezone || 'UTC');
  const [isDefault, setIsDefault] = useState(initialData?.is_default || false);
  const [is24x7, setIs24x7] = useState(initialData?.is_24x7 || false);
  const [excludePublicHolidays, setExcludePublicHolidays] = useState(initialData?.exclude_public_holidays || false);
  const [publicHolidayCountry, setPublicHolidayCountry] = useState(initialData?.public_holiday_country || '');
  const [workingWindows, setWorkingWindows] = useState<WorkingWindow[]>(initialData?.working_windows || []);
  const [savedWindows, setSavedWindows] = useState<WorkingWindow[]>(initialData?.working_windows || []);
  const [holidays, setHolidays] = useState<Holiday[]>(initialData?.holidays || []);

  const handleToggle24x7 = (checked: boolean) => {
    setIs24x7(checked);
    if (checked) {
      setSavedWindows(workingWindows);
      setWorkingWindows(ALL_DAY_WINDOWS);
      setExcludePublicHolidays(false);
      setPublicHolidayCountry('');
    } else {
      setWorkingWindows(savedWindows);
    }
  };
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

      const method = 'POST';
      const url = initialData
        ? `${apiUrl}/calendars/${initialData.id}`
        : `${apiUrl}/calendars`;

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          name,
          timezone,
          is_default: isDefault,
          is_24x7: is24x7,
          exclude_public_holidays: is24x7 ? false : excludePublicHolidays,
          public_holiday_country: !is24x7 && excludePublicHolidays ? publicHolidayCountry || null : null,
          working_windows: workingWindows,
          holidays: holidays,
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to save calendar');
      }

      onSuccess();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
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
    <div className="pet-form-container" style={{ background: '#f9f9f9', padding: '20px', borderRadius: '8px', border: '1px solid #ddd', marginBottom: '20px' }}>
      <h3>{initialData ? 'Edit Calendar' : 'New Calendar'}</h3>
      {error && <div className="notice notice-error" style={{ marginBottom: '15px' }}><p>{error}</p></div>}
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

        <div className="form-group" style={{ marginTop: '15px' }}>
          <label>
            <input 
              type="checkbox" 
              checked={is24x7} 
              onChange={e => handleToggle24x7(e.target.checked)} 
            />
            {' '}24/7 Calendar
          </label>
        </div>

        <div className="form-group" style={{ marginTop: '15px' }}>
          <label style={{ color: is24x7 ? '#999' : undefined }}>
            <input 
              type="checkbox" 
              checked={excludePublicHolidays} 
              onChange={e => setExcludePublicHolidays(e.target.checked)} 
              disabled={is24x7}
            />
            {' '}Exclude Public Holidays
          </label>
          {excludePublicHolidays && (
            <div style={{ marginTop: '10px' }}>
              <label>Country</label>
              <select 
                value={publicHolidayCountry} 
                onChange={e => setPublicHolidayCountry(e.target.value)}
                required
                style={{ width: '100%', padding: '8px' }}
              >
                <option value="">-- Select Country --</option>
                <option value="ZA">South Africa</option>
                <option value="US">United States</option>
                <option value="GB">United Kingdom</option>
                <option value="AU">Australia</option>
                <option value="CA">Canada</option>
                <option value="DE">Germany</option>
                <option value="FR">France</option>
                <option value="NZ">New Zealand</option>
                <option value="IN">India</option>
                <option value="SG">Singapore</option>
                <option value="AE">United Arab Emirates</option>
                <option value="KE">Kenya</option>
                <option value="NG">Nigeria</option>
              </select>
            </div>
          )}
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
                        disabled={is24x7}
                        style={is24x7 ? { color: '#999', background: '#f0f0f0' } : undefined}
                      />
                    </td>
                    <td>
                      <input 
                        type="time" 
                        value={window.end_time} 
                        onChange={e => updateWorkingWindow(day.id, 'end_time', e.target.value)}
                        disabled={is24x7}
                        style={is24x7 ? { color: '#999', background: '#f0f0f0' } : undefined}
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
            className="button button-primary"
            disabled={loading}
          >
            {loading ? 'Saving...' : 'Save Calendar'}
          </button>
          <button 
            type="button" 
            className="button"
            onClick={onCancel}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
};

export default CalendarForm;
