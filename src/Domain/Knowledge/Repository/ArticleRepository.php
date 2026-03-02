<?php

declare(strict_types=1);

namespace Pet\Domain\Knowledge\Repository;

use Pet\Domain\Knowledge\Entity\Article;

interface ArticleRepository
{
    public function save(Article $article): void;
    public function findById(int $id): ?Article;
    public function findAll(): array;
    public function findByCategory(string $category): array;
}
