<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Entity\RateCard;
use Pet\Domain\Commercial\Repository\RateCardRepository;

class CreateRateCardHandler
{
    private TransactionManager $transactionManager;
    private RateCardRepository $repository;

    public function __construct(TransactionManager $transactionManager, RateCardRepository $repository)
    {
        $this->transactionManager = $transactionManager;
        $this->repository = $repository;
    }

    public function handle(CreateRateCardCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command) {
            // Overlap validation under transaction
            $overlapping = $this->repository->findOverlapping(
                $command->roleId(),
                $command->serviceTypeId(),
                $command->contractId(),
                $command->validFrom(),
                $command->validTo()
            );

            if (!empty($overlapping)) {
                $ids = array_map(fn(RateCard $rc) => $rc->id(), $overlapping);
                throw new \DomainException(sprintf(
                    'Overlapping rate card(s) exist for role [%d] + serviceType [%d]%s: IDs [%s]',
                    $command->roleId(),
                    $command->serviceTypeId(),
                    $command->contractId() !== null ? " contract [{$command->contractId()}]" : ' (global)',
                    implode(', ', $ids)
                ));
            }

            $entity = new RateCard(
                $command->roleId(),
                $command->serviceTypeId(),
                $command->sellRate(),
                $command->contractId(),
                $command->validFrom(),
                $command->validTo()
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

            throw new \RuntimeException('Failed to persist RateCard and obtain identifier.');
        });
    }
}
