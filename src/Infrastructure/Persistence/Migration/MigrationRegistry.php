<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration;

class MigrationRegistry
{
    /**
     * Returns the authoritative list of all migrations in execution order.
     * This ensures both Web and CLI executions are deterministic and identical.
     * 
     * @return string[] Array of migration class names
     */
    public static function all(): array
    {
        return [
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateIdentityTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateCommercialTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateCostAdjustmentTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateDeliveryTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\DropTasksTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\RecreateProjectTasksTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateTimeTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateTimeEntriesReplaceTaskWithTicket::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSupportTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateKnowledgeTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateActivityTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSettingsTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateIdentitySchema::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateMalleableSchema::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddSchemaStatusToDefinition::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddMalleableIndexes::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddContactAffiliations::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddMissingCoreFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddMalleableToTimeEntries::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateAssetTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateTeamTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateTeamEscalationColumn::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateCommercialSchema::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateIdentityCoreFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateWorkTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddKpiTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreatePerformanceTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuoteComponentTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateCatalogTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateContractBaselineTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateBaselineComponentsTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTitleDescriptionToQuotes::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTypeToCatalogItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddWbsTemplateToCatalogItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddCatalogItemIdToQuoteCatalogItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateCalendarTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSlaTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTicketCoreFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTicketSlaFields::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddSectionToQuoteComponents::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuoteSectionsTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateSlaClockStateTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuotePaymentScheduleTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddSkuAndRoleIdToQuoteCatalogItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateWorkOrchestrationTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateAdvisoryTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateWorkItemsTableAddRevenueAndTier::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddManagerPriorityOverrideToWorkItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddCalendarIdToEmployees::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddRequiredRoleIdToWorkItems::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateWorkItemsRemoveProjectTask::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateEventBackboneTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateExternalIntegrationTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateBillingExportTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateQuickBooksShadowTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateFeedTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddFeedIndexes::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateLeaveCapacityTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateDemoSeedRegistryTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateAdminAuditLog::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateConversationTables::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddReplyToMessageIdToConversationEvents::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddParticipantTypesToConversationParticipants::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddVersionToConversations::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\UpdateQuoteBlocksAddPayloadAndCreatedAt::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTicketBackboneColumns::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreateTicketLinksTable::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddTicketIdToTasks::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddCorrectsEntryIdToTimeEntries::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\AddLeadIdToQuotes::class,
            \Pet\Infrastructure\Persistence\Migration\Definition\CreatePulsewayIntegrationTables::class,
        ];
    }
}
