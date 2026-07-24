<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MaintenanceApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testListMaintenanceSchedulesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/maintenance/schedules?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /maintenance/schedules endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /maintenance/schedules, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetMaintenanceScheduleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/maintenance/schedules/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /maintenance/schedules/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /maintenance/schedules/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testPatchMaintenanceScheduleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/maintenance/schedules/' . self::DUMMY_UUID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"intervalOverride":"P90D"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /maintenance/schedules/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /maintenance/schedules/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGenerateInspectionCampaignRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/maintenance/campaigns', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"organization":"/api/organizations/' . self::DUMMY_UUID . '","name":"Q1 Campaign","dueBefore":"2026-02-01T00:00:00+00:00"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /maintenance/campaigns endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /maintenance/campaigns, got ' . $statusCode,
    );
  }
}
