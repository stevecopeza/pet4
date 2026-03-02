<?php

declare(strict_types=1);

namespace Pet\Application\Knowledge\Command;

class ArchiveArticleCommand
{
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function id(): int
    {
        return $this->id;
    }
}
