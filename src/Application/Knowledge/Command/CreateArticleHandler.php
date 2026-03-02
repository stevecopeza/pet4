<?php

declare(strict_types=1);

namespace Pet\Application\Knowledge\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Knowledge\Entity\Article;
use Pet\Domain\Knowledge\Repository\ArticleRepository;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use InvalidArgumentException;

class CreateArticleHandler
{
    private TransactionManager $transactionManager;
    private ArticleRepository $articleRepository;
    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(TransactionManager $transactionManager, 
        ArticleRepository $articleRepository,
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->transactionManager = $transactionManager;
        $this->articleRepository = $articleRepository;
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function handle(CreateArticleCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $activeSchema = $this->schemaRepository->findActiveByEntityType('article');
        $malleableData = $command->malleableData();
        $schemaVersion = null;

        if ($activeSchema) {
            $schemaVersion = $activeSchema->version();
            $errors = $this->schemaValidator->validateData($malleableData, $activeSchema->schema());
            
            if (!empty($errors)) {
                throw new InvalidArgumentException('Invalid malleable data: ' . implode(', ', $errors));
            }
        }

        $article = new Article(
            $command->title(),
            $command->content(),
            $command->category(),
            $command->status(),
            null,
            $schemaVersion,
            $malleableData
        );

        $this->articleRepository->save($article);
    
        });
    }
}
