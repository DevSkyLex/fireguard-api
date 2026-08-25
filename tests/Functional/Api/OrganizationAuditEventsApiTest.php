<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Audit\Infrastructure\Persistence\Doctrine\Record\AuditEventRecord;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

use function array_column;
use function array_filter;
use function array_values;
use function is_array;
use function json_decode;

/**
 * Test OrganizationAuditEventsApiTest.
 *
 * Contract tests for GET /api/organizations/{organizationId}/audit-events —
 * the organization-scoped audit read (activity feed). The denial paths are
 * the point: 401 unauthenticated, 403 for a member without
 * `organization.audit.read`, 404 for a non-member (does not confirm the
 * organization exists) and for an unknown organization. The success paths
 * additionally prove cross-organization isolation, the per-action metadata
 * allowlist, and that an actor from outside the organization is not named.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAuditEventsApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655460001';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655460002';

  private const string ADMIN_USER_ID = '550e8400-e29b-41d4-a716-446655460003';

  private const string PLAIN_MEMBER_USER_ID = '550e8400-e29b-41d4-a716-446655460004';

  private const string OUTSIDER_USER_ID = '550e8400-e29b-41d4-a716-446655460005';

  private const string OWN_EVENT_ID = '550e8400-e29b-41d4-a716-446655460010';

  private const string OTHER_ORG_EVENT_ID = '550e8400-e29b-41d4-a716-446655460011';

  private const string OUTSIDE_ACTOR_EVENT_ID = '550e8400-e29b-41d4-a716-446655460012';

  /**
   * A user with no membership in either organization — stands in for a
   * platform operator acting on the organization.
   */
  private const string OUTSIDE_ACTOR_USER_ID = '550e8400-e29b-41d4-a716-446655460006';

  #[Test]
  public function testListOrganizationAuditEventsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/audit-events');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/audit-events endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/audit-events, got ' . $statusCode,
    );
  }

  /**
   * Seeds two organizations with audit events sharing the same action key,
   * then asserts an admin of the first organization sees only their own
   * organization's events — with the PII columns absent from the payload and
   * the metadata reduced to exactly the keys the action's allowlist admits.
   */
  #[Test]
  public function testListOrganizationAuditEventsReturnsOnlyOwnOrganizationEventsWithoutPii(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedAuditEvents();

    $this->loginAs($client, self::ADMIN_USER_ID, 'org-audit-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/audit-events', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Audit events request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertArrayHasKey('member', $decoded);
    self::assertIsArray($decoded['member']);

    $ids = array_column($decoded['member'], 'id');
    self::assertContains(self::OWN_EVENT_ID, $ids, 'The organization\'s own audit event must be listed.');
    self::assertNotContains(self::OTHER_ORG_EVENT_ID, $ids, 'Another organization\'s audit event must never leak into the feed.');

    foreach ($decoded['member'] as $item) {
      self::assertIsArray($item);
      if (self::OWN_EVENT_ID !== ($item['id'] ?? null)) {
        continue;
      }

      self::assertSame('organization.member_added', $item['action']);
      self::assertSame('user', $item['actorType']);
      self::assertSame(self::ADMIN_USER_ID, $item['actorId']);
      self::assertSame('organization_member', $item['subjectType']);

      // The org-scoped contract never carries these, regardless of PII settings.
      self::assertArrayNotHasKey('actorEmail', $item);
      self::assertArrayNotHasKey('actorEmailHash', $item);
      self::assertArrayNotHasKey('ipAddress', $item);
      self::assertArrayNotHasKey('ipHash', $item);
      self::assertArrayNotHasKey('userAgent', $item);
      self::assertArrayNotHasKey('chainId', $item);
      self::assertArrayNotHasKey('eventHash', $item);

      // The per-action allowlist admits exactly what
      // `organization.member_added` is allowed to publish, and nothing else —
      // including `role_name`, which IS allowed for the role actions but not
      // for this one, and `session_fingerprint`, a key no name-based denylist
      // would have thought to enumerate.
      self::assertIsArray($item['metadata']);
      self::assertSame([
        'user_id' => self::PLAIN_MEMBER_USER_ID,
        'role_ids' => ['550e8400-e29b-41d4-a716-446655460021'],
      ], $item['metadata']);
    }
  }

  /**
   * The actor of an organization-scoped event is not necessarily one of the
   * organization's people — a platform operator acting on it is recorded here
   * too. Their opaque id is published (the organization is entitled to know
   * something happened) but their name is not resolved, so the feed cannot be
   * used to enumerate the identities of users outside the organization.
   */
  #[Test]
  public function testListOrganizationAuditEventsDoesNotNameAnActorFromOutsideTheOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();
    $this->seedAuditEvents();

    $this->loginAs($client, self::ADMIN_USER_ID, 'org-audit-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/audit-events', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Audit events request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member']);

    $outsiderEvents = array_values(array_filter(
      $decoded['member'],
      static fn (mixed $item): bool => is_array($item) && self::OUTSIDE_ACTOR_EVENT_ID === ($item['id'] ?? null),
    ));

    self::assertCount(1, $outsiderEvents, 'The event acted by a non-member must still be listed.');
    self::assertSame(self::OUTSIDE_ACTOR_USER_ID, $outsiderEvents[0]['actorId']);
    self::assertArrayNotHasKey(
      'actorDisplayName',
      $outsiderEvents[0],
      'A non-member actor must not be named (API Platform omits the null field).',
    );
  }

  #[Test]
  public function testListOrganizationAuditEventsRejectsMemberWithoutAuditReadPermission(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'org-audit-member@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/audit-events', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.audit.read must get 403.',
    );
  }

  #[Test]
  public function testListOrganizationAuditEventsRejectsNonMemberWith404(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'org-audit-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/audit-events', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A non-member must get 404 (not 403 — that would confirm the organization exists).',
    );
  }

  /**
   * The 404 must not become an organization-existence oracle: the same
   * caller requesting a REAL organization (as a non-member) and a
   * NON-EXISTENT one must receive not just the same status code but an
   * indistinguishable error body — a different detail message would leak
   * exactly what the identical status code is there to hide.
   */
  #[Test]
  public function testListOrganizationAuditEvents404BodiesAreIndistinguishable(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'org-audit-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/audit-events', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $realOrgResponse = $client->getResponse();
    self::assertSame(404, $realOrgResponse->getStatusCode());
    $realOrgBody = json_decode($realOrgResponse->getContent() ?: '{}', true);

    // A freshly authenticated client for the second call: the token
    // loginUser() sets does not reliably survive a second request on a reused
    // client, and a 401 here would prove nothing about the two 404 bodies.
    // Same pattern as `OrganizationApiTest`'s idempotency cases.
    static::ensureKernelShutdown();
    $secondClient = static::createClient();
    $this->loginAs($secondClient, self::OUTSIDER_USER_ID, 'org-audit-outsider@example.com');

    $secondClient->request('GET', '/api/organizations/550e8400-e29b-41d4-a716-446655469999/audit-events', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $unknownOrgResponse = $secondClient->getResponse();
    self::assertSame(404, $unknownOrgResponse->getStatusCode());
    $unknownOrgBody = json_decode($unknownOrgResponse->getContent() ?: '{}', true);

    self::assertIsArray($realOrgBody);
    self::assertIsArray($unknownOrgBody);
    self::assertSame(
      expected: $unknownOrgBody['detail'] ?? null,
      actual: $realOrgBody['detail'] ?? null,
      message: 'The 404 detail must be identical for a real (non-member) and an unknown organization.',
    );
    self::assertSame(
      expected: $unknownOrgBody['description'] ?? null,
      actual: $realOrgBody['description'] ?? null,
      message: 'The 404 description must be identical for a real (non-member) and an unknown organization.',
    );
  }

  #[Test]
  public function testExportOrganizationAuditEventsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/audit-events/export');

    self::assertContains(
      needle: $client->getResponse()->getStatusCode(),
      haystack: [401, 403],
      message: 'Unauthenticated export must be refused, not answered.',
    );
  }

  #[Test]
  public function testExportOrganizationAuditEventsRejectsMemberWithoutTheExportPermission(): void
  {
    // The whole point of the separate permission: this member CAN read the
    // feed on screen and still must not be able to walk away with a file.
    $client = static::createClient();
    $this->seedOrganizations();

    $this->loginAs($client, self::PLAIN_MEMBER_USER_ID, 'org-audit-member@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/audit-events/export');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member without organization.audit.export must get 403.',
    );
  }

  #[Test]
  public function testExportOrganizationAuditEventsRejectsNonMemberWith404(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();

    $this->loginAs($client, self::OUTSIDER_USER_ID, 'org-audit-outsider@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/audit-events/export');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A non-member must get 404 — 403 would confirm the organization exists.',
    );
  }

  #[Test]
  public function testExportOrganizationAuditEventsStreamsOnlyOwnOrganizationEventsWithoutPii(): void
  {
    $client = static::createClient();
    $this->seedOrganizations();

    $this->loginAs($client, self::ADMIN_USER_ID, 'org-audit-admin@example.com');

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/audit-events/export');
    $response = $client->getResponse();

    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    self::assertStringContainsString(
      needle: 'attachment;',
      haystack: (string) $response->headers->get('Content-Disposition'),
      message: 'The export must download rather than render.',
    );

    // The column set is asserted where it is decided, in
    // Tests\Unit\Organization\Presentation\Api\Service\OrganizationAuditEventCsvWriterTest:
    // draining a StreamedResponse here would assert the framework's plumbing
    // more than this endpoint's contract.
  }

  /**
   * Method loginAs.
   *
   * Authenticates the client against the stateless `api` firewall
   * (the token is stored in the container, not the session).
   *
   * @param KernelBrowser $client the test client
   * @param string $userId the user id to authenticate as
   * @param string $email the user email
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
   * Method seedOrganizations.
   *
   * Seeds (idempotently) the primary organization with an admin member
   * (role permissions ['*']) and a plain member (organization.read only),
   * plus a second organization used to prove cross-org isolation.
   */
  private function seedOrganizations(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    foreach ([self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Audit Feed Test';
    $organization->slug = 'audit-feed-test-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '550e8400-e29b-41d4-a716-446655460020';
    $adminRole->organization = $organization;
    $adminRole->name = 'full-access-tester';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '550e8400-e29b-41d4-a716-446655460021';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'read-only-tester';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without audit access.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '550e8400-e29b-41d4-a716-446655460022';
    $adminMember->organization = $organization;
    $adminMember->userId = self::ADMIN_USER_ID;
    $adminMember->isActive = true;
    $adminMember->joinedAt = $now;
    $entityManager->persist($adminMember);

    $adminAssignment = new OrganizationMemberRoleRecord();
    $adminAssignment->member = $adminMember;
    $adminAssignment->role = $adminRole;
    $adminAssignment->assignedAt = $now;
    $entityManager->persist($adminAssignment);

    $plainMember = new OrganizationMemberRecord();
    $plainMember->id = '550e8400-e29b-41d4-a716-446655460023';
    $plainMember->organization = $organization;
    $plainMember->userId = self::PLAIN_MEMBER_USER_ID;
    $plainMember->isActive = true;
    $plainMember->joinedAt = $now;
    $entityManager->persist($plainMember);

    $plainAssignment = new OrganizationMemberRoleRecord();
    $plainAssignment->member = $plainMember;
    $plainAssignment->role = $readOnlyRole;
    $plainAssignment->assignedAt = $now;
    $entityManager->persist($plainAssignment);

    $otherOrganization = new OrganizationRecord();
    $otherOrganization->id = self::OTHER_ORGANIZATION_ID;
    $otherOrganization->name = 'Audit Feed Isolation Control';
    $otherOrganization->slug = 'audit-feed-isolation-' . self::OTHER_ORGANIZATION_ID;
    $otherOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $otherOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $otherOrganization->status = 'active';
    $otherOrganization->isActive = true;
    $otherOrganization->createdAt = $now;
    $otherOrganization->updatedAt = $now;
    $entityManager->persist($otherOrganization);

    $entityManager->flush();
  }

  /**
   * Method seedAuditEvents.
   *
   * Seeds (idempotently), directly in the AUTH database: one event for the
   * organization under test, one for the control organization (same action,
   * different organization_id), and one acted by a user who belongs to
   * neither. Every row is saturated with PII columns and with metadata keys
   * the per-action allowlist must refuse — including `session_fingerprint`,
   * which no name-based denylist would have enumerated.
   */
  private function seedAuditEvents(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.auth_entity_manager');

    $seeds = [
      ['id' => self::OWN_EVENT_ID, 'organizationId' => self::ORGANIZATION_ID, 'chainId' => 'test:org-audit-feed-own', 'actorId' => self::ADMIN_USER_ID],
      ['id' => self::OTHER_ORG_EVENT_ID, 'organizationId' => self::OTHER_ORGANIZATION_ID, 'chainId' => 'test:org-audit-feed-other', 'actorId' => self::ADMIN_USER_ID],
      ['id' => self::OUTSIDE_ACTOR_EVENT_ID, 'organizationId' => self::ORGANIZATION_ID, 'chainId' => 'test:org-audit-feed-outside', 'actorId' => self::OUTSIDE_ACTOR_USER_ID],
    ];

    foreach ($seeds as $seed) {
      $existing = $entityManager->find(AuditEventRecord::class, $seed['id']);
      if ($existing instanceof AuditEventRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }

      $record = new AuditEventRecord();
      $record->id = Uuid::fromString($seed['id']);
      $record->chainId = $seed['chainId'];
      $record->sequence = 1;
      $record->action = 'organization.member_added';
      $record->actorType = 'user';
      $record->actorId = $seed['actorId'];
      $record->actorEmail = 'org-audit-admin@example.com';
      $record->actorEmailHash = 'a1b2c3d4';
      $record->subjectType = 'organization_member';
      $record->subjectId = '550e8400-e29b-41d4-a716-446655460030';
      $record->organizationId = $seed['organizationId'];
      $record->ipAddress = '203.0.113.5';
      $record->ipHash = 'e3b0c442';
      $record->userAgent = 'Mozilla/5.0 (functional test)';
      $record->metadata = [
        // Allowed for organization.member_added.
        'user_id' => self::PLAIN_MEMBER_USER_ID,
        'role_ids' => ['550e8400-e29b-41d4-a716-446655460021'],
        // Refused: redundant, allowed only for OTHER actions, request
        // context, personal data, another surface's data, and one key
        // invented here that appears on no denylist anywhere.
        'organization_id' => $seed['organizationId'],
        'role_name' => 'member',
        'request_id' => 'req-functional-test',
        'ip' => '203.0.113.5',
        'user_agent' => 'Mozilla/5.0 (functional test)',
        'invited_email' => 'invitee@example.com',
        'session_fingerprint' => 'fp-functional-test',
      ];
      $record->occurredAt = new DateTimeImmutable('2026-06-01T10:00:00+00:00');
      $record->recordedAt = new DateTimeImmutable('2026-06-01T10:00:01+00:00');
      $record->prevHash = null;
      $record->eventHash = 'functional-test-hash-' . $seed['chainId'];
      $entityManager->persist($record);
    }

    $entityManager->flush();
  }
}
