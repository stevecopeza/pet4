<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use Pet\Application\Commercial\Command\ApproveQuoteCommand;
use Pet\Application\Commercial\Command\ApproveQuoteHandler;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Entity\Team;
use Pet\Domain\Team\Repository\TeamRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ApproveQuoteHandler.
 *
 * Critical invariants under test (Sprint 47):
 * - Quote creator (by WP user ID) can self-approve their own quote.
 * - Team manager / escalation manager can approve any quote.
 * - Neither creator nor manager → DomainException.
 * - Approver without an employee record → DomainException.
 * - Legacy quotes (createdByUserId = null) fall back to manager-only approval.
 */
final class ApproveQuoteHandlerTest extends TestCase
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeQuote(QuoteState $state, ?int $createdByUserId): Quote
    {
        return new Quote(
            customerId: 1,
            title: 'Test Quote',
            description: null,
            state: $state,
            createdByUserId: $createdByUserId
        );
    }

    private function makeEmployee(int $id): Employee
    {
        $employee = $this->createMock(Employee::class);
        $employee->method('id')->willReturn($id);
        return $employee;
    }

    private function makeTeam(?int $managerId, ?int $escalationManagerId = null): Team
    {
        $team = $this->createMock(Team::class);
        $team->method('managerId')->willReturn($managerId);
        $team->method('escalationManagerId')->willReturn($escalationManagerId);
        return $team;
    }

    private function makeHandler(
        Quote $quote,
        Employee $approverEmployee,
        array $teams
    ): ApproveQuoteHandler {
        $quoteRepo = $this->createMock(QuoteRepository::class);
        $quoteRepo->method('findById')->willReturn($quote);
        $quoteRepo->expects($this->once())->method('save');

        $employeeRepo = $this->createMock(EmployeeRepository::class);
        $employeeRepo->method('findByWpUserId')->willReturn($approverEmployee);

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findAll')->willReturn($teams);

        return new ApproveQuoteHandler($quoteRepo, $teamRepo, $employeeRepo);
    }

    private function makeHandlerNoSave(
        Quote $quote,
        ?Employee $approverEmployee,
        array $teams
    ): ApproveQuoteHandler {
        $quoteRepo = $this->createMock(QuoteRepository::class);
        $quoteRepo->method('findById')->willReturn($quote);
        $quoteRepo->expects($this->never())->method('save');

        $employeeRepo = $this->createMock(EmployeeRepository::class);
        $employeeRepo->method('findByWpUserId')->willReturn($approverEmployee);

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findAll')->willReturn($teams);

        return new ApproveQuoteHandler($quoteRepo, $teamRepo, $employeeRepo);
    }

    // ── Self-approval ─────────────────────────────────────────────────────────

    public function testCreatorCanSelfApprove(): void
    {
        // WP user 10 created the quote; employee record 55 belongs to WP user 10.
        // No team has employee 55 as manager — approval must succeed via creator path.
        $quote    = $this->makeQuote(QuoteState::pendingApproval(), createdByUserId: 10);
        $employee = $this->makeEmployee(55);
        $team     = $this->makeTeam(managerId: 99); // manager is employee 99, not 55

        $handler = $this->makeHandler($quote, $employee, [$team]);
        $handler->handle(new ApproveQuoteCommand(quoteId: 1, approverUserId: 10));

        // If we reach here, no exception was thrown: self-approval succeeded.
        $this->addToAssertionCount(1);
    }

    public function testCreatorApprovalSetsApprovedByUserId(): void
    {
        $quote    = $this->makeQuote(QuoteState::pendingApproval(), createdByUserId: 10);
        $employee = $this->makeEmployee(55);

        $quoteRepo = $this->createMock(QuoteRepository::class);
        $quoteRepo->method('findById')->willReturn($quote);
        $quoteRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Quote $saved): bool {
                // After approve(), the quote must have the approver employee ID recorded.
                return $saved->approvedByUserId() === 55;
            }));

        $employeeRepo = $this->createMock(EmployeeRepository::class);
        $employeeRepo->method('findByWpUserId')->with(10)->willReturn($employee);

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findAll')->willReturn([]);

        $handler = new ApproveQuoteHandler($quoteRepo, $teamRepo, $employeeRepo);
        $handler->handle(new ApproveQuoteCommand(quoteId: 1, approverUserId: 10));
    }

    // ── Manager approval ──────────────────────────────────────────────────────

    public function testManagerCanApproveAnyQuote(): void
    {
        // Quote was created by WP user 7; approver is WP user 20 (employee 99).
        // Employee 99 is manager of a team → approval allowed.
        $quote    = $this->makeQuote(QuoteState::pendingApproval(), createdByUserId: 7);
        $employee = $this->makeEmployee(99);
        $team     = $this->makeTeam(managerId: 99);

        $handler = $this->makeHandler($quote, $employee, [$team]);
        $handler->handle(new ApproveQuoteCommand(quoteId: 1, approverUserId: 20));

        $this->addToAssertionCount(1);
    }

    public function testEscalationManagerCanApprove(): void
    {
        $quote    = $this->makeQuote(QuoteState::pendingApproval(), createdByUserId: 7);
        $employee = $this->makeEmployee(88);
        $team     = $this->makeTeam(managerId: 99, escalationManagerId: 88);

        $handler = $this->makeHandler($quote, $employee, [$team]);
        $handler->handle(new ApproveQuoteCommand(quoteId: 1, approverUserId: 20));

        $this->addToAssertionCount(1);
    }

    // ── Blocked paths ─────────────────────────────────────────────────────────

    public function testNonCreatorNonManagerCannotApprove(): void
    {
        // Quote created by WP user 10; approver is WP user 20 (employee 55).
        // Employee 55 is NOT a team manager and NOT the creator → rejected.
        $quote    = $this->makeQuote(QuoteState::pendingApproval(), createdByUserId: 10);
        $employee = $this->makeEmployee(55);
        $team     = $this->makeTeam(managerId: 99);

        $handler = $this->makeHandlerNoSave($quote, $employee, [$team]);

        $this->expectException(\DomainException::class);
        $handler->handle(new ApproveQuoteCommand(quoteId: 1, approverUserId: 20));
    }

    public function testApproverWithNoEmployeeRecordIsRejected(): void
    {
        $quote = $this->makeQuote(QuoteState::pendingApproval(), createdByUserId: 10);

        $quoteRepo = $this->createMock(QuoteRepository::class);
        $quoteRepo->method('findById')->willReturn($quote);
        $quoteRepo->expects($this->never())->method('save');

        $employeeRepo = $this->createMock(EmployeeRepository::class);
        $employeeRepo->method('findByWpUserId')->willReturn(null); // no record

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findAll')->willReturn([]);

        $handler = new ApproveQuoteHandler($quoteRepo, $teamRepo, $employeeRepo);

        $this->expectException(\DomainException::class);
        $handler->handle(new ApproveQuoteCommand(quoteId: 1, approverUserId: 10));
    }

    public function testQuoteNotFoundThrows(): void
    {
        $quoteRepo = $this->createMock(QuoteRepository::class);
        $quoteRepo->method('findById')->willReturn(null);
        $quoteRepo->expects($this->never())->method('save');

        $handler = new ApproveQuoteHandler(
            $quoteRepo,
            $this->createMock(TeamRepository::class),
            $this->createMock(EmployeeRepository::class)
        );

        $this->expectException(\DomainException::class);
        $handler->handle(new ApproveQuoteCommand(quoteId: 999, approverUserId: 1));
    }

    // ── Legacy quote fallback ──────────────────────────────────────────────────

    public function testLegacyQuoteWithNullCreatorRequiresManager(): void
    {
        // Legacy quote: createdByUserId = null. Creator self-approval is not possible.
        // Approver is employee 55, who is NOT a manager → must be rejected.
        $quote    = $this->makeQuote(QuoteState::pendingApproval(), createdByUserId: null);
        $employee = $this->makeEmployee(55);
        $team     = $this->makeTeam(managerId: 99); // 55 is not a manager

        $handler = $this->makeHandlerNoSave($quote, $employee, [$team]);

        $this->expectException(\DomainException::class);
        $handler->handle(new ApproveQuoteCommand(quoteId: 1, approverUserId: 10));
    }

    public function testLegacyQuoteManagerCanStillApprove(): void
    {
        // Legacy quote with null creator. Manager approval must still work.
        $quote    = $this->makeQuote(QuoteState::pendingApproval(), createdByUserId: null);
        $employee = $this->makeEmployee(99);
        $team     = $this->makeTeam(managerId: 99);

        $handler = $this->makeHandler($quote, $employee, [$team]);
        $handler->handle(new ApproveQuoteCommand(quoteId: 1, approverUserId: 20));

        $this->addToAssertionCount(1);
    }
}
