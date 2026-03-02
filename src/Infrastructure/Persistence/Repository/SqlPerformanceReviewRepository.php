<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\PerformanceReview;
use Pet\Domain\Work\Repository\PerformanceReviewRepository;

class SqlPerformanceReviewRepository implements PerformanceReviewRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_performance_reviews';
    }

    public function save(PerformanceReview $review): int
    {
        $data = [
            'employee_id' => $review->employeeId(),
            'reviewer_id' => $review->reviewerId(),
            'period_start' => $review->periodStart()->format('Y-m-d'),
            'period_end' => $review->periodEnd()->format('Y-m-d'),
            'status' => $review->status(),
            'content' => json_encode($review->content()),
            'created_at' => $review->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $review->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($review->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $review->id()],
                $format,
                ['%d']
            );
            return $review->id();
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            return (int) $this->wpdb->insert_id;
        }
    }

    public function findById(int $id): ?PerformanceReview
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmployeeId(int $employeeId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE employee_id = %d ORDER BY period_end DESC",
            $employeeId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    public function findByReviewerId(int $reviewerId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE reviewer_id = %d ORDER BY period_end DESC",
            $reviewerId
        );
        $results = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrate'], $results);
    }

    private function hydrate(object $row): PerformanceReview
    {
        return new PerformanceReview(
            (int) $row->employee_id,
            (int) $row->reviewer_id,
            new \DateTimeImmutable($row->period_start),
            new \DateTimeImmutable($row->period_end),
            (int) $row->id,
            $row->status,
            json_decode($row->content, true) ?: [],
            new \DateTimeImmutable($row->created_at),
            new \DateTimeImmutable($row->updated_at)
        );
    }
}
