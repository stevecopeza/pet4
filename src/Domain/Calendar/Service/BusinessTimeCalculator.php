<?php

declare(strict_types=1);

namespace Pet\Domain\Calendar\Service;

use Pet\Domain\Calendar\Entity\Calendar;

class BusinessTimeCalculator
{
    /**
     * Calculates the number of business minutes between two UTC timestamps.
     * 
     * @param \DateTimeImmutable $startUtc
     * @param \DateTimeImmutable $endUtc
     * @param array $calendarSnapshot JSON snapshot from Calendar->createSnapshot()
     * @return int
     */
    public function calculateBusinessMinutes(
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        array $calendarSnapshot
    ): int {
        if ($endUtc <= $startUtc) {
            return 0;
        }

        $timezone = new \DateTimeZone($calendarSnapshot['timezone']);
        
        // Normalize Working Windows for O(1) lookup
        // Structure: $windows['monday'] = [['start' => '08:00', 'end' => '17:00']]
        $windows = $this->normalizeWindows($calendarSnapshot['working_windows']);
        
        // Normalize Holidays
        $holidays = $this->normalizeHolidays($calendarSnapshot['holidays']);

        // Convert to Calendar Timezone
        $cursor = $startUtc->setTimezone($timezone);
        $endLocal = $endUtc->setTimezone($timezone);
        
        $minutes = 0;
        
        // Iteration approach: Minute by Minute (Safe, Deterministic, but naive)
        // Optimization: We can jump by hours or days, but let's start safe.
        // Given PHP performance, a "Window Jump" algorithm is better than 1-minute loops for long periods.
        
        while ($cursor < $endLocal) {
            $currentDay = strtolower($cursor->format('l')); // monday
            $currentDate = $cursor->format('Y-m-d');
            
            // 1. Is it a holiday?
            if ($this->isHoliday($currentDate, $holidays)) {
                // Skip to next day 00:00
                $cursor = $cursor->modify('tomorrow midnight');
                continue;
            }
            
            // 2. Are there working windows today?
            if (!isset($windows[$currentDay])) {
                // Skip to next day 00:00
                $cursor = $cursor->modify('tomorrow midnight');
                continue;
            }

            // 3. Process windows for this day
            $dayProcessed = false;
            foreach ($windows[$currentDay] as $window) {
                // Parse window times for *this specific date*
                $windowStart = \DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i', 
                    "$currentDate {$window['start_time']}", 
                    $timezone
                );
                $windowEnd = \DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i', 
                    "$currentDate {$window['end_time']}", 
                    $timezone
                );
                
                // Handle logic where we might be mid-day
                if ($cursor >= $windowEnd) {
                    continue; // Window already passed
                }
                
                // Determine effective start and end for this segment
                $effectiveStart = ($cursor > $windowStart) ? $cursor : $windowStart;
                $effectiveEnd = ($endLocal < $windowEnd) ? $endLocal : $windowEnd;
                
                if ($effectiveEnd > $effectiveStart) {
                    $diff = $effectiveEnd->getTimestamp() - $effectiveStart->getTimestamp();
                    $minutes += (int) floor($diff / 60);
                }
                
                // Move cursor to end of this window
                if ($endLocal <= $windowEnd) {
                    return $minutes; // Reached target
                }
                $cursor = $windowEnd;
            }
            
