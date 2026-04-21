<?php

declare(strict_types=1);

namespace Pet\UI\Shortcode {
    function wp_enqueue_style(...$args): void {}
    function plugin_dir_url(string $path): string { return '/'; }
    function is_user_logged_in(): bool { return (bool) ($GLOBALS['pet_test_logged_in'] ?? true); }
    function wp_get_current_user(): object { return (object) ($GLOBALS['pet_test_user'] ?? ['ID' => 0]); }
    function shortcode_atts(array $pairs, array $atts, string $shortcode = ''): array { return array_merge($pairs, $atts); }
    function wp_verify_nonce(string $nonce, string $action): bool { return true; }
    function current_user_can(string $capability, ...$args): bool { return (bool) ($GLOBALS['pet_test_can_edit'] ?? true); }
    function sanitize_text_field(string $value): string { return trim($value); }
    function wp_update_user(array $data) { $GLOBALS['pet_test_wp_update_calls'] = (int) ($GLOBALS['pet_test_wp_update_calls'] ?? 0) + 1; return 1; }
    function is_wp_error($thing): bool { return false; }
    function update_user_meta(int $userId, string $key, $value): void { $GLOBALS['pet_test_user_meta_updates'] = (int) ($GLOBALS['pet_test_user_meta_updates'] ?? 0) + 1; }
    function get_user_meta(int $userId, string $key, bool $single = false) { return $GLOBALS['pet_test_user_meta'][$key] ?? ''; }
    function get_avatar_url(int $userId, array $args = []): string { return 'https://example.test/avatar.png'; }
    function date_i18n(string $format, int $timestamp): string { return date($format, $timestamp); }
    function wp_nonce_field(string $action, string $name): void {}
    function admin_url(string $path = ''): string { return '/wp-admin/' . ltrim($path, '/'); }
    function esc_html__(string $text, string $domain = 'default'): string { return $text; }
    function __(string $text, string $domain = 'default'): string { return $text; }
    function esc_html(string $text): string { return $text; }
    function esc_attr(string $text): string { return $text; }
    function esc_url(string $text): string { return $text; }
}

namespace Pet\Tests\Unit\UI\Shortcode {

    use Pet\UI\Shortcode\ShortcodeRegistrar;
    use PHPUnit\Framework\TestCase;

    final class TestableShortcodeRegistrar extends ShortcodeRegistrar
    {
        public function __construct(private array $details)
        {
        }

        protected function loadMyProfileDetails(object $user): array
        {
            return $this->details;
        }
    }

    final class ShortcodeRegistrarMyProfileTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $_POST = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $GLOBALS['pet_test_logged_in'] = true;
            $GLOBALS['pet_test_can_edit'] = true;
            $GLOBALS['pet_test_wp_update_calls'] = 0;
            $GLOBALS['pet_test_user_meta_updates'] = 0;
            $GLOBALS['pet_test_user_meta'] = [
                'pet_phone' => '0123456789',
                'pet_title' => 'Support Engineer',
            ];
            $GLOBALS['wp_roles'] = (object) [
                'roles' => [
                    'subscriber' => ['name' => 'Subscriber'],
                ],
            ];
            $GLOBALS['pet_test_user'] = [
                'ID' => 7,
                'display_name' => 'Jane Doe',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'user_email' => 'jane@example.test',
                'user_registered' => '2026-01-15 12:00:00',
                'roles' => ['subscriber'],
            ];
        }

        public function testRenderMyProfileShowsLoginPromptWhenLoggedOut(): void
        {
            $GLOBALS['pet_test_logged_in'] = false;
            $registrar = new TestableShortcodeRegistrar($this->makeProfileDetails());

            $html = $registrar->renderMyProfile();

            self::assertStringContainsString('Please log in to view your profile.', $html);
        }

        public function testRenderMyProfileShowsUnavailableMessageWhenUserMissing(): void
        {
            $GLOBALS['pet_test_user'] = ['ID' => 0];
            $registrar = new TestableShortcodeRegistrar($this->makeProfileDetails());

            $html = $registrar->renderMyProfile();

            self::assertStringContainsString('Unable to load profile.', $html);
        }

        public function testRenderMyProfileIncludesBaselineAndAdminParitySections(): void
        {
            $registrar = new TestableShortcodeRegistrar($this->makeProfileDetails());

            $html = $registrar->renderMyProfile();

            self::assertStringContainsString('Roles & Teams', $html);
            self::assertStringContainsString('Skills', $html);
            self::assertStringContainsString('Certifications', $html);
            self::assertStringContainsString('Availability / Work Pattern', $html);
            self::assertStringContainsString('Responsibilities & Current Work', $html);
            self::assertStringContainsString('Recent Activity / Context', $html);
            self::assertStringContainsString('Primary Role', $html);
            self::assertStringContainsString('Senior Support Engineer', $html);
            self::assertStringContainsString('Assigned Tickets', $html);
            self::assertStringContainsString('VPN outage triage', $html);
        }

        public function testRenderMyProfileHandlesMissingOptionalSectionsSafely(): void
        {
            $details = $this->makeProfileDetails();
            $details['skills'] = [];
            $details['certifications'] = [];
            $details['responsibilities'] = [
                'assigned_tickets' => 0,
                'assigned_projects' => 0,
                'assigned_tasks' => 0,
                'open_work_items' => 0,
                'top_tickets' => [],
                'top_projects' => [],
            ];
            $details['recent_activity'] = [];
            $registrar = new TestableShortcodeRegistrar($details);

            $html = $registrar->renderMyProfile();

            self::assertStringContainsString('No skills recorded yet.', $html);
            self::assertStringContainsString('No certifications recorded yet.', $html);
            self::assertStringContainsString('No assigned tickets.', $html);
            self::assertStringContainsString('No assigned projects.', $html);
            self::assertStringContainsString('No recent activity available.', $html);
        }

        public function testRenderMyProfileKeepsParitySectionsReadOnlyWithoutAdminEditControls(): void
        {
            $registrar = new TestableShortcodeRegistrar($this->makeProfileDetails());

            $html = $registrar->renderMyProfile();

            self::assertStringNotContainsString('Add capability…', $html);
            self::assertStringNotContainsString('Add certification…', $html);
            self::assertStringNotContainsString('Save Availability', $html);
        }

        public function testRenderMyProfileHasNoReadSideMutationOnGetRequest(): void
        {
            $registrar = new TestableShortcodeRegistrar($this->makeProfileDetails());
            $registrar->renderMyProfile();

            self::assertSame(0, $GLOBALS['pet_test_wp_update_calls']);
            self::assertSame(0, $GLOBALS['pet_test_user_meta_updates']);
        }

        private function makeProfileDetails(): array
        {
            return [
                'pet_teams' => ['Support', 'Escalations'],
                'skills' => [
                    ['name' => 'Network Diagnostics', 'self_rating' => 4, 'manager_rating' => 5, 'effective_date' => '2026-03-01'],
                ],
                'certifications' => [
                    ['name' => 'ITIL Foundation', 'issuer' => 'PeopleCert', 'obtained' => '2024-01-20', 'expiry' => '2027-01-20', 'status' => 'active'],
                ],
                'primary_role' => 'Senior Support Engineer',
                'availability' => [
                    'state' => 'Busy',
                    'work_pattern' => 'Mon-Fri 08:00-16:00',
                    'next_available' => 'After 14:00',
                    'location_note' => 'On-site',
                ],
                'responsibilities' => [
                    'assigned_tickets' => 3,
                    'assigned_projects' => 2,
                    'assigned_tasks' => 1,
                    'open_work_items' => 6,
                    'top_tickets' => [
                        ['title' => 'VPN outage triage', 'meta' => 'Ticket #44 · open', 'link' => '/wp-admin/admin.php?page=pet-support#ticket=44'],
                    ],
                    'top_projects' => [
                        ['title' => 'Core Network Refresh', 'meta' => 'Project #9 · active', 'link' => '/wp-admin/admin.php?page=pet-delivery#project=9'],
                    ],
                ],
                'recent_activity' => [
                    ['headline' => 'Resolved escalated support queue', 'meta' => 'ticket #44 · 2026-03-30T09:00:00Z'],
                ],
            ];
        }
    }
}
