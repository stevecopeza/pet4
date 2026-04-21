<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Opportunity;
use Pet\Domain\Commercial\Repository\OpportunityRepository;

class SqlOpportunityRepository implements OpportunityRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(Opportunity $opportunity): void
    {
        $table = $this->wpdb->prefix . 'pet_opportunities';

        $data = [
            'id'                  => $opportunity->id(),
            'customer_id'         => $opportunity->customerId(),
            'lead_id'             => $opportunity->leadId(),
            'name'                => $opportunity->name(),
            'stage'               => $opportunity->stage(),
            'estimated_value'     => $opportunity->estimatedValue(),
            'currency'            => $opportunity->currency(),
            'expected_close_date' => $opportunity->expectedCloseDate()
                                        ? $opportunity->expectedCloseDate()->format('Y-m-d') : null,
            'owner_id'            => $opportunity->ownerId(),
            'qualification_json'  => !empty($opportunity->qualification())
                                        ? json_encode($opportunity->qualification()) : null,
            'notes'               => $opportunity->notes(),
            'created_at'          => $opportunity->createdAt()->format('Y-m-d H:i:s'),
            'updated_at'          => $opportunity->updatedAt()
                                        ? $opportunity->updatedAt()->format('Y-m-d H:i:s') : null,
            'closed_at'           => $opportunity->closedAt()
                                        ? $opportunity->closedAt()->format('Y-m-d H:i:s') : null,
            'quote_id'            => $opportunity->quoteId(),
        ];

        $formats = ['%s','%d','%d','%s','%s','%f','%s','%s','%d','%s','%s','%s','%s','%s','%d'];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM $table WHERE id = %s", $opportunity->id())
        );

        if ($existing) {
            $this->wpdb->update($table, $data, ['id' => $opportunity->id()], $formats, ['%s']);
        } else {
            $this->wpdb->insert($table, $data, $formats);
        }
    }

    public function findById(string $id): ?Opportunity
    {
        $table = $this->wpdb->prefix . 'pet_opportunities';
        $row   = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id)
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_opportunities';
        $rows  = $this->wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByCustomerId(int $customerId): array
    {
        $table = $this->wpdb->prefix . 'pet_opportunities';
        $rows  = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM $table WHERE customer_id = %d ORDER BY created_at DESC", $customerId)
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByStage(string $stage): array
    {
        $table = $this->wpdb->prefix . 'pet_opportunities';
        $rows  = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM $table WHERE stage = %s ORDER BY expected_close_date ASC", $stage)
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findOpen(): array
    {
        $table = $this->wpdb->prefix . 'pet_opportunities';
        $rows  = $this->wpdb->get_results(
            "SELECT * FROM $table WHERE stage IN ('discovery','proposal','negotiation') ORDER BY expected_close_date ASC"
        );
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findAllEnriched(): array
    {
        $oppTable  = $this->wpdb->prefix . 'pet_opportunities';
        $custTable = $this->wpdb->prefix . 'pet_customers';
        return $this->wpdb->get_results(
            "SELECT o.*, c.name AS customer_name
             FROM {$oppTable} o
             LEFT JOIN {$custTable} c ON c.id = o.customer_id
             ORDER BY o.created_at DESC"
        );
    }

    public function delete(string $id): void
    {
        $table = $this->wpdb->prefix . 'pet_opportunities';
        $this->wpdb->delete($table, ['id' => $id], ['%s']);
    }

    private function hydrate(object $row): Opportunity
    {
        return new Opportunity(
            $row->id,
            (int) $row->customer_id,
            $row->name,
            $row->stage,
            (float) $row->estimated_value,
            (int) $row->owner_id,
            isset($row->lead_id) && $row->lead_id !== null ? (int) $row->lead_id : null,
            $row->currency,
            !empty($row->expected_close_date) ? new \DateTimeImmutable($row->expected_close_date) : null,
            !empty($row->qualification_json) ? (json_decode($row->qualification_json, true) ?: []) : [],
            $row->notes ?? null,
            new \DateTimeImmutable($row->created_at),
            !empty($row->updated_at) ? new \DateTimeImmutable($row->updated_at) : null,
            !empty($row->closed_at) ? new \DateTimeImmutable($row->closed_at) : null,
            isset($row->quote_id) && $row->quote_id !== null ? (int) $row->quote_id : null
        );
    }
}
