<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function http_build_query;

/**
 * Test AuditApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditApiTest extends WebTestCase
{
  // #region Methods
  /**
   * Method testAuditEventsEndpointRequiresAuthentication.
   *
   * Tests that the audit events endpoint requires authentication.
   */
  #[Test]
  public function testAuditEventsEndpointRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/audit-events');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 status code for unauthenticated request, got ' . $statusCode,
    );
  }

  /**
   * Method testAuditEventEndpointExists.
   *
   * Tests that the audit event endpoint exists and requires authentication.
   */
  #[Test]
  public function testAuditEventEndpointExists(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/audit-events/550e8400-e29b-41d4-a716-446655440000');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'Audit event endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated request, got ' . $statusCode,
    );
  }

  /**
   * Method testExportAuditEventsEndpointExistsAndRequiresAuthentication.
   *
   * Tests that the export endpoint exists (gated on `audit.export`, distinct
   * from the `audit.read` list/get endpoints) and requires authentication.
   */
  #[Test]
  public function testExportAuditEventsEndpointExistsAndRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/audit-events/export');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'Audit events export endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated request, got ' . $statusCode,
    );
  }

  /**
   * Method testExportAuditEventsEndpointAcceptsTheSameFiltersAsTheListEndpoint.
   *
   * Tests that the export endpoint accepts the same filter query parameters
   * as the list endpoint without erroring on request parsing (still gated
   * by authentication before the filters are ever evaluated).
   */
  #[Test]
  public function testExportAuditEventsEndpointAcceptsTheSameFiltersAsTheListEndpoint(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/audit-events/export?' . http_build_query([
      'action' => 'auth.login_success',
      'actorType' => 'user',
      'actorId' => 'user-1',
      'subjectType' => 'token',
      'subjectId' => 'token-1',
      'clientId' => 'client-1',
      'tenantId' => 'tenant-1',
      'ipHash' => 'hash-1',
      'from' => '2026-01-01T00:00:00+00:00',
      'to' => '2026-01-31T23:59:59+00:00',
    ]));

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'Audit events export endpoint should exist with filters applied (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated filtered request, got ' . $statusCode,
    );
  }
  // #endregion
}
