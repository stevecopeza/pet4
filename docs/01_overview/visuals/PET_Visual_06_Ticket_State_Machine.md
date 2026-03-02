# PET Visual 06 — Ticket State Machine

```mermaid
stateDiagram-v2
    [*] --> Ready
    Ready --> InProgress
    InProgress --> Blocked
    Blocked --> InProgress
    InProgress --> AwaitingSignoff
    AwaitingSignoff --> Done
    Done --> [*]
```
