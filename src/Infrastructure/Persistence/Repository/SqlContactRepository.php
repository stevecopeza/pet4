<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Identity\Entity\Contact;
use Pet\Domain\Identity\Entity\ContactAffiliation;
use Pet\Domain\Identity\Repository\ContactRepository;

class SqlContactRepository implements ContactRepository
{
    private $wpdb;
    private $tableName;
    private $affiliationsTable;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'pet_contacts';
        $this->affiliationsTable = $wpdb->prefix . 'pet_contact_affiliations';
    }

    public function save(Contact $contact): void
    {
        $data = [
            'first_name' => $contact->firstName(),
            'last_name' => $contact->lastName(),
            'email' => $contact->email(),
            'phone' => $contact->phone(),
            'malleable_schema_version' => $contact->malleableSchemaVersion(),
            'malleable_data' => !empty($contact->malleableData()) ? json_encode($contact->malleableData()) : null,
            'created_at' => $this->formatDate($contact->createdAt()),
            'archived_at' => $this->formatDate($contact->archivedAt()),
        ];

        $format = ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];

        if ($contact->id()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $contact->id()],
                $format,
                ['%d']
            );
            $contactId = $contact->id();
        } else {
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            $contactId = (int) $this->wpdb->insert_id;
        }

        // Handle affiliations
        $this->saveAffiliations($contactId, $contact->affiliations());
    }

    private function saveAffiliations(int $contactId, array $affiliations): void
    {
        // For simplicity, we'll clear and re-insert. 
        // In a production app, you might want to diff and update.
        $this->wpdb->delete($this->affiliationsTable, ['contact_id' => $contactId], ['%d']);

        foreach ($affiliations as $affiliation) {
            $this->wpdb->insert($this->affiliationsTable, [
                'contact_id' => $contactId,
                'customer_id' => $affiliation->customerId(),
                'site_id' => $affiliation->siteId(),
                'role' => $affiliation->role(),
                'is_primary' => $affiliation->isPrimary() ? 1 : 0,
                'created_at' => current_time('mysql'),
            ], ['%d', '%d', '%d', '%s', '%d', '%s']);
        }
    }

    public function findById(int $id): ?Contact
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql);

        if (!$row) {
            return null;
        }

        $affiliations = $this->loadAffiliations((int) $row->id);
        return $this->hydrate($row, $affiliations);
    }

    public function findByCustomerId(int $customerId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT c.* FROM {$this->tableName} c
             JOIN {$this->affiliationsTable} a ON c.id = a.contact_id
             WHERE a.customer_id = %d
             ORDER BY c.last_name ASC",
            $customerId
        );
        $results = $this->wpdb->get_results($sql);

        $contacts = [];
        foreach ($results as $row) {
            $affiliations = $this->loadAffiliations((int) $row->id);
            $contacts[] = $this->hydrate($row, $affiliations);
        }

        return $contacts;
    }

    public function findBySiteId(int $siteId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT c.* FROM {$this->tableName} c
             JOIN {$this->affiliationsTable} a ON c.id = a.contact_id
             WHERE a.site_id = %d
             ORDER BY c.last_name ASC",
            $siteId
        );
        $results = $this->wpdb->get_results($sql);

        $contacts = [];
        foreach ($results as $row) {
            $affiliations = $this->loadAffiliations((int) $row->id);
            $contacts[] = $this->hydrate($row, $affiliations);
        }

        return $contacts;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE archived_at IS NULL ORDER BY last_name ASC, first_name ASC";
        $results = $this->wpdb->get_results($sql);

        $contacts = [];
        foreach ($results as $row) {
            $affiliations = $this->loadAffiliations((int) $row->id);
            $contacts[] = $this->hydrate($row, $affiliations);
        }

        return $contacts;
    }

    private function loadAffiliations(int $contactId): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->affiliationsTable} WHERE contact_id = %d",
            $contactId
        );
        $rows = $this->wpdb->get_results($sql);

        $affiliations = [];
        foreach ($rows as $row) {
            $affiliations[] = new ContactAffiliation(
                (int) $row->customer_id,
                $row->site_id ? (int) $row->site_id : null,
                $row->role,
                (bool) $row->is_primary
            );
        }

        return $affiliations;
    }

    private function hydrate(object $row, array $affiliations = []): Contact
    {
        return new Contact(
            $row->first_name,
            $row->last_name,
            $row->email,
            $row->phone,
            $affiliations,
            (int) $row->id,
            $row->malleable_schema_version ? (int) $row->malleable_schema_version : null,
            $row->malleable_data ? json_decode($row->malleable_data, true) : [],
            new \DateTimeImmutable($row->created_at),
            $row->archived_at ? new \DateTimeImmutable($row->archived_at) : null
        );
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}
