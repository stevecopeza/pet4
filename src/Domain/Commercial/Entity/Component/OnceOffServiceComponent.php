<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

final class OnceOffServiceComponent extends QuoteComponent
{
    public const TOPOLOGY_SIMPLE = 'SIMPLE';
    public const TOPOLOGY_COMPLEX = 'COMPLEX';

    private string $topology;

    /** @var Phase[] */
    private array $phases;

    /** @var SimpleUnit[] */
    private array $units;

    public function __construct(
        string $topology,
        array $phases = [],
        array $units = [],
        ?string $description = null,
        ?int $id = null,
        string $section = 'General'
    ) {
        parent::__construct('once_off_service', $description, $id, $section);
        $this->topology = $topology;
        $this->phases = $phases;
        $this->units = $units;
        $this->assertInvariant();
    }

    public function topology(): string
    {
        return $this->topology;
    }

    /**
     * @return Phase[]
     */
    public function phases(): array
    {
        return $this->phases;
    }

    /**
     * @return SimpleUnit[]
     */
    public function units(): array
    {
        if ($this->topology === self::TOPOLOGY_SIMPLE) {
            return $this->units;
        }

        $all = [];
        foreach ($this->phases as $phase) {
            foreach ($phase->units() as $unit) {
                $all[] = $unit;
            }
        }
        return $all;
    }

    public function sellValue(): float
    {
        $total = 0.0;
        foreach ($this->units() as $unit) {
            $total += $unit->sellValue();
        }
        return $total;
    }

    public function internalCost(): float
    {
        $total = 0.0;
        foreach ($this->units() as $unit) {
            $total += $unit->internalCost();
        }
        return $total;
    }

    private function assertInvariant(): void
    {
        if ($this->topology === self::TOPOLOGY_SIMPLE) {
            if (!empty($this->phases)) {
                throw new \DomainException('Simple once-off service cannot contain phases.');
            }
        } elseif ($this->topology === self::TOPOLOGY_COMPLEX) {
            if (!empty($this->units)) {
                throw new \DomainException('Complex once-off service units must be inside phases.');
            }
        } else {
            throw new \DomainException('Invalid topology for once-off service component.');
        }
    }
}

