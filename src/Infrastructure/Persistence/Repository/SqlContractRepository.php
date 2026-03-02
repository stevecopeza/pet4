<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Contract;
use Pet\Domain\Commercial\Repository\ContractRepository;
use Pet\Domain\Commercial\ValueObject\ContractStatus;

class SqlContractRepository implements ContractRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_contracts';
    }

    public function save(Contract $contract): void
    {
        $data = [
            'quote_id' => $contract->quoteId(),
            'customer_id' => $contract->customerId(),
            'status' => $contract->status()->toString(),
            'total_value' => $contract->totalValue(),
            'currency' => $contract->currency(),
            'start_date' => $this->formatDate($contract->startDate()),
            'end_date' => $this->formatDate($contract->endDate()),
            'created_at' => $this->formatDate($contract->createdAt()),
            'updated_at' => $this->formatDate($contract->updatedAt()),
        ];

        $format = ['%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s'];

        if ($contract->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $contract->id()],
                $format,
                ['%d']
            );
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            // Reflect the ID back into the entity?
            // Since entities are immutable in our design usually, we rely on reloading or assume the caller handles it.
            // But for now, we can't easily mutate the private ID of the object.
            // In a stricter implementation, save() would return a new instance or void.
            // For now, let's just insert. The Listener logic assumed it could get ID, which is tricky with immutable objects without reflection or return.
            // However, the Listener logic I wrote earlier: `if ($contract->id())` will fail for new contracts because I can't set the ID on the object here.
            
            // To fix this, I should use reflection to set the ID on the instance, or return the ID.
            // Given the design patterns used elsewhere (e.g. SqlQuoteRepository doesn't seem to reflect ID back explicitly in the snippet I saw, wait...
            // Let's check SqlQuoteRepository again. It gets insert_id but doesn't seem to set it back on $quote object?
            // Actually, SqlQuoteRepository does `$quoteId = $this->wpdb->insert_id;` then uses it for components.
            // But the caller holding `$quote` reference won't see the ID.
            // This is a common issue. I will use Reflection to set the ID property to support the flow.
            
            $id = $this->wpdb->insert_id;
            $this->setId($contract, (int)$id);
        }
    }

    public function findById(int $id): ?Contract
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByQuoteId(int $quoteId): ?Contract
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE quote_id = %d LIMIT 1",
            $quoteId
        );
        $row = $this->wpdb->get_row($sql);

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(object $row): Contract
    {
        return new Contract(
            (int)$row->quote_id,
            (int)$row->customer_id,
            ContractStatus::fromString($row->status),
            (float)$row->total_value,
            $row->currency,
            new \DateTimeImmutable($row->start_date),
            (int)$row->id,
            $row->end_date ? new \DateTimeImmutable($row->end_date) : null,
            new \DateTimeImmutable($row->created_at),
            $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null
        );
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }

    private function setId(Contract $contract, int $id): void
    {
        $reflection = new \ReflectionClass($contract);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($contract, $id);
    }
}
