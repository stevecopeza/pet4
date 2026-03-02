# PET Visual 07 — Activity Projection Architecture

```mermaid
flowchart LR

Command --> DomainEvent
DomainEvent --> EventStore
EventStore --> ActivityProjection
ActivityProjection --> ActivityReadModel
ActivityReadModel --> UI
```
