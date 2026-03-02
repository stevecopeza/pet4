<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Repository\CustomerRepository;

class UpdateCustomerHandler
{
    private TransactionManager $transactionManager;
    private CustomerRepository $customerRepository;

    public function __construct(TransactionManager $transactionManager, CustomerRepository $customerRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->customerRepository = $customerRepository;
    }

    public function handle(UpdateCustomerCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $customer = $this->customerRepository->findById($command->id());

        if (!$customer) {
            throw new \RuntimeException('Customer not found');
        }

        $customer->update(
            $command->name(),
            $command->contactEmail(),
            $command->legalName(),
            $command->status(),
            $command->malleableData()
        );

        $this->customerRepository->save($customer);
    
        });
    }
}
