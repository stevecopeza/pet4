<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;

class SqlQuoteSectionRepository implements QuoteSectionRepository
{
    private $wpdb;
    private string $sectionsTable;
    private string $blocksTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->sectionsTable = $wpdb->prefix . 'pet_quote_sections';
        $this->blocksTable = $wpdb->prefix . 'pet_quote_blocks';
    }

    public function findByQuoteId(int $quoteId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->sectionsTable} WHERE quote_id = %d ORDER BY order_index ASC",
            $quoteId
        );
        $rows = $this->wpdb->get_results($sql);

        return array_map([$this, 'hydrateSection'], $rows);
    }

    public function save(QuoteSection $section): QuoteSection
    {
        if ($section->id() === null) {
            $this->wpdb->insert(
                $this->sectionsTable,
                [
                    'quote_id' => $section->quoteId(),
                    'name' => $section->name(),
                    'order_index' => $section->orderIndex(),
                    'show_total_value' => $section->showTotalValue() ? 1 : 0,
                    'show_item_count' => $section->showItemCount() ? 1 : 0,
                    'show_total_hours' => $section->showTotalHours() ? 1 : 0,
                ],
                ['%d', '%s', '%d', '%d', '%d', '%d']
            );
            if (!empty($this->wpdb->last_error)) {
                throw new \DomainException('Failed to insert quote section: ' . $this->wpdb->last_error);
            }
            $id = (int) $this->wpdb->insert_id;
            if ($id <= 0) {
                throw new \DomainException('Failed to insert quote section: missing insert ID.');
            }

            return new QuoteSection(
                $section->quoteId(),
                $section->name(),
                $section->orderIndex(),
                $section->showTotalValue(),
                $section->showItemCount(),
                $section->showTotalHours(),
                $id
            );
        }

        $this->wpdb->update(
            $this->sectionsTable,
            [
                'name' => $section->name(),
                'order_index' => $section->orderIndex(),
                'show_total_value' => $section->showTotalValue() ? 1 : 0,
                'show_item_count' => $section->showItemCount() ? 1 : 0,
                'show_total_hours' => $section->showTotalHours() ? 1 : 0,
            ],
            ['id' => $section->id()],
            ['%s', '%d', '%d', '%d', '%d'],
            ['%d']
        );
        if (!empty($this->wpdb->last_error)) {
            throw new \DomainException('Failed to update quote section: ' . $this->wpdb->last_error);
        }

        return $section;
    }

    public function delete(int $sectionId): void
    {
        $countSql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->blocksTable} WHERE section_id = %d",
            $sectionId
        );
        $count = (int) $this->wpdb->get_var($countSql);

        if ($count > 0) {
            throw new \DomainException('Cannot delete section that still contains blocks.');
        }

        $this->wpdb->delete(
            $this->sectionsTable,
            ['id' => $sectionId],
            ['%d']
        );
    }

    public function saveOrdering(int $quoteId, array $sections): void
    {
        foreach ($sections as $section) {
            if (!$section instanceof QuoteSection) {
                continue;
            }

            $this->wpdb->update(
                $this->sectionsTable,
                ['order_index' => $section->orderIndex()],
                [
                    'id' => $section->id(),
                    'quote_id' => $quoteId,
                ],
                ['%d'],
                ['%d', '%d']
            );
        }
    }

    private function hydrateSection($row): QuoteSection
    {
        return new QuoteSection(
            (int) $row->quote_id,
            (string) $row->name,
            (int) $row->order_index,
            (bool) $row->show_total_value,
            (bool) $row->show_item_count,
            (bool) $row->show_total_hours,
            (int) $row->id
        );
    }
}
