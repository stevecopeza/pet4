<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Listener;

use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Entity\Forecast;
use Pet\Domain\Commercial\Repository\ForecastRepository;

class CreateForecastFromQuoteListener
{
    private ForecastRepository $forecastRepository;

    public function __construct(ForecastRepository $forecastRepository)
    {
        $this->forecastRepository = $forecastRepository;
    }

    public function __invoke(QuoteAccepted $event): void
    {
        $quote = $event->quote();

        // Check if forecast already exists? 
        // The repository findByQuoteId might be useful if we want to be idempotent.
        $existingForecast = $this->forecastRepository->findByQuoteId($quote->id());
        if ($existingForecast) {
            // Already exists, maybe update?
            // For now, let's assume if it exists we don't need to do anything or we update it.
            // Let's just return to be safe.
            return;
        }

        // Create breakdown from components
        $breakdown = [];
        foreach ($quote->components() as $component) {
            $type = $component->type();
            if (!isset($breakdown[$type])) {
                $breakdown[$type] = 0.0;
            }
            $breakdown[$type] += $component->sellValue();
        }

        $forecast = new Forecast(
            (int)$quote->id(),
            $quote->totalValue(),
            1.0, // Probability 100% as it is accepted
            'committed',
            $breakdown
        );

        $this->forecastRepository->save($forecast);
    }
}
