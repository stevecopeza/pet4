<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class UpdatePersonKpiCommand
{
    private int $id;
    private float $actualValue;
    private float $score;

    public function __construct(int $id, float $actualValue, float $score)
    {
        $this->id = $id;
        $this->actualValue = $actualValue;
        $this->score = $score;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function actualValue(): float
    {
        return $this->actualValue;
    }

    public function score(): float
    {
        return $this->score;
    }
}
