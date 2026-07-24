<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_encode;

/**
 * Test InspectionApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testListInspectionResultsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/inspections/results');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /inspections/results endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /inspections/results, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListInspectionStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/inspections/statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /inspections/statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /inspections/statuses, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListInspectorTypesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/inspections/inspector-types');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /inspections/inspector-types endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /inspections/inspector-types, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListChecklistStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/checklists/statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /checklists/statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /checklists/statuses, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListNonConformityStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/non-conformities/statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /non-conformities/statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /non-conformities/statuses, got ' . $statusCode,
    );
  }

  // #region Methods

  // -------------------------------------------------------------------------
  // Inspection endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testCreateInspectionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/inspections',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'equipmentId' => self::DUMMY_UUID,
        'result' => 'pass',
        'performedAt' => '2026-01-15T10:00:00+00:00',
        'inspectorType' => 'user',
        'inspectorName' => 'John Doe',
        'inspectorUserId' => self::DUMMY_UUID,
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /inspections, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListInspectionsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/inspections');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /inspections, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetInspectionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /inspections/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /inspections/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testSubmitInspectionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID . '/submit',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{}',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /inspections/{id}/submit endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /submit, got ' . $statusCode,
    );
  }

  #[Test]
  public function testCloseInspectionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID . '/close',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{}',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /inspections/{id}/close endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /close, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // NonConformity endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testAddNonConformityRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID . '/non-conformities',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'description' => 'Issue found',
        'severity' => 'high',
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /non-conformities, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListNonConformitiesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID . '/non-conformities',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /non-conformities, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListOrganizationNonConformitiesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/non-conformities',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/non-conformities endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/non-conformities, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Checklist endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testCreateChecklistRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/checklists',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'name' => 'Fire Safety Checklist',
        'version' => 'v1.0',
        'items' => [],
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /checklists, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListChecklistsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/checklists');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /checklists, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetChecklistRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/checklists/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /checklists/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /checklists/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testArchiveChecklistRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/checklists/' . self::DUMMY_UUID . '/archive',
      server: ['CONTENT_TYPE' => 'application/json'],
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /checklists/{id}/archive endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /checklists/{id}/archive, got ' . $statusCode,
    );
  }

  #[Test]
  public function testUpdateChecklistRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/checklists/' . self::DUMMY_UUID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: (string) json_encode(['name' => 'Renamed Checklist']),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /checklists/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /checklists/{id}, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Edit and cancel inspection endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testEditInspectionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: (string) json_encode(['notes' => 'updated']),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /inspections/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /inspections/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testCancelInspectionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /inspections/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /inspections/{id}, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Non-conformity status and detail endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testGetNonConformityRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID . '/non-conformities/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /non-conformities/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /non-conformities/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testUpdateNonConformityStatusRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/inspections/' . self::DUMMY_UUID . '/non-conformities/' . self::DUMMY_UUID . '/status',
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: (string) json_encode(['status' => 'in_progress']),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /non-conformities/{id}/status endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /non-conformities/{id}/status, got ' . $statusCode,
    );
  }
  // #endregion
}
