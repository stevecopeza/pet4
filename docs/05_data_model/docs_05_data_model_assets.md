# Data Model -- Visual Assets

## Table: pet_assets

  Column        Type
  ------------- -----------
  id            bigint PK
  entity_type   varchar
  entity_id     bigint
  file_path     varchar
  version       int
  created_at    datetime

## Rules

-   Assets stored in PET-controlled directory.
-   No dependency on WordPress Media Library.
-   Assets are immutable.
-   New version = new record.
-   Historical references must use recorded version.
