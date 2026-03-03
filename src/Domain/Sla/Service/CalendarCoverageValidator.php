<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Service;

use Pet\Domain\Calendar\Entity\Calendar;

/**
 * Validates that a set of tier calendars provides complete 24/7 coverage
 * with no gaps or overlaps.
 */
class CalendarCoverageValidator
{
    /**
     * Validate a set of calendars for full weekly coverage.
     *
     * @param Calendar[] $calendars
     * @return string[] Array of validation errors (empty = valid)
     */
    public function validate(array $calendars): array
    {
        if (empty($calendars)) {
            return ['At least one calendar is required.'];
        }

        $errors = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $dayErrors = $this->validateDay($day, $calendars);
            $errors = array_merge($errors, $dayErrors);
        }

        return $errors;
    }

    /**
     * Validate a single day across all calendars.
     *
     * @param string $day
     * @param Calendar[] $calendars
     * @return string[]
     */
    private function validateDay(string $day, array $calendars): array
    {
        $errors = [];

        // Collect all windows for this day across all calendars
        $windows = [];
        foreach ($calendars as $calendar) {
            $snapshot = $calendar->createSnapshot();
            foreach ($snapshot['working_windows'] ?? [] as $w) {
                if (strtolower($w['day_of_week'] ?? '') === $day) {
                    $windows[] = [
                        'start' => $this->timeToMinutes($w['start_time']),
                        'end' => $this->timeToMinutes($w['end_time']),
                        'calendar' => $calendar->name(),
                    ];
                }
            }
        }

        if (empty($windows)) {
            $errors[] = "No coverage on " . ucfirst($day) . ".";
            return $errors;
        }

        // Sort by start time
        usort($windows, fn($a, $b) => $a['start'] <=> $b['start']);

        // Check for gaps and overlaps
        $covered = 0; // minutes since midnight
        foreach ($windows as $i => $w) {
            if ($w['start'] > $covered) {
                $errors[] = sprintf(
                    "Gap on %s from %s to %s.",
                    ucfirst($day),
                    $this->minutesToTime($covered),
                    $this->minutesToTime($w['start'])
                );
            }

            if ($i > 0 && $w['start'] < $windows[$i - 1]['end']) {
                $errors[] = sprintf(
                    "Overlap on %s between calendar '%s' and '%s' at %s.",
                    ucfirst($day),
                    $windows[$i - 1]['calendar'],
                    $w['calendar'],
                    $this->minutesToTime($w['start'])
                );
            }

            $covered = max($covered, $w['end']);
        }

        // Check coverage extends to end of day (1440 = 24:00)
        if ($covered < 1440) {
            $errors[] = sprintf(
                "Gap on %s from %s to 24:00.",
                ucfirst($day),
                $this->minutesToTime($covered)
            );
        }

        return $errors;
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
