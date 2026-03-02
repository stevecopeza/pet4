<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Repository\LeaveRequestRepository;
use DateTimeImmutable;

final class DecideLeaveRequestHandler
{
    private TransactionManager $transactionManager;
    public function __construct(TransactionManager $transactionManager, private LeaveRequestRepository $repo)
    {
        $this->transactionManager = $transactionManager;
    }

    public function handle(DecideLeaveRequestCommand $c): void
    {
        $this->transactionManager->transactional(function () use ($c) {
        $req = $this->repo->findById($c->requestId());
        if (!$req) {
            throw new \DomainException('Leave request not found');
        }
        $decision = $c->decision();
        $allowed = ['approved', 'rejected', 'cancelled'];
        if (!in_array($decision, $allowed, true)) {
            throw new \DomainException('Invalid decision');
        }
        if ($req->status() === 'approved' && $decision !== 'cancelled') {
            throw new \DomainException('Approved requests can only be cancelled');
        }
        if ($req->status() === 'rejected' && $decision !== 'cancelled') {
            throw new \DomainException('Rejected requests can only be cancelled');
        }
        $this->repo->setStatus(
            $req->id(),
            $decision,
            $c->decidedByEmployeeId(),
            new DateTimeImmutable(),
            $c->reason()
        );
    
        });
    }
}

