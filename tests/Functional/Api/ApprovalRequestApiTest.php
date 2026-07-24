<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApprovalRequestApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testListApprovalRequestsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/approval-requests');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/approval-requests endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testGetApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/approval-requests/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/approval-requests/{requestId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testApproveApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/approval-requests/' . self::DUMMY_UUID_2 . '/approve', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../approve endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testRejectApprovalRequestRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/approval-requests/' . self::DUMMY_UUID_2 . '/reject', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST .../reject endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testListApprovalActionTypesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/approvals/action-types');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /approvals/action-types endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }
}
