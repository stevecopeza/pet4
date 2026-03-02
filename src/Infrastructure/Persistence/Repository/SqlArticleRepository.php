<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Knowledge\Entity\Article;
use Pet\Domain\Knowledge\Repository\ArticleRepository;

class SqlArticleRepository implements ArticleRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(Article $article): void
    {
        $table = $this->wpdb->prefix . 'pet_articles';
        
        $data = [
            'title' => $article->title(),
            'content' => $article->content(),
            'category' => $article->category(),
            'status' => $article->status(),
            'malleable_schema_version' => $article->malleableSchemaVersion(),
            'malleable_data' => !empty($article->malleableData()) ? json_encode($article->malleableData()) : null,
            'created_at' => $article->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $article->updatedAt() ? $article->updatedAt()->format('Y-m-d H:i:s') : null,
        ];

        $formats = ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];

        if ($article->id()) {
            $this->wpdb->update($table, $data, ['id' => $article->id()], $formats, ['%d']);
        } else {
            $this->wpdb->insert($table, $data, $formats);
        }
    }

    public function findById(int $id): ?Article
    {
        $table = $this->wpdb->prefix . 'pet_articles';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_articles';
        $rows = $this->wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByCategory(string $category): array
    {
        $table = $this->wpdb->prefix . 'pet_articles';
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $table WHERE category = %s ORDER BY created_at DESC", $category));

        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate($row): Article
    {
        return new Article(
            $row->title,
            $row->content,
            $row->category,
            $row->status,
            (int) $row->id,
            isset($row->malleable_schema_version) ? (int) $row->malleable_schema_version : null,
            isset($row->malleable_data) ? (json_decode($row->malleable_data, true) ?: []) : [],
            new \DateTimeImmutable($row->created_at),
            $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null
        );
    }
}
