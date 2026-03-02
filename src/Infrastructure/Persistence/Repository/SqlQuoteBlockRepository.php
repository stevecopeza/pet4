<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\Block\QuoteBlock;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;

class SqlQuoteBlockRepository implements QuoteBlockRepository
{
    private \wpdb $wpdb;
    private string $table;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'pet_quote_blocks';
    }

    public function findByQuoteId(int $quoteId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE quote_id = %d ORDER BY section_id IS NULL, section_id, order_index",
            $quoteId
        );

        $rows = $this->wpdb->get_results($sql);

        return array_map(function ($row) {
            $payload = [];
            if (isset($row->payload_json) && $row->payload_json !== null && $row->payload_json !== '') {
                $decoded = json_decode((string) $row->payload_json, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            return new QuoteBlock(
                (int) $row->order_index,
                (string) $row->type,
                $row->component_id !== null ? (int) $row->component_id : null,
                0.0,
                0.0,
                (bool) $row->priced,
                $row->section_id !== null ? (int) $row->section_id : null,
                $payload,
                (int) $row->id
            );
        }, $rows);
    }

    public function insert(QuoteBlock $block, int $quoteId): QuoteBlock
    {
        $this->wpdb->insert(
            $this->table,
            [
                'quote_id' => $quoteId,
                'component_id' => $block->componentId(),
                'section_id' => $block->sectionId(),
                'type' => $block->type(),
                'order_index' => $block->position(),
                'priced' => $block->isPriced() ? 1 : 0,
                'payload_json' => json_encode($block->payload()),
            ],
            ['%d', '%d', '%d', '%s', '%d', '%d', '%s']
        );

        if (!empty($this->wpdb->last_error)) {
            throw new \DomainException('Failed to insert quote block: ' . $this->wpdb->last_error);
        }

        $id = (int) $this->wpdb->insert_id;

        return new QuoteBlock(
            $block->position(),
            $block->type(),
            $block->componentId(),
            $block->sellValue(),
            $block->internalCost(),
            $block->isPriced(),
            $block->sectionId(),
            $block->payload(),
            $id
        );
    }

    public function update(QuoteBlock $block, int $quoteId): void
    {
        $this->wpdb->update(
            $this->table,
            [
                'component_id' => $block->componentId(),
                'section_id' => $block->sectionId(),
                'type' => $block->type(),
                'order_index' => $block->position(),
                'priced' => $block->isPriced() ? 1 : 0,
                'payload_json' => json_encode($block->payload()),
            ],
            [
                'id' => $block->id(),
                'quote_id' => $quoteId,
            ],
            ['%d', '%d', '%s', '%d', '%d', '%s'],
            ['%d', '%d']
        );

        if (!empty($this->wpdb->last_error)) {
            throw new \DomainException('Failed to update quote block: ' . $this->wpdb->last_error);
        }
    }

    public function delete(int $blockId): void
    {
        $this->wpdb->delete(
            $this->table,
            ['id' => $blockId],
            ['%d']
        );
    }

    public function reorder(int $quoteId, array $changes): void
    {
        foreach ($changes as $change) {
            $id = (int) $change['id'];
            $orderIndex = (int) $change['orderIndex'];
            $sectionId = array_key_exists('sectionId', $change) && $change['sectionId'] !== null
                ? (int) $change['sectionId']
                : null;

            $this->wpdb->update(
                $this->table,
                [
                    'order_index' => $orderIndex,
                    'section_id' => $sectionId,
                ],
                [
                    'id' => $id,
                    'quote_id' => $quoteId,
                ],
                ['%d', '%d'],
                ['%d', '%d']
            );
        }
    }

    public function move(int $blockId, ?int $sectionId, int $orderIndex): void
    {
        $this->wpdb->update(
            $this->table,
            [
                'section_id' => $sectionId,
                'order_index' => $orderIndex,
            ],
            ['id' => $blockId],
            ['%d', '%d'],
            ['%d']
        );
    }
}

