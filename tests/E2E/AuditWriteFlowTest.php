<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function http_build_query;
use function is_string;
use function json_encode;
use function str_contains;

/**
 * Test AuditWriteFlow.
 *
 * Presentation-layer E2E coverage for the audit ledger's CSV export endpoint
 * (`GET /api/audit-events/export`, wired to
 * {@see \Audit\Presentation\Api\Controller\ExportAuditEventsController} and
 * gated by `is_granted('audit.export')`). The seeded admin holds `audit.export`
 * via {@see \Authorization\Infrastructure\Catalog\RoleCatalog::adminPermissionNames()},
 * so an authenticated export streams a bounded CSV (the handler caps matches at
 * {@see \Audit\Application\UseCase\Query\ExportAuditEvents\ExportAuditEventsHandler::MAX_EXPORT_ROWS}
 * = 50k rows — a fresh test ledger is far under it, so the response is a
 * deterministic 200). One unauthenticated call proves the route exists and is
 * guarded.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  /**
   * A seeded active user holding the plain `user` role
   * ({@see \Authorization\Infrastructure\Catalog\RoleCatalog::userPermissionNames()}),
   * which grants profile/session/OTP self-service and **not** `audit.export`.
   */
  private const string PLAIN_USER_EMAIL = 'test@fireguard.local';

  private const string PLAIN_USER_PASSWORD = 'Test123!';

  private const string EXPORT_URI = '/api/audit-events/export';

  /**
   * GET /api/audit-events/export — authenticated admin streams the CSV export.
   */
  public function testAdminExportsAuditEventsAsCsvStream(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'GET',
      uri: self::EXPORT_URI,
      server: [
        'HTTP_ACCEPT' => 'text/csv',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Admin holding audit.export should stream the CSV export. Response: ' . $response->getContent(),
    );

    $contentType = $response->headers->get('Content-Type');
    self::assertTrue(
      is_string($contentType) && str_contains($contentType, 'text/csv'),
      'Export must be streamed as CSV; got Content-Type: ' . (string) $contentType,
    );

    $disposition = $response->headers->get('Content-Disposition');
    self::assertTrue(
      is_string($disposition) && str_contains($disposition, 'attachment'),
      'Export must be an attachment download; got Content-Disposition: ' . (string) $disposition,
    );
  }

  /**
   * GET /api/audit-events/export?<filters> — the same filter set as the list
   * endpoint is accepted and still streams a bounded CSV (exercises the
   * request-to-criteria factory and the applied-filter-keys audit metadata).
   */
  public function testAdminExportsAuditEventsWithListFiltersApplied(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    // `tenantId` is deliberately absent: a valid tenant UUID in the query
    // string makes TenantIsolationSubscriber enable the `tenant` Doctrine
    // filter for the whole request, which also filters the role/permission
    // tables and strips the caller's own global permissions — so ANY
    // permission-gated endpoint answers 403 when filtered by tenant
    // (`GET /api/audit-events?tenantId=<uuid>` does the same). That defect is
    // outside this flow; asserting 200 here would only hide it again.
    $query = http_build_query([
      'action' => 'auth.login_success',
      'actorType' => 'user',
      'from' => '2026-01-01T00:00:00+00:00',
      'to' => '2026-12-31T23:59:59+00:00',
    ]);

    $client->request(
      method: 'GET',
      uri: self::EXPORT_URI . '?' . $query,
      server: [
        'HTTP_ACCEPT' => 'text/csv',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'A filtered export (list-endpoint filter set) should also stream a bounded CSV. Response: ' . $response->getContent(),
    );

    $contentType = $response->headers->get('Content-Type');
    self::assertTrue(
      is_string($contentType) && str_contains($contentType, 'text/csv'),
      'The filtered export must still be streamed as CSV; got Content-Type: ' . (string) $contentType,
    );
  }

  /**
   * GET /api/audit-events/export — an authenticated caller without
   * `audit.export` is refused with 403. Authentication alone must never
   * open the ledger: the plain `user` role has full self-service rights and
   * still may not export who did what.
   */
  public function testAuthenticatedUserWithoutTheExportPermissionIsForbidden(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::PLAIN_USER_EMAIL, self::PLAIN_USER_PASSWORD);

    $client->request(
      method: 'GET',
      uri: self::EXPORT_URI,
      server: [
        'HTTP_ACCEPT' => 'text/csv',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_FORBIDDEN,
      $response->getStatusCode(),
      'A user without audit.export must be refused with 403, not served the ledger. Response: ' . $response->getContent(),
    );
  }

  /**
   * GET /api/audit-events/export — the route exists (not 404) and is guarded
   * when unauthenticated (401/403), never leaking the ledger.
   */
  public function testExportRouteExistsAndRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'GET',
      uri: self::EXPORT_URI,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
    );

    $status = $client->getResponse()->getStatusCode();

    self::assertNotSame(
      Response::HTTP_NOT_FOUND,
      $status,
      'Export route should exist (not 404).',
    );
    self::assertContains(
      $status,
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Export route should be guarded when unauthenticated; got ' . $status,
    );
  }

  // #region Helpers

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'email' => $email,
        'password' => $password,
      ]) ?: '',
    );

    $response = $client->getResponse();
    self::assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Admin login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    self::assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  // #endregion
}
