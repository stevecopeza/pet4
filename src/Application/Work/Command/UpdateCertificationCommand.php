<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class UpdateCertificationCommand
{
    private int $id;
    private string $name;
    private string $issuingBody;
    private int $expiryMonths;

    public function __construct(int $id, string $name, string $issuingBody, int $expiryMonths = 0)
    {
        $this->id = $id;
        $this->name = $name;
        $this->issuingBody = $issuingBody;
        $this->expiryMonths = $expiryMonths;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function issuingBody(): string
    {
        return $this->issuingBody;
    }

    public function expiryMonths(): int
    {
        return $this->expiryMonths;
    }
}

