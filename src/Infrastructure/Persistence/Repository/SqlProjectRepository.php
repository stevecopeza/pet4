<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Delivery\ValueObject\ProjectState;

class SqlProjectRepository implements ProjectRepository
{
    private $wpdb;
    private $projectsTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->projectsTable = $wpdb->prefix . 'pet_projects';
    }

    public function save(Project $project): void
    {
        $data = [
            'customer_id' => $project->customerId(),
            'source_quote_id' => $project->sourceQuoteId(),
            'name' => $project->name(),
            'sold_hours' => $project->soldHours(),
            'state' => $project->state()->toString(),
            'sold_value' => $project->soldValue(),
            'start_date' => $project->startDate() ? $project->startDate()->format('Y-m-d') : null,
            'end_date' => $project->endDate() ? $project->endDate()->format('Y-m-d') : null,
            'malleable_schema_version' => $project->malleableSchemaVersion(),
            'malleable_data' => !empty($project->malleableData()) ? json_encode($project->malleableData()) : null,
            'created_at' => $project->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $project->updatedAt() ? $project->updatedAt()->format('Y-m-d H:i:s') : null,
            'archived_at' => $project->archivedAt() ? $project->archivedAt()->format('Y-m-d H:i:s') : null,
        ];

        $format = ['%d', '%d', '%s', '%f', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s'];

        if ($project->id()) {
            $this->wpdb->update(
                $this->projectsTable,
                $data,
                ['id' => $project->id()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert(
                $this->projectsTable,
                $data,
                $format
            );
        }

    }

    public function findById(int $id): ?Project
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->projectsTable} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->projectsTable} ORDER BY created_at DESC";
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByCustomerId(int $customerId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->projectsTable} WHERE customer_id = %d ORDER BY created_at DESC",
            $customerId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByQuoteId(int $quoteId): ?Project
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->projectsTable} WHERE source_quote_id = %d LIMIT 1",
            $quoteId
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function countActive(): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->projectsTable} WHERE state = %s",
            ProjectState::ACTIVE
        );
        
        return (int) $this->wpdb->get_var($sql);
    }

    public function sumSoldHours(): float
    {
        $sql = "SELECT SUM(sold_hours) FROM {$this->projectsTable}";
        return (float) $this->wpdb->get_var($sql);
    }

    private function hydrate(object $row): Project
    {
        return new Project(
            (int) $row->customer_id,
            $row->name,
            (float) $row->sold_hours,
            $row->source_quote_id ? (int) $row->source_quote_id : null,
            ProjectState::fromString($row->state),
            isset($row->sold_value) ? (float) $row->sold_value : 0.00,
            !empty($row->start_date) ? new \DateTimeImmutable($row->start_date) : null,
            !empty($row->end_date) ? new \DateTimeImmutable($row->end_date) : null,
            (int) $row->id,
            isset($row->malleable_schema_version) ? (int) $row->malleable_schema_version : null,
            isset($row->malleable_data) ? (json_decode($row->malleable_data, true) ?: []) : [],
            new \DateTimeImmutable($row->created_at),
            $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null,
            $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null
        );
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}
