<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Time\Entity;

use Pet\Domain\Time\Entity\TimeEntry;
use PHPUnit\Framework\TestCase;

class TimeEntryTest extends TestCase
{
    private function makeEntry(
        string $status = 'draft',
        ?int $id = null,
        ?int $correctsEntryId = null
    ): TimeEntry {
        return new TimeEntry(
            employeeId: 1,
            ticketId: 10,
            start: new \DateTimeImmutable('2026-01-15 09:00'),
            end: new \DateTimeImmutable('2026-01-15 10:30'),
            isBillable: true,
            description: 'Test entry',
            status: $status,
            id: $id,
            malleableData: [],
            createdAt: null,
            archivedAt: null,
            correctsEntryId: $correctsEntryId
        );
    }

    // ── Constructor invariants ──

    public function testConstructorCalculatesDuration(): void
    {
        $entry = $this->makeEntry();
        $this->assertSame(90, $entry->durationMinutes());
    }

    public function testConstructorRejectsEndBeforeStart(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('End time must be after start time');

        new TimeEntry(
            employeeId: 1,
            ticketId: 10,
            start: new \DateTimeImmutable('2026-01-15 10:00'),
            end: new \DateTimeImmutable('2026-01-15 09:00'),
            isBillable: true,
            description: 'Bad entry'
        );
    }

    public function testConstructorRejectsEqualStartEnd(): void
    {
        $this->expectException(\DomainException::class);
        $same = new \DateTimeImmutable('2026-01-15 09:00');

        new TimeEntry(
            employeeId: 1,
            ticketId: 10,
            start: $same,
            end: $same,
            isBillable: true,
            description: 'Zero duration'
        );
    }

    // ── Getters / immutability ──

    public function testImmutableGetters(): void
    {
        $entry = $this->makeEntry(id: 42);
        $this->assertSame(42, $entry->id());
        $this->assertSame(1, $entry->employeeId());
        $this->assertSame(10, $entry->ticketId());
        $this->assertTrue($entry->isBillable());
        $this->assertSame('Test entry', $entry->description());
        $this->assertSame('draft', $entry->status());
    }

    // ── submit() ──

    public function testSubmitFromDraft(): void
    {
        $entry = $this->makeEntry(id: 1);
        $entry->submit();
        $this->assertSame('submitted', $entry->status());
    }

    public function testSubmitRecordsEvent(): void
    {
        $entry = $this->makeEntry(id: 1);
        $entry->submit();
        $events = $entry->releaseEvents();
        $this->assertCount(1, $events);
    }

    public function testSubmitFromSubmittedThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only draft time entries can be submitted');

