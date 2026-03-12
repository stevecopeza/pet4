# Capacity Overrides & Utilization

## Precedence
- CapacityOverride (specific date) → Approved Leave → Holiday → Working Window

## Effective Capacity
- base_hours = working_window_hours
- if holiday → base_hours = 0
- if approved_leave overlaps → base_hours = 0
- if override exists → base_hours = working_window_hours × (capacity_pct / 100)
- overrides do not stack

## Utilization API Output
```
{
  "employee_id": 3,
  "date": "YYYY-MM-DD",
  "effective_capacity_hours": 6.8,
  "scheduled_hours": 5.0,
  "utilization_pct": 73.5
}
```

**Authority**: Normative
