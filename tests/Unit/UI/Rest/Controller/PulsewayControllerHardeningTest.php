<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\UI\Rest\Controller;

use Pet\Application\Integration\Pulseway\Service\DeviceSnapshotService;
use Pet\Application\Integration\Pulseway\Service\NotificationIngestionService;
use Pet\Application\System\Service\FeatureFlagService;
use Pet\Infrastructure\Integration\Pulseway\CredentialEncryptionService;
use Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository;
use Pet\UI\Rest\Controller\PulsewayController;
use PHPUnit\Framework\TestCase;

final class PulsewayControllerHardeningTest extends TestCase
{
    public function testCreateMappingReturns422ForDomainException(): void
    {
        $repo = new SqlPulsewayIntegrationRepository(new class {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public function insert(string $table, array $data): void { throw new \DomainException('invalid mapping'); }
            public function update(string $table, array $data, array $where): void {}
        });

        $controller = $this->makeController($repo);
        $request = new \WP_REST_Request('POST', '/pet/v1/pulseway/integrations/1/mappings');
        $request->set_param('id', 1);
        $request->set_json_params(['pet_team_id' => 3]);

        $response = $controller->createMapping($request);
        self::assertSame(422, $response->get_status());
        self::assertSame('invalid mapping', $response->get_data()['error'] ?? null);
    }

    public function testCreateMappingReturns500ForUnexpectedThrowable(): void
    {
        $repo = new SqlPulsewayIntegrationRepository(new class {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public function insert(string $table, array $data): void { throw new \RuntimeException('db failure'); }
            public function update(string $table, array $data, array $where): void {}
        });

        $controller = $this->makeController($repo);
        $request = new \WP_REST_Request('POST', '/pet/v1/pulseway/integrations/1/mappings');
        $request->set_param('id', 1);
        $request->set_json_params(['pet_team_id' => 3]);

        $response = $controller->createMapping($request);
        self::assertSame(500, $response->get_status());
        self::assertSame('Failed to create mapping', $response->get_data()['error'] ?? null);
    }

    public function testUpdateMappingReturns422ForDomainException(): void
    {
        $repo = new SqlPulsewayIntegrationRepository(new class {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public function insert(string $table, array $data): void {}
            public function update(string $table, array $data, array $where): void { throw new \DomainException('invalid mapping update'); }
        });

        $controller = $this->makeController($repo);
        $request = new \WP_REST_Request('PATCH', '/pet/v1/pulseway/mappings/10');
        $request->set_param('id', 10);
        $request->set_json_params(['pet_team_id' => 2]);

        $response = $controller->updateMapping($request);
        self::assertSame(422, $response->get_status());
        self::assertSame('invalid mapping update', $response->get_data()['error'] ?? null);
    }

    public function testUpdateMappingReturns500ForUnexpectedThrowable(): void
    {
        $repo = new SqlPulsewayIntegrationRepository(new class {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public function insert(string $table, array $data): void {}
            public function update(string $table, array $data, array $where): void { throw new \RuntimeException('update failed'); }
        });

        $controller = $this->makeController($repo);
        $request = new \WP_REST_Request('PATCH', '/pet/v1/pulseway/mappings/10');
        $request->set_param('id', 10);
        $request->set_json_params(['pet_team_id' => 2]);

        $response = $controller->updateMapping($request);
        self::assertSame(500, $response->get_status());
        self::assertSame('Failed to update mapping', $response->get_data()['error'] ?? null);
    }

    public function testUpdateRuleReturns422ForDomainException(): void
    {
        $repo = new SqlPulsewayIntegrationRepository(new class {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public function insert(string $table, array $data): void {}
            public function update(string $table, array $data, array $where): void { throw new \DomainException('invalid rule update'); }
        });

        $controller = $this->makeController($repo);
        $request = new \WP_REST_Request('PATCH', '/pet/v1/pulseway/rules/11');
        $request->set_param('id', 11);
        $request->set_json_params(['output_priority' => 'high']);

        $response = $controller->updateRule($request);
        self::assertSame(422, $response->get_status());
        self::assertSame('invalid rule update', $response->get_data()['error'] ?? null);
    }

    public function testUpdateRuleReturns500ForUnexpectedThrowable(): void
    {
        $repo = new SqlPulsewayIntegrationRepository(new class {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public function insert(string $table, array $data): void {}
            public function update(string $table, array $data, array $where): void { throw new \RuntimeException('rule update failed'); }
        });

        $controller = $this->makeController($repo);
        $request = new \WP_REST_Request('PATCH', '/pet/v1/pulseway/rules/11');
        $request->set_param('id', 11);
        $request->set_json_params(['output_priority' => 'high']);

        $response = $controller->updateRule($request);
        self::assertSame(500, $response->get_status());
        self::assertSame('Failed to update rule', $response->get_data()['error'] ?? null);
    }

    private function makeController(SqlPulsewayIntegrationRepository $repo): PulsewayController
    {
        $encryption = new CredentialEncryptionService();
        $flags = $this->createMock(FeatureFlagService::class);
        $ingestion = new NotificationIngestionService($repo, $encryption, $flags);
        $device = new DeviceSnapshotService($repo, $encryption, $flags);

        return new PulsewayController($repo, $encryption, $ingestion, $device, $flags);
    }
}

