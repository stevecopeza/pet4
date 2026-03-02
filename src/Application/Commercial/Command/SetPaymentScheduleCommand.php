<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class SetPaymentScheduleCommand
{
    private int $quoteId;
    private array $milestones;

    /**
     * @param int $quoteId
     * @param array $milestones Array of ['title' => string, 'amount' => float, 'dueDate' => ?string]
     */
    public function __construct(int $quoteId, array $milestones)
    {
        $this->quoteId = $quoteId;
        $this->milestones = $milestones;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function milestones(): array
    {
        return $this->milestones;
    }
}
