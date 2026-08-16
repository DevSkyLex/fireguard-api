<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{
  OrganizationMemberRecord,
  OrganizationMemberRoleRecord,
  OrganizationRecord,
  OrganizationRoleRecord,
  PlanRecord
};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;

/**
 * Test FacilityDuplicateApiTest.
 *
 * Covers `POST /organizations/{organizationId}/facilities/{facilityId}/duplicate`
 * — happy multi-level duplication, and the permission/scope/state denial
 * paths.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityDuplicateApiTest extends WebTestCase
{
  #[Test]
  public function testDuplicateReturns201AndClonesTheTwoLevelSubtreeWithNullCodes(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448010000001';
    $ownerUserId = '550e8400-e29b-41d4-a716-448010000002';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedUnlimitedPlan($entityManager, $organization, '550e8400-e29b-41d4-a716-448010000005', $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-448010000003', $now);
    $owner = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-448010000004', $ownerUserId, $now);
    $this->assignRole($entityManager, $owner, $role, $now);

    $sourceId = '550e8400-e29b-41d4-a716-448010000010';
    $childId = '550e8400-e29b-41d4-a716-448010000011';
    $archivedChildId = '550e8400-e29b-41d4-a716-448010000012';
    $grandchildId = '550e8400-e29b-41d4-a716-448010000013';

    $source = $this->seedFacility($entityManager, $sourceId, $organization, 'site', 'Original Site', $now, code: 'SRC-01');
    $this->seedFacility($entityManager, $childId, $organization, 'building', 'Building A', $now, parent: $source, code: 'BLDG-A');
    $archivedChild = $this->seedFacility($entityManager, $archivedChildId, $organization, 'building', 'Archived Building', $now, parent: $source, status: 'archived');
    $this->seedFacility($entityManager, $grandchildId, $organization, 'floor', 'Floor Under Archived', $now, parent: $archivedChild);

    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $sourceId . '/duplicate',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: '{}',
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

    $body = $this->decodeObject($response->getContent() ?: '{}');
    self::assertSame('Original Site (copy)', $body['name'] ?? null);
    // API Platform omits null-valued properties from the JSON-LD body rather
    // than emitting an explicit `null`.
    self::assertArrayNotHasKey('code', $body);
    self::assertSame('active', $body['status'] ?? null);
    self::assertArrayHasKey('id', $body);
    $newRootId = $body['id'];
    self::assertIsString($newRootId);
    self::assertNotSame($sourceId, $newRootId);

    // Two clones expected: the new root, and Building A's clone reattached
    // directly to it (the archived building is skipped, and its live child,
    // the floor, is reattached to the new root too).
    /** @var list<FacilityRecord> $clones */
    $clones = $entityManager->getRepository(FacilityRecord::class)->findBy(['organization' => $organization]);
    $newRootClones = [];
    foreach ($clones as $clone) {
      if ($newRootId === $clone->id) {
        continue;
      }
      if (null !== $clone->parentFacility && $newRootId === $clone->parentFacility->id) {
        $newRootClones[] = $clone;
      }
    }

    self::assertCount(2, $newRootClones, 'Expected exactly two direct clones under the new root.');
    foreach ($clones as $clone) {
      if ($sourceId === $clone->id || $childId === $clone->id || $archivedChildId === $clone->id || $grandchildId === $clone->id) {
        continue;
      }
      self::assertNull($clone->code, 'Every cloned facility must have a null code.');
    }

    $cloneNames = [];
    foreach ($newRootClones as $clone) {
      $cloneNames[] = $clone->name;
    }
    self::assertContains('Building A', $cloneNames);
    self::assertContains('Floor Under Archived', $cloneNames);
  }

  #[Test]
  public function testDuplicateReturns403ForCallerWithoutFacilitiesWritePermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448010000021';
    $readOnlyUserId = '550e8400-e29b-41d4-a716-448010000022';

    $organization = $this->seedOrganization($entityManager, $organizationId, '550e8400-e29b-41d4-a716-448010000023', $now);
    $role = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-448010000024', ['organization.facilities.read'], $now);
    $member = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-448010000025', $readOnlyUserId, $now);
    $this->assignRole($entityManager, $member, $role, $now);

    $sourceId = '550e8400-e29b-41d4-a716-448010000026';
    $this->seedFacility($entityManager, $sourceId, $organization, 'site', 'Read Only Site', $now);

    $entityManager->flush();

    $client->loginUser($this->securityUser($readOnlyUserId), 'api');
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $sourceId . '/duplicate',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: '{}',
    );

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testDuplicateReturns404ForAFacilityBelongingToAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationAId = '550e8400-e29b-41d4-a716-448010000031';
    $organizationBId = '550e8400-e29b-41d4-a716-448010000032';
    $ownerBUserId = '550e8400-e29b-41d4-a716-448010000033';

    $organizationA = $this->seedOrganization($entityManager, $organizationAId, '550e8400-e29b-41d4-a716-448010000034', $now);
    $sourceId = '550e8400-e29b-41d4-a716-448010000035';
    $this->seedFacility($entityManager, $sourceId, $organizationA, 'site', 'Org A Site', $now);

    $organizationB = $this->seedOrganization($entityManager, $organizationBId, $ownerBUserId, $now);
    $roleB = $this->seedFullAccessRole($entityManager, $organizationB, '550e8400-e29b-41d4-a716-448010000036', $now);
    $memberB = $this->seedMember($entityManager, $organizationB, '550e8400-e29b-41d4-a716-448010000037', $ownerBUserId, $now);
    $this->assignRole($entityManager, $memberB, $roleB, $now);

    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerBUserId), 'api');
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationAId . '/facilities/' . $sourceId . '/duplicate',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: '{}',
    );

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testDuplicateReturns409ForAnArchivedSourceFacility(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448010000041';
    $ownerUserId = '550e8400-e29b-41d4-a716-448010000042';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-448010000043', $now);
    $owner = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-448010000044', $ownerUserId, $now);
    $this->assignRole($entityManager, $owner, $role, $now);

    $sourceId = '550e8400-e29b-41d4-a716-448010000045';
    $this->seedFacility($entityManager, $sourceId, $organization, 'site', 'Archived Site', $now, status: 'archived');

    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities/' . $sourceId . '/duplicate',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: '{}',
    );

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private function entityManager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $entityManager;
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
    $organization->name = 'Facility Duplicate Test ' . $id;
    $organization->slug = 'facility-duplicate-test-' . $id;
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
   * Seeds a plan with a high facilities cap and assigns it to the
   * organization, so the quota check does not interfere with a test that is
   * not exercising the quota path itself.
   */
  private function seedUnlimitedPlan(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    DateTimeImmutable $now,
  ): void {
    $plan = new PlanRecord();
    $plan->id = $id;
    $plan->key = 'functional-test-plan-' . $id;
    $plan->name = 'Functional Test Plan';
    $plan->limits = ['facilities' => 1000, 'members' => 1000, 'equipment' => 1000, 'inspections' => 1000];
    $plan->isActive = true;
    $plan->isDefault = false;
    $plan->sortOrder = 0;
    $plan->createdAt = $now;
    $plan->updatedAt = $now;
    $entityManager->persist($plan);

    $organization->planId = $id;
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
    string $name = 'test_role',
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

  private function seedFullAccessRole(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    DateTimeImmutable $now,
  ): OrganizationRoleRecord {
    return $this->seedRole($entityManager, $organization, $id, ['*'], $now, 'full_access_role');
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

  private function seedFacility(
    EntityManagerInterface $entityManager,
    string $id,
    OrganizationRecord $organization,
    string $type,
    string $name,
    DateTimeImmutable $now,
    ?FacilityRecord $parent = null,
    ?string $code = null,
    string $status = 'active',
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = $parent;
    $facility->type = $type;
    $facility->name = $name;
    $facility->code = $code;
    $facility->status = $status;
    $facility->metadata = [];
    $facility->createdAt = $now;
    $facility->updatedAt = $now;
    $entityManager->persist($facility);

    return $facility;
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
}
