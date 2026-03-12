<?php

declare(strict_types=1);

namespace Pet\Infrastructure\DependencyInjection;

use DI\Container;
use DI\ContainerBuilder;

class ContainerFactory
{
    private static ?Container $instance = null;

    public static function create(): Container
    {
        if (self::$instance instanceof Container) {
            return self::$instance;
        }
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAnnotations(false);

        // Load definitions
        $builder->addDefinitions(self::getDefinitions());

        self::$instance = $builder->build();
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function getDefinitions(): array
    {
        return [
            \wpdb::class => function () {
                global $wpdb;
                return $wpdb;
            },
            'wpdb' => function () {
                global $wpdb;
                return $wpdb;
            },
            \Pet\Domain\Event\EventBus::class => function (\Psr\Container\ContainerInterface $c) {
                return new \Pet\Infrastructure\Event\PersistingEventBus(
                    $c->get(\Pet\Infrastructure\Event\InMemoryEventBus::class),
                    $c->get(\Pet\Domain\Event\Repository\EventStreamRepository::class)
                );
            },
            \Pet\Infrastructure\Event\InMemoryEventBus::class => \DI\create(\Pet\Infrastructure\Event\InMemoryEventBus::class),
            
            \Pet\Infrastructure\Persistence\Migration\MigrationRunner::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Migration\MigrationRunner($wpdb);
            },

            \Pet\Domain\Event\Repository\EventStreamRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlEventStreamRepository($wpdb);
            },

            \Pet\Domain\Finance\Repository\BillingExportRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlBillingExportRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlOutboxRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Repository\SqlQbInvoiceRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlQbInvoiceRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Repository\SqlQbPaymentRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlQbPaymentRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Repository\SqlExternalMappingRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlExternalMappingRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Transaction\SqlTransaction::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Transaction\SqlTransaction($wpdb);
            },
            \Pet\Application\System\Service\TransactionManager::class => \DI\get(\Pet\Infrastructure\Persistence\Transaction\SqlTransaction::class),
            \Pet\Application\System\Service\TouchedTracker::class => function () {
                global $wpdb;
                return new \Pet\Application\System\Service\TouchedTracker($wpdb);
            },

