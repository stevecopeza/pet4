<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Application\System\Service\TransactionManager;

use Pet\Domain\Work\Entity\LeaveRequest;
use Pet\Domain\Work\Repository\LeaveRequestRepository;

final class SubmitLeaveRequestHandler
{
    private TransactionManager $transactionManager;
    public function __construct(TransactionManager $transactionManager, private LeaveRequestRepository $repo)
    {
        $this->transactionManager = $transactionManager;
    }

    public function handle(SubmitLeaveRequestCommand $c): int
    {
        return $this->transactionManager->transactional(function () use ($c) {
        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16));
        $req = LeaveRequest::draft(
            $uuid,
            $c->employeeId(),
            $c->leaveTypeId(),
            $c->startDate(),
            $c->endDate(),
            $c->notes()
        );
        $req->setStatus('submitted');
        $this->repo->save($req);
        return $req->id();
    
        });
    }
}

