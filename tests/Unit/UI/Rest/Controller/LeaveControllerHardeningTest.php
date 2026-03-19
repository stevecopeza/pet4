<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

use Pet\Application\Work\Command\DecideLeaveRequestHandler;
use Pet\Application\Work\Command\SetCapacityOverrideHandler;
use Pet\Application\Work\Command\SubmitLeaveRequestHandler;
use Pet\Domain\Work\Entity\LeaveRequest;
use Pet\Domain\Work\Entity\LeaveType;
use Pet\Domain\Work\Repository\CapacityOverrideRepository;
use Pet\Domain\Work\Repository\LeaveRequestRepository;
use Pet\Domain\Work\Repository\LeaveTypeRepository;
use Pet\Tests\Stub\FakeTransactionManager;
use Pet\UI\Rest\Controller\LeaveController;
use PHPUnit\Framework\TestCase;

final class LeaveControllerHardeningTest extends TestCase
{
    public function testSubmitReturns422ForDomainException(): void
    {
        $repo = new class implements LeaveRequestRepository {
            public function save(LeaveRequest $req): void { throw new \DomainException('submit invalid'); }
            public function findById(int $id): ?LeaveRequest { return null; }
            public function findByEmployee(int $employeeId, int $limit = 50): array { return []; }
            public function setStatus(int $id, string $status, ?int $decidedByEmployeeId = null, ?\DateTimeImmutable $decidedAt = null, ?string $reason = null): void {}
            public function isApprovedOnDate(int $employeeId, \DateTimeImmutable $date): bool { return false; }
        };
        $types = new class implements LeaveTypeRepository {
            public function findAll(): array { return []; }
            public function findById(int $id): ?LeaveType { return new LeaveType(1, 'Annual', true); }
        };

        $controller = $this->makeController($repo, $types);
        $request = new \WP_REST_Request('POST', '/pet/v1/leave/requests');
        $request->set_param('employeeId', 5);
        $request->set_param('leaveTypeId', 1);
        $request->set_param('startDate', '2026-03-10');
        $request->set_param('endDate', '2026-03-11');

        $response = $controller->submit($request);
        self::assertSame(422, $response->get_status());
        $data = $response->get_data();
        self::assertSame('DOMAIN_ERROR', $data['error']['code'] ?? null);
    }

    public function testSubmitReturns500ForUnexpectedThrowable(): void
    {
        $repo = new class implements LeaveRequestRepository {
            public function save(LeaveRequest $req): void { throw new \RuntimeException('db down'); }
            public function findById(int $id): ?LeaveRequest { return null; }
            public function findByEmployee(int $employeeId, int $limit = 50): array { return []; }
            public function setStatus(int $id, string $status, ?int $decidedByEmployeeId = null, ?\DateTimeImmutable $decidedAt = null, ?string $reason = null): void {}
            public function isApprovedOnDate(int $employeeId, \DateTimeImmutable $date): bool { return false; }
        };
        $types = new class implements LeaveTypeRepository {
            public function findAll(): array { return []; }
            public function findById(int $id): ?LeaveType { return new LeaveType(1, 'Annual', true); }
        };

        $controller = $this->makeController($repo, $types);
        $request = new \WP_REST_Request('POST', '/pet/v1/leave/requests');
        $request->set_param('employeeId', 5);
        $request->set_param('leaveTypeId', 1);
        $request->set_param('startDate', '2026-03-10');
        $request->set_param('endDate', '2026-03-11');

        $response = $controller->submit($request);
        self::assertSame(500, $response->get_status());
        $data = $response->get_data();
        self::assertSame('INTERNAL_ERROR', $data['error']['code'] ?? null);
    }

    public function testDecideReturns422ForDomainException(): void
    {
        $repo = new class implements LeaveRequestRepository {
            public function save(LeaveRequest $req): void {}
            public function findById(int $id): ?LeaveRequest { return null; }
            public function findByEmployee(int $employeeId, int $limit = 50): array { return []; }
            public function setStatus(int $id, string $status, ?int $decidedByEmployeeId = null, ?\DateTimeImmutable $decidedAt = null, ?string $reason = null): void {}
            public function isApprovedOnDate(int $employeeId, \DateTimeImmutable $date): bool { return false; }
        };

        $controller = $this->makeController($repo);
        $request = $this->makeDecideRequest();

        $response = $controller->decide($request);
        self::assertSame(422, $response->get_status());
        $data = $response->get_data();
        self::assertSame('DOMAIN_ERROR', $data['error']['code'] ?? null);
    }

    public function testDecideReturns500ForUnexpectedThrowable(): void
    {
        $req = LeaveRequest::draft(
            'leave-uuid-1',
            1,
            1,
            new \DateTimeImmutable('2026-03-01'),
            new \DateTimeImmutable('2026-03-02'),
            null
        );
        $req->setId(10);
        $req->setStatus('submitted');

        $repo = new class($req) implements LeaveRequestRepository {
            public function __construct(private LeaveRequest $request)
            {
            }

            public function save(LeaveRequest $req): void {}
            public function findById(int $id): ?LeaveRequest { return $this->request; }
            public function findByEmployee(int $employeeId, int $limit = 50): array { return []; }
            public function setStatus(int $id, string $status, ?int $decidedByEmployeeId = null, ?\DateTimeImmutable $decidedAt = null, ?string $reason = null): void
            {
                throw new \RuntimeException('db write failure');
            }
            public function isApprovedOnDate(int $employeeId, \DateTimeImmutable $date): bool { return false; }
        };

        $controller = $this->makeController($repo);
        $request = $this->makeDecideRequest();

        $response = $controller->decide($request);
        self::assertSame(500, $response->get_status());
        $data = $response->get_data();
        self::assertSame('INTERNAL_ERROR', $data['error']['code'] ?? null);
    }

