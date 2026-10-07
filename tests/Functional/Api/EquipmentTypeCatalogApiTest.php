<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Equipment\Domain\Exception\EquipmentTypeCatalogException;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

/**
 * Test EquipmentTypeCatalogApiTest.
 *
 * Freezes catalog writes, revision races, historical preservation and scope denials.
 *
 * @category Functional Tests
 */
final class EquipmentTypeCatalogApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655491001';

  private const string USER_ID = '770e8400-e29b-41d4-a716-446655491002';

  private const string MEMBER_ID = '770e8400-e29b-41d4-a716-446655491003';

  private const string ROLE_ID = '770e8400-e29b-41d4-a716-446655491004';

  #[Test]
  public function testCatalogRequiresAuthentication(): void
  {
    $client = static::createClient();
    $client->request('GET', $this->path());
    self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testCustomTypeCreationArchiveAndStaleRevision(): void
  {
    $client = static::createClient();
    $this->seed($client, ['organization.equipment.read', 'organization.equipment.write']);

    $client->request('GET', $this->path(), server: ['HTTP_ACCEPT' => 'application/ld+json']);
    $initial = $this->response($client, 200);
    self::assertIsArray($initial['member']);
    self::assertCount(12, $initial['member']);
    $default = null;
    foreach ($initial['member'] as $type) {
      self::assertIsArray($type);
      if ('fire_extinguisher' === $type['value']) {
        $default = $type;
      }
    }
    self::assertIsArray($default);
    self::assertSame('fire', $default['family']);
    self::assertFalse($default['archived']);
    self::assertSame(1, $default['revision']);

    $this->write($client, 'POST', '', ['value' => 'water_mist', 'label' => 'Water mist', 'family' => 'fire']);
    $created = $this->response($client, 201);
    self::assertSame('water_mist', $created['value']);
    self::assertSame('Water mist', $created['label']);
    self::assertSame(1, $created['revision']);

    $this->write($client, 'POST', '', ['value' => 'water_mist', 'label' => 'Duplicate', 'family' => 'fire']);
    self::assertSame('equipment_type_exists', $this->response($client, 409)['code']);

    $this->write($client, 'PATCH', '/water_mist', ['revision' => 1, 'archived' => true]);
    $archived = $this->response($client, 200);
    self::assertTrue($archived['archived']);
    self::assertSame(2, $archived['revision']);

    $this->write($client, 'PATCH', '/water_mist', ['revision' => 1, 'label' => 'Stale label']);
    self::assertSame('equipment_type_revision_conflict', $this->response($client, 412)['code']);

    $client->loginUser(new SecurityUser(self::USER_ID, 'equipment-type-catalog@example.com', 'unused', ['ROLE_USER']), 'api');
    $client->request('GET', $this->path('/water_mist'), server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertTrue($this->response($client, 200)['archived']);
  }

  #[Test]
  public function testDefaultOverridePersistsAndPreservesHistoricalEquipment(): void
  {
    $client = static::createClient();
    $this->seed($client, ['*']);
    $this->write($client, 'PATCH', '/fire_extinguisher', ['revision' => 1, 'label' => 'Extincteur', 'archived' => true]);
    $updated = $this->response($client, 200);
    self::assertSame('Extincteur', $updated['label']);
    self::assertSame(2, $updated['revision']);

    /** @var EquipmentTypeCatalogPort $catalog */
    $catalog = static::getContainer()->get(EquipmentTypeCatalogPort::class);
    $catalog->validateAvailableType(self::ORGANIZATION_ID, 'fire_extinguisher', 'fire_extinguisher');

    $this->expectException(EquipmentTypeCatalogException::class);
    $catalog->validateAvailableType(self::ORGANIZATION_ID, 'fire_extinguisher');
  }

  #[Test]
  public function testUncataloguedHistoricalCodesAreVisibleButUnavailableForNewAssignments(): void
  {
    $client = static::createClient();
    $this->seed($client, ['*']);
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $organization = $entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);
    $record = new EquipmentRecord();
    $record->id = '770e8400-e29b-41d4-a716-446655491010';
    $record->organization = $organization;
    $record->type = 'legacy_custom';
    $record->status = 'in_stock';
    $record->recordStatus = 'published';
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = $record->createdAt;
    $entityManager->persist($record);
    $entityManager->flush();

    $client->request('GET', $this->path('/legacy_custom'), server: ['HTTP_ACCEPT' => 'application/ld+json']);
    $historical = $this->response($client, 200);
    self::assertSame('legacy_custom', $historical['value']);
    self::assertTrue($historical['archived']);
    self::assertSame('other', $historical['family']);
    /** @var EquipmentTypeCatalogPort $catalog */
    $catalog = static::getContainer()->get(EquipmentTypeCatalogPort::class);
    $catalog->validateAvailableType(self::ORGANIZATION_ID, 'legacy_custom', 'legacy_custom');
    $this->expectException(EquipmentTypeCatalogException::class);
    $catalog->validateAvailableType(self::ORGANIZATION_ID, 'legacy_custom');
  }

  #[Test]
  public function testReadOnlyMemberCannotWrite(): void
  {
    $client = static::createClient();
    $this->seed($client, ['organization.equipment.read']);
    $this->write($client, 'POST', '', ['value' => 'water_mist', 'label' => 'Water mist', 'family' => 'fire']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testMemberWithoutEquipmentReadIsDenied(): void
  {
    $client = static::createClient();
    $this->seed($client, ['organization.facility.read']);
    $client->request('GET', $this->path());
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testAnotherOrganizationIsHidden(): void
  {
    $client = static::createClient();
    $this->seed($client, ['*']);
    $client->request('GET', '/api/organizations/770e8400-e29b-41d4-a716-446655491099/equipment-types');
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testInvalidCatalogInputAndMissingRevisionAreRejected(): void
  {
    $client = static::createClient();
    $this->seed($client, ['*']);
    $this->write($client, 'POST', '', ['value' => 'INVALID', 'label' => 'Invalid', 'family' => 'fire']);
    self::assertSame(422, $client->getResponse()->getStatusCode());
    $this->write($client, 'PATCH', '/fire_extinguisher', ['archived' => true]);
    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  /**
   * Method path.
   */
  private function path(string $suffix = ''): string
  {
    return '/api/organizations/' . self::ORGANIZATION_ID . '/equipment-types' . $suffix;
  }

  /**
   * Method write.
   *
   * @param array<string, mixed> $body serialized write payload
   */
  private function write(KernelBrowser $client, string $method, string $suffix, array $body): void
  {
    $client->loginUser(new SecurityUser(self::USER_ID, 'equipment-type-catalog@example.com', 'unused', ['ROLE_USER']), 'api');
    $client->request($method, $this->path($suffix), server: [
      'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
    ], content: (string) json_encode($body));
  }

  /**
   * Method response.
   *
   * @return array<string, mixed> decoded response
   */
  private function response(KernelBrowser $client, int $status): array
  {
    self::assertSame($status, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $body = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($body);
    $object = [];
    foreach ($body as $key => $value) {
      self::assertIsString($key);
      $object[$key] = $value;
    }

    return $object;
  }

  /**
   * Method seed.
   *
   * @param list<string> $permissions permissions of the testing member
   */
  private function seed(KernelBrowser $client, array $permissions): void
  {
    $client->disableReboot();
    $users = $this->createStub(\User\Application\Port\Outbound\UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (\User\Domain\ValueObject\UserId $id) => \Tests\Support\Factory\UserTestFactory::createActive((string) $id, (string) $id . '@corp.example'));
    static::getContainer()->set(\User\Application\Port\Outbound\UserRepositoryPort::class, $users);
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . \Tests\Support\Auth\InteractiveTokenFactory::issue(static::getContainer(), self::USER_ID, 'equipment-type-catalog@example.com'));
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Equipment type catalog tests';
    $organization->slug = 'equipment-type-catalog-tests';
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-446655491098';
    $organization->createdByUserId = self::USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE_ID;
    $role->organization = $organization;
    $role->name = 'catalog-test-role';
    $role->permissions = $permissions;
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = self::MEMBER_ID;
    $member->organization = $organization;
    $member->userId = self::USER_ID;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $entityManager->persist($assignment);
    $entityManager->flush();
    $client->loginUser(new SecurityUser(self::USER_ID, 'equipment-type-catalog@example.com', 'unused', ['ROLE_USER']), 'api');
  }
}
