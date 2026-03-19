<?php

declare(strict_types=1);

namespace Pet\UI\Shortcode;

use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Team\Repository\TeamRepository;
use Pet\Domain\Work\Repository\PersonSkillRepository;
use Pet\Domain\Work\Repository\SkillRepository;
use Pet\Domain\Work\Repository\PersonCertificationRepository;
use Pet\Domain\Work\Repository\CertificationRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Support\Repository\TicketRepository;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Feed\Repository\FeedEventRepository;
use Pet\Domain\Work\Repository\AssignmentRepository;
use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Domain\Knowledge\Repository\ArticleRepository;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Application\Helpdesk\Query\HelpdeskOverviewQueryService;
use DateTimeImmutable;

class ShortcodeRegistrar
{
    public function register(): void
    {
        add_action('init', function () {
            add_shortcode('pet_my_profile', [$this, 'renderMyProfile']);
            add_shortcode('pet_my_work', [$this, 'renderMyWork']);
            add_shortcode('pet_my_calendar', [$this, 'renderMyCalendar']);
            add_shortcode('pet_activity_stream', [$this, 'renderActivityStream']);
            add_shortcode('pet_activity_wallboard', [$this, 'renderActivityWallboard']);
            add_shortcode('pet_helpdesk', [$this, 'renderHelpdeskOverview']);
            add_shortcode('pet_my_conversations', [$this, 'renderMyConversations']);
            add_shortcode('pet_my_approvals', [$this, 'renderMyApprovals']);
            add_shortcode('pet_knowledge_base', [$this, 'renderKnowledgeBase']);
        });
    }

    public function renderHelpdeskOverview(array $atts = [], ?string $content = null): string
    {
        if (!is_user_logged_in()) {
            return '<div class="pet-helpdesk-overview"><p>' . esc_html__('Sign in required to view helpdesk overview.', 'pet') . '</p></div>';
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'pet-helpdesk-overview',
                plugin_dir_url(dirname(__DIR__, 2)) . 'assets/helpdesk-overview.css',
                [],
                '1.0.0'
            );
        }

        $rawRefresh = isset($atts['refresh']) ? $atts['refresh'] : null;

        $atts = shortcode_atts(
            [
                'mode' => 'manager',
                'team' => 'all',
                'window_days' => '14',
                'refresh' => '60',
                'limit_critical' => '6',
                'limit_risk' => '8',
                'risk_bands' => 'breached,<30m,<2h,today',
                'show_flow' => 'true',
                'title' => 'Helpdesk Overview',
                'scope' => 'support+sla_project',
            ],
            $atts,
            'pet_helpdesk'
        );

        $mode = in_array($atts['mode'], ['manager', 'wallboard'], true) ? $atts['mode'] : 'manager';

        $windowDays = (int) $atts['window_days'];
        if ($windowDays <= 0) {
            $windowDays = 14;
        }

        $refresh = (int) $atts['refresh'];
        if ($refresh < 0) {
            $refresh = 0;
        }
        
        // Default wallboard refresh to 30s if not specified
        if ($mode === 'wallboard' && $rawRefresh === null) {
            $refresh = 30;
        }

        $limitCritical = (int) $atts['limit_critical'];
        if ($limitCritical <= 0) {
            $limitCritical = 6;
        }

        $limitRisk = (int) $atts['limit_risk'];
        if ($limitRisk <= 0) {
            $limitRisk = 8;
        }

        $riskBandsRaw = trim((string) $atts['risk_bands']);
        $riskBands = [];
        if ($riskBandsRaw !== '') {
            $riskBands = array_filter(array_map('trim', explode(',', $riskBandsRaw)));
        }
        if (empty($riskBands)) {
            $riskBands = ['breached', '<30m', '<2h', 'today'];
        }

        $showFlow = strtolower((string) $atts['show_flow']) !== 'false';
        $title = (string) $atts['title'];
        $scope = (string) $atts['scope'];

        $stats = [
            'open_tickets' => 0,
            'critical_tickets' => 0,
            'at_risk_tickets' => 0,
            'breached_tickets' => 0,
        ];

        $lanes = [
            'critical' => [],
            'risk' => [],
            'normal' => [],
        ];

        $flow = [
            'recent_created' => [],
            'recent_resolved' => [],
        ];

        try {
            $container = ContainerFactory::create();
            $featureFlags = $container->get(FeatureFlagService::class);
            if (!$featureFlags->isHelpdeskShortcodeEnabled()) {
                return '<!-- Helpdesk Overview disabled via feature flag -->';
            }
            if (!$featureFlags->isHelpdeskEnabled()) {
                return '<!-- Helpdesk disabled via feature flag -->';
            }

            $queryService = $container->get(HelpdeskOverviewQueryService::class);
            $userId = (int) get_current_user_id();
            
            $data = $queryService->getOverview($atts['team'], $userId, $showFlow);
            
            $stats = $data['stats'];
            $lanes = $data['lanes'];
            $flow = $data['flow'];
        } catch (\Throwable $e) {
            error_log('PET [pet_helpdesk] data error: ' . $e->getMessage());
        }
        $normalCount = $stats['open_tickets'] - $stats['critical_tickets'] - $stats['at_risk_tickets'];
        if ($normalCount < 0) {
            $normalCount = 0;
        }
        $attentionCount = $stats['critical_tickets'] + $stats['at_risk_tickets'];

        $rootClasses = ['pet-shortcode', 'pet-helpdesk', 'pet-helpdesk--mode-' . $mode];

        $html = '<div class="' . esc_attr(implode(' ', $rootClasses)) . '"';
        if ($refresh > 0) {
            $html .= ' data-refresh="' . esc_attr((string) $refresh) . '"';
        }
        $html .= ' data-team="' . esc_attr((string) $atts['team']) . '">';

