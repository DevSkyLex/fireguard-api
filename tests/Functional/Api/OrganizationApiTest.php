<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

/**
 * Test OrganizationApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testListOrganizationStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/statuses, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListOrganizationLegalTypesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/legal-types');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/legal-types endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/legal-types, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListOrganizationInvitationStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/invitation-statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/invitation-statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/invitation-statuses, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetOrganizationDashboardRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/dashboard');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/dashboard endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/dashboard, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetOrganizationDashboardTrendEndpointsRequireAuthentication(): void
  {
    $client = static::createClient();

    $trendEndpoints = [
      '/api/organizations/' . self::DUMMY_UUID . '/dashboard/trends/inspections',
      '/api/organizations/' . self::DUMMY_UUID . '/dashboard/trends/equipment-created',
      '/api/organizations/' . self::DUMMY_UUID . '/dashboard/trends/facilities-created',
      '/api/organizations/' . self::DUMMY_UUID . '/dashboard/trends/non-conformities-opened',
      '/api/organizations/' . self::DUMMY_UUID . '/dashboard/trends/non-conformities-resolved',
    ];

    foreach ($trendEndpoints as $endpoint) {
      $client->request('GET', $endpoint);

      $statusCode = $client->getResponse()->getStatusCode();

      self::assertNotEquals(
        expected: 404,
        actual: $statusCode,
        message: 'Dashboard trend endpoint should exist (got 404): ' . $endpoint,
      );

      self::assertContains(
        needle: $statusCode,
        haystack: [401, 403],
        message: 'Expected 401 or 403 for unauthenticated GET ' . $endpoint . ', got ' . $statusCode,
      );
    }
  }

  #[Test]
  public function testGetOrganizationDashboardNonConformityTrendCombinedMetricsRequiresAuthentication(): void
  {
    $client = static::createClient();

    // L3.9: the two-series non-conformity chart request (`metrics=` combining opened+resolved)
    // must still be authentication-gated — the auth check runs BEFORE the `metrics` filter is
    // even parsed, so an unauthenticated caller never learns whether the combination is valid.
    $trendEndpoints = [
      '/api/organizations/' . self::DUMMY_UUID . '/dashboard/trends/non-conformities-opened?metrics=non_conformities_resolved',
      '/api/organizations/' . self::DUMMY_UUID . '/dashboard/trends/non-conformities-resolved?metrics=non_conformities_opened',
    ];

    foreach ($trendEndpoints as $endpoint) {
      $client->request('GET', $endpoint);

      $statusCode = $client->getResponse()->getStatusCode();

      self::assertNotEquals(
        expected: 404,
        actual: $statusCode,
        message: 'Dashboard trend endpoint should exist (got 404): ' . $endpoint,
      );

      self::assertContains(
        needle: $statusCode,
        haystack: [401, 403],
        message: 'Expected 401 or 403 for unauthenticated GET ' . $endpoint . ', got ' . $statusCode,
      );
    }
  }

  /**
   * L3.10: `overview.nonConformities.severityLow`/`severityMedium`/
   * `severityHigh`/`severityCritical` must reflect EVERY non-conformity for
   * the organization regardless of status — unlike the neighboring
   * `criticalOpen` field, which only counts severity=critical AND
   * status IN (open, in_progress). This test seeds one non-conformity per
   * severity level, deliberately putting the critical one in the `waived`
   * status, so the two fields provably diverge: `criticalOpen` must read 0
   * (the critical non-conformity is not open) while `severityCritical` must
   * still read 1 (the severity breakdown ignores status entirely). It also
   * authenticates as a real organization member via `loginUser()` (works for
   * the `api` firewall even though it is `stateless: true` — the token is
   * stored in the container, not the session) instead of mocking the query
   * bus, so the real Doctrine DQL behind
   * `NonConformityRepository::countBySeverityForOrganizationId()` executes
   * against the (SQLite, in this suite) database — see ARCHITECTURE.md's
   * "test on SQLite / production on PostgreSQL" note.
   */
  #[Test]
  public function testGetOrganizationDashboardExposesNonConformitySeverityBreakdown(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655449900';
    $userId = '550e8400-e29b-41d4-a716-446655449901';
    $memberId = '550e8400-e29b-41d4-a716-446655449902';
    $roleId = '550e8400-e29b-41d4-a716-446655449903';
    $inspectionId = '550e8400-e29b-41d4-a716-446655449904';

    $existingOrganization = $entityManager->find(OrganizationRecord::class, $organizationId);
    if ($existingOrganization instanceof OrganizationRecord) {
      $entityManager->remove($existingOrganization);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Dashboard Severity Breakdown Test';
    $organization->slug = 'dashboard-severity-breakdown-test-' . $organizationId;
    $organization->ownerUserId = $userId;
    $organization->createdByUserId = $userId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $role = new OrganizationRoleRecord();
    $role->id = $roleId;
    $role->organization = $organization;
    $role->name = 'full-access-tester';
    $role->permissions = ['*'];
    $role->description = 'Functional-test-only role granting every permission.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    $member = new OrganizationMemberRecord();
    $member->id = $memberId;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $roleAssignment = new OrganizationMemberRoleRecord();
    $roleAssignment->member = $member;
    $roleAssignment->role = $role;
    $roleAssignment->assignedAt = $now;
    $entityManager->persist($roleAssignment);

    $inspection = new InspectionRecord();
    $inspection->id = $inspectionId;
    $inspection->organization = $organization;
    $inspection->equipmentId = '550e8400-e29b-41d4-a716-446655449905';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Severity Breakdown Inspector';
    $inspection->result = 'pass';
    $inspection->status = 'closed';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $entityManager->persist($inspection);

    $nonConformitySeeds = [
      ['id' => '550e8400-e29b-41d4-a716-446655449910', 'severity' => 'low', 'status' => 'open'],
      ['id' => '550e8400-e29b-41d4-a716-446655449911', 'severity' => 'low', 'status' => 'open'],
      ['id' => '550e8400-e29b-41d4-a716-446655449912', 'severity' => 'medium', 'status' => 'in_progress'],
      ['id' => '550e8400-e29b-41d4-a716-446655449913', 'severity' => 'high', 'status' => 'done'],
      // Deliberately `waived`, not `open`: proves severityCritical ignores status.
      ['id' => '550e8400-e29b-41d4-a716-446655449914', 'severity' => 'critical', 'status' => 'waived'],
    ];
    foreach ($nonConformitySeeds as $seed) {
      $nonConformity = new NonConformityRecord();
      $nonConformity->id = $seed['id'];
      $nonConformity->inspection = $inspection;
      $nonConformity->description = 'Seeded for the L3.10 severity-breakdown functional test.';
      $nonConformity->severity = $seed['severity'];
      $nonConformity->status = $seed['status'];
      $nonConformity->createdAt = $now;
      $nonConformity->updatedAt = $now;
      $entityManager->persist($nonConformity);
    }

    $entityManager->flush();

    $user = new SecurityUser(
      id: $userId,
      email: 'dashboard-severity-breakdown-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/dashboard?compare=false', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Dashboard request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertArrayHasKey('overview', $decoded);
    self::assertIsArray($decoded['overview']);
    self::assertArrayHasKey('nonConformities', $decoded['overview']);

    // `overview.<widget>` is normalized to `{summary: list<{key, value}>, primary}`
    // by `GetOrganizationDashboardProvider::normalizeOverview()`.
    $nonConformities = $decoded['overview']['nonConformities'];
    self::assertIsArray($nonConformities);
    self::assertArrayHasKey('summary', $nonConformities);
    self::assertIsArray($nonConformities['summary']);

    $summary = [];
    foreach ($nonConformities['summary'] as $entry) {
      self::assertIsArray($entry);
      self::assertIsString($entry['key']);
      $summary[$entry['key']] = $entry['value'];
    }

    self::assertSame(5, $summary['total'] ?? null);
    self::assertSame(2, $summary['severityLow'] ?? null);
    self::assertSame(1, $summary['severityMedium'] ?? null);
    self::assertSame(1, $summary['severityHigh'] ?? null);
    self::assertSame(1, $summary['severityCritical'] ?? null);
    // The waived critical non-conformity must NOT count as "open": proves
    // `severityCritical` and `criticalOpen` are independently sourced.
    self::assertSame(0, $summary['criticalOpen'] ?? null);
  }

  #[Test]
  public function testLegacyOrganizationStatisticsEndpointsAreRemoved(): void
  {
    $client = static::createClient();

    $legacyEndpoints = [
      '/api/organizations/' . self::DUMMY_UUID . '/statistics',
      '/api/organizations/' . self::DUMMY_UUID . '/statistics/facilities',
      '/api/organizations/' . self::DUMMY_UUID . '/statistics/membership',
      '/api/organizations/' . self::DUMMY_UUID . '/statistics/equipment',
      '/api/organizations/' . self::DUMMY_UUID . '/statistics/inspections',
      '/api/organizations/' . self::DUMMY_UUID . '/statistics/non-conformities',
    ];

    foreach ($legacyEndpoints as $endpoint) {
      $client->request('GET', $endpoint);

      self::assertSame(
        expected: 404,
        actual: $client->getResponse()->getStatusCode(),
        message: 'Legacy organization statistics endpoint should be removed: ' . $endpoint,
      );
    }
  }

  // #region Methods

  // -------------------------------------------------------------------------
  // Organization endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testCreateOrganizationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'name' => 'Acme Corp',
        'legalType' => 'company',
        'country' => 'FR',
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListOrganizationsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetOrganizationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteOrganizationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /organizations/{id}, got ' . $statusCode,
    );

    self::assertNotSame(
      expected: 422,
      actual: $statusCode,
      message: 'Authentication must be checked before the slug confirmation guard.',
    );
  }

  #[Test]
  public function testDeleteOrganizationWithSlugConfirmationQueryParameterRequiresAuthentication(): void
  {
    // The danger-zone confirmation guard (?slug=) must not change routing or
    // bypass the resource-level authentication check.
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '?slug=acme-corp');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id}?slug=... endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /organizations/{id}?slug=..., got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Member endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testInviteMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/members',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'userId' => self::DUMMY_UUID,
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /members, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListMembersRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/members',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /members, got ' . $statusCode,
    );
  }

  #[Test]
  public function testRemoveMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id}/members/{memberId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /members/{memberId}, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Role endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testCreateRoleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/roles',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'name' => 'Inspector',
        'permissions' => [],
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /roles, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListRolesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/roles',
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /roles, got ' . $statusCode,
    );
  }

  #[Test]
  public function testUpdateRoleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/roles/' . self::DUMMY_UUID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: (string) json_encode([
        'name' => 'Senior Inspector',
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /organizations/{id}/roles/{roleId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /roles/{roleId}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteRoleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::DUMMY_UUID . '/roles/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id}/roles/{roleId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /roles/{roleId}, got ' . $statusCode,
    );
  }

  // -------------------------------------------------------------------------
  // Member role assignment endpoints
  // -------------------------------------------------------------------------

  #[Test]
  public function testAssignRoleToMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID . '/roles',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode([
        'roleId' => self::DUMMY_UUID,
      ]),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /members/{memberId}/roles, got ' . $statusCode,
    );
  }

  #[Test]
  public function testRemoveRoleFromMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID . '/roles/' . self::DUMMY_UUID,
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id}/members/{memberId}/roles/{roleId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /members/{memberId}/roles/{roleId}, got ' . $statusCode,
    );
  }

  // #endregion
}
