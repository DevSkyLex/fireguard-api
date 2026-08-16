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
use function sprintf;
use function substr;

/**
 * Test FacilityMetadataFieldApiTest.
 *
 * Covers the organization-defined typed facility metadata schema CRUD
 * surface: `/organizations/{organizationId}/facility-metadata-fields` and
 * `/organizations/{organizationId}/facility-metadata-fields/{id}`.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataFieldApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testCreateRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/facility-metadata-fields');

    $statusCode = $client->getResponse()->getStatusCode();
    self::assertNotEquals(404, $statusCode, 'The endpoint should exist (got 404).');
    self::assertContains($statusCode, [401, 403]);
  }

  #[Test]
  public function testCreateSucceedsForAuthorizedCaller(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000001';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000002';
    $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'key' => 'surface-m2',
        'label' => 'Surface (m²)',
        'fieldType' => 'number',
        'required' => true,
        'unit' => 'm²',
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertSame('surface-m2', $decoded['key'] ?? null);
    self::assertSame('number', $decoded['fieldType'] ?? null);
    self::assertTrue($decoded['required'] ?? null);
    self::assertSame('m²', $decoded['unit'] ?? null);
  }

  #[Test]
  public function testCreateRejectsInvalidInput(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000010';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000011';
    $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'key' => 'Not A Valid Key!',
        'label' => 'x',
        'fieldType' => 'money',
      ]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testCreateRejectsSelectFieldWithoutOptions(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000015';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000016';
    $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'key' => 'building-category',
        'label' => 'Building category',
        'fieldType' => 'select',
      ]),
    );

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testCreateReturns403ForCallerWithoutPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000020';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000021';
    $memberUserId = '550e8400-e29b-41d4-a716-448030000022';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $readOnlyRole = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-448030000023', ['organization.facilities.read'], $now);
    $member = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-448030000024', $memberUserId, $now);
    $this->assignRole($entityManager, $member, $readOnlyRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($memberUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['key' => 'foo', 'label' => 'Foo', 'fieldType' => 'text']),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testCreateReturns404ForCallerOutsideOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000030';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000031';
    $outsiderUserId = '550e8400-e29b-41d4-a716-448030000032';
    $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['key' => 'foo', 'label' => 'Foo', 'fieldType' => 'text']),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testCreateReturns409ForDuplicateKey(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000040';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000041';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedField($entityManager, $organization, '550e8400-e29b-41d4-a716-448030000042', 'surface-m2', $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['key' => 'surface-m2', 'label' => 'Surface again', 'fieldType' => 'number']),
    );

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testCreateReturns422AtTheDefinitionCap(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000050';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000051';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    for ($index = 0; $index < 50; ++$index) {
      $fieldId = sprintf('550e8400-e29b-41d4-a716-4480300010%02d', $index);
      $this->seedField($entityManager, $organization, $fieldId, sprintf('field-%02d', $index), $now);
    }
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['key' => 'one-too-many', 'label' => 'One too many', 'fieldType' => 'text']),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testListReturnsDefinitionsForTheOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000060';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000061';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedField($entityManager, $organization, '550e8400-e29b-41d4-a716-448030000062', 'surface-m2', $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/facility-metadata-fields', server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode());
    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertArrayHasKey('member', $decoded);
  }

  #[Test]
  public function testListReturns404ForCallerOutsideOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000070';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000071';
    $outsiderUserId = '550e8400-e29b-41d4-a716-448030000072';
    $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/facility-metadata-fields', server: ['HTTP_ACCEPT' => 'application/ld+json']);

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testUpdateSucceedsForAuthorizedCaller(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000080';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000081';
    $fieldId = '550e8400-e29b-41d4-a716-448030000082';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedField($entityManager, $organization, $fieldId, 'surface-m2', $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields/' . $fieldId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['label' => 'Surface renamed', 'required' => true]),
    );

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertSame('Surface renamed', $decoded['label'] ?? null);
    self::assertTrue($decoded['required'] ?? null);
  }

  #[Test]
  public function testUpdateReturns404ForFieldInAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-448030000090';
    $otherOrganizationId = '550e8400-e29b-41d4-a716-448030000091';
    $ownerUserId = '550e8400-e29b-41d4-a716-448030000092';
    $otherOwnerUserId = '550e8400-e29b-41d4-a716-448030000093';
    $fieldId = '550e8400-e29b-41d4-a716-448030000094';
    $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $otherOrganization = $this->seedOwner($entityManager, $otherOrganizationId, $otherOwnerUserId, $now);
    $this->seedField($entityManager, $otherOrganization, $fieldId, 'surface-m2', $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields/' . $fieldId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['label' => 'Hijacked']),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testDeleteSucceedsForAuthorizedCaller(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-4480300000a0';
    $ownerUserId = '550e8400-e29b-41d4-a716-4480300000a1';
    $fieldId = '550e8400-e29b-41d4-a716-4480300000a2';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedField($entityManager, $organization, $fieldId, 'surface-m2', $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(method: 'DELETE', uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields/' . $fieldId);

    self::assertSame(204, $client->getResponse()->getStatusCode());
    self::assertNull($entityManager->find(FacilityMetadataFieldRecord::class, $fieldId));
  }

  #[Test]
  public function testDeleteReturns404ForUnknownField(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-4480300000b0';
    $ownerUserId = '550e8400-e29b-41d4-a716-4480300000b1';
    $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(method: 'DELETE', uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields/' . self::DUMMY_UUID);

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testDeleteReturns403ForCallerWithoutPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-08-16T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-4480300000c0';
    $ownerUserId = '550e8400-e29b-41d4-a716-4480300000c1';
    $memberUserId = '550e8400-e29b-41d4-a716-4480300000c2';
    $fieldId = '550e8400-e29b-41d4-a716-4480300000c3';
    $organization = $this->seedOwner($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedField($entityManager, $organization, $fieldId, 'surface-m2', $now);
    $readOnlyRole = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-4480300000c4', ['organization.facilities.read'], $now);
    $member = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-4480300000c5', $memberUserId, $now);
    $this->assignRole($entityManager, $member, $readOnlyRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($memberUserId), 'api');

    $client->request(method: 'DELETE', uri: '/api/organizations/' . $organizationId . '/facility-metadata-fields/' . $fieldId);

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

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

  /**
   * Seeds an organization and an owner member holding the full-access role
   * (`*`), so the caller has `organization.facilities.write`.
   */
  private function seedOwner(
    EntityManagerInterface $entityManager,
    string $organizationId,
    string $ownerUserId,
    DateTimeImmutable $now,
  ): OrganizationRecord {
    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $fullAccessRole = $this->seedRole($entityManager, $organization, $this->derivedId($organizationId, 'role'), ['*'], $now, 'full_access_role');
    $member = $this->seedMember($entityManager, $organization, $this->derivedId($organizationId, 'member'), $ownerUserId, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);

    return $organization;
  }

  /**
   * Derives a deterministic, column-length-safe (36 chars) identifier from a
   * base id and a tag, so call sites do not need to invent a fresh UUID
   * literal for every role/member fixture.
   */
  private function derivedId(string $baseId, string $tag): string
  {
    return substr($baseId, 0, 24) . substr(md5($baseId . $tag), 0, 12);
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
    $organization->name = 'Facility Metadata Field API Test ' . $id;
    $organization->slug = 'facility-metadata-field-api-test-' . $id;
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
