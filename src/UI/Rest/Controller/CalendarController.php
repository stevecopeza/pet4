<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Calendar\Entity\Calendar;
use Pet\Domain\Calendar\Entity\Holiday;
use Pet\Domain\Calendar\Entity\WorkingWindow;
use Pet\Domain\Calendar\Repository\CalendarRepository;

class CalendarController
{
    private CalendarRepository $repository;

    public function __construct(CalendarRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerRoutes(): void
    {
        register_rest_route('pet/v1', '/calendars', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getCalendars'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createCalendar'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route('pet/v1', '/calendars/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getCalendar'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST', // Update
                'callback' => [$this, 'updateCalendar'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteCalendar'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getCalendars(): \WP_REST_Response
    {
        $calendars = $this->repository->findAll();
        $data = array_map([$this, 'serializeCalendar'], $calendars);
        return new \WP_REST_Response($data, 200);
    }

    public function getCalendar(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $calendar = $this->repository->findById($id);

        if (!$calendar) {
            return new \WP_REST_Response(['message' => 'Calendar not found'], 404);
        }

        return new \WP_REST_Response($this->serializeCalendar($calendar), 200);
    }

    public function createCalendar(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $data = $request->get_json_params();
            $calendar = $this->deserializeCalendar($data);
            $this->repository->save($calendar);
            return new \WP_REST_Response($this->serializeCalendar($calendar), 201);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    public function updateCalendar(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = (int) $request->get_param('id');
            $existing = $this->repository->findById($id);
            if (!$existing) {
                return new \WP_REST_Response(['message' => 'Calendar not found'], 404);
            }

            $data = $request->get_json_params();
            // Create new entity but preserve ID and UUID from existing
            // Note: deserializeCalendar creates a new entity. We need to pass ID/UUID or set them.
            // Our Entity is immutable-ish (constructor only), so we pass existing ID/UUID to constructor.
            $calendar = $this->deserializeCalendar($data, $existing->id(), $existing->uuid());
            
            $this->repository->save($calendar);
            return new \WP_REST_Response($this->serializeCalendar($calendar), 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 400);
        }
    }

    public function deleteCalendar(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $this->repository->delete($id);
        return new \WP_REST_Response(null, 204);
    }

    private function serializeCalendar(Calendar $calendar): array
    {
        return [
            'id' => $calendar->id(),
            'uuid' => $calendar->uuid(),
            'name' => $calendar->name(),
            'timezone' => $calendar->timezone(),
            'is_default' => $calendar->isDefault(),
            'working_windows' => array_map(function (WorkingWindow $w) {
                return $w->toArray();
            }, $calendar->workingWindows()),
            'holidays' => array_map(function (Holiday $h) {
                return $h->toArray();
            }, $calendar->holidays()),
        ];
    }

    private function deserializeCalendar(array $data, ?int $id = null, ?string $uuid = null): Calendar
    {
        $windows = array_map(function ($w) {
            return new WorkingWindow(
                $w['day_of_week'],
                $w['start_time'],
                $w['end_time'],
                $w['type'] ?? 'standard',
                (float)($w['rate_multiplier'] ?? 1.0)
            );
        }, $data['working_windows'] ?? []);

        $holidays = array_map(function ($h) {
            return new Holiday(
                $h['name'],
                new \DateTimeImmutable($h['date']),
                (bool)($h['is_recurring'] ?? false)
            );
        }, $data['holidays'] ?? []);

        return new Calendar(
            $data['name'],
            $data['timezone'] ?? 'UTC',
            $windows,
            $holidays,
            (bool)($data['is_default'] ?? false),
            $id,
            $uuid
        );
    }
}
