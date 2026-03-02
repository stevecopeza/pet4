<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

final class SubmitTimeEntryCommand
{
    public function __construct(
        private int $timeEntryId
    ) {
    }

    public function timeEntryId(): int
    {
        return $this->timeEntryId;
    }
}