        if ($mode === 'manager') {
            $html .= '<div class="pet-helpdesk__header">';
            $html .= '<div class="pet-helpdesk__breadcrumbs">' . esc_html__('Support · Helpdesk', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__title-row">';
            $html .= '<h2 class="pet-helpdesk__title">' . esc_html($title) . '</h2>';
            $html .= '<div class="pet-helpdesk__chips">';
            $html .= '<span class="pet-helpdesk__chip">' . esc_html(sprintf(__('Scope: %s', 'pet'), $scope)) . '</span>';
            $html .= '<span class="pet-helpdesk__chip">' . esc_html(sprintf(__('Last %d days', 'pet'), $windowDays)) . '</span>';
            if (!empty($riskBands)) {
                $html .= '<span class="pet-helpdesk__chip">' . esc_html(implode(' · ', $riskBands)) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="pet-helpdesk__context">' . esc_html__('SLA health across support tickets and projects.', 'pet') . '</div>';
            
            // Toolbar
            $html .= '<div class="pet-helpdesk__toolbar" style="margin: 20px 0; display: flex; gap: 15px; align-items: center;">';
            $html .= '<input type="text" id="pet-helpdesk-search" placeholder="' . esc_attr__('Search tickets...', 'pet') . '" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; flex: 1; max-width: 300px;">';
            $html .= '<label class="pet-helpdesk__toggle" style="display: flex; align-items: center; gap: 5px; cursor: pointer;">';
            $html .= '<input type="checkbox" id="pet-helpdesk-my-tickets"> ';
            $html .= esc_html__('My Tickets', 'pet');
            $html .= '</label>';
            $html .= '</div>';

            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpis">';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--open">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('Open Tickets', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $stats['open_tickets']) . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-sub">' . esc_html__('All queues', 'pet') . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--critical">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('Critical', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $stats['critical_tickets']) . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-sub">' . esc_html__('Breached or overdue', 'pet') . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--risk">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('At Risk', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $stats['at_risk_tickets']) . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-sub">' . esc_html__('SLA due soon', 'pet') . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--breached">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('Breached', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $stats['breached_tickets']) . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-sub">' . esc_html__('Missed SLA', 'pet') . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--normal">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('In SLA', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $normalCount) . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-sub">' . esc_html__('Within target window', 'pet') . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--attention">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('Needs Attention', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $attentionCount) . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-sub">' . esc_html__('Critical + At Risk', 'pet') . '</div>';
            $html .= '</div>';

            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__grid">';

            $html .= '<section class="pet-helpdesk__panel pet-helpdesk__panel--critical">';
            $html .= '<div class="pet-helpdesk__panel-head">';
            $html .= '<h3 class="pet-helpdesk__panel-title">' . esc_html__('Critical', 'pet') . '</h3>';
            if ($stats['critical_tickets'] > 0) {
                $html .= '<span class="pet-helpdesk__badge">' . esc_html((string) $stats['critical_tickets']) . '</span>';
            }
            $html .= '</div>';
            $html .= '<div class="pet-helpdesk__cards">';
            $count = 0;
            foreach ($lanes['critical'] as $card) {
                if ($count >= $limitCritical) {
                    break;
                }
                $html .= $this->renderTicketCard($card, 'critical');
                $count++;
            }
            if ($count === 0) {
                $html .= '<div class="pet-helpdesk__card pet-helpdesk__card--neutral"><div class="pet-helpdesk__card-meta">' . esc_html__('No critical tickets.', 'pet') . '</div></div>';
            }
            $html .= '</div>';
            $html .= '</section>';

            $html .= '<section class="pet-helpdesk__panel pet-helpdesk__panel--risk">';
            $html .= '<div class="pet-helpdesk__panel-head">';
            $html .= '<h3 class="pet-helpdesk__panel-title">' . esc_html__('At Risk', 'pet') . '</h3>';
            if ($stats['at_risk_tickets'] > 0) {
                $html .= '<span class="pet-helpdesk__badge">' . esc_html((string) $stats['at_risk_tickets']) . '</span>';
            }
            $html .= '</div>';
            $html .= '<div class="pet-helpdesk__cards">';
            $count = 0;
            foreach ($lanes['risk'] as $card) {
                if ($count >= $limitRisk) {
                    break;
                }
                $html .= $this->renderTicketCard($card, 'risk');
                $count++;
            }
            if ($count === 0) {
                $html .= '<div class="pet-helpdesk__card pet-helpdesk__card--neutral"><div class="pet-helpdesk__card-meta">' . esc_html__('No at-risk tickets.', 'pet') . '</div></div>';
            }
            $html .= '</div>';
            $html .= '</section>';

            $html .= '<section class="pet-helpdesk__panel pet-helpdesk__panel--normal">';
            $html .= '<div class="pet-helpdesk__panel-head">';
            $html .= '<h3 class="pet-helpdesk__panel-title">' . esc_html__('Normal', 'pet') . '</h3>';
            $html .= '</div>';
            $html .= '<div class="pet-helpdesk__cards">';
            $count = 0;
            // Limit normal tickets to avoid huge lists
            $limitNormal = 10; 
            foreach ($lanes['normal'] as $card) {
                if ($count >= $limitNormal) {
                    break;
                }
                $html .= $this->renderTicketCard($card, 'normal');
                $count++;
            }
            if ($count === 0) {
                $html .= '<div class="pet-helpdesk__card pet-helpdesk__card--neutral"><div class="pet-helpdesk__card-meta">' . esc_html__('No active tickets.', 'pet') . '</div></div>';
            }
            $html .= '</div>';
            $html .= '</section>';

            if ($showFlow) {
                $html .= '<section class="pet-helpdesk__panel pet-helpdesk__panel--flow">';
                $html .= '<div class="pet-helpdesk__panel-head">';
                $html .= '<h3 class="pet-helpdesk__panel-title">' . esc_html__('Flow', 'pet') . '</h3>';
                $html .= '</div>';
                $html .= '<table class="pet-helpdesk__table">';
                $html .= '<thead><tr>';
                $html .= '<th>' . esc_html__('When', 'pet') . '</th>';
                $html .= '<th>' . esc_html__('Event', 'pet') . '</th>';
                $html .= '<th>' . esc_html__('Details', 'pet') . '</th>';
                $html .= '</tr></thead>';
                $html .= '<tbody>';
                if (empty($flow['recent_created']) && empty($flow['recent_resolved'])) {
                    $html .= '<tr><td colspan="3">' . esc_html__('No recent flow events.', 'pet') . '</td></tr>';
                } else {
                    foreach ($flow['recent_created'] as $event) {
                        $headline = method_exists($event, 'getHeadline') ? $event->getHeadline() : (method_exists($event, 'getTitle') ? $event->getTitle() : '');
                        $html .= '<tr><td>' . esc_html__('Created', 'pet') . '</td><td>' . esc_html((string) $headline) . '</td><td></td></tr>';
                    }
                    foreach ($flow['recent_resolved'] as $event) {
                        $headline = method_exists($event, 'getHeadline') ? $event->getHeadline() : (method_exists($event, 'getTitle') ? $event->getTitle() : '');
                        $html .= '<tr><td>' . esc_html__('Resolved', 'pet') . '</td><td>' . esc_html((string) $headline) . '</td><td></td></tr>';
                    }
                }
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</section>';
            }

            $html .= '</div>';
            
            $html .= $this->renderHelpdeskScripts();
        } else {
            $html .= '<div class="pet-helpdesk__wb-top">';
            $html .= '<div class="pet-helpdesk__wb-brand">' . esc_html__('Helpdesk', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__wb-context">' . esc_html(sprintf(__('Scope: %s · Last %d days', 'pet'), $scope, $windowDays)) . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__wb-kpis">';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--open">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('Open', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $stats['open_tickets']) . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--critical">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('Critical', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $stats['critical_tickets']) . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--risk">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('At Risk', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $stats['at_risk_tickets']) . '</div>';
            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__kpi pet-helpdesk__kpi--breached">';
            $html .= '<div class="pet-helpdesk__kpi-label">' . esc_html__('Breached', 'pet') . '</div>';
            $html .= '<div class="pet-helpdesk__kpi-value">' . esc_html((string) $stats['breached_tickets']) . '</div>';
            $html .= '</div>';

            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__wb-cols">';

            // Column 1: Critical
            $html .= '<div class="pet-helpdesk__wb-col">';
            $html .= '<h3 class="pet-helpdesk__panel-title">' . esc_html__('Critical', 'pet') . '</h3>';
            $html .= '<div class="pet-helpdesk__cards">';
            $count = 0;
            foreach ($lanes['critical'] as $card) {
                if ($count >= $limitCritical) {
                    break;
                }
                $html .= $this->renderTicketCard($card, 'critical');
                $count++;
            }
            if ($count === 0) {
                $html .= '<div class="pet-helpdesk__card pet-helpdesk__card--neutral"><div class="pet-helpdesk__card-meta">' . esc_html__('No critical tickets.', 'pet') . '</div></div>';
            }
            $html .= '</div>';
            $html .= '</div>';

            // Column 2: At Risk
            $html .= '<div class="pet-helpdesk__wb-col">';
            $html .= '<h3 class="pet-helpdesk__panel-title">' . esc_html__('At Risk', 'pet') . '</h3>';
            $html .= '<div class="pet-helpdesk__cards">';
            $count = 0;
            foreach ($lanes['risk'] as $card) {
                if ($count >= $limitRisk) {
                    break;
                }
                $html .= $this->renderTicketCard($card, 'risk');
                $count++;
            }
            if ($count === 0) {
                $html .= '<div class="pet-helpdesk__card pet-helpdesk__card--neutral"><div class="pet-helpdesk__card-meta">' . esc_html__('No at-risk tickets.', 'pet') . '</div></div>';
            }
            $html .= '</div>';
            $html .= '</div>';

            // Column 3: Normal
            $html .= '<div class="pet-helpdesk__wb-col">';
            $html .= '<h3 class="pet-helpdesk__panel-title">' . esc_html__('Normal', 'pet') . '</h3>';
            $html .= '<div class="pet-helpdesk__cards">';
            $count = 0;
            $limitNormal = 15; // Higher limit for wallboard
            foreach ($lanes['normal'] as $card) {
                if ($count >= $limitNormal) {
                    break;
                }
                $html .= $this->renderTicketCard($card, 'normal');
                $count++;
            }
            if ($count === 0) {
                $html .= '<div class="pet-helpdesk__card pet-helpdesk__card--neutral"><div class="pet-helpdesk__card-meta">' . esc_html__('No active tickets.', 'pet') . '</div></div>';
            }
            $html .= '</div>';
            $html .= '</div>';

            $html .= '</div>';

            $html .= '<div class="pet-helpdesk__wb-ticker">' . esc_html(sprintf(__('Helpdesk wallboard · Auto-refreshing every %ds', 'pet'), $refresh)) . '</div>';
        }

        if ($refresh > 0) {
            $html .= $this->renderActivityRefreshScript();
        }

        $html .= '</div>';

        return $html;
    }

    public function renderMyProfile(array $atts = [], ?string $content = null): string
    {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'pet-my-profile',
                plugin_dir_url(dirname(__DIR__, 2)) . 'assets/my-profile.css',
                [],
                '1.0.0'
            );
        }

        if (!is_user_logged_in()) {
            return '<div class="pet-my-profile"><p>' . esc_html__('Please log in to view your profile.', 'pet') . '</p></div>';
        }

        $user = wp_get_current_user();
        if (!$user || 0 === $user->ID) {
            return '<div class="pet-my-profile"><p>' . esc_html__('Unable to load profile.', 'pet') . '</p></div>';
        }

        $atts = shortcode_atts(
            [
                'show_roles' => '1',
                'show_skills' => '1',
                'show_certs' => '1',
            ],
            $atts,
            'pet_my_profile'
        );

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pet_my_profile_action'], $_POST['pet_my_profile_nonce']) && $_POST['pet_my_profile_action'] === 'update_profile') {
            if (wp_verify_nonce(sanitize_text_field((string) $_POST['pet_my_profile_nonce']), 'pet_my_profile_update')) {
                if (current_user_can('edit_user', $user->ID)) {
                    $firstName = isset($_POST['pet_first_name']) ? sanitize_text_field((string) $_POST['pet_first_name']) : '';
                    $lastName = isset($_POST['pet_last_name']) ? sanitize_text_field((string) $_POST['pet_last_name']) : '';
                    $displayName = isset($_POST['pet_display_name']) ? sanitize_text_field((string) $_POST['pet_display_name']) : '';
                    $phone = isset($_POST['pet_phone']) ? sanitize_text_field((string) $_POST['pet_phone']) : '';
                    $title = isset($_POST['pet_title']) ? sanitize_text_field((string) $_POST['pet_title']) : '';

                    if ($firstName === '' && $lastName === '' && $displayName === '') {
                        $message = '<div class="pet-my-profile-notice pet-my-profile-error">' . esc_html__('Please provide at least one name field.', 'pet') . '</div>';
                    } else {
                        $updateData = [
                            'ID' => $user->ID,
                        ];
                        if ($firstName !== '') {
                            $updateData['first_name'] = $firstName;
                        }
                        if ($lastName !== '') {
                            $updateData['last_name'] = $lastName;
                        }
                        if ($displayName !== '') {
                            $updateData['display_name'] = $displayName;
                        }

                        $result = wp_update_user($updateData);
                        if (is_wp_error($result)) {
                            $message = '<div class="pet-my-profile-notice pet-my-profile-error">' . esc_html($result->get_error_message()) . '</div>';
                        } else {
                            update_user_meta($user->ID, 'pet_phone', $phone);
                            update_user_meta($user->ID, 'pet_title', $title);
                            $message = '<div class="pet-my-profile-notice pet-my-profile-success">' . esc_html__('Profile updated.', 'pet') . '</div>';
                            $user = wp_get_current_user();
                        }
                    }
                } else {
                    $message = '<div class="pet-my-profile-notice pet-my-profile-error">' . esc_html__('You do not have permission to update this profile.', 'pet') . '</div>';
                }
            } else {
                $message = '<div class="pet-my-profile-notice pet-my-profile-error">' . esc_html__('Security check failed. Please try again.', 'pet') . '</div>';
            }
        }

        $displayName = $user->display_name;
        $firstName = $user->first_name;
        $lastName = $user->last_name;
        $email = $user->user_email;
        $phone = get_user_meta($user->ID, 'pet_phone', true);
        $title = get_user_meta($user->ID, 'pet_title', true);

        $wpRoles = [];
        if (!empty($user->roles) && is_array($user->roles)) {
            global $wp_roles;
            foreach ($user->roles as $roleKey) {
                if (isset($wp_roles->roles[$roleKey]['name'])) {
                    $wpRoles[] = $wp_roles->roles[$roleKey]['name'];
                } else {
                    $wpRoles[] = $roleKey;
                }
            }
        }

        $petTeams = [];
        $skills = [];
        $certifications = [];
        $skillsSource = 'pet';
        $certsSource = 'pet';

