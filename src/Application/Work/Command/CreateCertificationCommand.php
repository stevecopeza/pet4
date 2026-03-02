<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class CreateCertificationCommand
{
    private string $name;
    private string $issuingBody;
    private int $expiryMonths;

    public function __construct(string $name, string $issuingBody, int $expiryMonths = 0)
    {
        $this->name = $name;
        $this->issuingBody = $issuingBody;
        $this->expiryMonths = $expiryMonths;
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
