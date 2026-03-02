<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Domain\Identity\Repository\ContactRepository;
use Pet\Application\Identity\Command\CreateContactCommand;
use Pet\Application\Identity\Command\CreateContactHandler;
use Pet\Application\Identity\Command\UpdateContactCommand;
use Pet\Application\Identity\Command\UpdateContactHandler;
use Pet\Application\Identity\Command\ArchiveContactCommand;
use Pet\Application\Identity\Command\ArchiveContactHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ContactController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'contacts';

    private ContactRepository $contactRepository;
    private CreateContactHandler $createContactHandler;
    private UpdateContactHandler $updateContactHandler;
    private ArchiveContactHandler $archiveContactHandler;

    public function __construct(
        ContactRepository $contactRepository,
        CreateContactHandler $createContactHandler,
        UpdateContactHandler $updateContactHandler,
        ArchiveContactHandler $archiveContactHandler
    ) {
        $this->contactRepository = $contactRepository;
        $this->createContactHandler = $createContactHandler;
        $this->updateContactHandler = $updateContactHandler;
        $this->archiveContactHandler = $archiveContactHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getContacts'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createContact'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateContact'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveContact'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getContacts(WP_REST_Request $request): WP_REST_Response
    {
        $contacts = $this->contactRepository->findAll();

        $data = array_map(function ($contact) {
            $affiliations = array_map(function ($aff) {
                return [
                    'customerId' => $aff->customerId(),
                    'siteId' => $aff->siteId(),
                    'role' => $aff->role(),
                    'isPrimary' => $aff->isPrimary(),
                ];
            }, $contact->affiliations());

            return [
                'id' => $contact->id(),
                'firstName' => $contact->firstName(),
                'lastName' => $contact->lastName(),
                'email' => $contact->email(),
                'phone' => $contact->phone(),
                'affiliations' => $affiliations,
                'malleableData' => $contact->malleableData(),
                'createdAt' => $contact->createdAt()->format('Y-m-d H:i:s'),
                'archivedAt' => $contact->archivedAt() ? $contact->archivedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $contacts);

        return new WP_REST_Response($data, 200);
    }

    public function createContact(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (empty($params['firstName']) || empty($params['lastName']) || empty($params['email'])) {
            return new WP_REST_Response(['message' => 'Missing required fields'], 400);
        }

        try {
            $command = new CreateContactCommand(
                $params['firstName'],
                $params['lastName'],
                $params['email'],
                $params['phone'] ?? null,
                $params['affiliations'] ?? [],
                $params['malleableData'] ?? []
            );

            $this->createContactHandler->handle($command);

            return new WP_REST_Response(['message' => 'Contact created successfully'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function updateContact(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request['id'];
        $params = $request->get_json_params();

        try {
            $command = new UpdateContactCommand(
                $id,
                $params['firstName'],
                $params['lastName'],
                $params['email'],
                $params['phone'] ?? null,
                $params['affiliations'] ?? [],
                $params['malleableData'] ?? []
            );

            $this->updateContactHandler->handle($command);

            return new WP_REST_Response(['message' => 'Contact updated successfully'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function archiveContact(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request['id'];

        try {
            $command = new ArchiveContactCommand($id);
            $this->archiveContactHandler->handle($command);

            return new WP_REST_Response(['message' => 'Contact archived successfully'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }
}
