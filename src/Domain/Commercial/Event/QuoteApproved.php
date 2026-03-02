<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Commercial\Entity\Quote;

class QuoteApproved
{
    private Quote $quote;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
    }

    public function quote(): Quote
    {
        return $this->quote;
    }
}
