<?php

declare(strict_types=1);

namespace Pet\Application\Knowledge\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Knowledge\Repository\ArticleRepository;

class ArchiveArticleHandler
{
    private TransactionManager $transactionManager;
    private ArticleRepository $articleRepository;

    public function __construct(TransactionManager $transactionManager, ArticleRepository $articleRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->articleRepository = $articleRepository;
    }

    public function handle(ArchiveArticleCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $article = $this->articleRepository->findById($command->id());

        if (!$article) {
            throw new \RuntimeException('Article not found');
        }

        $article->archive();

        $this->articleRepository->save($article);
    
        });
    }
}