        try {
            $container = ContainerFactory::create();
            $employeeRepo = $container->get(EmployeeRepository::class);
            $employee = $employeeRepo->findByWpUserId((int) $user->ID);

            if ($employee && $employee->id() !== null) {
                $teamIds = $employee->teamIds();
                if (!empty($teamIds)) {
                    $teamRepo = $container->get(TeamRepository::class);
                    foreach ($teamIds as $teamId) {
                        $team = $teamRepo->find((int) $teamId);
                        if ($team && $team->isActive()) {
                            $petTeams[] = $team->name();
                        }
                    }
                }

                $personSkillRepo = $container->get(PersonSkillRepository::class);
                $skillRepo = $container->get(SkillRepository::class);
                $personSkills = $personSkillRepo->findByEmployeeId((int) $employee->id());
                $latestBySkill = [];
                foreach ($personSkills as $personSkill) {
                    $skillId = $personSkill->skillId();
                    if (!isset($latestBySkill[$skillId])) {
                        $latestBySkill[$skillId] = $personSkill;
                    }
                }
                foreach ($latestBySkill as $skillId => $personSkill) {
                    $skill = $skillRepo->findById((int) $skillId);
                    $name = $skill ? $skill->name() : sprintf(__('Skill #%d', 'pet'), $skillId);
                    $skills[] = [
                        'name' => $name,
                        'self_rating' => $personSkill->selfRating(),
                        'manager_rating' => $personSkill->managerRating(),
                        'effective_date' => $personSkill->effectiveDate()->format('Y-m-d'),
                    ];
                }

                $personCertRepo = $container->get(PersonCertificationRepository::class);
                $certRepo = $container->get(CertificationRepository::class);
                $personCerts = $personCertRepo->findByEmployeeId((int) $employee->id());
                foreach ($personCerts as $personCert) {
                    $cert = $certRepo->findById($personCert->certificationId());
                    $name = $cert ? $cert->name() : sprintf(__('Certification #%d', 'pet'), $personCert->certificationId());
                    $issuer = $cert ? $cert->issuingBody() : '';
                    $certifications[] = [
                        'name' => $name,
                        'issuer' => $issuer,
                        'obtained' => $personCert->obtainedDate()->format('Y-m-d'),
                        'expiry' => $personCert->expiryDate() ? $personCert->expiryDate()->format('Y-m-d') : '',
                        'status' => $personCert->status(),
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('PET [pet_my_profile] data error: ' . $e->getMessage());
        }

        if (empty($skills)) {
            $skillsSource = 'meta';
            $skillsJson = get_user_meta($user->ID, 'pet_skills_json', true);
            if (is_string($skillsJson) && $skillsJson !== '') {
                $decoded = json_decode($skillsJson, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $entry) {
                        if (is_array($entry) && isset($entry['name'])) {
                            $skills[] = ['name' => (string) $entry['name']];
                        } elseif (is_string($entry)) {
                            $skills[] = ['name' => $entry];
                        }
                    }
                }
            }
        }

        if (empty($certifications)) {
            $certsSource = 'meta';
            $certsJson = get_user_meta($user->ID, 'pet_certs_json', true);
            if (is_string($certsJson) && $certsJson !== '') {
                $decoded = json_decode($certsJson, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $entry) {
                        if (is_array($entry) && isset($entry['name'])) {
                            $certifications[] = [
                                'name' => (string) $entry['name'],
                                'issuer' => isset($entry['issuer']) ? (string) $entry['issuer'] : '',
                                'obtained' => isset($entry['obtained']) ? (string) $entry['obtained'] : '',
                                'expiry' => isset($entry['expiry']) ? (string) $entry['expiry'] : '',
                            ];
                        } elseif (is_string($entry)) {
                            $certifications[] = [
                                'name' => $entry,
                                'issuer' => '',
                                'obtained' => '',
                                'expiry' => '',
                            ];
                        }
                    }
                }
            }
        }

        // Avatar
        $avatarUrl = get_avatar_url($user->ID, ['size' => 192]);
        $memberSince = date_i18n('F Y', strtotime($user->user_registered));

        // Certification status helper
        $certStatusFn = function (string $expiry, string $status): array {
            if ($status !== '') {
                $s = strtolower($status);
                if (in_array($s, ['active', 'valid'], true)) return ['active', __('Active', 'pet')];
                if (in_array($s, ['expired', 'revoked'], true)) return ['expired', __('Expired', 'pet')];
                if (in_array($s, ['expiring'], true)) return ['expiring', __('Expiring', 'pet')];
            }
            if ($expiry !== '') {
                $exp = strtotime($expiry);
                if ($exp !== false) {
                    $now = time();
                    if ($exp < $now) return ['expired', __('Expired', 'pet')];
                    if ($exp < $now + 90 * 86400) return ['expiring', __('Expiring Soon', 'pet')];
                    return ['active', __('Active', 'pet')];
                }
            }
            return ['unknown', __('Unknown', 'pet')];
        };

        $uid = 'pmp-' . $user->ID;

        $html = '<div class="pet-my-profile">';
        $html .= $message;

        // ── HEADER: Avatar + Identity ──
        $html .= '<div class="pmp-header">';
        $html .= '<div class="pmp-avatar">';
        $html .= '<img src="' . esc_url($avatarUrl) . '" alt="' . esc_attr($displayName) . '" />';
        $html .= '<span class="pmp-avatar-status"></span>';
        $html .= '</div>';
        $html .= '<div class="pmp-identity">';
        $html .= '<h2 class="pmp-name">' . esc_html($displayName) . '</h2>';
        if ($title !== '') {
            $html .= '<p class="pmp-title">' . esc_html((string) $title) . '</p>';
        }
        $html .= '<div class="pmp-contact-row">';
        $html .= '<span class="pmp-contact-item"><span class="pmp-contact-icon">&#9993;</span> ' . esc_html($email) . '</span>';
        if ($phone !== '' && $phone !== false) {
            $html .= '<span class="pmp-contact-item"><span class="pmp-contact-icon">&#9742;</span> ' . esc_html((string) $phone) . '</span>';
        }
        $html .= '</div>';
        $html .= '<div class="pmp-member-since">' . esc_html(sprintf(__('Member since %s', 'pet'), $memberSince)) . '</div>';
        $html .= '</div>';
        $html .= '<div class="pmp-header-actions">';
        $html .= '<button type="button" class="pmp-edit-btn" onclick="document.getElementById(\'' . esc_attr($uid) . '-edit\').classList.add(\'is-active\');document.getElementById(\'' . esc_attr($uid) . '-view\').classList.add(\'is-hidden\');this.style.display=\'none\';">';
        $html .= '&#9998; ' . esc_html__('Edit', 'pet');
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';

        // ── EDIT FORM (hidden by default) ──
        $html .= '<form method="post" id="' . esc_attr($uid) . '-edit" class="pmp-edit-form">';
        $html .= '<div class="pmp-card">';
        $html .= '<h3 class="pmp-card-title">' . esc_html__('Edit Personal Details', 'pet') . '</h3>';
        $html .= '<div class="pmp-form-grid">';

        $html .= '<div class="pmp-form-group">';
        $html .= '<label class="pmp-form-label">' . esc_html__('First Name', 'pet') . '</label>';
        $html .= '<input type="text" name="pet_first_name" class="pmp-form-input" value="' . esc_attr($firstName) . '" />';
        $html .= '</div>';

        $html .= '<div class="pmp-form-group">';
        $html .= '<label class="pmp-form-label">' . esc_html__('Last Name', 'pet') . '</label>';
        $html .= '<input type="text" name="pet_last_name" class="pmp-form-input" value="' . esc_attr($lastName) . '" />';
        $html .= '</div>';

        $html .= '<div class="pmp-form-group pmp-full-width">';
        $html .= '<label class="pmp-form-label">' . esc_html__('Display Name', 'pet') . '</label>';
        $html .= '<input type="text" name="pet_display_name" class="pmp-form-input" value="' . esc_attr($displayName) . '" />';
        $html .= '</div>';

        $html .= '<div class="pmp-form-group">';
        $html .= '<label class="pmp-form-label">' . esc_html__('Phone', 'pet') . '</label>';
        $html .= '<input type="text" name="pet_phone" class="pmp-form-input" value="' . esc_attr((string) $phone) . '" />';
        $html .= '</div>';

        $html .= '<div class="pmp-form-group">';
        $html .= '<label class="pmp-form-label">' . esc_html__('Title / Position', 'pet') . '</label>';
        $html .= '<input type="text" name="pet_title" class="pmp-form-input" value="' . esc_attr((string) $title) . '" />';
        $html .= '</div>';

        $html .= '<div class="pmp-form-group pmp-full-width">';
        $html .= '<label class="pmp-form-label">' . esc_html__('Email', 'pet') . '</label>';
        $html .= '<div class="pmp-form-static">' . esc_html($email) . '</div>';
        $html .= '</div>';

        $html .= '</div>'; // .pmp-form-grid

        wp_nonce_field('pet_my_profile_update', 'pet_my_profile_nonce');
        $html .= '<input type="hidden" name="pet_my_profile_action" value="update_profile" />';

        $html .= '<div class="pmp-form-actions">';
        $html .= '<button type="submit" class="pmp-save-btn">&#10003; ' . esc_html__('Save Changes', 'pet') . '</button>';
        $html .= '<button type="button" class="pmp-cancel-btn" onclick="document.getElementById(\'' . esc_attr($uid) . '-edit\').classList.remove(\'is-active\');document.getElementById(\'' . esc_attr($uid) . '-view\').classList.remove(\'is-hidden\');document.querySelector(\'.pmp-edit-btn\').style.display=\'\';">';
        $html .= esc_html__('Cancel', 'pet');
        $html .= '</button>';
        $html .= '</div>';

        $html .= '</div>'; // .pmp-card
        $html .= '</form>';

        // ── VIEW SECTIONS ──
        $html .= '<div id="' . esc_attr($uid) . '-view" class="pmp-view-section">';

        // Roles & Teams
        if ($atts['show_roles'] === '1') {
            $html .= '<div class="pmp-card">';
            $html .= '<h3 class="pmp-card-title">' . esc_html__('Roles & Teams', 'pet') . '</h3>';

            if (!empty($wpRoles)) {
                $html .= '<div class="pmp-subsection-label">' . esc_html__('Roles', 'pet') . '</div>';
                $html .= '<div class="pmp-pills">';
                foreach ($wpRoles as $role) {
                    $html .= '<span class="pmp-pill pmp-pill--role"><span class="pmp-pill-icon">&#9733;</span>' . esc_html($role) . '</span>';
                }
                $html .= '</div>';
            }

            if (!empty($petTeams)) {
                $html .= '<div class="pmp-subsection-label">' . esc_html__('Teams', 'pet') . '</div>';
                $html .= '<div class="pmp-pills">';
                foreach ($petTeams as $team) {
                    $html .= '<span class="pmp-pill pmp-pill--team"><span class="pmp-pill-icon">&#9824;</span>' . esc_html($team) . '</span>';
                }
                $html .= '</div>';
            }

            if (empty($wpRoles) && empty($petTeams)) {
                $html .= '<div class="pmp-empty">' . esc_html__('No roles or teams assigned.', 'pet') . '</div>';
            }

            $html .= '</div>'; // .pmp-card
        }

        // Skills
        if ($atts['show_skills'] === '1') {
            $html .= '<div class="pmp-card">';
            $html .= '<h3 class="pmp-card-title">' . esc_html__('Skills', 'pet') . '</h3>';
            if (empty($skills)) {
                $html .= '<div class="pmp-empty">' . esc_html__('No skills recorded yet.', 'pet') . '</div>';
            } else {
                $html .= '<div class="pmp-skills-list">';
                foreach ($skills as $skill) {
                    $name = isset($skill['name']) ? (string) $skill['name'] : '';
                    $selfRating = isset($skill['self_rating']) ? (int) $skill['self_rating'] : 0;
                    $mgrRating = isset($skill['manager_rating']) ? (int) $skill['manager_rating'] : 0;
                    $effectiveDate = isset($skill['effective_date']) ? (string) $skill['effective_date'] : '';
                    $selfPct = min(100, max(0, $selfRating * 20));
                    $mgrPct = min(100, max(0, $mgrRating * 20));

                    $html .= '<div class="pmp-skill-row">';
                    $html .= '<div class="pmp-skill-header">';
                    $html .= '<span class="pmp-skill-name">' . esc_html($name) . '</span>';
                    if ($effectiveDate !== '') {
                        $html .= '<span class="pmp-skill-date">' . esc_html($effectiveDate) . '</span>';
                    }
                    $html .= '</div>';

                    $html .= '<div class="pmp-skill-bars">';
                    if ($selfRating > 0) {
                        $html .= '<div class="pmp-skill-bar-group">';
                        $html .= '<span class="pmp-skill-bar-label">' . esc_html__('Self', 'pet') . '</span>';
                        $html .= '<div class="pmp-skill-bar-track"><div class="pmp-skill-bar-fill pmp-skill-bar-fill--self" style="width:' . $selfPct . '%"></div></div>';
                        $html .= '</div>';
                    }
                    if ($mgrRating > 0) {
                        $html .= '<div class="pmp-skill-bar-group">';
                        $html .= '<span class="pmp-skill-bar-label">' . esc_html__('Manager', 'pet') . '</span>';
                        $html .= '<div class="pmp-skill-bar-track"><div class="pmp-skill-bar-fill pmp-skill-bar-fill--mgr" style="width:' . $mgrPct . '%"></div></div>';
                        $html .= '</div>';
                    }
                    if ($selfRating > 0 || $mgrRating > 0) {
                        $best = max($selfRating, $mgrRating);
                        $html .= '<span class="pmp-skill-rating">' . $best . '/5</span>';
                    }
                    $html .= '</div>'; // .pmp-skill-bars

                    $html .= '</div>'; // .pmp-skill-row
                }
                $html .= '</div>'; // .pmp-skills-list
            }
            $html .= '</div>'; // .pmp-card
        }

        // Certifications
        if ($atts['show_certs'] === '1') {
            $html .= '<div class="pmp-card">';
            $html .= '<h3 class="pmp-card-title">' . esc_html__('Certifications', 'pet') . '</h3>';
            if (empty($certifications)) {
                $html .= '<div class="pmp-empty">' . esc_html__('No certifications recorded yet.', 'pet') . '</div>';
            } else {
                $html .= '<div class="pmp-certs-list">';
                foreach ($certifications as $cert) {
                    $cName = isset($cert['name']) ? (string) $cert['name'] : '';
                    $cIssuer = isset($cert['issuer']) ? (string) $cert['issuer'] : '';
                    $cObtained = isset($cert['obtained']) ? (string) $cert['obtained'] : '';
                    $cExpiry = isset($cert['expiry']) ? (string) $cert['expiry'] : '';
                    $cStatus = isset($cert['status']) ? (string) $cert['status'] : '';
                    [$badgeClass, $badgeLabel] = $certStatusFn($cExpiry, $cStatus);

                    $html .= '<div class="pmp-cert-item">';
                    $html .= '<div class="pmp-cert-info">';
                    $html .= '<div class="pmp-cert-name">' . esc_html($cName) . '</div>';
                    if ($cIssuer !== '') {
                        $html .= '<div class="pmp-cert-issuer">' . esc_html($cIssuer) . '</div>';
                    }
                    $dateParts = [];
                    if ($cObtained !== '') {
                        $dateParts[] = sprintf(__('Obtained: %s', 'pet'), $cObtained);
                    }
                    if ($cExpiry !== '') {
                        $dateParts[] = sprintf(__('Expires: %s', 'pet'), $cExpiry);
                    }
                    if (!empty($dateParts)) {
                        $html .= '<div class="pmp-cert-dates">' . esc_html(implode(' &middot; ', $dateParts)) . '</div>';
                    }
                    $html .= '</div>'; // .pmp-cert-info
                    $html .= '<span class="pmp-cert-badge pmp-cert-badge--' . esc_attr($badgeClass) . '">' . esc_html($badgeLabel) . '</span>';
                    $html .= '</div>'; // .pmp-cert-item
                }
                $html .= '</div>'; // .pmp-certs-list
            }
            $html .= '</div>'; // .pmp-card
        }

        $html .= '</div>'; // .pmp-view-section
        $html .= '</div>'; // .pet-my-profile

        return $html;
    }

