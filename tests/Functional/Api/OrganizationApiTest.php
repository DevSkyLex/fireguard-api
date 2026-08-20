<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function array_keys;
use function json_decode;
use function json_encode;
use function substr;

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
   * against a real PostgreSQL database — the same engine as production.
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
  public function testGetOrganizationNavigationCountersRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/navigation-counters');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/navigation-counters endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/navigation-counters, got ' . $statusCode,
    );
  }

  /**
   * Seeds two open interventions (`draft`, `in_progress`), one closed
   * (`published`) and one open (`open`) plus one closed (`done`)
   * non-conformity, then asserts the endpoint counts only the open ones —
   * exercising the real Doctrine queries behind
   * `InterventionStatisticsPort::countOverview()` and
   * `NonConformityStatisticsPort::countNonConformitiesByStatus()` against
   * a real PostgreSQL database.
   */
  #[Test]
  public function testGetOrganizationNavigationCountersReturnsOpenCounts(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655449920';
    $userId = '550e8400-e29b-41d4-a716-446655449921';
    $memberId = '550e8400-e29b-41d4-a716-446655449922';
    $roleId = '550e8400-e29b-41d4-a716-446655449923';
    $inspectionId = '550e8400-e29b-41d4-a716-446655449924';

    $existingOrganization = $entityManager->find(OrganizationRecord::class, $organizationId);
    if ($existingOrganization instanceof OrganizationRecord) {
      $entityManager->remove($existingOrganization);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Navigation Counters Test';
    $organization->slug = 'navigation-counters-test-' . $organizationId;
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

    $interventionSeeds = [
      ['id' => '550e8400-e29b-41d4-a716-446655449930', 'status' => 'draft'],
      ['id' => '550e8400-e29b-41d4-a716-446655449931', 'status' => 'in_progress'],
      // Awaiting review: counts as open AND as submitted.
      ['id' => '550e8400-e29b-41d4-a716-446655449934', 'status' => 'submitted'],
      // Closed end state: must NOT count as open.
      ['id' => '550e8400-e29b-41d4-a716-446655449932', 'status' => 'published'],
      ['id' => '550e8400-e29b-41d4-a716-446655449933', 'status' => 'abandoned'],
    ];
    $number = 1;
    foreach ($interventionSeeds as $seed) {
      $intervention = new InterventionRecord();
      $intervention->id = $seed['id'];
      $intervention->organization = $organization;
      $intervention->name = 'Navigation counters intervention ' . $number;
      $intervention->number = $number++;
      $intervention->status = $seed['status'];
      $intervention->createdAt = $now;
      $intervention->updatedAt = $now;
      $entityManager->persist($intervention);
    }

    $inspection = new InspectionRecord();
    $inspection->id = $inspectionId;
    $inspection->organization = $organization;
    $inspection->equipmentId = '550e8400-e29b-41d4-a716-446655449925';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Navigation Counters Inspector';
    $inspection->result = 'fail';
    $inspection->status = 'closed';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $entityManager->persist($inspection);

    $nonConformitySeeds = [
      ['id' => '550e8400-e29b-41d4-a716-446655449940', 'status' => 'open'],
      ['id' => '550e8400-e29b-41d4-a716-446655449941', 'status' => 'in_progress'],
      // Closed statuses: must NOT count as open.
      ['id' => '550e8400-e29b-41d4-a716-446655449942', 'status' => 'done'],
      ['id' => '550e8400-e29b-41d4-a716-446655449943', 'status' => 'waived'],
    ];
    foreach ($nonConformitySeeds as $seed) {
      $nonConformity = new NonConformityRecord();
      $nonConformity->id = $seed['id'];
      $nonConformity->inspection = $inspection;
      $nonConformity->description = 'Seeded for the navigation-counters functional test.';
      $nonConformity->severity = 'medium';
      $nonConformity->status = $seed['status'];
      $nonConformity->createdAt = $now;
      $nonConformity->updatedAt = $now;
      $entityManager->persist($nonConformity);
    }

    $entityManager->flush();

    $user = new SecurityUser(
      id: $userId,
      email: 'navigation-counters-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/navigation-counters', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Navigation counters request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame(3, $decoded['openInterventions'] ?? null);
    self::assertSame(2, $decoded['openNonConformities'] ?? null);
    self::assertSame(1, $decoded['submittedInterventions'] ?? null);
  }

  #[Test]
  public function testGetOrganizationNavigationCountersRejectsNonMember(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655449950';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655449951';
    $outsiderUserId = '550e8400-e29b-41d4-a716-446655449952';

    $existingOrganization = $entityManager->find(OrganizationRecord::class, $organizationId);
    if ($existingOrganization instanceof OrganizationRecord) {
      $entityManager->remove($existingOrganization);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Navigation Counters Non-Member Test';
    $organization->slug = 'navigation-counters-non-member-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);
    $entityManager->flush();

    $user = new SecurityUser(
      id: $outsiderUserId,
      email: 'navigation-counters-non-member-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/navigation-counters', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(404, $client->getResponse()->getStatusCode());
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

  /**
   * `isOwner`/`roles` on `GET /organizations/{id}` are resolved through the
   * same `OrganizationCallerMembershipPort` projection as the organization
   * list and every mutation output: the owner sees `isOwner: true` and their
   * assigned role, a plain member with no role sees `isOwner: false` and an
   * empty `roles` list.
   */
  #[Test]
  public function testGetOrganizationReturnsIsOwnerTrueForTheOwnerAndFalseForAPlainMember(): void
  {
    $ownerClient = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470200';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470201';
    $memberUserId = '550e8400-e29b-41d4-a716-446655470202';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = $this->seedSettingsWriteOrganization($entityManager, $organizationId, $ownerUserId, $now);

    // A plain active member, granted a read-only role so it can reach the
    // resource but is not the owner and holds no admin-level role.
    $readRole = new OrganizationRoleRecord();
    $readRole->id = substr($organizationId, 0, 35) . 'c';
    $readRole->organization = $organization;
    $readRole->name = 'read_only_role';
    $readRole->permissions = ['organization.read'];
    $readRole->description = 'Functional-test-only read-only role.';
    $readRole->isSystem = false;
    $readRole->createdAt = $now;
    $entityManager->persist($readRole);

    $member = new OrganizationMemberRecord();
    $member->id = substr($organizationId, 0, 35) . 'd';
    $member->organization = $organization;
    $member->userId = $memberUserId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $memberRoleAssignment = new OrganizationMemberRoleRecord();
    $memberRoleAssignment->member = $member;
    $memberRoleAssignment->role = $readRole;
    $memberRoleAssignment->assignedAt = $now;
    $entityManager->persist($memberRoleAssignment);

    $entityManager->flush();

    // Owner.
    $ownerClient->loginUser($this->securityUser($ownerUserId), 'api');
    $ownerClient->request('GET', '/api/organizations/' . $organizationId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $ownerResponse = $ownerClient->getResponse();
    self::assertSame(200, $ownerResponse->getStatusCode(), 'GET should succeed for the owner. Response: ' . $ownerResponse->getContent());

    $ownerDecoded = json_decode($ownerResponse->getContent() ?: '{}', true);
    self::assertIsArray($ownerDecoded);
    self::assertTrue($ownerDecoded['isOwner'] ?? null);
    $ownerRoles = $ownerDecoded['roles'] ?? null;
    self::assertIsArray($ownerRoles);
    $ownerFirstRole = $ownerRoles[0] ?? null;
    self::assertIsArray($ownerFirstRole);
    self::assertSame('full_access_role', $ownerFirstRole['label'] ?? null);

    // Plain member: fresh client, own login, same organization.
    static::ensureKernelShutdown();
    $memberClient = static::createClient();
    $memberClient->loginUser($this->securityUser($memberUserId), 'api');
    $memberClient->request('GET', '/api/organizations/' . $organizationId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $memberResponse = $memberClient->getResponse();
    self::assertSame(200, $memberResponse->getStatusCode(), 'GET should succeed for an entitled plain member. Response: ' . $memberResponse->getContent());

    $memberDecoded = json_decode($memberResponse->getContent() ?: '{}', true);
    self::assertIsArray($memberDecoded);
    self::assertFalse($memberDecoded['isOwner'] ?? null);
    $memberRoles = $memberDecoded['roles'] ?? null;
    self::assertIsArray($memberRoles);
    $memberFirstRole = $memberRoles[0] ?? null;
    self::assertIsArray($memberFirstRole);
    self::assertSame('read_only_role', $memberFirstRole['label'] ?? null);
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

  /**
   * `PATCH /organizations/{id}` re-reads through the same `GetOrganization`
   * query as GET/suspend/restore/transfer, so its output carries the same
   * caller-membership projection — no dedicated "settings-update" branch to
   * drift out of sync with the others.
   */
  #[Test]
  public function testUpdateOrganizationSettingsSucceedsAndReturnsCallerMembership(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470300';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470301';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $this->seedSettingsWriteOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['name' => 'Renamed Settings-Write Test']),
    );

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Settings update should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame('Renamed Settings-Write Test', $decoded['name'] ?? null);
    self::assertTrue($decoded['isOwner'] ?? null);
    $roles = $decoded['roles'] ?? null;
    self::assertIsArray($roles);
    $firstRole = $roles[0] ?? null;
    self::assertIsArray($firstRole);
    self::assertSame('full_access_role', $firstRole['label'] ?? null);
  }

  // -------------------------------------------------------------------------
  // Suspend / Restore (POST /{id}/suspend, POST /{id}/restore) — P2.5
  // -------------------------------------------------------------------------

  #[Test]
  public function testSuspendOrganizationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/suspend');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /organizations/{id}/suspend endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{id}/suspend, got ' . $statusCode,
    );
  }

  #[Test]
  public function testSuspendOrganizationSucceedsAndIsIdempotentForAuthorizedCaller(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470100';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470101';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $this->seedSettingsWriteOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('POST', '/api/organizations/' . $organizationId . '/suspend', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $firstResponse = $client->getResponse();
    self::assertSame(200, $firstResponse->getStatusCode(), 'Suspend should succeed. Response: ' . $firstResponse->getContent());

    $decoded = json_decode($firstResponse->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame('suspended', $decoded['status'] ?? null);
    self::assertFalse($decoded['isActive'] ?? null);
    // The mutation output re-reads through the same caller-membership
    // projection as GET /organizations/{id}: the acting owner sees isOwner
    // true and the full_access_role seedSettingsWriteOrganization() grants.
    self::assertTrue($decoded['isOwner'] ?? null);
    $roles = $decoded['roles'] ?? null;
    self::assertIsArray($roles);
    $firstRole = $roles[0] ?? null;
    self::assertIsArray($firstRole);
    self::assertSame('full_access_role', $firstRole['label'] ?? null);

    // Idempotent: a repeat call against an already-suspended organization
    // still succeeds with 200, not a 409. Uses its own freshly authenticated
    // client: the token set by loginUser() does not reliably survive a
    // second request on a reused client.
    static::ensureKernelShutdown();
    $secondClient = static::createClient();
    $secondClient->loginUser($this->securityUser($ownerUserId), 'api');
    $secondClient->request('POST', '/api/organizations/' . $organizationId . '/suspend', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $secondResponse = $secondClient->getResponse();
    self::assertSame(200, $secondResponse->getStatusCode());
    $secondDecoded = json_decode($secondResponse->getContent() ?: '{}', true);
    self::assertIsArray($secondDecoded);
    self::assertSame('suspended', $secondDecoded['status'] ?? null);
  }

  #[Test]
  public function testSuspendOrganizationRejectsCallerWithoutSettingsWritePermission(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470110';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470111';
    $unentitledUserId = '550e8400-e29b-41d4-a716-446655470112';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Suspend 403 Test';
    $organization->slug = 'suspend-403-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    // Active member with NO role assigned — authenticated but not entitled.
    $unentitledMember = new OrganizationMemberRecord();
    $unentitledMember->id = '550e8400-e29b-41d4-a716-446655470113';
    $unentitledMember->organization = $organization;
    $unentitledMember->userId = $unentitledUserId;
    $unentitledMember->isActive = true;
    $unentitledMember->joinedAt = $now;
    $entityManager->persist($unentitledMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');

    $client->request('POST', '/api/organizations/' . $organizationId . '/suspend', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testSuspendOrganizationRejectsArchivedOrganization(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470120';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470121';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $this->seedSettingsWriteOrganization($entityManager, $organizationId, $ownerUserId, $now, 'archived');
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('POST', '/api/organizations/' . $organizationId . '/suspend', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  /**
   * `SuspendOrganizationProcessor` checks `organization.settings.write` via
   * `hasPermission()` BEFORE dispatching the command, the same coarse
   * permission-first pattern `DeleteOrganizationProcessor` already uses.
   * Since a caller with no membership row in a given organization id always
   * resolves zero permissions, this collapses to the identical 403 whether
   * the organization exists or not — `OrganizationNotFoundException`'s 404
   * mapping is effectively unreachable through this endpoint for any caller
   * lacking a role in that organization. This asserts the fail-closed
   * outcome (403, never a 500 or a leaked 404-vs-403 existence signal), not
   * a distinct not-found code path.
   */
  #[Test]
  public function testSuspendOrganizationRejectsNonexistentOrganization(): void
  {
    $client = static::createClient();

    $client->loginUser($this->securityUser('550e8400-e29b-41d4-a716-446655470131'), 'api');

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/suspend', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testRestoreOrganizationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/restore');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /organizations/{id}/restore endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{id}/restore, got ' . $statusCode,
    );
  }

  #[Test]
  public function testRestoreOrganizationSucceedsAndIsIdempotentForAuthorizedCaller(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470140';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470141';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $this->seedSettingsWriteOrganization($entityManager, $organizationId, $ownerUserId, $now, 'suspended');
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('POST', '/api/organizations/' . $organizationId . '/restore', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $firstResponse = $client->getResponse();
    self::assertSame(200, $firstResponse->getStatusCode(), 'Restore should succeed. Response: ' . $firstResponse->getContent());

    $decoded = json_decode($firstResponse->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame('active', $decoded['status'] ?? null);
    self::assertTrue($decoded['isActive'] ?? null);
    // Same shared caller-membership projection as suspend/GET: the acting
    // owner sees isOwner true and their full_access_role.
    self::assertTrue($decoded['isOwner'] ?? null);
    $roles = $decoded['roles'] ?? null;
    self::assertIsArray($roles);
    $firstRole = $roles[0] ?? null;
    self::assertIsArray($firstRole);
    self::assertSame('full_access_role', $firstRole['label'] ?? null);

    // Idempotent: a repeat call against an already-active organization still
    // succeeds with 200. Uses its own freshly authenticated client: the
    // token set by loginUser() does not reliably survive a second request on
    // a reused client.
    static::ensureKernelShutdown();
    $secondClient = static::createClient();
    $secondClient->loginUser($this->securityUser($ownerUserId), 'api');
    $secondClient->request('POST', '/api/organizations/' . $organizationId . '/restore', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $secondResponse = $secondClient->getResponse();
    self::assertSame(200, $secondResponse->getStatusCode());
    $secondDecoded = json_decode($secondResponse->getContent() ?: '{}', true);
    self::assertIsArray($secondDecoded);
    self::assertSame('active', $secondDecoded['status'] ?? null);
  }

  #[Test]
  public function testRestoreOrganizationRejectsCallerWithoutSettingsWritePermission(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470150';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470151';
    $unentitledUserId = '550e8400-e29b-41d4-a716-446655470152';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Restore 403 Test';
    $organization->slug = 'restore-403-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'suspended';
    $organization->isActive = false;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $unentitledMember = new OrganizationMemberRecord();
    $unentitledMember->id = '550e8400-e29b-41d4-a716-446655470153';
    $unentitledMember->organization = $organization;
    $unentitledMember->userId = $unentitledUserId;
    $unentitledMember->isActive = true;
    $unentitledMember->joinedAt = $now;
    $entityManager->persist($unentitledMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');

    $client->request('POST', '/api/organizations/' . $organizationId . '/restore', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  /**
   * See the docblock on testSuspendOrganizationRejectsNonexistentOrganization
   * — the same permission-first collapse applies here.
   */
  #[Test]
  public function testRestoreOrganizationRejectsNonexistentOrganization(): void
  {
    $client = static::createClient();

    $client->loginUser($this->securityUser('550e8400-e29b-41d4-a716-446655470161'), 'api');

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/restore', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  // -------------------------------------------------------------------------
  // Remove logo (DELETE /{organizationId}/logo) — P2.5
  // -------------------------------------------------------------------------

  #[Test]
  public function testRemoveOrganizationLogoRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '/logo');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{id}/logo endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /organizations/{id}/logo, got ' . $statusCode,
    );
  }

  #[Test]
  public function testRemoveOrganizationLogoSucceedsAndIsIdempotentWhenNoLogo(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470170';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470171';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $this->seedSettingsWriteOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('DELETE', '/api/organizations/' . $organizationId . '/logo');
    self::assertSame(204, $client->getResponse()->getStatusCode());

    // Idempotent: the organization already has no logo, a repeat call still
    // succeeds with 204. Uses its own freshly authenticated client: the
    // token set by loginUser() does not reliably survive a second request on
    // a reused client.
    static::ensureKernelShutdown();
    $secondClient = static::createClient();
    $secondClient->loginUser($this->securityUser($ownerUserId), 'api');
    $secondClient->request('DELETE', '/api/organizations/' . $organizationId . '/logo');
    self::assertSame(204, $secondClient->getResponse()->getStatusCode());
  }

  #[Test]
  public function testRemoveOrganizationLogoRejectsCallerWithoutSettingsWritePermission(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470180';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470181';
    $unentitledUserId = '550e8400-e29b-41d4-a716-446655470182';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Remove Logo 403 Test';
    $organization->slug = 'remove-logo-403-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $unentitledMember = new OrganizationMemberRecord();
    $unentitledMember->id = '550e8400-e29b-41d4-a716-446655470183';
    $unentitledMember->organization = $organization;
    $unentitledMember->userId = $unentitledUserId;
    $unentitledMember->isActive = true;
    $unentitledMember->joinedAt = $now;
    $entityManager->persist($unentitledMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');

    $client->request('DELETE', '/api/organizations/' . $organizationId . '/logo');

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testRemoveOrganizationLogoRejectsArchivedOrganization(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655470190';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655470191';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $this->seedSettingsWriteOrganization($entityManager, $organizationId, $ownerUserId, $now, 'archived');
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('DELETE', '/api/organizations/' . $organizationId . '/logo');

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  /**
   * See the docblock on testSuspendOrganizationRejectsNonexistentOrganization
   * — the same permission-first collapse applies here.
   */
  #[Test]
  public function testRemoveOrganizationLogoRejectsNonexistentOrganization(): void
  {
    $client = static::createClient();

    $client->loginUser($this->securityUser('550e8400-e29b-41d4-a716-446655470201'), 'api');

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '/logo');

    self::assertSame(403, $client->getResponse()->getStatusCode());
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

  // -------------------------------------------------------------------------
  // Transfer ownership (POST /{id}/transfer-ownership)
  // -------------------------------------------------------------------------

  #[Test]
  public function testTransferOwnershipRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . self::DUMMY_UUID . '/transfer-ownership',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode(['newOwnerUserId' => self::DUMMY_UUID, 'slug' => 'acme-corp']),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /organizations/{id}/transfer-ownership endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /transfer-ownership, got ' . $statusCode,
    );
  }

  #[Test]
  public function testTransferOwnershipSucceedsForTheCurrentOwner(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460100';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460101';
    $newOwnerUserId = '550e8400-e29b-41d4-a716-446655460102';
    $newOwnerMemberId = '550e8400-e29b-41d4-a716-446655460103';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Transfer Ownership Success Test';
    $organization->slug = 'transfer-ownership-success-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    // The acting owner must resolve as an active member of the organization
    // (see TransferOrganizationOwnershipHandler): real owners always are,
    // since CreateOrganizationHandler seeds an owner membership at creation.
    $ownerMember = new OrganizationMemberRecord();
    $ownerMember->id = '550e8400-e29b-41d4-a716-446655460109';
    $ownerMember->organization = $organization;
    $ownerMember->userId = $ownerUserId;
    $ownerMember->isActive = true;
    $ownerMember->joinedAt = $now;
    $entityManager->persist($ownerMember);

    $newOwnerMember = new OrganizationMemberRecord();
    $newOwnerMember->id = $newOwnerMemberId;
    $newOwnerMember->organization = $organization;
    $newOwnerMember->userId = $newOwnerUserId;
    $newOwnerMember->isActive = true;
    $newOwnerMember->joinedAt = $now;
    $entityManager->persist($newOwnerMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/transfer-ownership',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'newOwnerUserId' => $newOwnerUserId,
        'slug' => $organization->slug,
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Transfer should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame($newOwnerUserId, $decoded['ownerUserId'] ?? null);
    // The interesting isOwner case: the ACTING caller is the previous
    // owner, and `ownerUserId` above already reflects the new owner — so
    // isOwner must be false for the caller here, the post-transfer truth,
    // not the pre-transfer one.
    self::assertFalse($decoded['isOwner'] ?? null);
  }

  #[Test]
  public function testTransferOwnershipRejectsCallerWhoIsNotTheCurrentOwner(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460110';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460111';
    $notOwnerUserId = '550e8400-e29b-41d4-a716-446655460112';
    $newOwnerUserId = '550e8400-e29b-41d4-a716-446655460113';
    $notOwnerMemberId = '550e8400-e29b-41d4-a716-446655460114';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Transfer Ownership Wrong Actor Test';
    $organization->slug = 'transfer-ownership-wrong-actor-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    // The caller must be an ACTIVE MEMBER (just not the owner) for this test
    // to exercise 403: they already legitimately know the organization
    // exists, unlike a stranger with no membership at all (see
    // testTransferOwnershipRejectsNonMemberIdenticallyToNonexistentOrganization).
    $notOwnerMember = new OrganizationMemberRecord();
    $notOwnerMember->id = $notOwnerMemberId;
    $notOwnerMember->organization = $organization;
    $notOwnerMember->userId = $notOwnerUserId;
    $notOwnerMember->isActive = true;
    $notOwnerMember->joinedAt = $now;
    $entityManager->persist($notOwnerMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($notOwnerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/transfer-ownership',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'newOwnerUserId' => $newOwnerUserId,
        'slug' => $organization->slug,
      ]),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testTransferOwnershipRejectsMismatchedSlugConfirmation(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460120';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460121';
    $newOwnerUserId = '550e8400-e29b-41d4-a716-446655460122';
    $ownerMemberId = '550e8400-e29b-41d4-a716-446655460123';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Transfer Ownership Slug Mismatch Test';
    $organization->slug = 'transfer-ownership-slug-mismatch-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $ownerMember = new OrganizationMemberRecord();
    $ownerMember->id = $ownerMemberId;
    $ownerMember->organization = $organization;
    $ownerMember->userId = $ownerUserId;
    $ownerMember->isActive = true;
    $ownerMember->joinedAt = $now;
    $entityManager->persist($ownerMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/transfer-ownership',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'newOwnerUserId' => $newOwnerUserId,
        'slug' => 'definitely-the-wrong-slug',
      ]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testTransferOwnershipRejectsTargetThatIsNotAnActiveMember(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460130';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460131';
    $notAMemberUserId = '550e8400-e29b-41d4-a716-446655460132';
    $ownerMemberId = '550e8400-e29b-41d4-a716-446655460133';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Transfer Ownership Non-Member Target Test';
    $organization->slug = 'transfer-ownership-non-member-target-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $ownerMember = new OrganizationMemberRecord();
    $ownerMember->id = $ownerMemberId;
    $ownerMember->organization = $organization;
    $ownerMember->userId = $ownerUserId;
    $ownerMember->isActive = true;
    $ownerMember->joinedAt = $now;
    $entityManager->persist($ownerMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/transfer-ownership',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'newOwnerUserId' => $notAMemberUserId,
        'slug' => $organization->slug,
      ]),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testTransferOwnershipRejectsArchivedOrganization(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460140';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460141';
    $newOwnerUserId = '550e8400-e29b-41d4-a716-446655460142';
    $newOwnerMemberId = '550e8400-e29b-41d4-a716-446655460143';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Transfer Ownership Archived Organization Test';
    $organization->slug = 'transfer-ownership-archived-organization-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'archived';
    $organization->isActive = false;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $ownerMember = new OrganizationMemberRecord();
    $ownerMember->id = '550e8400-e29b-41d4-a716-446655460144';
    $ownerMember->organization = $organization;
    $ownerMember->userId = $ownerUserId;
    $ownerMember->isActive = true;
    $ownerMember->joinedAt = $now;
    $entityManager->persist($ownerMember);

    $newOwnerMember = new OrganizationMemberRecord();
    $newOwnerMember->id = $newOwnerMemberId;
    $newOwnerMember->organization = $organization;
    $newOwnerMember->userId = $newOwnerUserId;
    $newOwnerMember->isActive = true;
    $newOwnerMember->joinedAt = $now;
    $entityManager->persist($newOwnerMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/transfer-ownership',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'newOwnerUserId' => $newOwnerUserId,
        'slug' => $organization->slug,
      ]),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  /**
   * Companion pair proving the existence/slug oracle is closed:
   * testTransferOwnershipRejectsNonMemberOfExistingOrganizationLikeANonexistentOne
   * (existing organization, non-member caller) and
   * testTransferOwnershipRejectsNonexistentOrganizationWithTheSameShapeAsANonMember
   * (nonexistent organization id) each make exactly one authenticated request
   * — this stateless `api` firewall only honors loginUser() for the request
   * immediately following it, so the two cannot be chained in a single test —
   * and both assert the identical fixed 404 problem-details envelope via
   * assertNotFoundProblemShape(), proving a stranger cannot distinguish one
   * case from the other.
   */
  #[Test]
  public function testTransferOwnershipRejectsNonMemberOfExistingOrganizationLikeANonexistentOne(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460150';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460152';
    $outsiderUserId = '550e8400-e29b-41d4-a716-446655460153';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Transfer Ownership Existence Oracle Test';
    $organization->slug = 'transfer-ownership-existence-oracle-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');

    // Same wrong slug guess a stranger would try against an org they merely
    // suspect exists.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/transfer-ownership',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'newOwnerUserId' => self::DUMMY_UUID,
        'slug' => 'a-completely-wrong-slug-guess',
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(
      404,
      $response->getStatusCode(),
      'A non-member must be refused with 404, never with a slug-mismatch 422 that would confirm the organization exists.',
    );
    $this->assertNotFoundProblemShape($response->getContent() ?: '{}');
  }

  #[Test]
  public function testTransferOwnershipRejectsNonexistentOrganizationWithTheSameShapeAsANonMember(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $nonexistentOrganizationId = '550e8400-e29b-41d4-a716-446655460151';
    $outsiderUserId = '550e8400-e29b-41d4-a716-446655460154';

    $this->removeOrganizationIfExists($entityManager, $nonexistentOrganizationId);

    $client->loginUser($this->securityUser($outsiderUserId), 'api');

    // Same payload, same wrong slug guess, as
    // testTransferOwnershipRejectsNonMemberOfExistingOrganizationLikeANonexistentOne,
    // against an organization id that does not exist at all.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $nonexistentOrganizationId . '/transfer-ownership',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'newOwnerUserId' => self::DUMMY_UUID,
        'slug' => 'a-completely-wrong-slug-guess',
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(404, $response->getStatusCode());
    $this->assertNotFoundProblemShape($response->getContent() ?: '{}');
  }

  // -------------------------------------------------------------------------
  // Leave organization (DELETE /{organizationId}/members/me)
  // -------------------------------------------------------------------------

  #[Test]
  public function testLeaveOrganizationRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '/members/me');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{organizationId}/members/me endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /members/me, got ' . $statusCode,
    );
  }

  #[Test]
  public function testLeaveOrganizationSucceedsForANonOwnerNonAdminMember(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460200';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460201';
    $ownerMemberId = '550e8400-e29b-41d4-a716-446655460202';
    $adminRoleId = '550e8400-e29b-41d4-a716-446655460203';
    $leavingUserId = '550e8400-e29b-41d4-a716-446655460204';
    $leavingMemberId = '550e8400-e29b-41d4-a716-446655460205';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Leave Organization Success Test';
    $organization->slug = 'leave-organization-success-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    // The owner stays the sole administrator, so the leaving member (below)
    // never trips the last-admin guard.
    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = $adminRoleId;
    $adminRole->organization = $organization;
    $adminRole->name = 'admin';
    $adminRole->permissions = ['organization.*'];
    $adminRole->description = 'Functional-test admin role.';
    $adminRole->isSystem = true;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $ownerMember = new OrganizationMemberRecord();
    $ownerMember->id = $ownerMemberId;
    $ownerMember->organization = $organization;
    $ownerMember->userId = $ownerUserId;
    $ownerMember->isActive = true;
    $ownerMember->joinedAt = $now;
    $entityManager->persist($ownerMember);

    $ownerRoleAssignment = new OrganizationMemberRoleRecord();
    $ownerRoleAssignment->member = $ownerMember;
    $ownerRoleAssignment->role = $adminRole;
    $ownerRoleAssignment->assignedAt = $now;
    $entityManager->persist($ownerRoleAssignment);

    $leavingMember = new OrganizationMemberRecord();
    $leavingMember->id = $leavingMemberId;
    $leavingMember->organization = $organization;
    $leavingMember->userId = $leavingUserId;
    $leavingMember->isActive = true;
    $leavingMember->joinedAt = $now;
    $entityManager->persist($leavingMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($leavingUserId), 'api');

    $client->request('DELETE', '/api/organizations/' . $organizationId . '/members/me');

    self::assertSame(204, $client->getResponse()->getStatusCode());

    $entityManager->refresh($leavingMember);
    self::assertFalse($leavingMember->isActive, 'Membership should be deactivated after leaving.');
  }

  #[Test]
  public function testLeaveOrganizationRejectsTheCurrentOwner(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460210';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460211';
    $ownerMemberId = '550e8400-e29b-41d4-a716-446655460212';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Leave Organization Owner Cannot Leave Test';
    $organization->slug = 'leave-organization-owner-cannot-leave-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $ownerMember = new OrganizationMemberRecord();
    $ownerMember->id = $ownerMemberId;
    $ownerMember->organization = $organization;
    $ownerMember->userId = $ownerUserId;
    $ownerMember->isActive = true;
    $ownerMember->joinedAt = $now;
    $entityManager->persist($ownerMember);

    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('DELETE', '/api/organizations/' . $organizationId . '/members/me');

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testLeaveOrganizationRejectsACallerWhoIsNotAMember(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460220';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460221';
    $outsiderUserId = '550e8400-e29b-41d4-a716-446655460222';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Leave Organization Not A Member Test';
    $organization->slug = 'leave-organization-not-a-member-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');

    $client->request('DELETE', '/api/organizations/' . $organizationId . '/members/me');

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testLeaveOrganizationRejectsTheLastActiveAdministrator(): void
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-446655460230';
    // The owner is deliberately NOT the leaving caller (the owner-cannot-leave
    // guard runs first and would otherwise mask the last-admin guard this test
    // targets) and deliberately not an active member, so it contributes no
    // administrator of its own.
    $ownerUserId = '550e8400-e29b-41d4-a716-446655460231';
    $adminRoleId = '550e8400-e29b-41d4-a716-446655460232';
    $soleAdminUserId = '550e8400-e29b-41d4-a716-446655460233';
    $soleAdminMemberId = '550e8400-e29b-41d4-a716-446655460234';

    $this->removeOrganizationIfExists($entityManager, $organizationId);

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Leave Organization Last Admin Test';
    $organization->slug = 'leave-organization-last-admin-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = $adminRoleId;
    $adminRole->organization = $organization;
    $adminRole->name = 'admin';
    $adminRole->permissions = ['organization.*'];
    $adminRole->description = 'Functional-test admin role.';
    $adminRole->isSystem = true;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $soleAdminMember = new OrganizationMemberRecord();
    $soleAdminMember->id = $soleAdminMemberId;
    $soleAdminMember->organization = $organization;
    $soleAdminMember->userId = $soleAdminUserId;
    $soleAdminMember->isActive = true;
    $soleAdminMember->joinedAt = $now;
    $entityManager->persist($soleAdminMember);

    $soleAdminRoleAssignment = new OrganizationMemberRoleRecord();
    $soleAdminRoleAssignment->member = $soleAdminMember;
    $soleAdminRoleAssignment->role = $adminRole;
    $soleAdminRoleAssignment->assignedAt = $now;
    $entityManager->persist($soleAdminRoleAssignment);

    $entityManager->flush();

    $client->loginUser($this->securityUser($soleAdminUserId), 'api');

    $client->request('DELETE', '/api/organizations/' . $organizationId . '/members/me');

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  /**
   * Method assertNotFoundProblemShape.
   *
   * Asserts the fixed RFC 7807 / API Platform "Error" envelope shared by
   * every domain-not-found 404 in this API: the same key set, the same
   * `@var`, `type`, `title` and `@id`, regardless of which not-found domain
   * exception produced it. Only `detail`/`description` are free text and
   * deliberately excluded from this shape check — they legitimately differ
   * per exception, but only ever echo identifiers the caller already
   * supplied, never information the caller did not already have.
   *
   * @param string $rawBody the raw JSON response body
   */
  private function assertNotFoundProblemShape(string $rawBody): void
  {
    $body = json_decode($rawBody, true);
    self::assertIsArray($body);

    self::assertSame(
      ['@context', '@id', '@type', 'title', 'detail', 'status', 'type', 'description'],
      array_keys($body),
      'Every not-found 404 body must share this exact key set.',
    );
    self::assertSame('/api/contexts/Error', $body['@context'] ?? null);
    self::assertSame('/api/errors/404', $body['@id'] ?? null);
    self::assertSame('Error', $body['@type'] ?? null);
    self::assertSame('An error occurred', $body['title'] ?? null);
    self::assertSame(404, $body['status'] ?? null);
    self::assertSame('/errors/404', $body['type'] ?? null);
  }

  private function securityUser(string $userId): SecurityUser
  {
    return new SecurityUser(
      id: $userId,
      email: $userId . '@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
  }

  private function removeOrganizationIfExists(EntityManagerInterface $entityManager, string $organizationId): void
  {
    $existingOrganization = $entityManager->find(OrganizationRecord::class, $organizationId);
    if ($existingOrganization instanceof OrganizationRecord) {
      $entityManager->remove($existingOrganization);
      $entityManager->flush();
    }
  }

  /**
   * Seeds an organization owned by `$ownerUserId`, with a full-access
   * ('*') role assigned to the owner's active membership — used by the
   * suspend/restore/remove-logo tests, which all gate on
   * `organization.settings.write`. Does not flush; the caller flushes once
   * after any additional fixtures are persisted.
   */
  private function seedSettingsWriteOrganization(
    EntityManagerInterface $entityManager,
    string $organizationId,
    string $ownerUserId,
    DateTimeImmutable $now,
    string $status = 'active',
  ): OrganizationRecord {
    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Settings-Write Test ' . $organizationId;
    $organization->slug = 'settings-write-test-' . $organizationId;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = $status;
    $organization->isActive = 'active' === $status;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    // `id` columns are plain string(36) — not necessarily persisted through
    // OrganizationRoleId/OrganizationMemberId, so any 36-char unique string
    // works. Derived from the already-globally-unique $organizationId by
    // replacing only its last character, so uniqueness never depends on
    // manually tracked offsets across this file's many seeded organizations.
    $role = new OrganizationRoleRecord();
    $role->id = substr($organizationId, 0, 35) . 'a';
    $role->organization = $organization;
    $role->name = 'full_access_role';
    $role->permissions = ['*'];
    $role->description = 'Functional-test-only role granting every permission.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    $member = new OrganizationMemberRecord();
    $member->id = substr($organizationId, 0, 35) . 'b';
    $member->organization = $organization;
    $member->userId = $ownerUserId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $roleAssignment = new OrganizationMemberRoleRecord();
    $roleAssignment->member = $member;
    $roleAssignment->role = $role;
    $roleAssignment->assignedAt = $now;
    $entityManager->persist($roleAssignment);

    return $organization;
  }

  // #endregion
}
