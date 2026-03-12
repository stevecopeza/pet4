<?php

declare(strict_types=1);

namespace Pet\UI\Rest;

use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use Psr\Container\ContainerInterface;

class ApiRegistry
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $controllers = [
            \Pet\UI\Rest\Controller\DashboardController::class,
            \Pet\UI\Rest\Controller\ProjectController::class,
            \Pet\UI\Rest\Controller\QuoteController::class,
            \Pet\UI\Rest\Controller\CatalogItemController::class,
            \Pet\UI\Rest\Controller\LeadController::class,
            \Pet\UI\Rest\Controller\TimeEntryController::class,
            \Pet\UI\Rest\Controller\CustomerController::class,
            \Pet\UI\Rest\Controller\ContactController::class,
            \Pet\UI\Rest\Controller\SiteController::class,
            \Pet\UI\Rest\Controller\EmployeeController::class,
            \Pet\UI\Rest\Controller\TeamController::class,
            \Pet\UI\Rest\Controller\TicketController::class,
            \Pet\UI\Rest\Controller\ArticleController::class,
            \Pet\UI\Rest\Controller\ActivityController::class,
            \Pet\UI\Rest\Controller\SettingsController::class,
            \Pet\UI\Rest\Controller\SchemaController::class,
            \Pet\UI\Rest\Controller\RoleController::class,
            \Pet\UI\Rest\Controller\AssignmentController::class,
            \Pet\UI\Rest\Controller\SkillController::class,
            \Pet\UI\Rest\Controller\CapabilityController::class,
            \Pet\UI\Rest\Controller\EmployeeSkillController::class,
            \Pet\UI\Rest\Controller\CertificationController::class,
            \Pet\UI\Rest\Controller\EmployeeCertificationController::class,
            \Pet\UI\Rest\Controller\KpiDefinitionController::class,
            \Pet\UI\Rest\Controller\RoleKpiController::class,
            \Pet\UI\Rest\Controller\PersonKpiController::class,
            \Pet\UI\Rest\Controller\PerformanceReviewController::class,
            \Pet\UI\Rest\Controller\CalendarController::class,
            \Pet\UI\Rest\Controller\SlaController::class,
            \Pet\UI\Rest\Controller\EscalationRuleController::class,
            \Pet\UI\Rest\Controller\SystemController::class,
            \Pet\UI\Rest\Controller\WorkController::class,
            \Pet\UI\Rest\Controller\WorkItemController::class,
            \Pet\UI\Rest\Controller\FeedController::class,
            \Pet\UI\Rest\Controller\EventStreamController::class,
            \Pet\UI\Rest\Controller\BillingController::class,
            \Pet\UI\Rest\Controller\QuickBooksController::class,
            \Pet\UI\Rest\Controller\LeaveController::class,
            \Pet\UI\Rest\Controller\ConversationController::class,
            \Pet\UI\Rest\Controller\LogController::class,
            \Pet\UI\Rest\Controller\PulsewayController::class,
            \Pet\UI\Rest\Controller\HealthHistoryController::class,
            \Pet\UI\Rest\Controller\ServiceTypeController::class,
            \Pet\UI\Rest\Controller\RateCardController::class,
            \Pet\UI\Rest\Controller\CatalogProductController::class,
            \Pet\UI\Rest\Controller\EscalationController::class,
        ];

        foreach ($controllers as $controllerClass) {
            /** @var \Pet\UI\Rest\Controller\RestController $controller */
            $controller = $this->container->get($controllerClass);
            $controller->registerRoutes();
        }
    }
}