    private function enqueueShortcodesCss(): void
    {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'pet-shortcodes',
                plugin_dir_url(dirname(__DIR__, 2)) . 'assets/shortcodes.css',
                [],
                '1.0.0'
            );
        }
    }

    private function scFormatDue(string $due): array
    {
        if ($due === '') return ['', 'soon'];
        $today = date('Y-m-d');
        if ($due < $today) return [sprintf(__('Overdue: %s', 'pet'), $due), 'overdue'];
        if ($due === $today) return [__('Due today', 'pet'), 'today'];
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($due === $tomorrow) return [__('Due tomorrow', 'pet'), 'soon'];
        return [$due, 'soon'];
    }

    private function scFormatSla(?int $minutes): array
    {
        if ($minutes === null) return ['', 'ok'];
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;
        $label = $h > 0 ? sprintf('%dh %dm', $h, $m) : sprintf('%dm', $m);
        if ($minutes < 0) return [sprintf(__('Breached %s', 'pet'), $label), 'breach'];
        if ($minutes < 60) return [sprintf(__('%s left', 'pet'), $label), 'warn'];
        return [sprintf(__('%s left', 'pet'), $label), 'ok'];
    }

    public function renderMyWork(array $atts = [], ?string $content = null): string
    {
        $this->enqueueShortcodesCss();

        if (!is_user_logged_in()) {
            return '<div class="pet-sc pet-my-work"><div class="sc-empty">' . esc_html__('Please log in to view your work.', 'pet') . '</div></div>';
        }

        $userId = (string) get_current_user_id();

        $supportItems = [];
        $projectItems = [];
        $departmentQueues = [];

        try {
            $container = ContainerFactory::create();
            $workItemRepo = $container->get(WorkItemRepository::class);
            $ticketRepo = $container->get(TicketRepository::class);
            $customerRepo = $container->get(CustomerRepository::class);
            $employeeRepo = $container->get(EmployeeRepository::class);
            $teamRepo = $container->get(TeamRepository::class);
            $queueVisibility = $container->get(\Pet\Application\Work\Service\WorkQueueVisibilityService::class);
            $queueQuery = $container->get(\Pet\Application\Work\Service\WorkQueueQueryService::class);

            // 1) Build support items based on the same assignment mapping used by TicketController
            $allItems = $workItemRepo->findAll();
            $ticketAssignments = [];
            foreach ($allItems as $item) {
                if ($item->getSourceType() !== 'ticket') {
                    continue;
                }
                $assigned = $item->getAssignedUserId();
                if ($assigned === null || $assigned === '') {
                    continue;
                }
                $ticketId = (int) $item->getSourceId();
                $ticketAssignments[$ticketId] = $assigned;
            }

            if (!empty($ticketAssignments)) {
                $tickets = $ticketRepo->findActive();

                foreach ($tickets as $ticket) {
                    $ticketId = $ticket->id();
                    $assigned = $ticketAssignments[$ticketId] ?? null;

                    if ($assigned === null || $assigned === '' || (string) $assigned !== (string) $userId) {
                        continue;
                    }

                    $relatedItem = null;
                    foreach ($allItems as $item) {
                        if ($item->getSourceType() === 'ticket' && (int) $item->getSourceId() === (int) $ticketId) {
                            $relatedItem = $item;
                            break;
                        }
                    }

                    if ($relatedItem !== null && $relatedItem->getStatus() === 'completed') {
                        continue;
                    }

                    $statusLabel = '';
                    $dueDate = '';

                    if ($relatedItem !== null) {
                        $status = $relatedItem->getStatus();
                        $statusLabel = $status === 'active'
                            ? __('In Progress', 'pet')
                            : ($status === 'waiting' ? __('Waiting', 'pet') : ucfirst($status));

                        $dueAt = $relatedItem->getScheduledDueUtc();
                        if ($dueAt) {
                            $dueDate = $dueAt->format('Y-m-d');
                        }
                    } else {
                        $statusLabel = ucfirst($ticket->status());
                    }

                    $resolutionDue = $ticket->resolutionDueAt();
                    if ($resolutionDue) {
                        $dueDate = $resolutionDue->format('Y-m-d');
                    }

                    $customerName = '';
                    $customer = $customerRepo->findById($ticket->customerId());
                    if ($customer) {
                        $customerName = $customer->name();
                    }

                    $slaMinutes = $relatedItem !== null ? $relatedItem->getSlaTimeRemainingMinutes() : null;
                    $priority = $relatedItem !== null ? $relatedItem->getPriorityScore() : 0.0;

                    $supportItems[] = [
                        'title' => $ticket->subject(),
                        'reference' => sprintf(__('Ticket #%d', 'pet'), $ticketId),
                        'status' => $statusLabel,
                        'due' => $dueDate,
                        'customer' => $customerName,
                        'link' => admin_url('admin.php?page=pet-support'),
                        'sla_minutes' => $slaMinutes,
                        'priority' => $priority,
                    ];
                }
            }

            // 2) Project work items (non-ticket) assigned to the current user
            foreach ($allItems as $item) {
                if ($item->getSourceType() === 'ticket') {
                    continue;
                }

                $assigned = $item->getAssignedUserId();
                if ($assigned === null || $assigned === '' || (string) $assigned !== (string) $userId) {
                    continue;
                }

                $status = $item->getStatus();
                if ($status === 'completed') {
                    continue;
                }

                $statusLabel = $status === 'active' ? __('In Progress', 'pet') : ($status === 'waiting' ? __('Waiting', 'pet') : ucfirst($status));
                $dueAt = $item->getScheduledDueUtc();
                $dueDate = $dueAt ? $dueAt->format('Y-m-d') : '';

                $reference = sprintf(__('Work item %s', 'pet'), $item->getId());
                $link = admin_url('admin.php?page=pet-time');

                $projectItems[] = [
                    'title' => $reference,
                    'reference' => $reference,
                    'status' => $statusLabel,
                    'due' => $dueDate,
                    'customer' => '',
                    'link' => $link,
                ];
            }

            $visibleQueues = $queueVisibility->listVisibleQueues((int)$userId, current_user_can('manage_options'));
            foreach ($visibleQueues as $q) {
                $queueKey = (string)($q['queue_key'] ?? '');
                if (!str_starts_with($queueKey, 'support:team:')) {
                    continue;
                }
                $parts = explode(':', $queueKey);
                $teamId = $parts[2] ?? null;
                if ($teamId === null) {
                    continue;
                }
                $team = $teamRepo->find((int)$teamId);
                if (!$team || !$team->isActive()) {
                    continue;
                }

                $departmentName = $team->name();
                $items = $queueQuery->listItemsForQueue($queueKey);
                if (empty($items)) {
                    continue;
                }

                foreach ($items as $row) {
                    $title = (string)($row['title'] ?? '');
                    $reference = (string)($row['reference_code'] ?? '');
                    $statusLabel = '';
                    $status = (string)($row['status'] ?? '');
                    if ($status === 'active') {
                        $statusLabel = __('In Progress', 'pet');
                    } elseif ($status === 'waiting') {
                        $statusLabel = __('Waiting', 'pet');
                    } elseif ($status !== '') {
                        $statusLabel = ucfirst($status);
                    }

                    $dueDate = '';
                    $dueAt = $row['due_at'] ?? null;
                    if (is_string($dueAt) && $dueAt !== '') {
                        $dueDate = substr($dueAt, 0, 10);
                    }

                    $customerName = '';
                    $customerId = $row['customer_id'] ?? null;
                    if (is_int($customerId) && $customerId > 0) {
                        $customer = $customerRepo->findById($customerId);
                        if ($customer) {
                            $customerName = $customer->name();
                        }
                    }

                    $link = admin_url('admin.php?page=pet-support');
                    $departmentQueues[$departmentName][] = [
                        'title' => $title !== '' ? $title : $reference,
                        'reference' => $reference,
                        'status' => $statusLabel,
                        'due' => $dueDate,
                        'due_sort' => $dueDate !== '' ? $dueDate : '9999-12-31',
                        'customer' => $customerName,
                        'link' => $link,
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('PET [pet_my_work] data error: ' . $e->getMessage());
        }

        if (!empty($departmentQueues)) {
            ksort($departmentQueues, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($departmentQueues as $departmentName => &$rows) {
                usort($rows, function (array $a, array $b): int {
                    if ($a['due_sort'] === $b['due_sort']) {
                        return strcmp((string) $a['title'], (string) $b['title']);
                    }
                    return $a['due_sort'] <=> $b['due_sort'];
                });
            }
            unset($rows);
        }

        // KPI calculations
        $today = date('Y-m-d');
        $atRisk = 0;
        foreach ($supportItems as $si) {
            $sla = $si['sla_minutes'] ?? null;
            if ($sla !== null && $sla < 0) $atRisk++;
        }
        $dueToday = count(array_filter($supportItems, function ($r) use ($today) { return ($r['due'] ?? '') === $today; }));
        $totalQueue = 0;
        foreach ($departmentQueues as $rows) { $totalQueue += count($rows); }

        $html = '<div class="pet-sc pet-my-work">';
        $html .= '<h2 class="sc-title">' . esc_html__('My Work', 'pet') . '</h2>';
        $html .= '<p class="sc-subtitle">' . esc_html__('Your assigned tickets, tasks, and department queues.', 'pet') . '</p>';

        // KPI strip
        $html .= '<div class="sc-kpi-strip">';
        $html .= '<div class="sc-kpi sc-kpi--accent"><div class="sc-kpi-value">' . count($supportItems) . '</div><div class="sc-kpi-label">' . esc_html__('My Tickets', 'pet') . '</div></div>';
        $html .= '<div class="sc-kpi sc-kpi--' . ($atRisk > 0 ? 'danger' : 'success') . '"><div class="sc-kpi-value">' . $atRisk . '</div><div class="sc-kpi-label">' . esc_html__('At Risk', 'pet') . '</div></div>';
        $html .= '<div class="sc-kpi sc-kpi--' . ($dueToday > 0 ? 'warn' : 'teal') . '"><div class="sc-kpi-value">' . $dueToday . '</div><div class="sc-kpi-label">' . esc_html__('Due Today', 'pet') . '</div></div>';
        $html .= '<div class="sc-kpi sc-kpi--purple"><div class="sc-kpi-value">' . count($projectItems) . '</div><div class="sc-kpi-label">' . esc_html__('Tasks', 'pet') . '</div></div>';
        $html .= '<div class="sc-kpi sc-kpi--teal"><div class="sc-kpi-value">' . $totalQueue . '</div><div class="sc-kpi-label">' . esc_html__('Queue', 'pet') . '</div></div>';
        $html .= '</div>';

        // Tabs
        $html .= '<div class="sc-tabs">';
        $html .= '<button type="button" class="sc-tab is-active" data-panel="sc-mine">' . esc_html__('My Items', 'pet') . '</button>';
        $html .= '<button type="button" class="sc-tab" data-panel="sc-dept">' . esc_html__('Department Queue', 'pet') . '</button>';
        $html .= '</div>';

        // Panel: My Items
        $html .= '<div class="sc-panel is-visible" id="sc-mine">';

        // Support tickets
        $html .= '<div class="sc-card">';
        $html .= '<div class="sc-card-title">' . esc_html__('Support Tickets', 'pet') . ' <span class="sc-badge">' . count($supportItems) . '</span></div>';
        if (empty($supportItems)) {
            $html .= '<div class="sc-empty"><span class="sc-empty-icon">&#10003;</span>' . esc_html__('No support tickets assigned.', 'pet') . '</div>';
        } else {
            foreach ($supportItems as $row) {
                [$dueLabel, $dueClass] = $this->scFormatDue((string) $row['due']);
                [$slaLabel, $slaClass] = $this->scFormatSla($row['sla_minutes'] ?? null);
                $statusSlug = strtolower(str_replace(' ', '', (string) $row['status']));
                $pillClass = 'sc-pill--status';
                if (strpos($statusSlug, 'progress') !== false) $pillClass = 'sc-pill--active';
                elseif ($statusSlug === 'waiting') $pillClass = 'sc-pill--waiting';

                $html .= '<div class="sc-item">';
                $html .= '<div class="sc-item-icon sc-item-icon--ticket">&#127915;</div>';
                $html .= '<div class="sc-item-body">';
                $html .= '<div class="sc-item-title">';
                if ($row['link']) $html .= '<a href="' . esc_url((string) $row['link']) . '">';
                $html .= esc_html((string) $row['title']);
                if ($row['link']) $html .= '</a>';
                $html .= '</div>';
                $html .= '<div class="sc-item-meta">';
                $html .= '<span>' . esc_html((string) $row['reference']) . '</span>';
                if ($row['customer'] !== '') $html .= '<span class="sc-item-meta-sep"></span><span>' . esc_html((string) $row['customer']) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="sc-item-right">';
                $html .= '<span class="sc-pill ' . esc_attr($pillClass) . '">' . esc_html((string) $row['status']) . '</span>';
                if ($slaLabel !== '') $html .= '<span class="sc-sla sc-sla--' . esc_attr($slaClass) . '">' . esc_html($slaLabel) . '</span>';
                if ($dueLabel !== '') $html .= '<span class="sc-due sc-due--' . esc_attr($dueClass) . '">' . esc_html($dueLabel) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        // Project tasks
        $html .= '<div class="sc-card">';
        $html .= '<div class="sc-card-title">' . esc_html__('Project Tasks', 'pet') . ' <span class="sc-badge">' . count($projectItems) . '</span></div>';
        if (empty($projectItems)) {
            $html .= '<div class="sc-empty"><span class="sc-empty-icon">&#128203;</span>' . esc_html__('No project tasks assigned.', 'pet') . '</div>';
        } else {
            foreach ($projectItems as $row) {
                [$dueLabel, $dueClass] = $this->scFormatDue((string) $row['due']);
                $html .= '<div class="sc-item">';
                $html .= '<div class="sc-item-icon sc-item-icon--task">&#128203;</div>';
                $html .= '<div class="sc-item-body">';
                $html .= '<div class="sc-item-title">' . esc_html((string) $row['title']) . '</div>';
                $html .= '<div class="sc-item-meta"><span>' . esc_html((string) $row['reference']) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="sc-item-right">';
                $html .= '<span class="sc-pill sc-pill--active">' . esc_html((string) $row['status']) . '</span>';
                if ($dueLabel !== '') $html .= '<span class="sc-due sc-due--' . esc_attr($dueClass) . '">' . esc_html($dueLabel) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';
        $html .= '</div>'; // #sc-mine

        // Panel: Department Queue
        $html .= '<div class="sc-panel" id="sc-dept">';
        if (empty($departmentQueues)) {
            $html .= '<div class="sc-empty"><span class="sc-empty-icon">&#128230;</span>' . esc_html__('No unassigned items in your department queues.', 'pet') . '</div>';
        } else {
            foreach ($departmentQueues as $departmentName => $rows) {
                $html .= '<div class="sc-card">';
                $html .= '<div class="sc-card-title">' . esc_html((string) $departmentName) . ' <span class="sc-badge">' . count($rows) . '</span></div>';
                foreach ($rows as $row) {
                    [$dueLabel, $dueClass] = $this->scFormatDue((string) $row['due']);
                    $html .= '<div class="sc-item">';
                    $html .= '<div class="sc-item-icon sc-item-icon--ticket">&#127915;</div>';
                    $html .= '<div class="sc-item-body">';
                    $html .= '<div class="sc-item-title">' . esc_html((string) $row['title']) . '</div>';
                    $html .= '<div class="sc-item-meta">';
                    $html .= '<span>' . esc_html((string) $row['reference']) . '</span>';
                    if (($row['customer'] ?? '') !== '') $html .= '<span class="sc-item-meta-sep"></span><span>' . esc_html((string) $row['customer']) . '</span>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '<div class="sc-item-right">';
                    $html .= '<span class="sc-pill sc-pill--pending">' . esc_html__('Unassigned', 'pet') . '</span>';
                    if ($dueLabel !== '') $html .= '<span class="sc-due sc-due--' . esc_attr($dueClass) . '">' . esc_html($dueLabel) . '</span>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }
        $html .= '</div>'; // #sc-dept

        // Tab switching script
        $html .= '<script>(function(){var r=document.querySelector(".pet-my-work");if(!r)return;r.querySelectorAll(".sc-tab").forEach(function(t){t.addEventListener("click",function(){r.querySelectorAll(".sc-tab").forEach(function(x){x.classList.remove("is-active")});t.classList.add("is-active");r.querySelectorAll(".sc-panel").forEach(function(p){p.classList.toggle("is-visible",p.id===t.getAttribute("data-panel"))})})})})();</script>';

        $html .= '</div>';

        return $html;
    }

    public function renderMyCalendar(array $atts = [], ?string $content = null): string
    {
        $this->enqueueShortcodesCss();

        if (!is_user_logged_in()) {
            return '<div class="pet-sc pet-my-calendar"><div class="sc-empty">' . esc_html__('Please log in to view your calendar.', 'pet') . '</div></div>';
        }

        $userId = (string) get_current_user_id();
        $now = new DateTimeImmutable('now');
        $end = $now->modify('+14 days');

        $itemsByDate = [];

        try {
            $container = ContainerFactory::create();
            $workItemRepo = $container->get(WorkItemRepository::class);
            $ticketRepo = $container->get(TicketRepository::class);

            $items = $workItemRepo->findByAssignedUser($userId);
            foreach ($items as $item) {
                $dueAt = $item->getScheduledDueUtc();
                if (!$dueAt) {
                    continue;
                }
                if ($dueAt < $now || $dueAt > $end) {
                    continue;
                }

                $dateKey = $dueAt->format('Y-m-d');
                $timeLabel = $dueAt->format('H:i');
                $type = $item->getSourceType() === 'ticket' ? __('Ticket', 'pet') : ($item->getSourceType() === 'escalation' ? __('Escalation', 'pet') : __('Work', 'pet'));

                $title = '';
                $link = '';
                if ($item->getSourceType() === 'ticket') {
                    $ticketId = (int) $item->getSourceId();
                    $ticket = $ticketRepo->findById($ticketId);
                    if ($ticket) {
                        $title = $ticket->subject();
                    } else {
                        $title = sprintf(__('Ticket #%d', 'pet'), $ticketId);
                    }
                    $link = admin_url('admin.php?page=pet-support');
                } else {
                    $title = sprintf(__('Work item %s', 'pet'), $item->getId());
                    $link = admin_url('admin.php?page=pet-time');
                }

                $itemsByDate[$dateKey][] = [
                    'time' => $timeLabel,
                    'type' => $type,
                    'sourceType' => $item->getSourceType(),
                    'title' => $title,
                    'link' => $link,
                ];
            }
        } catch (\Throwable $e) {
            error_log('PET [pet_my_calendar] data error: ' . $e->getMessage());
        }

        ksort($itemsByDate);

        $todayStr = $now->format('Y-m-d');
        $tomorrowStr = $now->modify('+1 day')->format('Y-m-d');
        $totalItems = 0;
        foreach ($itemsByDate as $entries) { $totalItems += count($entries); }

        $html = '<div class="pet-sc pet-my-calendar">';
        $html .= '<h2 class="sc-title">' . esc_html__('My Calendar', 'pet') . '</h2>';
        $html .= '<p class="sc-subtitle">' . esc_html(sprintf(__('%d items in the next 14 days', 'pet'), $totalItems)) . '</p>';

        if (empty($itemsByDate)) {
            $html .= '<div class="sc-empty"><span class="sc-empty-icon">&#128197;</span>' . esc_html__('No upcoming items in the next 14 days.', 'pet') . '</div>';
            $html .= '</div>';
            return $html;
        }

        foreach ($itemsByDate as $date => $entries) {
            $isToday = ($date === $todayStr);
            $isTomorrow = ($date === $tomorrowStr);
            $friendlyDate = $isToday ? __('Today', 'pet') : ($isTomorrow ? __('Tomorrow', 'pet') : '');
            $formattedDate = date_i18n('l, M j', strtotime($date));

            $html .= '<div class="sc-cal-day">';
            $html .= '<div class="sc-cal-date' . ($isToday ? ' sc-cal-date--today' : '') . '">';
            if ($friendlyDate !== '') {
                $html .= esc_html($friendlyDate) . ' <span class="sc-cal-date-sub">' . esc_html($formattedDate) . '</span>';
            } else {
                $html .= esc_html($formattedDate);
            }
            $html .= '</div>';

            $html .= '<div class="sc-cal-timeline">';
            foreach ($entries as $entry) {
                $src = $entry['sourceType'] ?? 'work';
                $html .= '<div class="sc-cal-entry sc-cal-entry--' . esc_attr($src) . '">';
                $html .= '<span class="sc-cal-time">' . esc_html($entry['time']) . '</span>';
                $html .= '<span class="sc-cal-type">' . esc_html($entry['type']) . '</span>';
                if ($entry['link']) {
                    $html .= '<a href="' . esc_url((string) $entry['link']) . '" class="sc-cal-title">' . esc_html((string) $entry['title']) . '</a>';
                } else {
                    $html .= '<span class="sc-cal-title">' . esc_html((string) $entry['title']) . '</span>';
                }
                $html .= '</div>';
            }
            $html .= '</div>'; // .sc-cal-timeline
            $html .= '</div>'; // .sc-cal-day
        }

        $html .= '</div>';

        return $html;
    }

    public function renderActivityStream(array $atts = [], ?string $content = null): string
    {
        if (!is_user_logged_in()) {
            return '<div class="pet-activity-stream"><p>' . esc_html__('Please log in to view activity.', 'pet') . '</p></div>';
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'pet-activity-stream',
                plugin_dir_url(dirname(__DIR__, 2)) . 'assets/activity-stream.css',
                [],
                '1.0.0'
            );
        }

        $atts = shortcode_atts(
            [
                'mode' => 'default',
                'limit' => '20',
                'scope' => 'my',
                'team' => 'current',
                'types' => 'all',
                'window_days' => '14',
                'show_filters' => 'true',
                'refresh' => '0',
                'title' => 'Activity',
                'empty_message' => 'No recent activity.',
                'link_mode' => 'open',
            ],
            $atts,
            'pet_activity_stream'
        );

        $mode = in_array($atts['mode'], ['default', 'compact', 'wallboard'], true) ? $atts['mode'] : 'default';

        $limit = (int) $atts['limit'];
        if ($limit <= 0) {
            $limit = 20;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $windowDays = (int) $atts['window_days'];
        if ($windowDays <= 0) {
            $windowDays = 14;
        }

        $rawTypes = trim((string) $atts['types']);
        $types = [];
        if ($rawTypes !== '' && strtolower($rawTypes) !== 'all') {
            $types = array_filter(array_map('trim', explode(',', $rawTypes)));
        }

        $showFilters = strtolower((string) $atts['show_filters']) !== 'false';

        $refresh = (int) $atts['refresh'];
        if ($refresh < 0) {
            $refresh = 0;
        }

        $title = (string) $atts['title'];
        $emptyMessage = (string) $atts['empty_message'];
        $linkMode = (string) $atts['link_mode'] === 'none' ? 'none' : 'open';

        $events = [];

        try {
            if (class_exists('\WP_REST_Request') && function_exists('rest_do_request')) {
                $request = new \WP_REST_Request('GET', '/pet/v1/activity');
                $request->set_param('limit', $limit);
                $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $from = $now->modify('-' . $windowDays . ' days');
                $request->set_param('from', $from->format('c'));

                if (!empty($types)) {
                    $request->set_param('event_type', array_values($types));
                }

                $search = isset($atts['q']) ? (string) $atts['q'] : '';
                if ($search !== '') {
                    $request->set_param('q', $search);
                }

                $response = rest_do_request($request);
                if (!$response->is_error()) {
                    $data = $response->get_data();
                    if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
                        $events = $data['items'];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('PET [pet_activity_stream] data error: ' . $e->getMessage());
        }

        $classes = ['pet-activity-stream', 'pet-activity-mode-' . $mode];
        if ($showFilters && $mode !== 'wallboard') {
            $classes[] = 'pet-activity-has-filters';
        }

        $html = '<div class="' . esc_attr(implode(' ', $classes)) . '"';
        if ($refresh > 0) {
            $html .= ' data-refresh="' . esc_attr((string) $refresh) . '"';
        }
        $html .= '>';

        $html .= '<div class="pet-activity-header">';
        $html .= '<h2 class="pet-activity-title">' . esc_html($title) . '</h2>';
        if ($showFilters && $mode !== 'wallboard') {
            $html .= '<div class="pet-activity-filters">';
            $html .= '<span class="pet-activity-filter-chip pet-activity-filter-scope">' . esc_html(sprintf(__('Scope: %s', 'pet'), ucfirst((string) $atts['scope']))) . '</span>';
            $html .= '<span class="pet-activity-filter-chip pet-activity-filter-window">' . esc_html(sprintf(__('Last %d days', 'pet'), $windowDays)) . '</span>';
            if (!empty($types)) {
                $html .= '<span class="pet-activity-filter-chip pet-activity-filter-types">' . esc_html(implode(', ', $types)) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        if (empty($events)) {
            $html .= '<div class="pet-activity-empty">' . esc_html($emptyMessage) . '</div>';
            if ($refresh > 0) {
                $html .= $this->renderActivityRefreshScript();
            }
            $html .= '</div>';
            return $html;
        }

        $grouped = [
            'today' => [],
            'yesterday' => [],
            'this_week' => [],
            'older' => [],
        ];

        $now = new DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0, 0);
        $yesterdayStart = $todayStart->modify('-1 day');
        $weekStart = $todayStart->modify('-7 days');

        foreach ($events as $event) {
            $occurredAt = isset($event['occurred_at']) ? $event['occurred_at'] : null;
            $dt = null;
            if (is_string($occurredAt) && $occurredAt !== '') {
                try {
                    $dt = new DateTimeImmutable($occurredAt);
                } catch (\Throwable $e) {
                    $dt = null;
                }
            }
            if (!$dt) {
                $grouped['older'][] = $event;
                continue;
            }

            if ($dt >= $todayStart) {
                $grouped['today'][] = $event;
            } elseif ($dt >= $yesterdayStart) {
                $grouped['yesterday'][] = $event;
            } elseif ($dt >= $weekStart) {
                $grouped['this_week'][] = $event;
            } else {
                $grouped['older'][] = $event;
            }
        }

        $labels = [
            'today' => __('Today', 'pet'),
            'yesterday' => __('Yesterday', 'pet'),
            'this_week' => __('This Week', 'pet'),
            'older' => __('Older', 'pet'),
        ];

        foreach (['today', 'yesterday', 'this_week', 'older'] as $key) {
            if (empty($grouped[$key])) {
                continue;
            }
            $html .= '<div class="pet-activity-group">';
            $html .= '<h3 class="pet-activity-group-header">' . esc_html($labels[$key]) . '</h3>';
            $html .= '<ul class="pet-activity-list">';

            usort($grouped[$key], function ($a, $b) {
                $aTime = isset($a['occurred_at']) ? strtotime((string) $a['occurred_at']) : 0;
                $bTime = isset($b['occurred_at']) ? strtotime((string) $b['occurred_at']) : 0;
                return $bTime <=> $aTime;
            });

            foreach ($grouped[$key] as $event) {
                $html .= $this->renderActivityCard($event, $mode === 'wallboard', $linkMode);
            }

            $html .= '</ul>';
            $html .= '</div>';
        }

        if ($refresh > 0) {
            $html .= $this->renderActivityRefreshScript();
        }

        $html .= '</div>';

        return $html;
    }

    public function renderActivityWallboard(array $atts = [], ?string $content = null): string
    {
        if (!is_user_logged_in()) {
            return '<div class="pet-activity-wallboard"><p>' . esc_html__('Please log in to view activity.', 'pet') . '</p></div>';
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'pet-activity-stream',
                plugin_dir_url(dirname(__DIR__, 2)) . 'assets/activity-stream.css',
                [],
                '1.0.0'
            );
        }

        $atts = shortcode_atts(
            [
                'limit' => '20',
                'refresh' => '30',
            ],
            $atts,
            'pet_activity_wallboard'
        );

        $limit = (int) $atts['limit'];
        if ($limit <= 0) {
            $limit = 20;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $refresh = (int) $atts['refresh'];
        if ($refresh <= 0) {
            $refresh = 30;
        }

        $events = [];

        try {
            if (class_exists('\WP_REST_Request') && function_exists('rest_do_request')) {
                $request = new \WP_REST_Request('GET', '/pet/v1/activity');
                $request->set_param('limit', $limit);
                $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $from = $now->modify('-1 day');
                $request->set_param('from', $from->format('c'));

                $response = rest_do_request($request);
                if (!$response->is_error()) {
                    $data = $response->get_data();
                    if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
                        $events = $data['items'];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('PET [pet_activity_wallboard] data error: ' . $e->getMessage());
        }

        $html = '<div class="pet-activity-wallboard" data-refresh="' . esc_attr((string) $refresh) . '">';

        if (empty($events)) {
            $html .= '<p>' . esc_html__('No recent activity.', 'pet') . '</p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<ul class="pet-activity-wallboard-list">';

        usort($events, function ($a, $b) {
            $aTime = isset($a['occurred_at']) ? strtotime((string) $a['occurred_at']) : 0;
            $bTime = isset($b['occurred_at']) ? strtotime((string) $b['occurred_at']) : 0;
            return $bTime <=> $aTime;
        });

        $events = array_slice($events, 0, $limit);

        foreach ($events as $event) {
            $html .= $this->renderActivityCard($event, true, 'open');
        }

        $html .= '</ul>';

        $html .= $this->renderActivityRefreshScript();

        $html .= '</div>';

        return $html;
    }

    public function renderMyConversations(array $atts = [], ?string $content = null): string
    {
        $this->enqueueShortcodesCss();

        if (!is_user_logged_in()) {
            return '<div class="pet-sc pet-my-conversations"><div class="sc-empty">' . esc_html__('Sign in required.', 'pet') . '</div></div>';
        }

        $atts = shortcode_atts(
            ['limit' => '10', 'title' => 'My Conversations'],
            $atts,
            'pet_my_conversations'
        );

        $limit = max(1, (int) $atts['limit']);
        $userId = get_current_user_id();
        $conversations = [];

        try {
            $container = ContainerFactory::create();
            $conversationRepo = $container->get(ConversationRepository::class);
            $conversations = $conversationRepo->findRecentByUserId($userId, $limit);
        } catch (\Throwable $e) {
            error_log('PET [pet_my_conversations] error: ' . $e->getMessage());
            return '<div class="pet-sc pet-my-conversations"><div class="sc-empty">' . esc_html__('Error loading conversations.', 'pet') . '</div></div>';
        }

        $openCount = 0;
        foreach ($conversations as $c) { if ($c->state() === 'open') $openCount++; }

        $html = '<div class="pet-sc pet-my-conversations">';
        $html .= '<h2 class="sc-title">' . esc_html($atts['title']) . '</h2>';
        $html .= '<p class="sc-subtitle">' . esc_html(sprintf(__('%d conversations, %d open', 'pet'), count($conversations), $openCount)) . '</p>';

        if (empty($conversations)) {
            $html .= '<div class="sc-empty"><span class="sc-empty-icon">&#128172;</span>' . esc_html__('No recent conversations.', 'pet') . '</div>';
        } else {
            foreach ($conversations as $conversation) {
                $url = admin_url('admin.php?page=pet-conversations&id=' . $conversation->id());
                $ctx = $conversation->contextType();
                $ctxClass = in_array($ctx, ['ticket'], true) ? 'ticket' : (in_array($ctx, ['project', 'project_task'], true) ? 'project' : 'general');
                $pillClass = $conversation->state() === 'open' ? 'sc-pill--open' : 'sc-pill--resolved';
                $ago = human_time_diff($conversation->createdAt()->getTimestamp(), time());

                $html .= '<div class="sc-item">';
                $html .= '<div class="sc-item-icon sc-item-icon--conversation">&#128172;</div>';
                $html .= '<div class="sc-item-body">';
                $html .= '<div class="sc-item-title"><a href="' . esc_url($url) . '">' . esc_html($conversation->subject()) . '</a></div>';
                $html .= '<div class="sc-item-meta">';
                $html .= '<span class="sc-convo-context sc-convo-context--' . esc_attr($ctxClass) . '">' . esc_html(ucfirst($ctx)) . '</span>';
                if ($conversation->contextId() !== '') {
                    $html .= '<span>#' . esc_html($conversation->contextId()) . '</span>';
                }
                $html .= '<span class="sc-item-meta-sep"></span><span>' . esc_html(sprintf(__('%s ago', 'pet'), $ago)) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="sc-item-right">';
                $html .= '<span class="sc-pill ' . esc_attr($pillClass) . '">' . esc_html(ucfirst($conversation->state())) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        return $html;
    }

    public function renderMyApprovals(array $atts = [], ?string $content = null): string
    {
        $this->enqueueShortcodesCss();

        if (!is_user_logged_in()) {
            return '<div class="pet-sc pet-my-approvals"><div class="sc-empty">' . esc_html__('Sign in required.', 'pet') . '</div></div>';
        }

        $atts = shortcode_atts(['title' => 'Pending Approvals'], $atts, 'pet_my_approvals');

        $userId = get_current_user_id();
        $decisions = [];

        try {
            $container = ContainerFactory::create();
            $decisionRepo = $container->get(DecisionRepository::class);
            $decisions = $decisionRepo->findPendingByUserId($userId);
        } catch (\Throwable $e) {
            error_log('PET [pet_my_approvals] error: ' . $e->getMessage());
            return '<div class="pet-sc pet-my-approvals"><div class="sc-empty">' . esc_html__('Error loading approvals.', 'pet') . '</div></div>';
        }

        $html = '<div class="pet-sc pet-my-approvals">';
        $html .= '<h2 class="sc-title">' . esc_html($atts['title']) . '</h2>';
        $html .= '<p class="sc-subtitle">' . esc_html(sprintf(__('%d pending decisions', 'pet'), count($decisions))) . '</p>';

        if (empty($decisions)) {
            $html .= '<div class="sc-empty"><span class="sc-empty-icon">&#10003;</span>' . esc_html__('No pending approvals — you\'re all caught up.', 'pet') . '</div>';
        } else {
            foreach ($decisions as $decision) {
                $url = admin_url('admin.php?page=pet-conversations&id=' . $decision->conversationId());
                $daysPending = max(0, (int) ((time() - $decision->requestedAt()->getTimestamp()) / 86400));
                $urgencyClass = $daysPending >= 5 ? 'high' : ($daysPending >= 2 ? 'medium' : 'low');
                $urgencyLabel = $daysPending === 0 ? __('Today', 'pet') : sprintf(__('%dd ago', 'pet'), $daysPending);

                $payload = $decision->payload();
                $details = '';
                if (!empty($payload['description'])) {
                    $details = (string) $payload['description'];
                } elseif (!empty($payload['reason'])) {
                    $details = (string) $payload['reason'];
                }

                $html .= '<div class="sc-item">';
                $html .= '<div class="sc-item-icon sc-item-icon--approval">&#9888;</div>';
                $html .= '<div class="sc-item-body">';
                $html .= '<div class="sc-item-title">' . esc_html(ucwords(str_replace('_', ' ', $decision->decisionType()))) . '</div>';
                $html .= '<div class="sc-item-meta">';
                if ($details !== '') {
                    $html .= '<span>' . esc_html(mb_strimwidth($details, 0, 80, '...')) . '</span>';
                }
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="sc-item-right">';
                $html .= '<span class="sc-approval-urgency sc-approval-urgency--' . esc_attr($urgencyClass) . '">' . esc_html($urgencyLabel) . '</span>';
                $html .= '<a href="' . esc_url($url) . '" class="sc-action-btn">' . esc_html__('Review', 'pet') . '</a>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        return $html;
    }

    public function renderKnowledgeBase(array $atts = [], ?string $content = null): string
    {
        $this->enqueueShortcodesCss();

        $atts = shortcode_atts(
            ['category' => '', 'limit' => '20', 'title' => 'Knowledge Base'],
            $atts,
            'pet_knowledge_base'
        );

        $limit = max(1, (int) $atts['limit']);
        $articles = [];

        try {
            $container = ContainerFactory::create();
            $articleRepo = $container->get(ArticleRepository::class);
            $articles = !empty($atts['category'])
                ? $articleRepo->findByCategory($atts['category'])
                : $articleRepo->findAll();
            if (count($articles) > $limit) {
                $articles = array_slice($articles, 0, $limit);
            }
        } catch (\Throwable $e) {
            error_log('PET [pet_knowledge_base] error: ' . $e->getMessage());
            return '<div class="pet-sc pet-knowledge-base"><div class="sc-empty">' . esc_html__('Error loading knowledge base.', 'pet') . '</div></div>';
        }

        // Group by category
        $byCategory = [];
        foreach ($articles as $article) {
            $cat = ucfirst($article->category());
            $byCategory[$cat][] = $article;
        }
        ksort($byCategory, SORT_NATURAL | SORT_FLAG_CASE);

        $uid = 'pet-kb-' . wp_rand(1000, 9999);

        $html = '<div class="pet-sc pet-knowledge-base">';
        $html .= '<h2 class="sc-title">' . esc_html($atts['title']) . '</h2>';
        $html .= '<p class="sc-subtitle">' . esc_html(sprintf(__('%d articles across %d categories', 'pet'), count($articles), count($byCategory))) . '</p>';

        // Search
        $html .= '<div class="sc-search-wrap">';
        $html .= '<input type="text" class="sc-search" id="' . esc_attr($uid) . '-search" placeholder="' . esc_attr__('Search articles...', 'pet') . '" />';
        $html .= '</div>';

        if (empty($articles)) {
            $html .= '<div class="sc-empty"><span class="sc-empty-icon">&#128218;</span>' . esc_html__('No articles found.', 'pet') . '</div>';
        } else {
            foreach ($byCategory as $cat => $catArticles) {
                $html .= '<div class="sc-category-group">';
                $html .= '<div class="sc-category-label">' . esc_html($cat) . ' <span class="sc-badge">' . count($catArticles) . '</span></div>';
                foreach ($catArticles as $article) {
                    $url = admin_url('admin.php?page=pet-knowledge&id=' . $article->id());
                    $excerpt = mb_strimwidth(strip_tags($article->content()), 0, 120, '...');

                    $html .= '<div class="sc-item" data-search-text="' . esc_attr(strtolower($article->title() . ' ' . $article->category() . ' ' . $excerpt)) . '">';
                    $html .= '<div class="sc-item-icon sc-item-icon--article">&#128218;</div>';
                    $html .= '<div class="sc-item-body">';
                    $html .= '<div class="sc-item-title"><a href="' . esc_url($url) . '">' . esc_html($article->title()) . '</a></div>';
                    if ($excerpt !== '' && $excerpt !== '...') {
                        $html .= '<div class="sc-article-excerpt">' . esc_html($excerpt) . '</div>';
                    }
                    $html .= '</div>';
                    $html .= '<div class="sc-item-right">';
                    $html .= '<span class="sc-pill sc-pill--status">' . esc_html($cat) . '</span>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }

        // Search filter script
        $html .= '<script>(function(){var s=document.getElementById("' . esc_js($uid) . '-search");if(!s)return;s.addEventListener("input",function(){var q=s.value.toLowerCase();var items=s.closest(".pet-knowledge-base").querySelectorAll(".sc-item[data-search-text]");items.forEach(function(el){el.style.display=el.getAttribute("data-search-text").indexOf(q)!==-1?"":"none"})})})();</script>';

        $html .= '</div>';

        return $html;
    }

    private function renderActivityCard(array $event, bool $wallboard, string $linkMode): string
    {
        $occurredAt = isset($event['occurred_at']) ? (string) $event['occurred_at'] : '';
        $actorName = isset($event['actor_display_name']) ? (string) $event['actor_display_name'] : '';
        $headline = isset($event['headline']) ? (string) $event['headline'] : '';
        $subline = isset($event['subline']) ? (string) $event['subline'] : '';
        $tags = isset($event['tags']) && is_array($event['tags']) ? $event['tags'] : [];
        $referenceType = isset($event['reference_type']) ? (string) $event['reference_type'] : '';
        $referenceId = isset($event['reference_id']) ? (string) $event['reference_id'] : '';
        $referenceUrl = isset($event['reference_url']) ? (string) $event['reference_url'] : '';
        $customerName = isset($event['customer_name']) ? (string) $event['customer_name'] : '';
        $actorAvatarUrl = isset($event['actor_avatar_url']) ? (string) $event['actor_avatar_url'] : '';
        $companyLogoUrl = isset($event['company_logo_url']) ? (string) $event['company_logo_url'] : '';
        $sla = isset($event['sla']) && is_array($event['sla']) ? $event['sla'] : null;

        $occurredTime = '';
        if ($occurredAt !== '') {
            try {
                $dt = new DateTimeImmutable($occurredAt);
                $occurredTime = $dt->format('Y-m-d H:i');
            } catch (\Throwable $e) {
                $occurredTime = $occurredAt;
            }
        }

        $timeBadge = $occurredTime !== '' ? $occurredTime : '';

        $slaBadge = '';
        if ($sla && isset($sla['seconds_remaining'])) {
            $seconds = $sla['seconds_remaining'];
            if (is_int($seconds)) {
                $minutes = (int) floor(abs($seconds) / 60);
                $label = $seconds < 0 ? sprintf(__('Breached %d min', 'pet'), $minutes) : sprintf(__('%d min left', 'pet'), $minutes);
                $class = 'pet-sla-badge-neutral';
                if ($seconds < 0) {
                    $class = 'pet-sla-badge-breached';
                } elseif ($seconds < 3600) {
                    $class = 'pet-sla-badge-risk';
                }
                $slaBadge = '<span class="pet-activity-sla-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
            }
        }

        $tagPills = '';
        if (!empty($tags)) {
            $visibleTags = array_slice($tags, 0, $wallboard ? 1 : 3);
            $extraCount = count($tags) - count($visibleTags);
            foreach ($visibleTags as $tag) {
                $tagPills .= '<span class="pet-activity-tag">' . esc_html((string) $tag) . '</span>';
            }
            if (!$wallboard && $extraCount > 0) {
                $tagPills .= '<span class="pet-activity-tag-extra">+' . esc_html((string) $extraCount) . '</span>';
            }
        }

        $referenceLabel = '';
        if ($referenceType !== '' && $referenceId !== '') {
            $referenceLabel = ucfirst($referenceType) . ' ' . $referenceId;
        } elseif ($referenceId !== '') {
            $referenceLabel = $referenceId;
        }

        $referenceHtml = '';
        if ($referenceLabel !== '') {
            if ($referenceUrl !== '') {
                if ($linkMode === 'open') {
                    $referenceHtml = '<a class="pet-activity-reference" href="' . esc_url($referenceUrl) . '">' . esc_html($referenceLabel) . '</a>';
                } else {
                    $referenceHtml = '<span class="pet-activity-reference">' . esc_html($referenceLabel) . '</span>';
                }
            } else {
                $referenceHtml = '<span class="pet-activity-reference">' . esc_html($referenceLabel) . '</span>';
            }
        }

        $avatarHtml = '';
        if ($actorAvatarUrl !== '') {
            $avatarHtml = '<span class="pet-activity-avatar"><img src="' . esc_url($actorAvatarUrl) . '" alt="" /></span>';
        } else {
            $initial = $actorName !== '' ? mb_substr($actorName, 0, 1) : 'S';
            $avatarHtml = '<span class="pet-activity-avatar pet-activity-avatar-fallback">' . esc_html($initial) . '</span>';
        }

        $logoHtml = '';
        if ($companyLogoUrl !== '') {
            $logoHtml = '<span class="pet-activity-company-logo"><img src="' . esc_url($companyLogoUrl) . '" alt="" /></span>';
        }

        $classes = 'pet-activity-item';
        if ($wallboard) {
            $classes .= ' pet-activity-item-wallboard';
        }

        $html = '<li class="' . esc_attr($classes) . '">';
        $html .= '<div class="pet-activity-card">';
        $html .= '<div class="pet-activity-card-header">';
        $html .= $avatarHtml;
        $html .= '<div class="pet-activity-header-main">';
        if ($actorName !== '') {
            $html .= '<div class="pet-activity-actor">' . esc_html($actorName) . '</div>';
        }
        if ($headline !== '') {
            $html .= '<div class="pet-activity-headline">' . esc_html($headline) . '</div>';
        }
        if (!$wallboard && $subline !== '') {
            $html .= '<div class="pet-activity-subline">' . esc_html($subline) . '</div>';
        }
        $html .= '</div>';
        if ($logoHtml !== '') {
            $html .= $logoHtml;
        }
        $html .= '<div class="pet-activity-header-meta">';
        if ($timeBadge !== '') {
            $html .= '<span class="pet-activity-time-badge">' . esc_html($timeBadge) . '</span>';
        }
        if (!$wallboard && $slaBadge !== '') {
            $html .= $slaBadge;
        }
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="pet-activity-card-footer">';
        if ($tagPills !== '') {
            $html .= '<div class="pet-activity-tags">' . $tagPills . '</div>';
        }
        if ($referenceHtml !== '' || $customerName !== '') {
            $html .= '<div class="pet-activity-reference-row">';
            if ($referenceHtml !== '') {
                $html .= $referenceHtml;
            }
            if ($customerName !== '') {
                $html .= '<span class="pet-activity-customer">' . esc_html($customerName) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</li>';

        return $html;
    }

    private function renderActivityRefreshScript(): string
    {
        $script = '<script type="text/javascript">';
        $script .= '(function(){';
        $script .= 'var root=document.currentScript.parentElement;';
        $script .= 'if(!root){return;}';
        $script .= 'var refresh=parseInt(root.getAttribute("data-refresh"),10)||0;';
        $script .= 'if(!refresh){return;}';
        $script .= 'function reloadContainer(){window.location.reload();}';
        $script .= 'setInterval(reloadContainer,refresh*1000);';
        $script .= '})();';
        $script .= '</script>';
        return $script;
    }

    private function renderTicketCard(array $card, string $lane): string
    {
        $classMap = [
            'critical' => 'pet-helpdesk__card--danger',
            'risk' => 'pet-helpdesk__card--warn',
            'normal' => 'pet-helpdesk__card--normal',
        ];
        $class = $classMap[$lane] ?? 'pet-helpdesk__card--normal';

        $assigneeName = $card['assignee_name'] ?? 'Unassigned';
        $assigneeAvatar = $card['assignee_avatar_url'] ?? '';
        $assigneeId = $card['assignee_user_id'] ?? '';
        $customerName = $card['customer_name'] ?? 'Unknown';
        $relativeDue = $card['relative_due'] ?? '';
        $ticketId = $card['ticket_id'] ?? '';
        $subject = $card['subject'] ?? '';

        $html = '<article class="pet-helpdesk__card ' . esc_attr($class) . '" data-assignee-id="' . esc_attr($assigneeId) . '">';
        
        $html .= '<div class="pet-helpdesk__card-header" style="display: flex; justify-content: space-between; font-size: 0.8em; opacity: 0.8; margin-bottom: 5px;">';
        $html .= '<span class="pet-ticket-id">#' . esc_html((string) $ticketId) . '</span>';
        $html .= '<span class="pet-ticket-customer" style="font-weight: 500;">' . esc_html($customerName) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="pet-helpdesk__card-title" style="font-weight: 600; margin-bottom: 8px;">' . esc_html($subject) . '</div>';
        
        $html .= '<div class="pet-helpdesk__card-footer" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85em;">';
        
        $html .= '<div class="pet-ticket-assignee" style="display: flex; align-items: center; gap: 5px;">';
        if ($assigneeAvatar) {
            $html .= '<img src="' . esc_url($assigneeAvatar) . '" alt="" style="width: 20px; height: 20px; border-radius: 50%;">';
        }
        $html .= '<span class="pet-ticket-assignee-name">' . esc_html($assigneeName) . '</span>';
        $html .= '</div>';
        
        if ($relativeDue) {
            $dueClass = (strpos($relativeDue, 'overdue') !== false) ? 'color: #d32f2f;' : 'color: #666;';
            $html .= '<div class="pet-ticket-due" style="' . $dueClass . '">' . esc_html($relativeDue) . '</div>';
        }
        
        $html .= '</div>';
        
        $html .= '</article>';
        
        return $html;
    }

    private function renderHelpdeskScripts(): string
    {
        ob_start();
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var searchInput = document.getElementById('pet-helpdesk-search');
            var myTicketsCheckbox = document.getElementById('pet-helpdesk-my-tickets');
            var currentUserId = '<?php echo esc_js((string) get_current_user_id()); ?>';

            if (!searchInput || !myTicketsCheckbox) return;

            function filterTickets() {
                var term = searchInput.value.toLowerCase();
                var showMy = myTicketsCheckbox.checked;
                var cards = document.querySelectorAll('.pet-helpdesk__card');

                cards.forEach(function(card) {
                    var text = card.innerText.toLowerCase();
                    var assigneeId = card.getAttribute('data-assignee-id');
                    
                    var matchesTerm = !term || text.includes(term);
                    var matchesUser = !showMy || (assigneeId === currentUserId);
                    
                    card.style.display = (matchesTerm && matchesUser) ? 'block' : 'none';
                });
            }

            searchInput.addEventListener('input', filterTickets);
            myTicketsCheckbox.addEventListener('change', filterTickets);
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
