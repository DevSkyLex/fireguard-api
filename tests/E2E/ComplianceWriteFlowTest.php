<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;
use function str_contains;
use function str_starts_with;

/**
 * Test ComplianceWriteFlow.
 *
 * Drives the "registre de sécurité" PDF export operations that route through
 * {@see \Compliance\Presentation\Api\Controller\ExportSafetyRegisterController}
 * (both the organization-wide and the facility-scoped routes). Uses the seeded
 * MAX-plan organization whose owner is the seeded admin, so the plan
 * entitlement gate and the `organization.compliance.export` permission both
 * pass and the controller renders a real PDF end-to-end (authorize → entitle →
 * fetch read-model → render → dispatch audit event → stream the response).
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  // Seeded "Paris Headquarters" site belonging to the seeded organization
  // (see FacilityFixtures); used to exercise the facility-scoped export.
  private const string SEEDED_FACILITY_ID = '22222222-2222-4222-8222-222222222221';

  /**
   * GET /api/organizations/{organizationId}/compliance/export — organization-wide PDF.
   *
   * The seeded organization is on the MAX plan and the admin owns it, so the
   * entitlement + permission gates pass and the controller must stream a PDF.
   */
  public function testSeededOrganizationSafetyRegisterExportsPdfForEntitledAdmin(): void
  {
    $client = static::createClientWithFixtures();

    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/compliance/export',
      server: [
        'HTTP_ACCEPT' => 'application/pdf',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Entitled admin should export the organization safety register PDF. Response: ' . $response->getContent(),
    );

    $contentType = $response->headers->get('Content-Type');
    $this->assertTrue(is_string($contentType) && str_contains($contentType, 'application/pdf'), 'Export should be served as application/pdf.');

    $disposition = $response->headers->get('Content-Disposition');
    $this->assertTrue(is_string($disposition) && str_contains($disposition, 'registre-securite'), 'Export should be an attachment named registre-securite.');

    $body = $response->getContent();
    $this->assertTrue(is_string($body) && str_starts_with($body, '%PDF'), 'Export body should be a PDF document.');
  }

  /**
   * GET /api/organizations/{organizationId}/facilities/{facilityId}/compliance/export — facility-scoped PDF.
   *
   * Exercises the facility branch of the controller against a seeded facility
   * that belongs to the entitled seeded organization.
   */
  public function testSeededFacilitySafetyRegisterExportsPdfForEntitledAdmin(): void
  {
    $client = static::createClientWithFixtures();

    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/facilities/' . self::SEEDED_FACILITY_ID . '/compliance/export',
      server: [
        'HTTP_ACCEPT' => 'application/pdf',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    $this->assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Entitled admin should export the facility safety register PDF. Response: ' . $response->getContent(),
    );

    $contentType = $response->headers->get('Content-Type');
    $this->assertTrue(is_string($contentType) && str_contains($contentType, 'application/pdf'), 'Facility export should be served as application/pdf.');

    $body = $response->getContent();
    $this->assertTrue(is_string($body) && str_starts_with($body, '%PDF'), 'Facility export body should be a PDF document.');
  }

  /**
   * Both export routes exist and are guarded: no auth → not 404, and 401/403.
   */
  public function testSafetyRegisterExportRoutesRequireAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $routes = [
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/compliance/export',
      '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/facilities/' . self::SEEDED_FACILITY_ID . '/compliance/export',
    ];

    foreach ($routes as $uri) {
      $client->request(
        method: 'GET',
        uri: $uri,
        server: [
          'HTTP_ACCEPT' => 'application/pdf',
        ],
      );

      $status = $client->getResponse()->getStatusCode();

      $this->assertNotSame(Response::HTTP_NOT_FOUND, $status, 'Export route should exist (not 404): ' . $uri);
      $this->assertContains(
        $status,
        [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
        'Export route should be guarded when unauthenticated: ' . $uri,
      );
    }
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
    $this->assertContains(
      $response->getStatusCode(),
      [Response::HTTP_OK, Response::HTTP_CREATED],
      'Seeded admin login should succeed. Response: ' . $response->getContent(),
    );

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;

    $this->assertTrue(is_string($token) && '' !== $token, 'Login response should contain access_token.');

    return $token;
  }

  // #endregion
}
