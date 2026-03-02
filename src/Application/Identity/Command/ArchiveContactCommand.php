<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

class ArchiveContactCommand
{
    public int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }
}
