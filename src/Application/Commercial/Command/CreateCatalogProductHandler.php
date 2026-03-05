<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Entity\CatalogProduct;
use Pet\Domain\Commercial\Repository\CatalogProductRepository;

class CreateCatalogProductHandler
{
    private TransactionManager $transactionManager;
    private CatalogProductRepository $repository;

    public function __construct(TransactionManager $transactionManager, CatalogProductRepository $repository)
    {
        $this->transactionManager = $transactionManager;
        $this->repository = $repository;
    }

    public function handle(CreateCatalogProductCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command) {
            $entity = new CatalogProduct(
                $command->sku(),
                $command->name(),
                $command->unitPrice(),
                $command->unitCost(),
                $command->description(),
                $command->category()
            );
            $this->repository->save($entity);

            $id = $entity->id();
            if ($id !== null && $id > 0) {
                return $id;
            }

            global $wpdb;
            if ($wpdb instanceof \wpdb && isset($wpdb->insert_id) && (int)$wpdb->insert_id > 0) {
                return (int)$wpdb->insert_id;
            }

            throw new \RuntimeException('Failed to persist CatalogProduct and obtain identifier.');
        });
    }
}
