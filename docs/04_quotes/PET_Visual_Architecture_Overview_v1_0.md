
# PET Visual Architecture Overview (v1.0)

Status: Demo-Ready Architecture Summary  
Purpose: Board-ready overview of how Quotes, Delivery, Tickets, Activity, and Timesheets integrate.

---

# 1. System Philosophy

PET is:

- Domain-driven
- Event-backed
- Immutable-history focused
- Delivery-topology aware

Quotes are commercial intent.  
Acceptance creates operational truth.

---

# 2. High-Level System Flow

```mermaid
flowchart LR

Customer --> Quote
Quote -->|Accepted| DeliveryTopology
DeliveryTopology --> Tickets
Tickets --> TimeEntries
Tickets --> ActivityStream
Quote --> ActivityStream
DeliveryTopology --> ActivityStream
```

---

# 3. Quote as Composable Document

```mermaid
flowchart TD

Quote
  --> TextBlock
  --> SimpleServiceBlock
  --> ProjectBlock
  --> RepeatServiceBlock
  --> PriceAdjustmentBlock
```

- Ordered block model
- Drag/drop reordering
- Totals derived from priced blocks
- No modal trees
- Scalable for future block types

---

# 4. Delivery Topology Model

## Simple Once-off Service

```mermaid
flowchart TD
Component(Simple)
  --> Unit1
  --> Unit2
  Unit2 -. depends on .-> Unit1
```

## Complex Project

```mermaid
flowchart TD
Component(Complex)
  --> PhaseA
  PhaseA --> UnitA1
  PhaseA --> UnitA2
  --> PhaseB
  PhaseB --> UnitB1
```

Units are atomic governed work items.

---

# 5. Acceptance Creates Operational Truth

```mermaid
sequenceDiagram

participant User
participant Quote
participant Domain
participant Project
participant Ticket

User->>Quote: Accept Quote
Quote->>Domain: QuoteAccepted
Domain->>Project: Create Project
Domain->>Ticket: Create Ticket per Unit
Ticket->>ActivityStream: Emit events
```

No delivery exists before acceptance.

---

# 6. Ticket Governance Model

Each Ticket includes:

- Owner (team)
- Assignee (optional)
- Due date
- State machine
- Dependencies
- Acceptance criteria
- Append-only history
- Time entries
- Comment + attachment support

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

---

# 7. Recurring Service Model

Two modes:

- SLA Mode (reactive)
- Scheduled Work Mode (proactive)

```mermaid
sequenceDiagram

System->>Ticket: Create first occurrence
Ticket->>System: Closed
System->>Ticket: Generate next occurrence
```

Only next occurrence generated (no ticket explosion).

---

# 8. Activity Projection Architecture

```mermaid
flowchart LR

Command --> DomainEvent
DomainEvent --> EventStore
EventStore --> Projection
Projection --> ActivityReadModel
ActivityReadModel --> UI
```

Managers see complete operational truth.

---

# 9. Key Architectural Guarantees

- Commercial pricing lives at unit level
- Phase totals derived only
- History immutable
- Activity stream event-projected
- Quote → Delivery transition explicit and auditable
