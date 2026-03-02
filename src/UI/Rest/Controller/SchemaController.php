<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Configuration\Entity\SchemaDefinition;
use Pet\Domain\Configuration\Entity\SchemaStatus;
use Pet\Domain\Configuration\Repository\SchemaDefinitionRepository;
use Pet\Domain\Configuration\Service\SchemaValidator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use InvalidArgumentException;
use DomainException;

class SchemaController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'schemas';

    private SchemaDefinitionRepository $schemaRepository;
    private SchemaValidator $schemaValidator;

    public function __construct(
        SchemaDefinitionRepository $schemaRepository,
        SchemaValidator $schemaValidator
    ) {
        $this->schemaRepository = $schemaRepository;
        $this->schemaValidator = $schemaValidator;
    }

    public function registerRoutes(): void
    {
        // List Schemas
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<entity_type>[a-zA-Z0-9_-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSchemas'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // Create Draft
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/draft', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createDraftSchema'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // Get Schema Details
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSchemaById'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE, // PUT
                'callback' => [$this, 'updateDraftSchema'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);

        // Publish Schema
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/publish', [
            [
                'methods' => WP_REST_Server::EDITABLE, // POST
                'callback' => [$this, 'publishSchema'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getSchemas(WP_REST_Request $request): WP_REST_Response
    {
        $entityType = $request->get_param('entity_type');
        $status = $request->get_param('status');

        if ($status === 'active') {
             $schema = $this->schemaRepository->findActiveByEntityType($entityType);
             if (!$schema) {
                 return new WP_REST_Response([], 200);
             }
             return new WP_REST_Response([$this->serializeSchema($schema)], 200);
        }

        $schemas = $this->schemaRepository->findByEntityType($entityType);

        $data = array_map(function (SchemaDefinition $schema) {
            return $this->serializeSchema($schema);
        }, $schemas);

        return new WP_REST_Response($data, 200);
    }

    public function getSchemaById(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $schema = $this->schemaRepository->findById($id);

        if (!$schema) {
            return new WP_REST_Response(['error' => 'Schema not found'], 404);
        }

        return new WP_REST_Response($this->serializeSchema($schema), 200);
    }

    public function createDraftSchema(WP_REST_Request $request): WP_REST_Response
    {
        $entityType = $request->get_param('entityType');
        $cloneFromActive = $request->get_param('cloneFromActive');

        if (empty($entityType)) {
            return new WP_REST_Response(['error' => 'Entity type is required'], 400);
        }

        // Check if draft already exists
        $existingDraft = $this->schemaRepository->findDraftByEntityType($entityType);
        if ($existingDraft) {
            return new WP_REST_Response(['error' => 'A draft schema already exists for this entity type'], 400);
        }

        // Determine version
        $latest = $this->schemaRepository->findLatestByEntityType($entityType);
        $version = $latest ? $latest->version() + 1 : 1;

        // Determine schema content
        $schemaContent = ['fields' => []];
        if ($cloneFromActive && $latest && $latest->status() === SchemaStatus::ACTIVE) {
            $schemaContent = $latest->schema();
        }

        $schema = new SchemaDefinition(
            $entityType,
            $version,
            $schemaContent,
            null,
            SchemaStatus::DRAFT,
            null,
            null,
            new \DateTimeImmutable(),
            get_current_user_id()
        );

        $this->schemaRepository->save($schema);

        // Fetch the saved schema to get the ID
        $savedSchema = $this->schemaRepository->findDraftByEntityType($entityType);

        return new WP_REST_Response($this->serializeSchema($savedSchema), 201);
    }

    public function updateDraftSchema(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $schemaDef = $this->schemaRepository->findById($id);

        if (!$schemaDef) {
            return new WP_REST_Response(['error' => 'Schema not found'], 404);
        }

        if ($schemaDef->status() !== SchemaStatus::DRAFT) {
            return new WP_REST_Response(['error' => 'Only draft schemas can be updated'], 400);
        }

        $params = $request->get_json_params();
        $schemaJson = $params['schema'] ?? null;

        if (!$schemaJson || !is_array($schemaJson)) {
            return new WP_REST_Response(['error' => 'Invalid schema data'], 400);
        }

        try {
            // Payload is { "schema": [ ...fields... ] }
            $fullSchema = ['fields' => $schemaJson];
            $this->schemaValidator->validate($fullSchema);

            $schemaDef->updateSchema($fullSchema);
            $this->schemaRepository->save($schemaDef);

            return new WP_REST_Response($this->serializeSchema($schemaDef), 200);

        } catch (InvalidArgumentException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        } catch (DomainException $e) {
             return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function publishSchema(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $schemaDef = $this->schemaRepository->findById($id);

        if (!$schemaDef) {
            return new WP_REST_Response(['error' => 'Schema not found'], 404);
        }

        try {
            $schemaDef->publish(get_current_user_id());
            
            // Mark current active as historical
            $this->schemaRepository->markActiveAsHistorical($schemaDef->entityType());
            
            // Save the new active schema
            $this->schemaRepository->save($schemaDef);

            return new WP_REST_Response($this->serializeSchema($schemaDef), 200);

        } catch (DomainException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    private function serializeSchema(SchemaDefinition $schema): array
    {
        $fields = $schema->schema()['fields'] ?? [];

        return [
            'id' => $schema->id(),
            'entityType' => $schema->entityType(),
            'version' => $schema->version(),
            'status' => $schema->status()->value,
            'schema' => $fields,
            'fields' => $fields, // Redundant key for backward compatibility/safety
            'createdAt' => $schema->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'publishedAt' => $schema->publishedAt() ? $schema->publishedAt()->format('Y-m-d\TH:i:s\Z') : null,
        ];
    }
}
