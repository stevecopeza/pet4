<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Repository\RoleRepository;

class UpdateRoleHandler
{
    private TransactionManager $transactionManager;
    private $roleRepository;

    public function __construct(TransactionManager $transactionManager, RoleRepository $roleRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->roleRepository = $roleRepository;
    }

    public function handle(UpdateRoleCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $role = $this->roleRepository->findById($command->id());

        if (!$role) {
            throw new \RuntimeException('Role not found');
        }

        if ($role->status() !== 'draft') {
            throw new \RuntimeException('Only draft roles can be edited');
        }

        $role->update(
            $command->name(),
            $command->level(),
            $command->description(),
            $command->successCriteria(),
            $command->requiredSkills()
        );

        $this->roleRepository->save($role);
    
        });
    }
}
