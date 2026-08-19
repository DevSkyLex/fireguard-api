<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function http_build_query;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Test InterventionApiTest.
 *
 * The core `Intervention` CRUD contract — `POST`/`GET`/`PATCH`/`DELETE
 * /interventions` — which, unlike its side surfaces (attachments, overdue
 * filter, recurrences, statistics, team assignment), had no dedicated
 * functional test. Covers the success shapes, the `If-Match` revision
 * precondition (428/412/200), the multi-value `status[]`/`priority[]`
 * filters, the draft/abandoned-only delete window (409 otherwise), and the
 * two isolation denial paths: 403 for an authenticated member without the
 * required `organization.interventions.*` permission, and 404 — not 403 —
 * for an intervention belonging to another organization.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '650e8400-e29b-41d4-a716-449001000001';

  private const string ADMIN_USER_ID = '650e8400-e29b-41d4-a716-449001000002';

  private const string ADMIN_MEMBER_ID = '650e8400-e29b-41d4-a716-449001000011';

  private const string OTHER_ORGANIZATION_ID = '650e8400-e29b-41d4-a716-449001000101';

  private const string OTHER_OWNER_USER_ID = '650e8400-e29b-41d4-a716-449001000102';

  // #region Create

  #[Test]
  public function testCreateInterventionReturns201WithDraftStatusAllowedTransitionsAndAllowedActions(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request(
      method: 'POST',
      uri: '/api/interventions',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
        'type' => 'site_setup',
        'name' => 'Newly Created Intervention',
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

    $body = $this->decodeObject($response->getContent() ?: '{}');
    self::assertArrayHasKey('id', $body);
    self::assertIsString($body['id']);
    self::assertNotSame('', $body['id']);
    self::assertSame('draft', $body['status'] ?? null);

    self::assertArrayHasKey('allowedTransitions', $body);
    self::assertIsArray($body['allowedTransitions']);
    self::assertContains('planned', $body['allowedTransitions']);
    self::assertContains('abandoned', $body['allowedTransitions']);

    self::assertArrayHasKey('allowedActions', $body, 'A mutation response must carry the caller-specific allowedActions surface.');
    self::assertIsArray($body['allowedActions']);
    self::assertSame(true, $body['allowedActions']['canDelete'] ?? null, 'A draft intervention owned by a full-access caller must be deletable.');
    self::assertSame(true, $body['allowedActions']['canEditDetails'] ?? null);
  }

  // #endregion

  // #region Read

  #[Test]
  public function testGetInterventionItemReturns200(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $id = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000201', status: 'draft', number: 301);

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/interventions/' . $id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $body = $this->decodeObject($response->getContent() ?: '{}');
    self::assertSame($id, $body['id'] ?? null);
    self::assertSame('draft', $body['status'] ?? null);
  }

  #[Test]
  public function testGetInterventionCollectionReturns200WithHydraEnvelope(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000202', status: 'draft', number: 302);

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/interventions?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ]), server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $body = $this->decodeObject($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $body);
    self::assertIsArray($body['member']);
    self::assertArrayHasKey('totalItems', $body);
    self::assertGreaterThanOrEqual(1, $body['totalItems']);
  }

  // #endregion

  // #region Multi-value filters

  #[Test]
  public function testStatusMultiValueFilterReturnsTheUnionAndTheSingleValueFormStillWorks(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $draftId = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000210', status: 'draft', number: 310);
    $plannedId = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000211', status: 'planned', number: 311);
    $abandonedId = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000212', status: 'abandoned', number: 312);

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/interventions?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'status' => ['draft', 'planned'],
    ]), server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $ids = $this->memberIds($this->decode($response->getContent() ?: '{}'));

    self::assertContains($draftId, $ids, 'status[]=draft&status[]=planned must include the draft intervention.');
    self::assertContains($plannedId, $ids, 'status[]=draft&status[]=planned must include the planned intervention.');
    self::assertNotContains($abandonedId, $ids, 'status[]=draft&status[]=planned must exclude the abandoned intervention.');

    static::ensureKernelShutdown();
    $singleClient = static::createClient();
    $singleClient->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $singleClient->request('GET', '/api/interventions?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'status' => 'draft',
    ]), server: ['HTTP_ACCEPT' => 'application/ld+json']);

    self::assertSame(200, $singleClient->getResponse()->getStatusCode());
    $singleIds = $this->memberIds($this->decode($singleClient->getResponse()->getContent() ?: '{}'));
    self::assertContains($draftId, $singleIds, 'The single-value status=draft form must still work.');
    self::assertNotContains($plannedId, $singleIds, 'The single-value status=draft form must not include planned.');
  }

  #[Test]
  public function testPriorityMultiValueFilterReturns400ForAnUnknownPriority(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/interventions?' . http_build_query([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'priority' => ['high', 'impossible'],
    ]), server: ['HTTP_ACCEPT' => 'application/ld+json']);

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  // #endregion

  // #region PATCH / If-Match revision precondition

  #[Test]
  public function testPatchWithCorrectIfMatchReturns200AndBumpsTheRevision(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $id = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000220', status: 'draft', number: 320);

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request(
      method: 'PATCH',
      uri: '/api/interventions/' . $id,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-1"'],
      content: (string) json_encode(['name' => 'Renamed Intervention']),
    );

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $body = $this->decodeObject($response->getContent() ?: '{}');
    self::assertSame('Renamed Intervention', $body['name'] ?? null);
    self::assertSame(2, $body['revision'] ?? null, 'A successful PATCH must bump the revision.');
  }

  #[Test]
  public function testPatchWithAStaleIfMatchReturns412(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $id = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000221', status: 'draft', number: 321);

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    // First PATCH bumps the record to revision 2 …
    $client->request(
      method: 'PATCH',
      uri: '/api/interventions/' . $id,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"'],
      content: (string) json_encode(['name' => 'First Rename']),
    );
    self::assertSame(200, $client->getResponse()->getStatusCode());

    // … so a second PATCH still claiming revision-1 is now stale.
    static::ensureKernelShutdown();
    $staleClient = static::createClient();
    $staleClient->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $staleClient->request(
      method: 'PATCH',
      uri: '/api/interventions/' . $id,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"'],
      content: (string) json_encode(['name' => 'Stale Rename']),
    );
    self::assertSame(412, $staleClient->getResponse()->getStatusCode());
  }

  #[Test]
  public function testPatchWithoutIfMatchReturns428(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $id = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000222', status: 'draft', number: 322);

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request(
      method: 'PATCH',
      uri: '/api/interventions/' . $id,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: (string) json_encode(['name' => 'Renamed Without Precondition']),
    );

    self::assertSame(428, $client->getResponse()->getStatusCode());
  }

  // #endregion

  // #region DELETE

  #[Test]
  public function testDeleteADraftInterventionReturns204(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $id = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000230', status: 'draft', number: 330);

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('DELETE', '/api/interventions/' . $id, server: ['HTTP_IF_MATCH' => '"revision-1"']);

    self::assertSame(204, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testDeleteAPlannedInterventionReturns409(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    // Mutating a non-draft intervention needs the `execute` permission AND
    // caller-is-responsible/participant (InterventionMemberPolicy) — set the
    // admin member as responsible so the request reaches the deletable-window
    // check in the gateway, rather than being turned away earlier as 403.
    $id = $this->seedIntervention(
      id: '650e8400-e29b-41d4-a716-449001000231',
      status: 'planned',
      number: 331,
      responsibleId: self::ADMIN_MEMBER_ID,
    );

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('DELETE', '/api/interventions/' . $id, server: ['HTTP_IF_MATCH' => '"revision-1"']);

    self::assertSame(
      expected: 409,
      actual: $client->getResponse()->getStatusCode(),
      message: 'Only draft or abandoned interventions can be deleted; a planned one must be refused with 409.',
    );
  }

  // #endregion

  // #region Denial paths

  #[Test]
  public function testGetInterventionReturns403ForAMemberWithoutTheInterventionsReadPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $adminRole = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449001000010', ['*'], $now, 'full_access');
    $admin = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449001000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $admin, $adminRole, $now);

    $unentitledUserId = '650e8400-e29b-41d4-a716-449001000240';
    $unentitledRole = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449001000241', ['organization.facilities.read'], $now, 'facilities_only');
    $unentitledMember = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449001000242', $unentitledUserId, $now);
    $this->assignRole($entityManager, $unentitledMember, $unentitledRole, $now);
    $entityManager->flush();

    $id = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000243', status: 'draft', number: 340);

    $client->loginUser($this->securityUser($unentitledUserId), 'api');
    $client->request('GET', '/api/interventions/' . $id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'An organization member without organization.interventions.read must be refused with 403, not 404 (the record does exist).',
    );
  }

  #[Test]
  public function testGetAndPatchInterventionReturn404ForAnInterventionInAnotherOrganization(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();
    $id = $this->seedIntervention(id: '650e8400-e29b-41d4-a716-449001000250', status: 'draft', number: 350);

    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $otherOrganization = $this->seedOrganization($entityManager, self::OTHER_ORGANIZATION_ID, self::OTHER_OWNER_USER_ID, $now);
    $otherRole = $this->seedRole($entityManager, $otherOrganization, '650e8400-e29b-41d4-a716-449001000103', ['*'], $now, 'full_access_other');
    $otherMember = $this->seedMember($entityManager, $otherOrganization, '650e8400-e29b-41d4-a716-449001000104', self::OTHER_OWNER_USER_ID, $now);
    $this->assignRole($entityManager, $otherMember, $otherRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser(self::OTHER_OWNER_USER_ID), 'api');
    $client->request('GET', '/api/interventions/' . $id, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'GET of an intervention belonging to another organization must be 404, not 403 — 403 would confirm it exists.',
    );

    static::ensureKernelShutdown();
    $patchClient = static::createClient();
    $patchClient->loginUser($this->securityUser(self::OTHER_OWNER_USER_ID), 'api');
    $patchClient->request(
      method: 'PATCH',
      uri: '/api/interventions/' . $id,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"'],
      content: (string) json_encode(['name' => 'Should Not Apply']),
    );
    self::assertSame(
      expected: 404,
      actual: $patchClient->getResponse()->getStatusCode(),
      message: 'PATCH of an intervention belonging to another organization must be 404, not 403.',
    );
  }

  // #endregion

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private function entityManager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $entityManager;
  }

  private function seedOrganizationWithFullAccessAdmin(): void
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $role = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449001000010', ['*'], $now, 'full_access');
    $member = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449001000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $member, $role, $now);
    $entityManager->flush();
  }

  private function seedOrganization(
    EntityManagerInterface $entityManager,
    string $id,
    string $ownerUserId,
    DateTimeImmutable $now,
  ): OrganizationRecord {
    $existing = $entityManager->find(OrganizationRecord::class, $id);
    if ($existing instanceof OrganizationRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Intervention API Test ' . $id;
    $organization->slug = 'intervention-api-test-' . $id;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    return $organization;
  }

  /**
   * @param list<string> $permissions
   */
  private function seedRole(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    array $permissions,
    DateTimeImmutable $now,
    string $name,
  ): OrganizationRoleRecord {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->permissions = $permissions;
    $role->description = 'Functional-test-only role.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    return $role;
  }

  private function seedMember(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    string $userId,
    DateTimeImmutable $joinedAt,
  ): OrganizationMemberRecord {
    $member = new OrganizationMemberRecord();
    $member->id = $id;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $joinedAt;
    $entityManager->persist($member);

    return $member;
  }

  private function assignRole(
    EntityManagerInterface $entityManager,
    OrganizationMemberRecord $member,
    OrganizationRoleRecord $role,
    DateTimeImmutable $now,
  ): void {
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $entityManager->persist($assignment);
  }

  /**
   * Seeds one intervention directly through the entity manager (like
   * `InterventionOverdueFilterApiTest`), so a status the workflow would not
   * let a caller reach directly (`planned`, `abandoned`) can be exercised
   * without driving the whole transition sequence.
   */
  private function seedIntervention(string $id, string $status, int $number, ?string $responsibleId = null): string
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $existing = $entityManager->find(InterventionRecord::class, $id);
    if ($existing instanceof InterventionRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    /** @var OrganizationRecord $organization */
    $organization = $entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $intervention = new InterventionRecord();
    $intervention->id = $id;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = 'Intervention API Test — ' . $id;
    $intervention->number = $number;
    $intervention->status = $status;
    $intervention->priority = 'normal';
    $intervention->siteId = null;
    $intervention->responsibleId = $responsibleId;
    $intervention->dueAt = null;
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $entityManager->persist($intervention);
    $entityManager->flush();

    return $id;
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

  /**
   * @return array<mixed>
   */
  private function decodeObject(string $content): array
  {
    $decoded = json_decode($content, true);
    self::assertIsArray($decoded);

    return $decoded;
  }

  /**
   * @return array<string, mixed>
   */
  private function decode(string $json): array
  {
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
      return [];
    }
    $result = [];
    foreach ($decoded as $key => $value) {
      if (is_string($key)) {
        $result[$key] = $value;
      }
    }

    return $result;
  }

  /**
   * @param array<string, mixed> $collection
   *
   * @return list<string>
   */
  private function memberIds(array $collection): array
  {
    $members = $collection['member'] ?? [];
    if (!is_array($members)) {
      return [];
    }
    $ids = [];
    foreach ($members as $member) {
      if (is_array($member) && isset($member['id']) && is_string($member['id'])) {
        $ids[] = $member['id'];
      }
    }

    return $ids;
  }
}
