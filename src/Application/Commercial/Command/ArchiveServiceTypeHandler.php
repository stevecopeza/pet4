<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

use Pet\Application\System\Service\TransactionManager;
use Pet\Domain\Commercial\Repository\ServiceTypeRepository;
use Pet\Domain\Commercial\Repository\RateCardRepository;

class ArchiveServiceTypeHandler
{
    private TransactionManager $transactionManager;
    private ServiceTypeRepository $serviceTypeRepository;
    private RateCardRepository $rateCardRepository;

    public function __construct(
        TransactionManager $transactionManager,
        ServiceTypeRepository $serviceTypeRepository,
        RateCardRepository $rateCardRepository
    ) {
        $this->transactionManager = $transactionManager;
        $this->serviceTypeRepository = $serviceTypeRepository;
        $this->rateCardRepository = $rateCardRepository;
    }

    public function handle(ArchiveServiceTypeCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            $entity = $this->serviceTypeRepository->findById($command->id());
            if (!$entity) {
                throw new \DomainException("ServiceType not found: {$command->id()}");
            }

            // Check for active rate cards referencing this service type
            $activeCards = $this->rateCardRepository->findAll([
                'service_type_id' => $command->id(),
                'status' => 'active',
            ]);
            if (!empty($activeCards)) {
                throw new \DomainException(
                    "Cannot archive ServiceType [{$command->id()}]: " . count($activeCards) . ' active rate card(s) reference it.'
                );
            }

            $entity->archive();
            $this->serviceTypeRepository->save($entity);
        });
    }
}
