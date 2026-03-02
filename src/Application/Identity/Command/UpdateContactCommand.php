<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

class UpdateContactCommand
{
    public int $id;
    public string $firstName;
    public string $lastName;
    public string $email;
    public ?string $phone;
    public array $affiliations;
    public array $malleableData;

    public function __construct(
        int $id,
        string $firstName,
        string $lastName,
        string $email,
        ?string $phone = null,
        array $affiliations = [],
        array $malleableData = []
    ) {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phone = $phone;
        $this->affiliations = $affiliations;
        $this->malleableData = $malleableData;
    }
}
