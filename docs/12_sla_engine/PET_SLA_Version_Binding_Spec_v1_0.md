# PET SLA Version Binding Specification v1.0

## Problem

SlaClockState.slaVersionId is persisted as 0.

## Requirement

When creating a clock state: slaVersionId must equal
SlaDefinition.version_number.

Existing clocks remain immutable.
