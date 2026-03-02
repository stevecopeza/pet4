<?php

declare(strict_types=1);

namespace Pet\Domain\Configuration\Repository;

use Pet\Domain\Configuration\Entity\SchemaDefinition;

interface SchemaDefinitionRepository
{
    public function save(SchemaDefinition $schemaDefinition): void;
    public function findById(int $id): ?SchemaDefinition;
    public function findLatestByEntityType(string $entityType): ?SchemaDefinition;
    public function findDraftByEntityType(string $entityType): ?SchemaDefinition;
    public function findActiveByEntityType(string $entityType): ?SchemaDefinition;
    public function findByEntityType(string $entityType): array;
    public function findByEntityTypeAndVersion(string $entityType, int $version): ?SchemaDefinition;
    public function markActiveAsHistorical(string $entityType): void;
}
