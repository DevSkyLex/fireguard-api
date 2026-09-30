<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Shared\Application\Port\Outbound\CachePort;
use Shared\Infrastructure\Symfony\Adapter\Outbound\CacheAdapter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Uid\Uuid;
use Tests\Support\Auth\InteractiveTokenFactory;
use User\Application\Port\Outbound\PresencePreferenceRepositoryPort;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function array_column;
use function base64_decode;
use function explode;
use function implode;
use function json_decode;
use function json_encode;
use function strtr;
use function time;

use const JSON_THROW_ON_ERROR;

/**
 * HTTP contracts use PostgreSQL test clones and a local hub double.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserPresenceApiTest extends WebTestCase
{
  /**
   * @return iterable<string, array{string, string}>
   */
  public static function authenticatedRoutes(): iterable
  {
    yield 'preference read' => ['GET', '/api/me/presence-preference'];
    yield 'preference update' => ['PATCH', '/api/me/presence-preference'];
    yield 'personal subscription' => ['GET', '/api/me/presence-preference/subscription'];
    yield 'organization subscription' => ['GET', '/api/presence/subscription?organization=550e8400-e29b-41d4-a716-446655440000'];
  }

  #[Test]
  #[DataProvider('authenticatedRoutes')]
  /**
   * @since 1.0.0
   */
  public function authenticationIsRequired(string $method, string $url): void
  {
    $client = static::createClient();
    $client->request($method, $url, server: ['CONTENT_TYPE' => 'application/merge-patch+json'], content: '{"doNotDisturb":true}');
    self::assertResponseStatusCodeSame(401);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function preferencePersistsRevisionsAndRemainsPrivateToTheAccount(): void
  {
    $client = $this->client();
    $userId = Uuid::v4()->toRfc4122();
    $this->login($client, $userId);
    $client->request('GET', '/api/me/presence-preference', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseIsSuccessful();
    self::assertSame(['doNotDisturb' => false, 'revision' => 0], $this->preferenceBody($client));
    foreach ([[true, 1], [true, 1], [false, 2], [true, 3]] as [$dnd, $revision]) {
      $client->request('PATCH', '/api/me/presence-preference', server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: json_encode(['doNotDisturb' => $dnd], JSON_THROW_ON_ERROR));
      self::assertResponseStatusCodeSame(200);
      self::assertSame(['doNotDisturb' => $dnd, 'revision' => $revision], $this->preferenceBody($client));
    }
    $client->request('GET', '/api/me/presence-preference', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(['doNotDisturb' => true, 'revision' => 3], $this->preferenceBody($client));
    $this->login($client, Uuid::v4()->toRfc4122());
    $client->request('GET', '/api/me/presence-preference', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(['doNotDisturb' => false, 'revision' => 0], $this->preferenceBody($client));
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function missingOrNonBooleanPreferenceIsRejected(): void
  {
    $client = $this->client();
    $this->login($client, Uuid::v4()->toRfc4122());
    foreach (['{}', '{"doNotDisturb":null}', '{"doNotDisturb":"yes"}', '{"invisible":"yes"}'] as $body) {
      $client->request('PATCH', '/api/me/presence-preference', server: ['CONTENT_TYPE' => 'application/merge-patch+json'], content: $body);
      self::assertResponseStatusCodeSame(422);
    }
  }

  #[Test]
  public function invisiblePreferenceIsPrivatePersistentAndExclusiveOfNpd(): void
  {
    $client = $this->client();
    $this->login($client, Uuid::v4()->toRfc4122());
    foreach ([['doNotDisturb' => true], ['invisible' => true], ['doNotDisturb' => true], ['invisible' => true]] as $patch) {
      $client->request('PATCH', '/api/me/presence-preference', server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: json_encode($patch, JSON_THROW_ON_ERROR));
      self::assertResponseStatusCodeSame(200);
      $current = $this->body($client);
      self::assertSame(isset($patch['invisible']), $current['invisible']);
      self::assertSame(isset($patch['doNotDisturb']), $current['doNotDisturb']);
    }
    $client->request('GET', '/api/me/presence-preference', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    $body = $this->body($client);
    self::assertTrue($body['invisible']);
    self::assertFalse($body['doNotDisturb']);
    self::assertSame(4, $body['revision']);
    $this->login($client, Uuid::v4()->toRfc4122());
    $client->request('GET', '/api/me/presence-preference', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertFalse($this->body($client)['invisible']);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function personalSubscriptionIsExactPrivateAndExpiring(): void
  {
    $client = $this->client();
    $userId = Uuid::v4()->toRfc4122();
    $this->login($client, $userId);
    $client->request('GET', '/api/me/presence-preference/subscription', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseStatusCodeSame(200);
    $this->assertSubscription($this->body($client), '/users/' . $userId . '/presence-preference');
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function directoryOnlyMemberReadsThreeStatesWithoutLeakingForeignOrInactivePresence(): void
  {
    $client = $this->client();
    $userId = Uuid::v4()->toRfc4122();
    $organization = $this->organization($userId, ['organization.members.read']);
    $this->login($client, $userId);
    $client->request('POST', '/api/presence/ping', server: ['CONTENT_TYPE' => 'application/ld+json'], content: json_encode(['organization' => '/api/organizations/' . $organization['id']], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(200);
    /**
     * @var CachePort $cache
     */
    $cache = static::getContainer()->get(CachePort::class);
    $cache->set('messaging.presence.' . $organization['id'] . '.' . $organization['busy'], '2026-09-26T00:00:00+00:00', 90);
    $cache->set('messaging.presence.' . $organization['id'] . '.' . $organization['inactive'], '2026-09-26T00:00:00+00:00', 90);
    $cache->set('messaging.presence.' . $organization['id'] . '.' . $organization['foreign'], '2026-09-26T00:00:00+00:00', 90);
    /**
     * @var PresencePreferenceRepositoryPort $preferences
     */
    $preferences = static::getContainer()->get(PresencePreferenceRepositoryPort::class);
    $preferences->save($organization['busyUser'], true);
    $client->request('GET', '/api/presence?organization=' . $organization['id'] . '&memberIds=' . implode(',', [$organization['caller'], $organization['busy'], $organization['expired'], $organization['inactive'], $organization['foreign']]), server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseStatusCodeSame(200);
    $body = $this->body($client);
    $rows = $body['member'] ?? $body['hydra:member'] ?? [];
    self::assertIsArray($rows);
    self::assertSame(['active', 'do_not_disturb', 'offline', 'offline', 'offline'], array_column($rows, 'status'));
    self::assertSame([true, true, false, false, false], array_column($rows, 'online'));
    $preferences->save($organization['busyUser'], null, true);
    $client->request('GET', '/api/presence?organization=' . $organization['id'] . '&memberIds=' . $organization['busy'], server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseStatusCodeSame(200);
    $hiddenBody = $this->body($client);
    $hiddenRows = $hiddenBody['member'] ?? $hiddenBody['hydra:member'] ?? [];
    self::assertIsArray($hiddenRows);
    self::assertCount(1, $hiddenRows);
    self::assertIsArray($hiddenRows[0]);
    self::assertSame('offline', $hiddenRows[0]['status']);
    self::assertFalse($hiddenRows[0]['online']);
    self::assertNull($hiddenRows[0]['lastSeenAt'] ?? null);
    $client->request('GET', '/api/presence/subscription?organization=' . $organization['id'], server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseStatusCodeSame(200);
    $this->assertSubscription($this->body($client), '/organizations/' . $organization['id'] . '/presence');
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function membershipWithoutReadPermissionCanPingButCannotReadOrSubscribe(): void
  {
    $client = $this->client();
    $user = Uuid::v4()->toRfc4122();
    $organization = $this->organization($user, []);
    $this->login($client, $user);
    $client->request('POST', '/api/presence/ping', server: ['CONTENT_TYPE' => 'application/ld+json'], content: json_encode(['organization' => '/api/organizations/' . $organization['id']], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(200);
    $client->request('GET', '/api/presence?organization=' . $organization['id'] . '&memberIds=' . $organization['caller']);
    self::assertResponseStatusCodeSame(403);
    $client->request('GET', '/api/presence/subscription?organization=' . $organization['id']);
    self::assertResponseStatusCodeSame(403);
    $this->login($client, Uuid::v4()->toRfc4122());
    $client->request('GET', '/api/presence/subscription?organization=' . $organization['id']);
    self::assertResponseStatusCodeSame(404);
    $client->request('POST', '/api/presence/ping', server: ['CONTENT_TYPE' => 'application/ld+json'], content: json_encode(['organization' => '/api/organizations/' . $organization['id']], JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(404);
  }

  /**
   * @since 1.0.0
   */
  private function client(): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    $hub = $this->createStub(HubInterface::class);
    $hub->method('publish')->willReturn('test-event');
    static::getContainer()->set(HubInterface::class, $hub);
    static::getContainer()->set(CachePort::class, new CacheAdapter(new ArrayAdapter()));

    return $client;
  }

  /**
   * @since 1.0.0
   */
  private function login(KernelBrowser $client, string $userId): void
  {
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    $user = new UserRecord();
    $user->id = $userId;
    $user->username = 'presence-' . $userId;
    $user->email = $userId . '@presence-test.example';
    $user->firstName = 'Presence';
    $user->lastName = 'Tester';
    $user->status = 'active';
    $user->createdAt = new DateTimeImmutable();
    $em->persist($user);
    $em->flush();
    if ('' === $userId) {
      throw new LogicException('A test user identifier is required.');
    }
    $token = InteractiveTokenFactory::issue(static::getContainer(), $userId, $user->email);
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
  }

  /**
   * @return array<string, mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $body = json_decode($client->getResponse()->getContent() ?: '{}', true);
    self::assertIsArray($body);

    /** @var array<string, mixed> $body */
    return $body;
  }

  /**
   * @return array{doNotDisturb: mixed, revision: mixed}
   */
  private function preferenceBody(KernelBrowser $client): array
  {
    $body = $this->body($client);

    return ['doNotDisturb' => $body['doNotDisturb'], 'revision' => $body['revision']];
  }

  /**
   * @param array<string, mixed> $body
   */
  private function assertSubscription(array $body, string $topic): void
  {
    self::assertSame($topic, $body['topic']);
    self::assertIsString($body['token']);
    $parts = explode('.', $body['token']);
    $claimsJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
    self::assertIsString($claimsJson);
    $claims = json_decode($claimsJson, true);
    self::assertIsArray($claims);
    self::assertIsArray($claims['mercure']);
    self::assertIsInt($claims['exp']);
    self::assertIsString($body['expiresAt']);
    self::assertSame([$topic], $claims['mercure']['subscribe']);
    self::assertSame([], $claims['mercure']['publish'] ?? []);
    self::assertSame($claims['exp'], new DateTimeImmutable($body['expiresAt'])->getTimestamp());
    self::assertGreaterThan(time(), $claims['exp']);
  }

  /**
   * @param list<string> $permissions
   *
   * @return array<string, string>
   */
  private function organization(string $callerUser, array $permissions): array
  {
    /**
     * @var EntityManagerInterface $em
     */
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable();
    $org = new OrganizationRecord();
    $org->id = Uuid::v4()->toRfc4122();
    $org->name = 'Presence tests';
    $org->slug = 'presence-' . $org->id;
    $org->ownerUserId = Uuid::v4()->toRfc4122();
    $org->createdByUserId = $org->ownerUserId;
    $org->createdAt = $now;
    $org->updatedAt = $now;
    $em->persist($org);
    $ids = ['id' => $org->id, 'foreign' => Uuid::v4()->toRfc4122()];
    foreach (['caller', 'busy', 'expired', 'inactive'] as $name) {
      $member = new OrganizationMemberRecord();
      $member->id = Uuid::v4()->toRfc4122();
      $member->organization = $org;
      $member->userId = 'caller' === $name ? $callerUser : Uuid::v4()->toRfc4122();
      $member->isActive = 'inactive' !== $name;
      $member->joinedAt = $now;
      $em->persist($member);
      $ids[$name] = $member->id;
      $ids[$name . 'User'] = $member->userId;
      if ('caller' !== $name) {
        continue;
      }
      $role = new OrganizationRoleRecord();
      $role->id = Uuid::v4()->toRfc4122();
      $role->organization = $org;
      $role->name = 'presence-custom';
      $role->permissions = $permissions;
      $role->isSystem = false;
      $role->createdAt = $now;
      $em->persist($role);
      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $role;
      $assignment->assignedAt = $now;
      $em->persist($assignment);
    }
    $em->flush();

    return $ids;
  }
}
