<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Finance\Entity;

use Pet\Domain\Finance\Entity\BillingExport;
use PHPUnit\Framework\TestCase;

final class BillingExportTest extends TestCase
{
    public function testQueueThenMarkSentThenConfirm(): void
    {
        $export = BillingExport::draft(
            'uuid-1',
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
            99
        );

        $this->assertSame('draft', $export->status());

        $export->queue();
        $this->assertSame('queued', $export->status());

        $export->markSent();
        $this->assertSame('sent', $export->status());

        $export->confirm();
        $this->assertSame('confirmed', $export->status());
    }

    public function testConfirmIsIdempotentWhenAlreadyConfirmed(): void
    {
        $export = BillingExport::draft(
            'uuid-2',
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
            99
        );
        $export->queue();
        $export->markSent();
        $export->confirm();

        $export->confirm();

        $this->assertSame('confirmed', $export->status());
    }

    public function testConfirmRejectsAnyStateOtherThanSentOrConfirmed(): void
    {
        $export = BillingExport::draft(
            'uuid-3',
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
            99
        );

        $this->expectException(\DomainException::class);
        $export->confirm();
    }

    public function testQueueRejectsNonDraftToPreventDuplicateQueueing(): void
    {
        $export = BillingExport::draft(
            'uuid-4',
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31'),
            99
        );
        $export->queue();

        $this->expectException(\DomainException::class);
        $export->queue();
    }
}

