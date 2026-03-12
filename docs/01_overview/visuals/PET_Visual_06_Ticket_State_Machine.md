# PET Visual 06 — Ticket State Machine

## Support (lifecycle_owner='support')
```mermaid
stateDiagram-v2
    [*] --> New
    New --> Open
    Open --> Pending
    Open --> Resolved
    Open --> Closed
    Pending --> Open
    Pending --> Resolved
    Pending --> Closed
    Resolved --> Closed
    Resolved --> Open
    Closed --> [*]
```

## Project (lifecycle_owner='project')
```mermaid
stateDiagram-v2
    [*] --> Planned
    Planned --> Ready
    Ready --> InProgress
    InProgress --> Blocked
    Blocked --> InProgress
    InProgress --> Done
    Done --> Closed
    Closed --> [*]
```

Note: `baseline_locked` is NOT a status. It is an orthogonal boolean property (`is_baseline_locked`) on the ticket.

## Internal (lifecycle_owner='internal')
```mermaid
stateDiagram-v2
    [*] --> Planned
    Planned --> InProgress
    InProgress --> Done
    Done --> Closed
    Closed --> [*]
```
