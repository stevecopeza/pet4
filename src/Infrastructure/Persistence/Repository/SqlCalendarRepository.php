<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Calendar\Entity\Calendar;
use Pet\Domain\Calendar\Entity\Holiday;
use Pet\Domain\Calendar\Entity\WorkingWindow;
use Pet\Domain\Calendar\Repository\CalendarRepository;

class SqlCalendarRepository implements CalendarRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(Calendar $calendar): void
    {
        $table = $this->wpdb->prefix . 'pet_calendars';
        $data = [
            'uuid' => $calendar->uuid(),
            'name' => $calendar->name(),
            'timezone' => $calendar->timezone(),
            'is_default' => $calendar->isDefault() ? 1 : 0,
        ];

        if ($calendar->id()) {
            $this->wpdb->update($table, $data, ['id' => $calendar->id()]);
            $calendarId = $calendar->id();
            
            // Clear existing windows/holidays to simpler handle updates (full replacement strategy)
            $this->wpdb->delete($this->wpdb->prefix . 'pet_calendar_working_windows', ['calendar_id' => $calendarId]);
            $this->wpdb->delete($this->wpdb->prefix . 'pet_calendar_holidays', ['calendar_id' => $calendarId]);
        } else {
            $this->wpdb->insert($table, $data);
            $calendarId = $this->wpdb->insert_id;
        }

        // Save Windows
        $windowsTable = $this->wpdb->prefix . 'pet_calendar_working_windows';
        foreach ($calendar->workingWindows() as $window) {
            $this->wpdb->insert($windowsTable, [
                'calendar_id' => $calendarId,
                'day_of_week' => $this->mapDayToNumber($window->dayOfWeek()),
                'start_time' => $window->startTime(),
                'end_time' => $window->endTime(),
                'type' => $window->type(),
                'rate_multiplier' => $window->rateMultiplier(),
            ]);
        }

        // Save Holidays
        $holidaysTable = $this->wpdb->prefix . 'pet_calendar_holidays';
        foreach ($calendar->holidays() as $holiday) {
            $this->wpdb->insert($holidaysTable, [
                'calendar_id' => $calendarId,
                'name' => $holiday->name(),
                'holiday_date' => $holiday->date()->format('Y-m-d'),
                'is_recurring' => $holiday->isRecurring() ? 1 : 0,
            ]);
        }
        
        // Handle "Default" logic: if this is default, unset others
        if ($calendar->isDefault()) {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE $table SET is_default = 0 WHERE id != %d",
                $calendarId
            ));
        }
    }

    public function findById(int $id): ?Calendar
    {
        $table = $this->wpdb->prefix . 'pet_calendars';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByUuid(string $uuid): ?Calendar
    {
        $table = $this->wpdb->prefix . 'pet_calendars';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE uuid = %s", $uuid));

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }
    
    public function findDefault(): ?Calendar
    {
        $table = $this->wpdb->prefix . 'pet_calendars';
        $row = $this->wpdb->get_row("SELECT * FROM $table WHERE is_default = 1 LIMIT 1");

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_calendars';
        $rows = $this->wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function delete(int $id): void
    {
        $this->wpdb->delete($this->wpdb->prefix . 'pet_calendars', ['id' => $id]);
    }

    private function mapRowToEntity($row): Calendar
    {
        $windows = $this->findWindowsByCalendarId((int)$row->id);
        $holidays = $this->findHolidaysByCalendarId((int)$row->id);

        return new Calendar(
            $row->name,
            $row->timezone,
            $windows,
            $holidays,
            (bool)$row->is_default,
            (int)$row->id,
            $row->uuid
        );
    }

    private function findWindowsByCalendarId(int $calendarId): array
    {
        $table = $this->wpdb->prefix . 'pet_calendar_working_windows';
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $table WHERE calendar_id = %d", $calendarId));

        return array_map(function ($row) {
            return new WorkingWindow(
                $this->mapNumberToDay((int)$row->day_of_week),
                substr($row->start_time, 0, 5), // HH:MM:SS -> HH:MM
                substr($row->end_time, 0, 5),
                $row->type,
                (float)$row->rate_multiplier
            );
        }, $rows);
    }

    private function findHolidaysByCalendarId(int $calendarId): array
    {
        $table = $this->wpdb->prefix . 'pet_calendar_holidays';
        $rows = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $table WHERE calendar_id = %d", $calendarId));

        return array_map(function ($row) {
            return new Holiday(
                $row->name,
                new \DateTimeImmutable($row->holiday_date),
                (bool)$row->is_recurring
            );
        }, $rows);
    }
    
    private function mapDayToNumber(string $day): int
    {
        $days = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6
        ];
        return $days[strtolower($day)] ?? 1;
    }

    private function mapNumberToDay(int $num): string
    {
        $days = [
            0 => 'sunday', 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday',
            4 => 'thursday', 5 => 'friday', 6 => 'saturday'
        ];
        return $days[$num] ?? 'monday';
    }
}