            // If we are here, we finished all windows for the day, skip to next day
            // But only if cursor is still on today (it should be at the end of last window)
            if ($cursor->format('Y-m-d') === $currentDate) {
                 $cursor = $cursor->modify('tomorrow midnight');
            }
        }

        return $minutes;
    }

    /**
     * Adds business minutes to a start time, returning the projected end time.
     * 
     * @param \DateTimeImmutable $startUtc
     * @param int $minutesToAdd
     * @param array $calendarSnapshot
     * @return \DateTimeImmutable
     */
    public function addBusinessMinutes(
        \DateTimeImmutable $startUtc,
        int $minutesToAdd,
        array $calendarSnapshot
    ): \DateTimeImmutable {
        if ($minutesToAdd <= 0) {
            return $startUtc;
        }

        $timezone = new \DateTimeZone($calendarSnapshot['timezone']);
        $windows = $this->normalizeWindows($calendarSnapshot['working_windows']);
        $holidays = $this->normalizeHolidays($calendarSnapshot['holidays']);
        
        $cursor = $startUtc->setTimezone($timezone);
        $remaining = $minutesToAdd;
        
        // Safety: max loops to prevent infinite if calendar has no working time
        $loops = 0;
        $maxLoops = 10000;

        while ($remaining > 0 && $loops < $maxLoops) {
            $loops++;
            $currentDay = strtolower($cursor->format('l'));
            $currentDate = $cursor->format('Y-m-d');
            
            // 1. Check Holiday
            if ($this->isHoliday($currentDate, $holidays)) {
                $cursor = $cursor->modify('tomorrow midnight');
                continue;
            }
            
            // 2. Check Windows
            if (!isset($windows[$currentDay])) {
                $cursor = $cursor->modify('tomorrow midnight');
                continue;
            }
            
            // 3. Iterate Windows
            foreach ($windows[$currentDay] as $window) {
                if ($remaining <= 0) break;

                $windowStart = \DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i', 
                    "$currentDate {$window['start_time']}", 
                    $timezone
                );
                $windowEnd = \DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i', 
                    "$currentDate {$window['end_time']}", 
                    $timezone
                );
                
                // If cursor is past this window, skip
                if ($cursor >= $windowEnd) {
                    continue;
                }
                
                // Effective start is max(cursor, windowStart)
                $effectiveStart = ($cursor > $windowStart) ? $cursor : $windowStart;
                
                // How much time available in this window?
                $availableSeconds = $windowEnd->getTimestamp() - $effectiveStart->getTimestamp();
                $availableMinutes = (int) floor($availableSeconds / 60);
                
                if ($remaining <= $availableMinutes) {
                    // We can finish here!
                    return $effectiveStart->modify("+{$remaining} minutes");
                } else {
                    // Consume entire window
                    $remaining -= $availableMinutes;
                    // Move cursor to end of window (so we can jump to next window or day)
                    $cursor = $windowEnd;
                }
            }
            
            // If we are here, we finished the day's windows (or skipped them), move to next day
            // But only if we haven't already moved to tomorrow (check if cursor is still today)
            if ($cursor->format('Y-m-d') === $currentDate) {
                 $cursor = $cursor->modify('tomorrow midnight');
            }
        }
        
        return $cursor;
    }

    private function normalizeWindows(array $rawWindows): array
    {
        $normalized = [];
        foreach ($rawWindows as $w) {
            $day = strtolower($w['day_of_week']);
            if (!isset($normalized[$day])) {
                $normalized[$day] = [];
            }
            $normalized[$day][] = $w;
        }
        return $normalized;
    }

    private function normalizeHolidays(array $rawHolidays): array
    {
        // Keyed by 'Y-m-d' or 'm-d' for recurring
        $holidays = [];
        foreach ($rawHolidays as $h) {
            if ($h['is_recurring']) {
                $md = substr($h['date'], 5); // 12-25
                $holidays['recurring'][] = $md;
            } else {
                $holidays['fixed'][] = $h['date'];
            }
        }
        return $holidays;
    }

    private function isHoliday(string $dateYmd, array $normalizedHolidays): bool
    {
        // Check fixed
        if (in_array($dateYmd, $normalizedHolidays['fixed'] ?? [])) {
            return true;
        }
        
        // Check recurring
        $md = substr($dateYmd, 5);
        if (in_array($md, $normalizedHolidays['recurring'] ?? [])) {
            return true;
        }
        
        return false;
    }
}
