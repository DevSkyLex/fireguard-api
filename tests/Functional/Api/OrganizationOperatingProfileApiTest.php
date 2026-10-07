<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Persisted organization operational profiles and permission boundaries.
 *
 * @category FunctionalTest
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOperatingProfileApiTest extends WebTestCase
{
  private const string ORGANIZATION = '550e8400-e29b-41d4-a716-446655448721';

  private const string USER = '550e8400-e29b-41d4-a716-446655448722';

  private const string OWNER = '550e8400-e29b-41d4-a716-446655448723';

  private const string ROLE = '550e8400-e29b-41d4-a716-446655448724';

  private const string MEMBER = '550e8400-e29b-41d4-a716-446655448725';

  #[Test]
  #[DataProvider('creationProfiles')]
  public function creationPersistsAndReturnsProfile(?string $profile, string $expected): void
  {
    $client = $this->client();
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturn(UserTestFactory::createActive(self::USER));
    static::getContainer()->set(UserRepositoryPort::class, $users);
    $payload = ['name' => 'Operating profile creation'];
    if (null !== $profile) {
      $payload['operatingProfile'] = $profile;
    }

    $client->request('POST', '/api/organizations', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: json_encode($payload, JSON_THROW_ON_ERROR));

    self::assertResponseStatusCodeSame(201);
    $body = $this->body($client);
    self::assertSame($expected, $body['operatingProfile']);
    self::assertSame($expected, $this->manager()->getConnection()->fetchOne('SELECT operating_profile FROM organizations WHERE id = ?', [$body['id']]));
  }

  #[Test]
  public function creationRejectsUnsupportedProfileBeforeWriting(): void
  {
    $client = $this->client();

    $client->request('POST', '/api/organizations', server: ['CONTENT_TYPE' => 'application/ld+json'], content: json_encode(['name' => 'Invalid operating profile', 'operatingProfile' => 'administrator'], JSON_THROW_ON_ERROR));

    self::assertResponseStatusCodeSame(422);
    $count = $this->manager()->getConnection()->fetchOne('SELECT COUNT(*) FROM organizations WHERE created_by_user_id = ?', [self::USER]);
    self::assertTrue(is_int($count) || is_string($count));
    self::assertSame(0, (int) $count);
  }

  #[Test]
  public function profileUpdatePersistsWithoutChangingRoles(): void
  {
    $client = $this->client();
    $this->seedOrganization('operator', ['*']);
    $permissionsBefore = $this->manager()->getConnection()->fetchOne('SELECT permissions FROM organization_roles WHERE id = ?', [self::ROLE]);

    $this->patch($client, ['operatingProfile' => 'service_provider']);

    self::assertResponseStatusCodeSame(200);
    self::assertSame('service_provider', $this->body($client)['operatingProfile']);
    self::assertSame('service_provider', $this->persistedProfile());
    self::assertSame($permissionsBefore, $this->manager()->getConnection()->fetchOne('SELECT permissions FROM organization_roles WHERE id = ?', [self::ROLE]));
    $count = $this->manager()->getConnection()->fetchOne('SELECT COUNT(*) FROM organization_member_roles WHERE member_id = ?', [self::MEMBER]);
    self::assertTrue(is_int($count) || is_string($count));
    self::assertSame(1, (int) $count);
  }

  /**
   * @param array<string,mixed> $payload partial update
   */
  #[Test]
  #[DataProvider('preservingUpdates')]
  public function nullOrOmittedProfilePreservesExistingProvider(array $payload): void
  {
    $client = $this->client();
    $this->seedOrganization('service_provider', ['*']);

    $this->patch($client, $payload);

    self::assertResponseStatusCodeSame(200);
    self::assertSame('service_provider', $this->body($client)['operatingProfile']);
    self::assertSame('service_provider', $this->persistedProfile());
  }

  #[Test]
  public function updateRejectsUnsupportedProfileWithoutChangingExistingValue(): void
  {
    $client = $this->client();
    $this->seedOrganization('operator', ['*']);

    $this->patch($client, ['operatingProfile' => 'administrator']);

    self::assertResponseStatusCodeSame(422);
    self::assertSame('operator', $this->persistedProfile());
  }

  #[Test]
  public function providerProfileDoesNotGrantSettingsPermissionToReadOnlyMember(): void
  {
    $client = $this->client();
    $this->seedOrganization('service_provider', ['organization.read']);

    $this->patch($client, ['operatingProfile' => 'operator']);

    self::assertResponseStatusCodeSame(403);
    self::assertSame('service_provider', $this->persistedProfile());
  }

  #[Test]
  public function callerCannotUpdateForeignOrganizationProfile(): void
  {
    $client = $this->client();
    $this->seedOrganization('operator', ['*'], memberUserId: self::OWNER);

    $this->patch($client, ['operatingProfile' => 'service_provider']);

    self::assertResponseStatusCodeSame(403);
    self::assertSame('operator', $this->persistedProfile());
  }

  #[Test]
  public function detailReturnsPersistedProviderProfile(): void
  {
    $client = $this->client();
    $this->seedOrganization('service_provider', ['organization.read']);

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION, server: ['HTTP_ACCEPT' => 'application/ld+json']);

    self::assertResponseStatusCodeSame(200);
    self::assertSame('service_provider', $this->body($client)['operatingProfile']);
  }

  #[Test]
  public function collectionReturnsPersistedProviderProfile(): void
  {
    $client = $this->client();
    $this->seedOrganization('service_provider', ['organization.read']);

    $client->request('GET', '/api/organizations', server: ['HTTP_ACCEPT' => 'application/ld+json']);

    self::assertResponseStatusCodeSame(200);
    $body = $this->body($client);
    self::assertIsArray($body['member']);
    self::assertCount(1, $body['member']);
    self::assertIsArray($body['member'][0]);
    self::assertSame(self::ORGANIZATION, $body['member'][0]['id']);
    self::assertSame('service_provider', $body['member'][0]['operatingProfile']);
  }

  /**
   * @return iterable<string,array{?string,string}>
   */
  public static function creationProfiles(): iterable
  {
    yield 'omitted defaults to operator' => [null, 'operator'];
    yield 'explicit operator' => ['operator', 'operator'];
    yield 'service provider' => ['service_provider', 'service_provider'];
  }

  /**
   * @return iterable<string,array{array<string,mixed>}>
   */
  public static function preservingUpdates(): iterable
  {
    yield 'omitted' => [['description' => 'Updated description']];
    yield 'null' => [['operatingProfile' => null]];
  }

  private function client(): KernelBrowser
  {
    $client = static::createClient();
    $client->loginUser(new SecurityUser(self::USER, 'operating-profile-test@example.com', 'hashed-password', ['ROLE_USER']), 'api');

    return $client;
  }

  /**
   * @param list<string> $permissions assigned permissions
   */
  private function seedOrganization(string $profile, array $permissions, string $memberUserId = self::USER): void
  {
    $manager = $this->manager();
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'Operating profile test';
    $organization->slug = 'operating-profile-test';
    $organization->ownerUserId = self::OWNER;
    $organization->createdByUserId = self::OWNER;
    $organization->operatingProfile = $profile;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $manager->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE;
    $role->organization = $organization;
    $role->name = 'operating_profile_tester';
    $role->permissions = $permissions;
    $role->createdAt = $now;
    $manager->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = self::MEMBER;
    $member->organization = $organization;
    $member->userId = $memberUserId;
    $member->joinedAt = $now;
    $manager->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $manager->persist($assignment);
    $manager->flush();
  }

  /**
   * @param array<string,mixed> $payload partial update
   */
  private function patch(KernelBrowser $client, array $payload): void
  {
    $client->request('PATCH', '/api/organizations/' . self::ORGANIZATION, server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: json_encode($payload, JSON_THROW_ON_ERROR));
  }

  private function persistedProfile(): mixed
  {
    return $this->manager()->getConnection()->fetchOne('SELECT operating_profile FROM organizations WHERE id = ?', [self::ORGANIZATION]);
  }

  private function manager(): EntityManagerInterface
  {
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager;
  }

  /**
   * @return array<string,mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($body);

    /** @var array<string,mixed> $body */
    return $body;
  }
}
