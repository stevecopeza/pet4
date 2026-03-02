<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

final class LeaveType
{
    public function __construct(
        private int $id,
        private string $name,
        private bool $paid
    ) {}

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function paid(): bool { return $this->paid; }
}

