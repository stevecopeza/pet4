<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

use Pet\Domain\Configuration\Repository\SettingRepository;

class FeatureFlagService
{
    private SettingRepository $settings;

    public function __construct(SettingRepository $settings)
    {
        $this->settings = $settings;
    }

    public function isSlaSchedulerEnabled(): bool
    {
        return $this->isEnabled('pet_sla_scheduler_enabled');
    }

    public function isWorkProjectionEnabled(): bool
    {
        return $this->isEnabled('pet_work_projection_enabled');
    }

    public function isQueueVisibilityEnabled(): bool
    {
        return $this->isEnabled('pet_queue_visibility_enabled');
    }

    public function isPriorityEngineEnabled(): bool
    {
        return $this->isEnabled('pet_priority_engine_enabled');
    }

    public function isEscalationEngineEnabled(): bool
    {
        return $this->isEnabled('pet_escalation_engine_enabled');
    }

    public function isHelpdeskEnabled(): bool
    {
        // Map legacy shortcode flag if new flag not set?
        // For strict gating, we use the new flag 'pet_helpdesk_enabled'.
        // If the user wants to enable helpdesk, they must set this flag.
        return $this->isEnabled('pet_helpdesk_enabled');
    }

    public function isHelpdeskShortcodeEnabled(): bool
    {
        return $this->isEnabled('pet_helpdesk_shortcode_enabled');
    }

    public function isAdvisoryEnabled(): bool
    {
        return $this->isEnabled('pet_advisory_enabled');
    }

    public function isAdvisoryReportsEnabled(): bool
    {
        return $this->isEnabled('pet_advisory_reports_enabled');
    }

    public function isResilienceIndicatorsEnabled(): bool
    {
        return $this->isEnabled('pet_resilience_indicators_enabled');
    }

    public function isPulsewayEnabled(): bool
    {
        return $this->isEnabled('pet_pulseway_enabled');
    }

    public function isPulsewayTicketCreationEnabled(): bool
    {
        return $this->isEnabled('pet_pulseway_ticket_creation_enabled');
    }

    public function isDashboardsEnabled(): bool
    {
        return $this->isEnabled('pet_dashboards_enabled');
    }

    public function isSupportOperationalImprovementsEnabled(): bool
    {
        return $this->isEnabled('pet_support_operational_improvements_enabled');
    }

    public function isStaffTimeCaptureEnabled(): bool
    {
        return $this->isEnabled('pet_staff_time_capture_enabled');
    }

    public function isStaffSetupJourneyEnabled(): bool
    {
        return $this->isEnabled('pet_staff_setup_journey_enabled');
    }

    private function isEnabled(string $key): bool
    {
        $setting = $this->settings->findByKey($key);
        if (!$setting) {
            return false;
        }

        $value = filter_var($setting->value(), FILTER_VALIDATE_BOOLEAN);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[PET FeatureFlag] %s: %s', $key, $value ? 'ENABLED' : 'DISABLED'));
        }

        return $value;
    }
}
