# PET Visual 03 — Quote Acceptance to Delivery Topology

```mermaid
flowchart TD

QA[Quote Accepted]

QA --> PRJ[Project Created]

QA --> UNITS[All Simple Units]

UNITS --> TK[Ticket Created Per Unit]

TK --> ACT[Activity Stream]

TK --> TS[Timesheets]

PRJ --> ACT
QA --> ACT
```
