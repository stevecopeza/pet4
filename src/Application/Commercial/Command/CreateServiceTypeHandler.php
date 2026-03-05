<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Entity\ServiceType;
use Pet\Domain\Commercial\Repository\ServiceTypeRepository;

class CreateServiceTypeHandler
{
    private TransactionManager $transactionManager;
    private ServiceTypeRepository $repository;

    public function __construct(TransactionManager $transactionManager, ServiceTypeRepository $repository)
    {
        $this->transactionManager = $transactionManager;
        $this->repository = $repository;
    }

    public function handle(CreateServiceTypeCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command) {
            $entity = new ServiceType($command->name(), $command->description());
            $this->repository->save($entity);

            $id = $entity->id();
            if ($id !== null && $id > 0) {
                return $id;
            }

            global $wpdb;
            if ($wpdb instanceof \wpdb && isset($wpdb->insert_id) && (int)$wpdb->insert_id > 0) {
                return (int)$wpdb->insert_id;
            }

            throw new \RuntimeException('Failed to persist ServiceType and obtain identifier.');
        });
    }
}