            \Pet\Domain\Sla\Repository\EscalationRuleRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlEscalationRuleRepository($wpdb);
            },
            \Pet\Domain\Sla\Repository\SlaRepository::class => function (\Psr\Container\ContainerInterface $c) {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSlaRepository(
                    $wpdb,
                    $c->get(\Pet\Domain\Calendar\Repository\CalendarRepository::class)
                );
            },
            \Pet\Domain\Calendar\Repository\CalendarRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCalendarRepository($wpdb);
            },

            \Pet\Application\Conversation\Command\CreateConversationHandler::class => function (\Psr\Container\ContainerInterface $c) {
                return new \Pet\Application\Conversation\Command\CreateConversationHandler(
                    $c->get(\Pet\Application\System\Service\TransactionManager::class),
                    $c->get(\Pet\Domain\Conversation\Repository\ConversationRepository::class),
                    $c->get(\Pet\Domain\Identity\Repository\EmployeeRepository::class),
                    $c->get(\Pet\Domain\Identity\Repository\ContactRepository::class),
                    $c->get(\Pet\Domain\Team\Repository\TeamRepository::class),
                    $c->get(\Pet\Domain\Commercial\Repository\QuoteRepository::class)
                );
            },

            \Pet\Application\Conversation\Command\PostMessageHandler::class => function (\Psr\Container\ContainerInterface $c) {
                return new \Pet\Application\Conversation\Command\PostMessageHandler(
                    $c->get(\Pet\Application\System\Service\TransactionManager::class),
                    $c->get(\Pet\Domain\Conversation\Repository\ConversationRepository::class),
                    $c->get(\Pet\Domain\Identity\Repository\EmployeeRepository::class),
                    $c->get(\Pet\Domain\Identity\Repository\ContactRepository::class),
                    $c->get(\Pet\Domain\Team\Repository\TeamRepository::class)
                );
            },

            \Pet\Domain\Team\Repository\TeamRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlTeamRepository($wpdb);
            },
            
            \Pet\Domain\Identity\Repository\EmployeeRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlEmployeeRepository($wpdb);
            },
            \Pet\Domain\Identity\Repository\CustomerRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCustomerRepository($wpdb);
            },
            \Pet\Domain\Identity\Repository\ContactRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlContactRepository($wpdb);
            },
            \Pet\Domain\Identity\Repository\SiteRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSiteRepository($wpdb);
            },
            \Pet\Domain\Delivery\Repository\ProjectRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlProjectRepository($wpdb);
            },

            \Pet\Domain\Commercial\Repository\CostAdjustmentRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCostAdjustmentRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\ForecastRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlForecastRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\QuoteRepository::class => function (\Psr\Container\ContainerInterface $c) {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlQuoteRepository(
                    $wpdb,
                    $c->get(\Pet\Domain\Commercial\Repository\CostAdjustmentRepository::class)
                );
            },
            \Pet\Domain\Commercial\Repository\CatalogItemRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCatalogItemRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\ContractRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlContractRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\BaselineRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlBaselineRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\LeadRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlLeadRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\QuoteSectionRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlQuoteSectionRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\ServiceTypeRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlServiceTypeRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Repository\SqlServiceTypeRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlServiceTypeRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\RateCardRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlRateCardRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Repository\SqlRateCardRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlRateCardRepository($wpdb);
            },
            \Pet\Domain\Commercial\Repository\CatalogProductRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCatalogProductRepository($wpdb);
            },
            \Pet\Infrastructure\Persistence\Repository\SqlCatalogProductRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCatalogProductRepository($wpdb);
            },
            \Pet\Application\Commercial\Service\RateCardResolver::class => function (\Psr\Container\ContainerInterface $c) {
                return new \Pet\Application\Commercial\Service\RateCardResolver(
                    $c->get(\Pet\Domain\Commercial\Repository\RateCardRepository::class)
                );
            },
            
            \Pet\Domain\Time\Repository\TimeEntryRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlTimeEntryRepository($wpdb);
            },
            
            \Pet\Domain\Support\Repository\TicketRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlTicketRepository($wpdb);
            },
            \Pet\Domain\Support\Repository\SlaClockStateRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSlaClockStateRepository($wpdb);
            },

            // SLA: pure domain resolver + application-layer orchestrator
            \Pet\Domain\Support\Service\SlaStateResolver::class => \DI\create(\Pet\Domain\Support\Service\SlaStateResolver::class),
            \Pet\Application\Support\Service\SlaCheckService::class => \DI\autowire(\Pet\Application\Support\Service\SlaCheckService::class),
            // Deprecated facade — delegates to SlaCheckService
            \Pet\Domain\Support\Service\SlaAutomationService::class => \DI\autowire(\Pet\Domain\Support\Service\SlaAutomationService::class),

            // Feed Repositories (constructors self-resolve global $wpdb)
            \Pet\Domain\Feed\Repository\FeedEventRepository::class => function () {
                return new \Pet\Infrastructure\Persistence\Repository\SqlFeedEventRepository();
            },
            \Pet\Domain\Feed\Repository\AnnouncementRepository::class => function () {
                return new \Pet\Infrastructure\Persistence\Repository\SqlAnnouncementRepository();
            },
            \Pet\Domain\Feed\Repository\AnnouncementAcknowledgementRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlAnnouncementAcknowledgementRepository($wpdb);
            },
            \Pet\Domain\Feed\Repository\FeedReactionRepository::class => function () {
                return new \Pet\Infrastructure\Persistence\Repository\SqlFeedReactionRepository();
            },

            \Pet\Domain\Knowledge\Repository\ArticleRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlArticleRepository($wpdb);
            },

            \Pet\Domain\Activity\Repository\ActivityLogRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlActivityLogRepository($wpdb);
            },

            \Pet\Domain\Configuration\Repository\SettingRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSettingRepository($wpdb);
            },

            \Pet\Application\System\Service\FeatureFlagService::class => function (\Psr\Container\ContainerInterface $c) {
                return new \Pet\Application\System\Service\FeatureFlagService(
                    $c->get(\Pet\Domain\Configuration\Repository\SettingRepository::class)
                );
            },

            \Pet\Application\System\Service\AdminAuditLogger::class => function (\Psr\Container\ContainerInterface $c) {
                return new \Pet\Application\System\Service\AdminAuditLogger(
                    $c->get(\wpdb::class)
                );
            },
            
            \Pet\Infrastructure\System\Service\LogService::class => function () {
                return new \Pet\Infrastructure\System\Service\LogService();
            },

            \Pet\Domain\Configuration\Repository\SchemaDefinitionRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSchemaDefinitionRepository($wpdb);
            },

            // DUPLICATE TeamRepository REMOVED (already defined above at line ~135)

            // Work Domain Repositories
            \Pet\Domain\Work\Repository\RoleRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlRoleRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\LeaveTypeRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlLeaveTypeRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\LeaveRequestRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlLeaveRequestRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\CapacityOverrideRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCapacityOverrideRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\SkillRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlSkillRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\CapabilityRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCapabilityRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\ProficiencyLevelRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlProficiencyLevelRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\CertificationRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlCertificationRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\AssignmentRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlAssignmentRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\PersonSkillRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlPersonSkillRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\PersonCertificationRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlPersonCertificationRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\KpiDefinitionRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlKpiDefinitionRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\RoleKpiRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlRoleKpiRepository($wpdb);
            },
            \Pet\Domain\Work\Repository\PersonKpiRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlPersonKpiRepository($wpdb);
            },
            
            \Pet\Application\Identity\Directory\UserDirectory::class => \DI\autowire(\Pet\Infrastructure\Identity\Directory\WordPressUserDirectory::class),
            \Pet\Domain\Work\Repository\PerformanceReviewRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlPerformanceReviewRepository($wpdb);
            },
            
            \Pet\Domain\Work\Repository\WorkItemRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository($wpdb);
            },
            
            \Pet\Domain\Work\Repository\DepartmentQueueRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlDepartmentQueueRepository($wpdb);
            },
            
            \Pet\Domain\Advisory\Repository\AdvisorySignalRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlAdvisorySignalRepository($wpdb);
            },

            // DUPLICATE Feed repos REMOVED (already defined above at line ~211)

            // Work Domain Services

            \Pet\Domain\Work\Service\DepartmentResolver::class => function () {
                return new \Pet\Domain\Work\Service\DepartmentResolver();
            },

            // DUPLICATE CalendarRepository REMOVED (already defined above at line ~109)

            // Conversation & Decision Repositories
            \Pet\Domain\Conversation\Repository\ConversationRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\Conversation\SqlConversationRepository($wpdb);
            },
            \Pet\Domain\Conversation\Repository\DecisionRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\Conversation\SqlDecisionRepository($wpdb);
            },
            \Pet\Application\Conversation\Service\ActionGatingService::class => \DI\autowire(\Pet\Application\Conversation\Service\ActionGatingService::class),
            \Pet\Domain\Conversation\Service\ConversationAccessControl::class => \DI\autowire(\Pet\Domain\Conversation\Service\ConversationAccessControl::class),
            // DUPLICATE SlaRepository REMOVED (already defined above at line ~102)

            \Pet\Domain\Commercial\Repository\QuoteBlockRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlQuoteBlockRepository($wpdb);
            },

            // Application Services
            \Pet\Application\Delivery\Service\ProjectHealthTransitionEmitter::class => \DI\autowire(\Pet\Application\Delivery\Service\ProjectHealthTransitionEmitter::class),

            // Application Handlers
            \Pet\Application\Delivery\Command\CreateProjectHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\CreateProjectHandler::class),
            \Pet\Application\Delivery\Command\AddTaskHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\AddTaskHandler::class),
            \Pet\Application\Delivery\Command\UpdateProjectHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\UpdateProjectHandler::class),
            \Pet\Application\Delivery\Command\ArchiveProjectHandler::class => \DI\autowire(\Pet\Application\Delivery\Command\ArchiveProjectHandler::class),
            
            \Pet\Application\Commercial\Command\CreateQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateQuoteHandler::class),
            \Pet\Application\Commercial\Command\UpdateQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\UpdateQuoteHandler::class),
            \Pet\Application\Commercial\Command\SendQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\SendQuoteHandler::class),
            \Pet\Application\Commercial\Command\AcceptQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\AcceptQuoteHandler::class),
            \Pet\Application\Commercial\Command\CreateProjectTicketHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateProjectTicketHandler::class),
            \Pet\Application\Commercial\Command\SetPaymentScheduleHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\SetPaymentScheduleHandler::class),
            \Pet\Application\Commercial\Command\AddQuoteLineHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\AddQuoteLineHandler::class),
            \Pet\Application\Commercial\Command\AddComponentHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\AddComponentHandler::class),
            \Pet\Application\Commercial\Command\RemoveComponentHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\RemoveComponentHandler::class),
            \Pet\Application\Commercial\Command\ArchiveQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\ArchiveQuoteHandler::class),
            \Pet\Application\Commercial\Command\AddQuoteSectionHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\AddQuoteSectionHandler::class),
            \Pet\Application\Commercial\Command\CreateQuoteBlockHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateQuoteBlockHandler::class),
            \Pet\Application\Commercial\Command\UpdateQuoteBlockHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\UpdateQuoteBlockHandler::class),
            \Pet\Application\Commercial\Command\DeleteQuoteBlockHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\DeleteQuoteBlockHandler::class),
            \Pet\Application\Commercial\Command\UpdateQuoteSectionHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\UpdateQuoteSectionHandler::class),
            \Pet\Application\Commercial\Command\CloneQuoteSectionHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CloneQuoteSectionHandler::class),
            \Pet\Application\Commercial\Command\DeleteQuoteSectionHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\DeleteQuoteSectionHandler::class),
            \Pet\Application\Commercial\Listener\QuoteAcceptedListener::class => function (\Psr\Container\ContainerInterface $c) {
                return new \Pet\Application\Commercial\Listener\QuoteAcceptedListener(
                    $c->get(\Pet\Domain\Commercial\Repository\ContractRepository::class),
                    $c->get(\Pet\Domain\Commercial\Repository\BaselineRepository::class),
                    $c->get(\Pet\Domain\Event\EventBus::class),
                    $c->get(\Pet\Domain\Sla\Repository\SlaRepository::class),
                    $c->get(\Pet\Domain\Calendar\Repository\CalendarRepository::class)
                );
            },
            \Pet\Application\Commercial\Listener\CreateForecastFromQuoteListener::class => \DI\autowire(\Pet\Application\Commercial\Listener\CreateForecastFromQuoteListener::class),

            \Pet\Application\Conversation\Command\AddParticipantHandler::class => \DI\autowire(\Pet\Application\Conversation\Command\AddParticipantHandler::class),
            \Pet\Application\Conversation\Command\RemoveParticipantHandler::class => \DI\autowire(\Pet\Application\Conversation\Command\RemoveParticipantHandler::class),

            \Pet\Application\Delivery\Listener\CreateProjectFromQuoteListener::class => \DI\autowire(\Pet\Application\Delivery\Listener\CreateProjectFromQuoteListener::class),
            \Pet\Application\Commercial\Command\CreateLeadHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateLeadHandler::class),
            \Pet\Application\Commercial\Command\UpdateLeadHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\UpdateLeadHandler::class),
            \Pet\Application\Commercial\Command\DeleteLeadHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\DeleteLeadHandler::class),
            \Pet\Application\Commercial\Command\ConvertLeadToQuoteHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\ConvertLeadToQuoteHandler::class),
            \Pet\Application\Commercial\Command\AddCostAdjustmentHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\AddCostAdjustmentHandler::class),
            \Pet\Application\Commercial\Command\RemoveCostAdjustmentHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\RemoveCostAdjustmentHandler::class),
            \Pet\Application\Commercial\Command\CreateServiceTypeHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateServiceTypeHandler::class),
            \Pet\Application\Commercial\Command\UpdateServiceTypeHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\UpdateServiceTypeHandler::class),
            \Pet\Application\Commercial\Command\ArchiveServiceTypeHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\ArchiveServiceTypeHandler::class),
            \Pet\Application\Commercial\Command\CreateRateCardHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateRateCardHandler::class),
            \Pet\Application\Commercial\Command\ArchiveRateCardHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\ArchiveRateCardHandler::class),
            \Pet\Application\Commercial\Command\ResolveRateCardHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\ResolveRateCardHandler::class),
            \Pet\Application\Commercial\Command\CreateCatalogProductHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\CreateCatalogProductHandler::class),
            \Pet\Application\Commercial\Command\UpdateCatalogProductHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\UpdateCatalogProductHandler::class),
            \Pet\Application\Commercial\Command\ArchiveCatalogProductHandler::class => \DI\autowire(\Pet\Application\Commercial\Command\ArchiveCatalogProductHandler::class),

            \Pet\Domain\Work\Repository\RoleTeamRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\SqlRoleTeamRepository($wpdb);
            },

            \Pet\Application\Team\Command\CreateTeamHandler::class => \DI\autowire(\Pet\Application\Team\Command\CreateTeamHandler::class),
            \Pet\Application\Team\Command\UpdateTeamHandler::class => \DI\autowire(\Pet\Application\Team\Command\UpdateTeamHandler::class),
            \Pet\Application\Team\Command\ArchiveTeamHandler::class => \DI\autowire(\Pet\Application\Team\Command\ArchiveTeamHandler::class),
            
            \Pet\Application\Time\Command\LogTimeHandler::class => \DI\autowire(\Pet\Application\Time\Command\LogTimeHandler::class),
            \Pet\Application\Time\Command\SubmitTimeEntryHandler::class => \DI\autowire(\Pet\Application\Time\Command\SubmitTimeEntryHandler::class),
            \Pet\Application\Time\Command\UpdateDraftTimeEntryHandler::class => \DI\autowire(\Pet\Application\Time\Command\UpdateDraftTimeEntryHandler::class),
            \Pet\Application\Identity\Command\CreateEmployeeHandler::class => \DI\autowire(\Pet\Application\Identity\Command\CreateEmployeeHandler::class),
            \Pet\Application\Identity\Command\UpdateEmployeeHandler::class => \DI\autowire(\Pet\Application\Identity\Command\UpdateEmployeeHandler::class),
            \Pet\Application\Identity\Command\ArchiveEmployeeHandler::class => \DI\autowire(\Pet\Application\Identity\Command\ArchiveEmployeeHandler::class),

            // Work Handlers
            \Pet\Application\Work\Command\CreateRoleHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreateRoleHandler::class),
            \Pet\Application\Work\Command\PublishRoleHandler::class => \DI\autowire(\Pet\Application\Work\Command\PublishRoleHandler::class),
            \Pet\Application\Work\Command\UpdateRoleHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdateRoleHandler::class),
            \Pet\Application\Work\Command\AssignRoleToPersonHandler::class => \DI\autowire(\Pet\Application\Work\Command\AssignRoleToPersonHandler::class),
            \Pet\Application\Work\Command\EndAssignmentHandler::class => \DI\autowire(\Pet\Application\Work\Command\EndAssignmentHandler::class),
            \Pet\Application\Work\Command\CreateSkillHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreateSkillHandler::class),
            \Pet\Application\Work\Command\UpdateSkillHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdateSkillHandler::class),
            \Pet\Application\Work\Command\RateEmployeeSkillHandler::class => \DI\autowire(\Pet\Application\Work\Command\RateEmployeeSkillHandler::class),
            \Pet\Application\Work\Command\CreateCertificationHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreateCertificationHandler::class),
            \Pet\Application\Work\Command\UpdateCertificationHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdateCertificationHandler::class),
            \Pet\Application\Work\Command\AssignCertificationToPersonHandler::class => \DI\autowire(\Pet\Application\Work\Command\AssignCertificationToPersonHandler::class),
            \Pet\Application\Work\Command\CreateKpiDefinitionHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreateKpiDefinitionHandler::class),
            \Pet\Application\Work\Command\UpdateKpiDefinitionHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdateKpiDefinitionHandler::class),
            \Pet\Application\Work\Command\AssignKpiToRoleHandler::class => \DI\autowire(\Pet\Application\Work\Command\AssignKpiToRoleHandler::class),
            \Pet\Application\Work\Command\GeneratePersonKpisHandler::class => \DI\autowire(\Pet\Application\Work\Command\GeneratePersonKpisHandler::class),
            \Pet\Application\Work\Command\UpdatePersonKpiHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdatePersonKpiHandler::class),
            \Pet\Application\Work\Command\CreatePerformanceReviewHandler::class => \DI\autowire(\Pet\Application\Work\Command\CreatePerformanceReviewHandler::class),
            \Pet\Application\Work\Command\UpdatePerformanceReviewHandler::class => \DI\autowire(\Pet\Application\Work\Command\UpdatePerformanceReviewHandler::class),
            \Pet\Application\Work\Command\AssignWorkItemHandler::class => \DI\autowire(\Pet\Application\Work\Command\AssignWorkItemHandler::class),
            \Pet\Application\Work\Command\OverrideWorkItemPriorityHandler::class => \DI\autowire(\Pet\Application\Work\Command\OverrideWorkItemPriorityHandler::class),
            
            // Projectors
            \Pet\Application\Work\Projection\WorkItemProjector::class => \DI\autowire(\Pet\Application\Work\Projection\WorkItemProjector::class),
            \Pet\Application\Work\Cron\WorkItemPriorityUpdateJob::class => \DI\autowire(\Pet\Application\Work\Cron\WorkItemPriorityUpdateJob::class),
            \Pet\Domain\Work\Service\SlaClockCalculator::class => \DI\autowire(\Pet\Domain\Work\Service\SlaClockCalculator::class),
            \Pet\Domain\Calendar\Service\BusinessTimeCalculator::class => \DI\autowire(\Pet\Domain\Calendar\Service\BusinessTimeCalculator::class),
            \Pet\Domain\Work\Service\CapacityCalendar::class => \DI\autowire(\Pet\Domain\Work\Service\CapacityCalendar::class),
            \Pet\Application\Integration\Service\OutboxDispatcherService::class => \DI\autowire(\Pet\Application\Integration\Service\OutboxDispatcherService::class),
            \Pet\Application\Integration\Cron\OutboxDispatchJob::class => \DI\autowire(\Pet\Application\Integration\Cron\OutboxDispatchJob::class),
            \Pet\Application\Integration\Service\QbMockPullService::class => \DI\autowire(\Pet\Application\Integration\Service\QbMockPullService::class),

            \Pet\Application\Identity\Command\CreateCustomerHandler::class => \DI\autowire(\Pet\Application\Identity\Command\CreateCustomerHandler::class),

            \Pet\Application\Finance\Command\CreateBillingExportHandler::class => \DI\autowire(\Pet\Application\Finance\Command\CreateBillingExportHandler::class),
            \Pet\Application\Finance\Command\AddBillingExportItemHandler::class => \DI\autowire(\Pet\Application\Finance\Command\AddBillingExportItemHandler::class),
            \Pet\Application\Finance\Command\QueueBillingExportForQuickBooksHandler::class => \DI\autowire(\Pet\Application\Finance\Command\QueueBillingExportForQuickBooksHandler::class),
            \Pet\Application\Identity\Command\UpdateCustomerHandler::class => \DI\autowire(\Pet\Application\Identity\Command\UpdateCustomerHandler::class),
            \Pet\Application\Identity\Command\ArchiveCustomerHandler::class => \DI\autowire(\Pet\Application\Identity\Command\ArchiveCustomerHandler::class),
            \Pet\Application\Identity\Command\CreateContactHandler::class => \DI\autowire(\Pet\Application\Identity\Command\CreateContactHandler::class),
            \Pet\Application\Identity\Command\UpdateContactHandler::class => \DI\autowire(\Pet\Application\Identity\Command\UpdateContactHandler::class),
            \Pet\Application\Identity\Command\ArchiveContactHandler::class => \DI\autowire(\Pet\Application\Identity\Command\ArchiveContactHandler::class),
            \Pet\Application\Support\Command\CreateTicketHandler::class => \DI\autowire(\Pet\Application\Support\Command\CreateTicketHandler::class),
            \Pet\Application\Support\Command\UpdateTicketHandler::class => \DI\autowire(\Pet\Application\Support\Command\UpdateTicketHandler::class),
            \Pet\Application\Support\Command\DeleteTicketHandler::class => \DI\autowire(\Pet\Application\Support\Command\DeleteTicketHandler::class),
            \Pet\Application\Knowledge\Command\CreateArticleHandler::class => \DI\autowire(\Pet\Application\Knowledge\Command\CreateArticleHandler::class),
            \Pet\Application\Knowledge\Command\UpdateArticleHandler::class => \DI\autowire(\Pet\Application\Knowledge\Command\UpdateArticleHandler::class),
            \Pet\Application\Knowledge\Command\ArchiveArticleHandler::class => \DI\autowire(\Pet\Application\Knowledge\Command\ArchiveArticleHandler::class),

            \Pet\UI\Rest\Controller\ProjectController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\QuoteController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\LeadController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\TimeEntryController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CustomerController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\ContactController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SiteController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\EmployeeController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\TicketController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\ArticleController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\ActivityController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SettingsController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SchemaController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\DashboardController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CalendarController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SlaController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\RoleController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\AssignmentController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\FeedController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\SkillController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CapabilityController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\EmployeeSkillController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CertificationController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\EmployeeCertificationController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\KpiDefinitionController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\RoleKpiController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\PersonKpiController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\WorkController::class => \DI\autowire(\Pet\UI\Rest\Controller\WorkController::class),
            \Pet\UI\Rest\Controller\WorkItemController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\LeaveController::class => \DI\autowire(),
            \Pet\Application\System\Service\DemoSeedService::class => function () {
                global $wpdb;
                return new \Pet\Application\System\Service\DemoSeedService($wpdb);
            },
            \Pet\Application\System\Service\DemoPurgeService::class => function () {
                global $wpdb;
                return new \Pet\Application\System\Service\DemoPurgeService($wpdb);
            },
            \Pet\Application\System\Service\TouchedTracker::class => function () {
                global $wpdb;
                return new \Pet\Application\System\Service\TouchedTracker($wpdb);
            },

            // ── Pulseway RMM Integration ──
            \Pet\Infrastructure\Integration\Pulseway\CredentialEncryptionService::class => \DI\create(\Pet\Infrastructure\Integration\Pulseway\CredentialEncryptionService::class),
            \Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository::class => function () {
                global $wpdb;
                return new \Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository($wpdb);
            },
            \Pet\Application\Integration\Pulseway\Service\NotificationIngestionService::class => \DI\autowire(\Pet\Application\Integration\Pulseway\Service\NotificationIngestionService::class),
            \Pet\Application\Integration\Pulseway\Service\DeviceSnapshotService::class => \DI\autowire(\Pet\Application\Integration\Pulseway\Service\DeviceSnapshotService::class),
            \Pet\Application\Integration\Pulseway\Service\TicketRuleEngine::class => \DI\autowire(\Pet\Application\Integration\Pulseway\Service\TicketRuleEngine::class),
            \Pet\Application\Integration\Pulseway\Service\PulsewayTicketCreationService::class => \DI\autowire(\Pet\Application\Integration\Pulseway\Service\PulsewayTicketCreationService::class),
            \Pet\UI\Rest\Controller\PulsewayController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\HealthHistoryController::class => \DI\create(\Pet\UI\Rest\Controller\HealthHistoryController::class),
            \Pet\UI\Rest\Controller\ServiceTypeController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\RateCardController::class => \DI\autowire(),
            \Pet\UI\Rest\Controller\CatalogProductController::class => \DI\autowire(),
        ];
    }
}
