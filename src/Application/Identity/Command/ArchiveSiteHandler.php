<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Identity\Repository\SiteRepository;

class ArchiveSiteHandler
{
    private TransactionManager $transactionManager;
    private SiteRepository $siteRepository;

    public function __construct(TransactionManager $transactionManager, SiteRepository $siteRepository)
    {
        $this->transactionManager = $transactionManager;
        $this->siteRepository = $siteRepository;
    }

    public function handle(ArchiveSiteCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
        $site = $this->siteRepository->findById($command->id());
        if (!$site) {
            throw new \RuntimeException("Site not found");
        }

        $this->siteRepository->delete($site->id());
    
        });
    }
}
