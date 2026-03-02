<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Repository\RoleRepository;

class PublishRoleHandler
{
    private TransactionManager $transactionManager;
    private $roleRepository;

    public function __construct(TransactionManager $transactionManager, RoleRepository $roleRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->roleRepository = $roleRepository;
    }

    public function handle(PublishRoleCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $role = $this->roleRepository->findById($command->roleId());

        if (!$role) {
            throw new \InvalidArgumentException('Role not found.');
        }

        $role->publish();
        $this->roleRepository->save($role);
    
        });
    }
}
