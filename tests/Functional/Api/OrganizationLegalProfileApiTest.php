<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;
use function str_repeat;
use function substr;

/**
 * Class OrganizationLegalProfileApiTest
 *
 * Verifies registered office replacement semantics and the existing organization access boundary.
 *
 * @category Functional Tests
 */
final class OrganizationLegalProfileApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655478600';

  private const string OWNER_ID = '550e8400-e29b-41d4-a716-446655478601';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655478602';

  #[Test]
  public function testProfileCanBeSetReplacedLeftUnchangedAndCleared(): void
  {
    $this->seedOrganization();
    $body = $this->patch([
      'registeredAddress' => ['line1' => '  10 rue du Test  ', 'city' => ' Paris ', 'countryCode' => ' fr '],
      'privacyContactEmail' => ' privacy@example.com ',
    ]);
    $address = $body['registeredAddress'] ?? null;
    self::assertIsArray($address);
    self::assertSame('10 rue du Test', $address['line1'] ?? null);
    self::assertSame('FR', $address['countryCode'] ?? null);
    self::assertSame('privacy@example.com', $body['privacyContactEmail'] ?? null);
    self::assertNull($body['legalName'] ?? null, 'Partial legal profiles remain valid.');

    $body = $this->patch(['description' => 'A separate settings change']);
    $address = $body['registeredAddress'] ?? null;
    self::assertIsArray($address);
    self::assertSame('Paris', $address['city'] ?? null);
    self::assertSame('privacy@example.com', $body['privacyContactEmail'] ?? null);

    $body = $this->patch(['registeredAddress' => null, 'privacyContactEmail' => null]);
    $address = $body['registeredAddress'] ?? null;
    self::assertIsArray($address);
    self::assertSame('Paris', $address['city'] ?? null);
    self::assertSame('privacy@example.com', $body['privacyContactEmail'] ?? null);

    $body = $this->patch(['registeredAddress' => ['city' => 'Lyon']]);
    $address = $body['registeredAddress'] ?? null;
    self::assertIsArray($address);
    self::assertSame('Lyon', $address['city'] ?? null);
    self::assertNull($address['line1'] ?? null);
    self::assertNull($address['countryCode'] ?? null);

    $read = $this->readAs(self::MEMBER_ID);
    $address = $read['registeredAddress'] ?? null;
    self::assertIsArray($address);
    self::assertSame('Lyon', $address['city'] ?? null);
    self::assertSame('privacy@example.com', $read['privacyContactEmail'] ?? null);

    $body = $this->patch(['registeredAddress' => new stdClass(), 'privacyContactEmail' => '']);
    self::assertNull($body['registeredAddress'] ?? null);
    self::assertNull($body['privacyContactEmail'] ?? null);

    $read = $this->readAs(self::MEMBER_ID);
    self::assertNull($read['registeredAddress'] ?? null);
    self::assertNull($read['privacyContactEmail'] ?? null);
  }

  #[Test]
  public function testAddressLengthLimitsApplyAfterTrimmingAndPersistNormalizedValues(): void
  {
    $this->seedOrganization();
    $components = [
      'line1' => str_repeat('a', 255),
      'line2' => str_repeat('b', 255),
      'postalCode' => str_repeat('c', 32),
      'city' => str_repeat('d', 128),
      'region' => str_repeat('e', 128),
    ];
    $padded = [];
    foreach ($components as $field => $value) {
      $padded[$field] = ' ' . $value . ' ';
    }
    $this->patch(['registeredAddress' => $padded]);
    $body = $this->readAs(self::OWNER_ID);
    $address = $body['registeredAddress'] ?? null;
    self::assertIsArray($address);
    foreach ($components as $field => $value) {
      self::assertSame($value, $address[$field] ?? null);
    }
  }

  /**
   * @param array<string, mixed> $payload
   */
  #[Test]
  #[DataProvider('invalidProfiles')]
  public function testInvalidProfilesAreRejectedWithoutPersistence(array $payload, int $expectedStatus): void
  {
    $this->seedOrganization();
    $this->patch($payload, $expectedStatus);
    $body = $this->readAs(self::OWNER_ID);
    self::assertNull($body['registeredAddress'] ?? null);
    self::assertNull($body['privacyContactEmail'] ?? null);
  }

  /**
   * @return iterable<string, array{array<string, mixed>, int}>
   */
  public static function invalidProfiles(): iterable
  {
    yield 'unassigned ISO code' => [['registeredAddress' => ['countryCode' => 'ZZ']], 400];
    yield 'country name' => [['registeredAddress' => ['countryCode' => 'France']], 422];
    yield 'email' => [['privacyContactEmail' => 'invalid'], 422];
    yield 'nested field length' => [['registeredAddress' => ['city' => str_repeat('a', 129)]], 422];
  }

  #[Test]
  #[DataProvider('invalidAddressShapes')]
  public function testMalformedAddressCannotEraseTheExistingProfile(mixed $address): void
  {
    $this->seedOrganization();
    $original = $this->patch([
      'registeredAddress' => ['line1' => '10 rue du Test', 'city' => 'Paris', 'countryCode' => 'FR'],
      'privacyContactEmail' => 'privacy@example.com',
    ]);

    $this->patch(['registeredAddress' => $address, 'privacyContactEmail' => 'changed@example.com'], 400);

    $read = $this->readAs(self::OWNER_ID);
    self::assertSame(
      $this->stableAddressPayload($original['registeredAddress']),
      $this->stableAddressPayload($read['registeredAddress'] ?? null),
    );
    self::assertSame('privacy@example.com', $read['privacyContactEmail'] ?? null);
  }

  /**
   * @return iterable<string, array{mixed}>
   */
  public static function invalidAddressShapes(): iterable
  {
    yield 'false' => [false];
    yield 'true' => [true];
    yield 'integer' => [0];
    yield 'string' => ['Paris'];
    yield 'empty array' => [[]];
    yield 'list' => [['foo']];
    yield 'unknown property' => [['bogus' => 'foo']];
    yield 'mixed properties' => [['city' => 'Lyon', 'bogus' => 'foo']];
  }

  #[Test]
  public function testPermissionDenialPrecedesMalformedAddressShape(): void
  {
    $this->seedOrganization();
    $original = $this->patch(['registeredAddress' => ['city' => 'Paris']]);

    $this->patch(['registeredAddress' => false], 403, self::MEMBER_ID);

    $read = $this->readAs(self::OWNER_ID);
    self::assertSame(
      $this->stableAddressPayload($original['registeredAddress']),
      $this->stableAddressPayload($read['registeredAddress'] ?? null),
    );
  }

  #[Test]
  public function testMemberCanReadContactButCannotEditAndOutsideUserCannotRead(): void
  {
    $this->seedOrganization();
    $this->patch(['privacyContactEmail' => 'privacy@example.com']);
    $body = $this->readAs(self::MEMBER_ID);
    self::assertSame('privacy@example.com', $body['privacyContactEmail'] ?? null);
    $this->patch(['privacyContactEmail' => 'attacker@example.com'], 403, self::MEMBER_ID);
    $body = $this->readAs(self::OWNER_ID);
    self::assertSame('privacy@example.com', $body['privacyContactEmail'] ?? null);

    static::ensureKernelShutdown();
    $client = static::createClient();
    $client->loginUser($this->securityUser('550e8400-e29b-41d4-a716-446655478699'), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    self::assertStringNotContainsString('privacy@example.com', $client->getResponse()->getContent() ?: '');
  }

  /**
   * @param array<string, mixed> $payload
   *
   * @return array<string, mixed>
   */
  private function patch(array $payload, int $expectedStatus = 200, string $userId = self::OWNER_ID): array
  {
    static::ensureKernelShutdown();
    $client = static::createClient();
    $client->loginUser($this->securityUser($userId), 'api');
    $client->request(
      'PATCH',
      '/api/organizations/' . self::ORGANIZATION_ID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode($payload),
    );
    $response = $client->getResponse();
    self::assertSame($expectedStatus, $response->getStatusCode(), $response->getContent() ?: '');

    return $this->decodeBody($response->getContent() ?: '{}');
  }

  /**
   * @return array<string, mixed>
   */
  private function readAs(string $userId): array
  {
    static::ensureKernelShutdown();
    $client = static::createClient();
    $client->loginUser($this->securityUser($userId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');

    return $this->decodeBody($response->getContent() ?: '{}');
  }

  /**
   * @return array<string, mixed>
   */
  private function decodeBody(string $content): array
  {
    $decoded = json_decode($content, true);
    self::assertIsArray($decoded);
    $body = [];
    foreach ($decoded as $key => $value) {
      self::assertIsString($key);
      $body[$key] = $value;
    }

    return $body;
  }

  /**
   * Embedded JSON-LD identifiers are generated per response; every other field,
   * including @var, remains part of the conservation assertion.
   *
   * @return array<array-key, mixed>
   */
  private function stableAddressPayload(mixed $address): array
  {
    self::assertIsArray($address);
    unset($address['@id']);

    return $address;
  }

  private function seedOrganization(): void
  {
    static::createClient();
    /** @var EntityManagerInterface $manager */
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $existing = $manager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    if ($existing instanceof OrganizationRecord) {
      $manager->remove($existing);
      $manager->flush();
    }
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Legal Profile Test';
    $organization->slug = 'legal-profile-test';
    $organization->ownerUserId = self::OWNER_ID;
    $organization->createdByUserId = self::OWNER_ID;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $manager->persist($organization);

    foreach ([self::OWNER_ID => ['*'], self::MEMBER_ID => ['organization.read']] as $userId => $permissions) {
      $role = new OrganizationRoleRecord();
      $role->id = substr($userId, 0, 34) . 'a' . substr($userId, 35);
      $role->organization = $organization;
      $role->name = self::OWNER_ID === $userId ? 'test_owner' : 'test_reader';
      $role->permissions = $permissions;
      $role->createdAt = $now;
      $manager->persist($role);
      $member = new OrganizationMemberRecord();
      $member->id = substr($userId, 0, 34) . 'b' . substr($userId, 35);
      $member->organization = $organization;
      $member->userId = $userId;
      $member->joinedAt = $now;
      $manager->persist($member);
      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $role;
      $assignment->assignedAt = $now;
      $manager->persist($assignment);
    }
    $manager->flush();
  }

  private function securityUser(string $userId): SecurityUser
  {
    return new SecurityUser($userId, $userId . '@example.com', 'hashed-password', ['ROLE_USER']);
  }
}
