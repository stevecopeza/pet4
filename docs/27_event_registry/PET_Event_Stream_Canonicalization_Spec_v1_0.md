# PET Event Stream Canonicalization Specification v1.0

## Problem

Core domain events are not durably persisted.

## Requirement

All core domain events must be appended before dispatch: -
QuoteAccepted - TicketCreated - ProjectCreated - SLAWarning / Breach -
EscalationTriggered

## Purpose

Enable replay, audit, and projection rebuild.