    public function testSetOverrideReturns422ForDomainException(): void
    {
        $requests = new class implements LeaveRequestRepository {
            public function save(LeaveRequest $req): void {}
            public function findById(int $id): ?LeaveRequest { return null; }
            public function findByEmployee(int $employeeId, int $limit = 50): array { return []; }
            public function setStatus(int $id, string $status, ?int $decidedByEmployeeId = null, ?\DateTimeImmutable $decidedAt = null, ?string $reason = null): void {}
            public function isApprovedOnDate(int $employeeId, \DateTimeImmutable $date): bool { return false; }
        };
        $types = new class implements LeaveTypeRepository {
            public function findAll(): array { return []; }
            public function findById(int $id): ?LeaveType { return null; }
        };
        $overrides = new class implements CapacityOverrideRepository {
            public function setOverride(int $employeeId, \DateTimeImmutable $date, int $capacityPct, ?string $reason): void
            {
                throw new \DomainException('capacity invalid');
            }
            public function findForDate(int $employeeId, \DateTimeImmutable $date): ?\Pet\Domain\Work\Entity\CapacityOverride { return null; }
        };

        $controller = $this->makeController($requests, $types, $overrides);
        $request = new \WP_REST_Request('POST', '/pet/v1/leave/capacity-override');
        $request->set_param('employeeId', 7);
        $request->set_param('date', '2026-03-20');
        $request->set_param('capacityPct', 75);

        $response = $controller->setOverride($request);
        self::assertSame(422, $response->get_status());
        $data = $response->get_data();
        self::assertSame('DOMAIN_ERROR', $data['error']['code'] ?? null);
    }

    public function testSetOverrideReturns500ForUnexpectedThrowable(): void
    {
        $requests = new class implements LeaveRequestRepository {
            public function save(LeaveRequest $req): void {}
            public function findById(int $id): ?LeaveRequest { return null; }
            public function findByEmployee(int $employeeId, int $limit = 50): array { return []; }
            public function setStatus(int $id, string $status, ?int $decidedByEmployeeId = null, ?\DateTimeImmutable $decidedAt = null, ?string $reason = null): void {}
            public function isApprovedOnDate(int $employeeId, \DateTimeImmutable $date): bool { return false; }
        };
        $types = new class implements LeaveTypeRepository {
            public function findAll(): array { return []; }
            public function findById(int $id): ?LeaveType { return null; }
        };
        $overrides = new class implements CapacityOverrideRepository {
            public function setOverride(int $employeeId, \DateTimeImmutable $date, int $capacityPct, ?string $reason): void
            {
                throw new \RuntimeException('capacity store failure');
            }
            public function findForDate(int $employeeId, \DateTimeImmutable $date): ?\Pet\Domain\Work\Entity\CapacityOverride { return null; }
        };

        $controller = $this->makeController($requests, $types, $overrides);
        $request = new \WP_REST_Request('POST', '/pet/v1/leave/capacity-override');
        $request->set_param('employeeId', 7);
        $request->set_param('date', '2026-03-20');
        $request->set_param('capacityPct', 75);

        $response = $controller->setOverride($request);
        self::assertSame(500, $response->get_status());
        $data = $response->get_data();
        self::assertSame('INTERNAL_ERROR', $data['error']['code'] ?? null);
    }

    private function makeController(
        LeaveRequestRepository $requests,
        ?LeaveTypeRepository $types = null,
        ?CapacityOverrideRepository $overrides = null
    ): LeaveController
    {
        $types = $types ?? new class implements LeaveTypeRepository {
            public function findAll(): array { return []; }
            public function findById(int $id): ?\Pet\Domain\Work\Entity\LeaveType { return null; }
        };

        $submitHandler = new SubmitLeaveRequestHandler(new FakeTransactionManager(), $requests);
        $decideHandler = new DecideLeaveRequestHandler(new FakeTransactionManager(), $requests);
        $overrides = $overrides ?? new class implements CapacityOverrideRepository {
            public function setOverride(int $employeeId, \DateTimeImmutable $date, int $capacityPct, ?string $reason): void {}
            public function findForDate(int $employeeId, \DateTimeImmutable $date): ?\Pet\Domain\Work\Entity\CapacityOverride { return null; }
        };
        $overrideHandler = new SetCapacityOverrideHandler(new FakeTransactionManager(), $overrides);

        return new LeaveController($types, $requests, $submitHandler, $decideHandler, $overrideHandler);
    }

    private function makeDecideRequest(): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/pet/v1/leave/requests/10/decide');
        $request->set_param('id', 10);
        $request->set_param('decidedByEmployeeId', 77);
        $request->set_param('decision', 'approved');
        return $request;
    }
}
