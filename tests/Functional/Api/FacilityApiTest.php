<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityMetadataFieldRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{
  OrganizationMemberRecord,
  OrganizationMemberRoleRecord,
  OrganizationRecord,
  OrganizationRoleRecord
};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;
use function md5;
use function substr;

final class FacilityApiTest extends WebTestCase
{
  #[Test]
  public function testListFacilityStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/facilities/statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /facilities/statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /facilities/statuses, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListFacilitiesWithHasCoordinatesFilterRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/550e8400-e29b-41d4-a716-446655449000/facilities?hasCoordinates=true');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/facilities?hasCoordinates=true endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/facilities?hasCoordinates=true, got ' . $statusCode,
    );
  }

  /**
   * Covers the FacilityMetadataSchemaGuard integration on the create
   * facility path: with an organization-defined typed schema in place, a
   * metadata value that parses as the definition's type is accepted.
   */
  #[Test]
  public function testCreateFacilityAcceptsMetadataMatchingTheOrganizationSchema(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-449040000001';
    $ownerUserId = '550e8400-e29b-41d4-a716-449040000002';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedField($entityManager, $organization, '550e8400-e29b-41d4-a716-449040000003', 'surface-m2', $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'building',
        'name' => 'Warehouse With Schema',
        'metadata' => ['surface-m2' => 450],
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
  }

  /**
   * With the same organization schema, a value that does not parse as the
   * definition's type (a string where "number" is expected) is rejected —
   * mapped centrally to 422 by FacilityMetadataValidationExceptionSubscriber.
   */
  #[Test]
  public function testCreateFacilityRejectsMetadataFailingTheOrganizationSchema(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-449040000010';
    $ownerUserId = '550e8400-e29b-41d4-a716-449040000011';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedField($entityManager, $organization, '550e8400-e29b-41d4-a716-449040000012', 'surface-m2', $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'building',
        'name' => 'Warehouse With Bad Metadata',
        'metadata' => ['surface-m2' => 'not-a-number'],
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    $decoded = json_decode((string) $response->getContent(), true);
    self::assertIsArray($decoded);
    $detail = $decoded['detail'] ?? '';
    self::assertIsString($detail);
    self::assertStringContainsString('surface-m2', $detail);
  }

  /**
   * A metadata key with no matching definition is untouched free-form
   * usage: the back-compat rule the schema feature is built on.
   */
  #[Test]
  public function testCreateFacilityAllowsUnschemadMetadataKeys(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-449040000020';
    $ownerUserId = '550e8400-e29b-41d4-a716-449040000021';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedField($entityManager, $organization, '550e8400-e29b-41d4-a716-449040000022', 'surface-m2', $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'building',
        'name' => 'Warehouse With Free-Form Key',
        'metadata' => ['some-legacy-key' => 'whatever'],
      ]),
    );

    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  private function entityManager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $entityManager;
  }

  private function seedOwner(
    EntityManagerInterface $entityManager,
    string $organizationId,
    string $ownerUserId,
    DateTimeImmutable $now,
  ): OrganizationRecord {
    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $fullAccessRole = $this->seedRole($entityManager, $organization, $this->derivedId($organizationId, 'role'), ['*'], $now);
    $member = $this->seedMember($entityManager, $organization, $this->derivedId($organizationId, 'member'), $ownerUserId, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);

    return $organization;
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
    $organization->name = 'Facility API Test ' . $id;
    $organization->slug = 'facility-api-test-' . $id;
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
  ): OrganizationRoleRecord {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = 'full_access_role';
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

  private function seedField(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    string $key,
    DateTimeImmutable $now,
  ): FacilityMetadataFieldRecord {
    $record = new FacilityMetadataFieldRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->key = $key;
    $record->label = 'Label for ' . $key;
    $record->fieldType = 'number';
    $record->options = [];
    $record->facilityType = null;
    $record->required = false;
    $record->unit = null;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $entityManager->persist($record);

    return $record;
  }

  private function derivedId(string $baseId, string $tag): string
  {
    return substr($baseId, 0, 24) . substr(md5($baseId . $tag), 0, 12);
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