        $entry = $this->makeEntry(status: 'submitted', id: 1);
        $entry->submit();
    }

    public function testSubmitFromLockedThrows(): void
    {
        $this->expectException(\DomainException::class);
        $entry = $this->makeEntry(status: 'locked', id: 1);
        $entry->submit();
    }

    // ── lock() ──

    public function testLock(): void
    {
        $entry = $this->makeEntry(status: 'submitted', id: 1);
        $entry->lock();
        $this->assertSame('locked', $entry->status());
    }

    // ── updateDraft() ──

    public function testUpdateDraftSuccess(): void
    {
        $entry = $this->makeEntry();
        $newStart = new \DateTimeImmutable('2026-01-15 14:00');
        $newEnd = new \DateTimeImmutable('2026-01-15 15:00');
        $entry->updateDraft('Updated description', $newStart, $newEnd, false);

        $this->assertSame('Updated description', $entry->description());
        $this->assertSame(60, $entry->durationMinutes());
        $this->assertFalse($entry->isBillable());
    }

    public function testUpdateDraftRejectsSubmitted(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only draft time entries can be edited');

        $entry = $this->makeEntry(status: 'submitted', id: 1);
        $entry->updateDraft(
            'Nope',
            new \DateTimeImmutable('2026-01-15 09:00'),
            new \DateTimeImmutable('2026-01-15 10:00'),
            false
        );
    }

    public function testUpdateDraftRejectsEndBeforeStart(): void
    {
        $this->expectException(\DomainException::class);
        $entry = $this->makeEntry();
        $entry->updateDraft(
            'Bad range',
            new \DateTimeImmutable('2026-01-15 10:00'),
            new \DateTimeImmutable('2026-01-15 09:00'),
            true
        );
    }

    // ── setId() ──

    public function testSetIdOnce(): void
    {
        $entry = $this->makeEntry();
        $entry->setId(99);
        $this->assertSame(99, $entry->id());
    }

    public function testSetIdTwiceThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot change an already-assigned ID');

        $entry = $this->makeEntry(id: 1);
        $entry->setId(2);
    }

    // ── archive() ──

    public function testArchive(): void
    {
        $entry = $this->makeEntry();
        $this->assertNull($entry->archivedAt());
        $entry->archive();
        $this->assertInstanceOf(\DateTimeImmutable::class, $entry->archivedAt());
    }

    // ── createCorrection() ──

    public function testCreateCorrectionLinksToOriginal(): void
    {
        $original = $this->makeEntry(id: 42);

        $correction = TimeEntry::createCorrection(
            $original,
            'Corrected description',
            new \DateTimeImmutable('2026-01-15 09:00'),
            new \DateTimeImmutable('2026-01-15 10:00'),
            false
        );

        $this->assertSame(42, $correction->correctsEntryId());
        $this->assertTrue($correction->isCorrection());
        $this->assertSame('draft', $correction->status());
        $this->assertNull($correction->id());
        $this->assertSame(1, $correction->employeeId()); // inherited
        $this->assertSame(10, $correction->ticketId()); // inherited
        $this->assertFalse($correction->isBillable());
    }

    public function testCreateCorrectionRejectsUnsavedOriginal(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot correct an unsaved time entry');

        $original = $this->makeEntry(); // no id

        TimeEntry::createCorrection(
            $original,
            'Nope',
            new \DateTimeImmutable('2026-01-15 09:00'),
            new \DateTimeImmutable('2026-01-15 10:00'),
            true
        );
    }

    // ── createReversal() ──

    public function testCreateReversalLinksToOriginal(): void
    {
        $original = $this->makeEntry(id: 42);

        $reversal = TimeEntry::createReversal($original, 'Wrong ticket');

        $this->assertSame(42, $reversal->correctsEntryId());
        $this->assertTrue($reversal->isCorrection());
        $this->assertStringContains('REVERSAL', $reversal->description());
        $this->assertStringContains('Wrong ticket', $reversal->description());
        $this->assertSame('draft', $reversal->status());
        $this->assertSame(90, $reversal->durationMinutes()); // same times
    }

    public function testCreateReversalDefaultReason(): void
    {
        $original = $this->makeEntry(id: 42);
        $reversal = TimeEntry::createReversal($original);

        $this->assertStringContains('REVERSAL: Test entry', $reversal->description());
    }

    public function testCreateReversalRejectsUnsaved(): void
    {
        $this->expectException(\DomainException::class);
        TimeEntry::createReversal($this->makeEntry());
    }

    // ── isCorrection() ──

    public function testIsCorrectionFalseByDefault(): void
    {
        $this->assertFalse($this->makeEntry()->isCorrection());
    }

    public function testIsCorrectionTrueWhenLinked(): void
    {
        $entry = $this->makeEntry(correctsEntryId: 99);
        $this->assertTrue($entry->isCorrection());
        $this->assertSame(99, $entry->correctsEntryId());
    }

    // ── releaseEvents() clears queue ──

    public function testReleaseEventsClearsQueue(): void
    {
        $entry = $this->makeEntry(id: 1);
        $entry->submit();
        $events = $entry->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertEmpty($entry->releaseEvents());
    }

    // ── Helper ──

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
