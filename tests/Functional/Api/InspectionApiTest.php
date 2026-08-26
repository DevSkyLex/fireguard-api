<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function array_values;
use function json_decode;
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

  private const string ORGANIZATION_ID = '860e8400-e29b-41d4-a716-446655490001';

  private const string ADMIN_USER_ID = '860e8400-e29b-41d4-a716-446655490002';

  private const string ADMIN_MEMBER_ID = '860e8400-e29b-41d4-a716-446655490003';

  private const string READ_ONLY_USER_ID = '860e8400-e29b-41d4-a716-446655490004';

  private const string NO_ACCESS_USER_ID = '860e8400-e29b-41d4-a716-446655490005';

  private const string OUTSIDER_ORGANIZATION_ID = '860e8400-e29b-41d4-a716-446655490006';

  private const string OUTSIDER_USER_ID = '860e8400-e29b-41d4-a716-446655490007';

  private const string APPROVAL_ORGANIZATION_ID = '860e8400-e29b-41d4-a716-446655490010';

  private const string APPROVAL_ADMIN_USER_ID = '860e8400-e29b-41d4-a716-446655490011';

  private const string APPROVAL_NO_REQUEST_USER_ID = '860e8400-e29b-41d4-a716-446655490012';

  private const string APPROVAL_ADMIN_MEMBER_ID = '860e8400-e29b-41d4-a716-446655490074';

  private const string EQUIPMENT_ID = '860e8400-e29b-41d4-a716-446655490015';

  private const string OPEN_INSPECTION_ID = '860e8400-e29b-41d4-a716-446655490020';

  private const string CLOSED_INSPECTION_ID = '860e8400-e29b-41d4-a716-446655490021';

  private const string OTHER_INSPECTION_ID = '860e8400-e29b-41d4-a716-446655490022';

  private const string OUTSIDER_INSPECTION_ID = '860e8400-e29b-41d4-a716-446655490023';

  private const string APPROVAL_INSPECTION_ID = '860e8400-e29b-41d4-a716-446655490030';

  private const string OPEN_NC_ID = '860e8400-e29b-41d4-a716-446655490040';

  private const string RESOLVED_NC_ID = '860e8400-e29b-41d4-a716-446655490041';

  private const string OTHER_INSPECTION_NC_ID = '860e8400-e29b-41d4-a716-446655490042';

  private const string CLOSED_INSPECTION_NC_ID = '860e8400-e29b-41d4-a716-446655490043';

  private const string OUTSIDER_NC_ID = '860e8400-e29b-41d4-a716-446655490044';

  private const string APPROVAL_CRITICAL_NC_ID = '860e8400-e29b-41d4-a716-446655490050';

  private const string APPROVAL_GATED_NC_ID = '860e8400-e29b-41d4-a716-446655490051';

  private const string APPROVAL_LOW_NC_ID = '860e8400-e29b-41d4-a716-446655490052';

  private const string APPROVAL_PENDING_NC_ID = '860e8400-e29b-41d4-a716-446655490053';

  private const string PENDING_APPROVAL_REQUEST_ID = '860e8400-e29b-41d4-a716-446655490080';

  // #region Methods

  // -------------------------------------------------------------------------
  // Inspection endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testCanonicalInspectionCollectionDoesNotRevealForeignOrganizations(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    // OUTSIDER_ORGANIZATION_ID is seeded and real; the caller is a member of
    // ORGANIZATION_ID and not of it. An absent id answers 404, so this one must
    // too — answering 403 tells an outsider the organization exists, which is
    // the enumeration oracle `Messaging/MODULE.md` forbids and that
    // twenty-five sibling surfaces already avoid with `resolveAccess()`.
    //
    // One request only: the token `loginUser()` sets does not reliably survive
    // a second one in this suite, and a stray 401 would hide the difference.
    $client->request('GET', '/api/inspections?organization=/api/organizations/' . self::OUTSIDER_ORGANIZATION_ID);

    self::assertSame(
      404,
      $client->getResponse()->getStatusCode(),
      'A real organization the caller does not belong to must answer 404, exactly like an absent one.',
    );
  }

  #[Test]
  public function testCanonicalInspectionMutationDoesNotRevealForeignInspections(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    // Same oracle as the collection above, one level down and worse: here the
    // enumerable value is the INSPECTION id. The processor loads it by global
    // id, then permission-checks against the record's OWN organization — so a
    // foreign inspection answers 403 while an absent one answers 404, and the
    // difference confirms the id exists.
    $client->request(
      method: 'DELETE',
      uri: '/api/inspections/' . self::OUTSIDER_INSPECTION_ID,
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    self::assertSame(
      404,
      $client->getResponse()->getStatusCode(),
      'An inspection in another organization must answer 404, exactly like an absent one.',
    );
  }

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

  // #region Non-conformities — success paths

  #[Test]
  public function testAddNonConformityReturnsCreatedPayload(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request(
      method: 'POST',
      uri: $this->nonConformitiesUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID),
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'description' => 'Extinguisher pressure gauge in the red zone.',
        'severity' => 'high',
        'notes' => 'Spotted on the second floor landing.',
      ]),
    );

    self::assertSame(
      expected: 201,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Expected 201 when adding a non-conformity. Response: ' . (string) $client->getResponse()->getContent(),
    );

    $payload = $this->decodeObject($client);

    self::assertIsString($payload['id']);
    self::assertSame(self::OPEN_INSPECTION_ID, $payload['inspectionId']);
    self::assertSame('Extinguisher pressure gauge in the red zone.', $payload['description']);
    self::assertSame('high', $payload['severity']);
    self::assertSame('open', $payload['status'], 'A newly recorded non-conformity always starts open.');
    self::assertSame('Spotted on the second floor landing.', $payload['notes']);
    self::assertIsString($payload['createdAt']);
    self::assertIsString($payload['updatedAt']);
  }

  #[Test]
  public function testListNonConformitiesReturnsTheInspectionRows(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request('GET', $this->nonConformitiesUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID));

    $ids = $this->memberIds($this->decodeCollection($client));

    self::assertContains(self::OPEN_NC_ID, $ids);
    self::assertContains(self::RESOLVED_NC_ID, $ids);
    self::assertNotContains(
      needle: self::OTHER_INSPECTION_NC_ID,
      haystack: $ids,
      message: 'The per-inspection list must not leak a sibling inspection rows.',
    );
  }

  #[Test]
  public function testListNonConformitiesFiltersByStatus(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request('GET', $this->nonConformitiesUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID) . '?status=done');

    $ids = $this->memberIds($this->decodeCollection($client));

    self::assertContains(self::RESOLVED_NC_ID, $ids);
    self::assertNotContains(self::OPEN_NC_ID, $ids);
  }

  #[Test]
  public function testListOrganizationNonConformitiesSpansEveryInspection(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities');

    $ids = $this->memberIds($this->decodeCollection($client));

    self::assertContains(self::OPEN_NC_ID, $ids);
    self::assertContains(self::OTHER_INSPECTION_NC_ID, $ids, 'The organization-wide register spans every inspection.');
    self::assertNotContains(
      needle: self::OUTSIDER_NC_ID,
      haystack: $ids,
      message: 'The organization-wide register must never leak another organization rows.',
    );
  }

  #[Test]
  public function testGetNonConformityReturnsItsDetails(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request('GET', $this->nonConformityUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID, self::OPEN_NC_ID));

    self::assertSame(
      expected: 200,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Response: ' . (string) $client->getResponse()->getContent(),
    );

    $payload = $this->decodeObject($client);

    self::assertSame(self::OPEN_NC_ID, $payload['id']);
    self::assertSame(self::OPEN_INSPECTION_ID, $payload['inspectionId']);
    self::assertSame('high', $payload['severity']);
    self::assertSame('open', $payload['status']);
  }

  #[Test]
  public function testUpdateNonConformityStatusReturnsTheNewStatus(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID, self::OPEN_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'in_progress']),
    );

    self::assertSame(
      expected: 200,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Response: ' . (string) $client->getResponse()->getContent(),
    );

    $payload = $this->decodeObject($client);

    self::assertSame(self::OPEN_NC_ID, $payload['id']);
    self::assertSame('in_progress', $payload['status']);
    self::assertNull($payload['resolvedAt'], 'in_progress is not a resolution: resolvedAt stays null.');
  }

  #[Test]
  public function testUpdateNonConformityStatusToDoneStampsResolvedAt(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID, self::OPEN_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'done']),
    );

    self::assertSame(
      expected: 200,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Response: ' . (string) $client->getResponse()->getContent(),
    );

    $payload = $this->decodeObject($client);

    self::assertSame('done', $payload['status']);
    self::assertIsString($payload['resolvedAt'], 'Reaching a terminal status stamps resolvedAt.');
  }

  // #endregion

  // #region Non-conformities — 403, authenticated but not entitled

  #[Test]
  public function testAddNonConformityRejectsMemberWithoutWritePermission(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::READ_ONLY_USER_ID, 'inspection-read-only@example.com');
    $client->request(
      method: 'POST',
      uri: $this->nonConformitiesUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID),
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['description' => 'Should not be recorded.', 'severity' => 'low']),
    );

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.inspection.write must get 403.',
    );
  }

  #[Test]
  public function testUpdateNonConformityStatusRejectsMemberWithoutWritePermission(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::READ_ONLY_USER_ID, 'inspection-read-only@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID, self::OPEN_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'in_progress']),
    );

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.inspection.write must get 403.',
    );
  }

  #[Test]
  public function testGetNonConformityRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::NO_ACCESS_USER_ID, 'inspection-no-access@example.com');
    $client->request('GET', $this->nonConformityUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID, self::OPEN_NC_ID));

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.inspection.read must get 403.',
    );
  }

  #[Test]
  public function testListNonConformitiesRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::NO_ACCESS_USER_ID, 'inspection-no-access@example.com');
    $client->request('GET', $this->nonConformitiesUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID));

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.inspection.read must get 403.',
    );
  }

  #[Test]
  public function testListOrganizationNonConformitiesRejectsMemberWithoutReadPermission(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::NO_ACCESS_USER_ID, 'inspection-no-access@example.com');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/non-conformities');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.inspection.read must get 403.',
    );
  }

  // #endregion

  // #region Non-conformities — 404, cross-organization and mismatched parents

  #[Test]
  public function testAddNonConformityOnAnotherOrganizationInspectionReturnsNotFound(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request(
      method: 'POST',
      uri: $this->nonConformitiesUri(self::ORGANIZATION_ID, self::OUTSIDER_INSPECTION_ID),
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['description' => 'Should not be recorded.', 'severity' => 'low']),
    );

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An inspection owned by another organization must read as absent, not forbidden.',
    );
  }

  #[Test]
  public function testGetNonConformityFromAnotherOrganizationReturnsNotFound(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request('GET', $this->nonConformityUri(self::ORGANIZATION_ID, self::OUTSIDER_INSPECTION_ID, self::OUTSIDER_NC_ID));

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Another organization non-conformity must read as absent.',
    );
  }

  #[Test]
  public function testGetNonConformityUnderTheWrongInspectionReturnsNotFound(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request('GET', $this->nonConformityUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID, self::OTHER_INSPECTION_NC_ID));

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A non-conformity addressed under an inspection that does not own it must read as absent.',
    );
  }

  #[Test]
  public function testUpdateNonConformityStatusUnderTheWrongInspectionReturnsNotFound(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID, self::OTHER_INSPECTION_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'in_progress']),
    );

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'The inspection/non-conformity pair must match, or the row reads as absent.',
    );
  }

  // #endregion

  // #region Non-conformities — 409 conflicts, and the closed-inspection asymmetry

  #[Test]
  public function testUpdateNonConformityStatusOnAResolvedRowReturnsConflict(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::ORGANIZATION_ID, self::OPEN_INSPECTION_ID, self::RESOLVED_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'open']),
    );

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Reopening a resolved non-conformity is a conflict, not a bad request.',
    );
  }

  #[Test]
  public function testAddNonConformityOnAClosedInspectionReturnsConflict(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request(
      method: 'POST',
      uri: $this->nonConformitiesUri(self::ORGANIZATION_ID, self::CLOSED_INSPECTION_ID),
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['description' => 'Found after the report was closed.', 'severity' => 'low']),
    );

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A closed inspection is a terminal report: nothing new may be added to it.',
    );
  }

  #[Test]
  public function testUpdateNonConformityStatusIsStillAllowedOnAClosedInspection(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::ADMIN_USER_ID, 'inspection-admin@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::ORGANIZATION_ID, self::CLOSED_INSPECTION_ID, self::CLOSED_INSPECTION_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'done']),
    );

    // Deliberate asymmetry with the POST above, recorded as an invariant in
    // `src/Inspection/MODULE.md`: closing an inspection freezes the REPORT,
    // never the remediation of what it found. Remediation routinely outlives
    // the closure, and an approved `nc_waiver` re-dispatched by
    // `NonConformityWaiverExecutorAdapter` lands on exactly this path days
    // after the fact. Freezing it here would also strand the organization
    // open/overdue/critical counters, which count a closed inspection rows.
    self::assertSame(
      expected: 200,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A closed inspection freezes the report, not the remediation of its non-conformities. Response: ' . (string) $client->getResponse()->getContent(),
    );

    self::assertSame('done', $this->decodeObject($client)['status']);
  }

  // #endregion

  // #region Non-conformities — the live four-eyes waiver gate (R17)

  #[Test]
  public function testWaivingDefersToApprovalWhenTheOrganizationRequiresIt(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::APPROVAL_ADMIN_USER_ID, 'inspection-approval-admin@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::APPROVAL_ORGANIZATION_ID, self::APPROVAL_INSPECTION_ID, self::APPROVAL_CRITICAL_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'waived']),
    );

    self::assertSame(
      expected: 202,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A gated waiver is accepted for approval, not applied. Response: ' . (string) $client->getResponse()->getContent(),
    );

    $payload = $this->decodeObject($client);

    self::assertSame('pending_approval', $payload['status']);
    self::assertIsString($payload['approvalRequestId']);
    self::assertNotSame('', $payload['approvalRequestId']);
    self::assertSame('pending', $payload['approvalStatus']);
    self::assertIsString($payload['expiresAt']);

    $this->assertNonConformityStatusIs(
      self::APPROVAL_CRITICAL_NC_ID,
      'open',
      'The status must stay untouched until a second member approves.',
    );
  }

  #[Test]
  public function testAWaiverAlreadyPendingIsReturnedRatherThanDuplicated(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::APPROVAL_ADMIN_USER_ID, 'inspection-approval-admin@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::APPROVAL_ORGANIZATION_ID, self::APPROVAL_INSPECTION_ID, self::APPROVAL_PENDING_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'waived']),
    );

    self::assertSame(
      expected: 202,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Asking again while a decision is pending stays 202. Response: ' . (string) $client->getResponse()->getContent(),
    );

    $payload = $this->decodeObject($client);

    // The reservation is idempotent: the request already awaiting a decision
    // is handed back rather than a second one being opened for the same
    // (organization, action type, subject).
    self::assertSame(self::PENDING_APPROVAL_REQUEST_ID, $payload['approvalRequestId']);
    self::assertSame('pending', $payload['approvalStatus']);

    self::assertSame(
      expected: 1,
      actual: $this->countPendingWaiverRequests(self::APPROVAL_PENDING_NC_ID),
      message: 'A repeated ask must never open a duplicate pending request.',
    );
  }

  #[Test]
  public function testWaivingAppliesImmediatelyBelowTheSeverityThreshold(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    $this->loginAs($client, self::APPROVAL_ADMIN_USER_ID, 'inspection-approval-admin@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::APPROVAL_ORGANIZATION_ID, self::APPROVAL_INSPECTION_ID, self::APPROVAL_LOW_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'waived']),
    );

    // The organization gates `nc_waiver` at `critical`; a `low` finding ranks
    // below the threshold, so the gate lets it through untouched.
    self::assertSame(
      expected: 200,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Below the policy severity threshold the waiver applies immediately. Response: ' . (string) $client->getResponse()->getContent(),
    );

    self::assertSame('waived', $this->decodeObject($client)['status']);
  }

  #[Test]
  public function testWaivingRejectsAWriterWhoMayNotRequestApprovals(): void
  {
    $client = static::createClient();
    $this->seedInspectionFixtures();

    // Holds organization.inspection.write — so the processor own gate lets it
    // through — but NOT organization.approvals.request, which the approval
    // gate demands before it may open a request on the caller behalf.
    $this->loginAs($client, self::APPROVAL_NO_REQUEST_USER_ID, 'inspection-approval-writer@example.com');
    $client->request(
      method: 'PATCH',
      uri: $this->nonConformityStatusUri(self::APPROVAL_ORGANIZATION_ID, self::APPROVAL_INSPECTION_ID, self::APPROVAL_GATED_NC_ID),
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['status' => 'waived']),
    );

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Missing organization.approvals.request must deny the deferred waiver, not fail it. Response: ' . (string) $client->getResponse()->getContent(),
    );

    $this->assertNonConformityStatusIs(
      self::APPROVAL_GATED_NC_ID,
      'open',
      'A denied waiver leaves the non-conformity untouched.',
    );
  }

  // #endregion

  // #endregion

  // #region Fixtures

  /**
   * Method loginAs.
   *
   * Authenticates the client against the stateless `api` firewall (the token
   * is stored in the container, not the session).
   */
  private function loginAs(KernelBrowser $client, string $userId, string $email): void
  {
    $user = new SecurityUser(
      id: $userId,
      email: $email,
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');
  }

  /**
   * Method nonConformitiesUri.
   */
  private function nonConformitiesUri(string $organizationId, string $inspectionId): string
  {
    return '/api/organizations/' . $organizationId . '/inspections/' . $inspectionId . '/non-conformities';
  }

  /**
   * Method nonConformityUri.
   */
  private function nonConformityUri(string $organizationId, string $inspectionId, string $nonConformityId): string
  {
    return $this->nonConformitiesUri($organizationId, $inspectionId) . '/' . $nonConformityId;
  }

  /**
   * Method nonConformityStatusUri.
   */
  private function nonConformityStatusUri(string $organizationId, string $inspectionId, string $nonConformityId): string
  {
    return $this->nonConformityUri($organizationId, $inspectionId, $nonConformityId) . '/status';
  }

  /**
   * Method decodeObject.
   *
   * @return array<string, mixed>
   */
  private function decodeObject(KernelBrowser $client): array
  {
    $decoded = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($decoded);

    /** @var array<string, mixed> $decoded */
    return $decoded;
  }

  /**
   * Method decodeCollection.
   *
   * @return list<mixed>
   */
  private function decodeCollection(KernelBrowser $client): array
  {
    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Expected a successful collection response. Response: ' . (string) $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member']);

    return array_values($decoded['member']);
  }

  /**
   * Method memberIds.
   *
   * @param list<mixed> $members
   *
   * @return list<string>
   */
  private function memberIds(array $members): array
  {
    $ids = [];
    foreach ($members as $item) {
      self::assertIsArray($item);
      self::assertIsString($item['id'] ?? null);
      $ids[] = $item['id'];
    }

    return $ids;
  }

  /**
   * Method assertNonConformityStatusIs.
   *
   * Reads the row straight from the database rather than through the API, so
   * a deferred or denied waiver is proven not to have touched the aggregate.
   */
  private function assertNonConformityStatusIs(string $nonConformityId, string $expectedStatus, string $message): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $entityManager->clear();

    $record = $entityManager->find(NonConformityRecord::class, $nonConformityId);
    self::assertInstanceOf(NonConformityRecord::class, $record);
    self::assertSame($expectedStatus, $record->status, $message);
  }

  /**
   * Method countPendingWaiverRequests.
   *
   * Counts the pending `nc_waiver` approval requests opened for one
   * non-conformity, read straight from the table.
   */
  private function countPendingWaiverRequests(string $nonConformityId): int
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $count = $entityManager->getConnection()->fetchOne(
      'SELECT COUNT(*) FROM approval_requests WHERE subject_id = :subjectId AND action_type = :actionType AND status = :status',
      ['subjectId' => $nonConformityId, 'actionType' => 'nc_waiver', 'status' => 'pending'],
    );
    self::assertIsNumeric($count);

    return (int) $count;
  }

  /**
   * Method seedInspectionFixtures.
   *
   * Seeds (idempotently) everything the non-conformity contract tests need:
   *
   * - {@see self::ORGANIZATION_ID} with four-eyes approval left at its
   *   default (OFF), an admin member (`['*']`), a read-only member
   *   (`organization.inspection.read` only) and a no-access member
   *   (`organization.read` only);
   * - {@see self::OUTSIDER_ORGANIZATION_ID} with its own member, inspection
   *   and non-conformity — the cross-organization 404 material;
   * - {@see self::APPROVAL_ORGANIZATION_ID} with `nc_waiver` approval
   *   ENABLED at the `critical` threshold, an admin member and a member who
   *   may write inspections but may NOT request approvals.
   */
  private function seedInspectionFixtures(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID, self::APPROVAL_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    // A pending approval request is keyed by (organization, action, subject)
    // and is NOT cascaded by the organization delete: clear it explicitly so
    // the deferral tests start from a clean slate on every run.
    $entityManager->getConnection()->executeStatement(
      'DELETE FROM approval_requests WHERE organization_id = :organizationId',
      ['organizationId' => self::APPROVAL_ORGANIZATION_ID],
    );

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->newOrganization(self::ORGANIZATION_ID, 'Inspection API Test Org', self::ADMIN_USER_ID, $now);
    $entityManager->persist($organization);

    $outsiderOrganization = $this->newOrganization(self::OUTSIDER_ORGANIZATION_ID, 'Inspection API Test Outsider Org', self::OUTSIDER_USER_ID, $now);
    $entityManager->persist($outsiderOrganization);

    $approvalOrganization = $this->newOrganization(self::APPROVAL_ORGANIZATION_ID, 'Inspection API Test Approval Org', self::APPROVAL_ADMIN_USER_ID, $now);
    // Opt in to the four-eyes gate for `nc_waiver` only, keeping the default
    // `critical` threshold explicit so the test does not silently depend on
    // `OrganizationApprovalDefaults::NC_WAIVER_MIN_SEVERITY`.
    $approvalOrganization->settings = [
      'approval' => [
        'action_rules' => [
          'nc_waiver' => ['enabled' => true, 'min_approver_role' => 'admin', 'min_severity' => 'critical'],
        ],
        'allow_self_approval' => false,
        'approval_ttl_days' => 14,
      ],
    ];
    $entityManager->persist($approvalOrganization);

    $entityManager->persist($this->newRole('860e8400-e29b-41d4-a716-446655490060', $organization, 'inspection-full-access', ['*'], $now));
    $entityManager->persist($this->newRole('860e8400-e29b-41d4-a716-446655490061', $organization, 'inspection-read-only', ['organization.inspection.read'], $now));
    $entityManager->persist($this->newRole('860e8400-e29b-41d4-a716-446655490062', $organization, 'inspection-no-access', ['organization.read'], $now));
    $entityManager->persist($this->newRole('860e8400-e29b-41d4-a716-446655490063', $outsiderOrganization, 'inspection-outsider-full-access', ['*'], $now));
    $entityManager->persist($this->newRole('860e8400-e29b-41d4-a716-446655490064', $approvalOrganization, 'inspection-approval-full-access', ['*'], $now));
    // Deliberately WITHOUT organization.approvals.request.
    $entityManager->persist($this->newRole(
      '860e8400-e29b-41d4-a716-446655490065',
      $approvalOrganization,
      'inspection-approval-writer',
      ['organization.inspection.read', 'organization.inspection.write'],
      $now,
    ));

    $entityManager->flush();

    $this->assignMember($entityManager, self::ADMIN_MEMBER_ID, $organization, self::ADMIN_USER_ID, '860e8400-e29b-41d4-a716-446655490060', $now);
    $this->assignMember($entityManager, '860e8400-e29b-41d4-a716-446655490071', $organization, self::READ_ONLY_USER_ID, '860e8400-e29b-41d4-a716-446655490061', $now);
    $this->assignMember($entityManager, '860e8400-e29b-41d4-a716-446655490072', $organization, self::NO_ACCESS_USER_ID, '860e8400-e29b-41d4-a716-446655490062', $now);
    $this->assignMember($entityManager, '860e8400-e29b-41d4-a716-446655490073', $outsiderOrganization, self::OUTSIDER_USER_ID, '860e8400-e29b-41d4-a716-446655490063', $now);
    $this->assignMember($entityManager, self::APPROVAL_ADMIN_MEMBER_ID, $approvalOrganization, self::APPROVAL_ADMIN_USER_ID, '860e8400-e29b-41d4-a716-446655490064', $now);
    $this->assignMember($entityManager, '860e8400-e29b-41d4-a716-446655490075', $approvalOrganization, self::APPROVAL_NO_REQUEST_USER_ID, '860e8400-e29b-41d4-a716-446655490065', $now);

    $entityManager->flush();

    $openInspection = $this->newInspection(self::OPEN_INSPECTION_ID, $organization, 'submitted', $now);
    $closedInspection = $this->newInspection(self::CLOSED_INSPECTION_ID, $organization, 'closed', $now);
    $otherInspection = $this->newInspection(self::OTHER_INSPECTION_ID, $organization, 'submitted', $now);
    $outsiderInspection = $this->newInspection(self::OUTSIDER_INSPECTION_ID, $outsiderOrganization, 'submitted', $now);
    $approvalInspection = $this->newInspection(self::APPROVAL_INSPECTION_ID, $approvalOrganization, 'submitted', $now);

    foreach ([$openInspection, $closedInspection, $otherInspection, $outsiderInspection, $approvalInspection] as $inspection) {
      $entityManager->persist($inspection);
    }

    $entityManager->flush();

    $entityManager->persist($this->newNonConformity(self::OPEN_NC_ID, $openInspection, 'high', 'open', $now));
    $entityManager->persist($this->newNonConformity(self::RESOLVED_NC_ID, $openInspection, 'medium', 'done', $now));
    $entityManager->persist($this->newNonConformity(self::OTHER_INSPECTION_NC_ID, $otherInspection, 'low', 'open', $now));
    $entityManager->persist($this->newNonConformity(self::CLOSED_INSPECTION_NC_ID, $closedInspection, 'high', 'open', $now));
    $entityManager->persist($this->newNonConformity(self::OUTSIDER_NC_ID, $outsiderInspection, 'high', 'open', $now));
    $entityManager->persist($this->newNonConformity(self::APPROVAL_CRITICAL_NC_ID, $approvalInspection, 'critical', 'open', $now));
    $entityManager->persist($this->newNonConformity(self::APPROVAL_GATED_NC_ID, $approvalInspection, 'critical', 'open', $now));
    $entityManager->persist($this->newNonConformity(self::APPROVAL_LOW_NC_ID, $approvalInspection, 'low', 'open', $now));
    $entityManager->persist($this->newNonConformity(self::APPROVAL_PENDING_NC_ID, $approvalInspection, 'critical', 'open', $now));

    $entityManager->flush();

    // A waiver already awaiting a decision for APPROVAL_PENDING_NC_ID: the
    // partial unique index on `status = 'pending'` is what makes a second ask
    // return this row instead of opening a duplicate.
    $entityManager->getConnection()->executeStatement(
      'INSERT INTO approval_requests '
      . '(id, organization_id, action_type, subject_id, status, requested_by_member_id, requested_by_user_id, payload, expires_at, created_at, updated_at) '
      . 'VALUES (:id, :organizationId, :actionType, :subjectId, :status, :requestedByMemberId, :requestedByUserId, :payload, :expiresAt, :createdAt, :updatedAt)',
      [
        'id' => self::PENDING_APPROVAL_REQUEST_ID,
        'organizationId' => self::APPROVAL_ORGANIZATION_ID,
        'actionType' => 'nc_waiver',
        'subjectId' => self::APPROVAL_PENDING_NC_ID,
        'status' => 'pending',
        'requestedByMemberId' => self::APPROVAL_ADMIN_MEMBER_ID,
        'requestedByUserId' => self::APPROVAL_ADMIN_USER_ID,
        'payload' => (string) json_encode([
          'organizationId' => self::APPROVAL_ORGANIZATION_ID,
          'inspectionId' => self::APPROVAL_INSPECTION_ID,
          'nonConformityId' => self::APPROVAL_PENDING_NC_ID,
          'severity' => 'critical',
          'status' => 'waived',
        ]),
        'expiresAt' => $now->modify('+14 days'),
        'createdAt' => $now,
        'updatedAt' => $now,
      ],
      [
        'expiresAt' => Types::DATETIME_IMMUTABLE,
        'createdAt' => Types::DATETIME_IMMUTABLE,
        'updatedAt' => Types::DATETIME_IMMUTABLE,
      ],
    );

    $entityManager->clear();
  }

  /**
   * Method newOrganization.
   */
  private function newOrganization(string $id, string $name, string $ownerUserId, DateTimeImmutable $now): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = 'inspection-api-test-' . $id;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;

    return $organization;
  }

  /**
   * Method newRole.
   *
   * @param list<string> $permissions
   */
  private function newRole(string $id, OrganizationRecord $organization, string $name, array $permissions, DateTimeImmutable $now): OrganizationRoleRecord
  {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->permissions = $permissions;
    $role->description = 'Functional-test-only role.';
    $role->isSystem = false;
    $role->createdAt = $now;

    return $role;
  }

  /**
   * Method assignMember.
   */
  private function assignMember(
    EntityManagerInterface $entityManager,
    string $memberId,
    OrganizationRecord $organization,
    string $userId,
    string $roleId,
    DateTimeImmutable $now,
  ): void {
    $member = new OrganizationMemberRecord();
    $member->id = $memberId;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $role = $entityManager->find(OrganizationRoleRecord::class, $roleId);
    self::assertInstanceOf(OrganizationRoleRecord::class, $role);

    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $entityManager->persist($assignment);
  }

  /**
   * Method newInspection.
   */
  private function newInspection(string $id, OrganizationRecord $organization, string $status, DateTimeImmutable $now): InspectionRecord
  {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = self::EQUIPMENT_ID;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Test Inspector';
    $inspection->result = 'fail';
    $inspection->status = $status;
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;

    return $inspection;
  }

  /**
   * Method newNonConformity.
   */
  private function newNonConformity(
    string $id,
    InspectionRecord $inspection,
    string $severity,
    string $status,
    DateTimeImmutable $now,
  ): NonConformityRecord {
    $nonConformity = new NonConformityRecord();
    $nonConformity->id = $id;
    $nonConformity->inspection = $inspection;
    $nonConformity->description = 'Seeded non-conformity ' . $id;
    $nonConformity->severity = $severity;
    $nonConformity->status = $status;
    $nonConformity->resolvedAt = 'done' === $status || 'waived' === $status ? $now : null;
    $nonConformity->createdAt = $now;
    $nonConformity->updatedAt = $now;

    return $nonConformity;
  }

  // #endregion
}
