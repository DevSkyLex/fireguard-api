<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, PlanRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function is_float;
use function is_int;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** HTTP contracts for physical floor dimensions across canonical and organization routes. */
final class FacilityFloorMetricsApiTest extends WebTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '940e8400-e29b-41d4-a716-446655442001';

  private const string ADMIN_ID = '940e8400-e29b-41d4-a716-446655442002';

  private const string READER_ID = '940e8400-e29b-41d4-a716-446655442003';

  private const string OUTSIDER_ID = '940e8400-e29b-41d4-a716-446655442004';

  private const string BUILDING_ID = '940e8400-e29b-41d4-a716-446655442010';

  private const string FLOOR_ID = '940e8400-e29b-41d4-a716-446655442011';

  private const string SITE_ID = '940e8400-e29b-41d4-a716-446655442012';
  // #endregion

  // #region Methods
  /**
   * Canonical patches preserve absence and explicitly erase null values.
   */
  #[Test]
  public function canonicalPatchPreservesAbsentMetric(): void
  {
    $client = $this->client();
    $this->patch($client, ['elevationMeters' => null]);

    self::assertResponseIsSuccessful();
    $payload = $this->payload($client);
    self::assertNull($payload['elevationMeters'] ?? null);
    self::assertSame(2.5, $payload['heightMeters']);
    self::assertSame(2, $payload['revision']);
    $record = $this->record();
    self::assertNull($record->elevationMeters);
    self::assertSame(2.5, $record->heightMeters);
  }

  /**
   * Changing the type clears stored metrics even when both keys were omitted.
   */
  #[Test]
  public function canonicalTypeChangeClearsMetrics(): void
  {
    $client = $this->client();
    $this->patch($client, ['type' => 'area']);

    self::assertResponseIsSuccessful();
    $record = $this->record();
    self::assertSame('area', $record->type);
    self::assertNull($record->elevationMeters);
    self::assertNull($record->heightMeters);
  }

  /**
   * Metric fields survive every facility detail and collection projection.
   */
  #[Test]
  #[DataProvider('readRoutes')]
  public function metricsAreReturnedOnReads(string $route, bool $collection): void
  {
    $client = $this->client();
    $client->request('GET', $route, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseIsSuccessful();
    $payload = $this->payload($client);
    if ($collection) {
      $rows = $payload['member'] ?? $payload['hydra:member'] ?? [];
      self::assertIsArray($rows);
      $payload = null;
      foreach ($rows as $row) {
        self::assertIsArray($row);
        if (self::FLOOR_ID === ($row['id'] ?? null)) {
          $payload = $row;

          break;
        }
      }
      self::assertIsArray($payload);
    }
    self::assertSame(-3.0, $this->metric($payload, 'elevationMeters'));
    self::assertSame(2.5, $payload['heightMeters']);
  }

  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function readRoutes(): iterable
  {
    yield 'canonical detail' => ['/api/facilities/' . self::FLOOR_ID, false];
    yield 'organization detail' => ['/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::FLOOR_ID, false];
    yield 'canonical collection' => ['/api/facilities?organization=/api/organizations/' . self::ORGANIZATION_ID, true];
    yield 'organization collection' => ['/api/organizations/' . self::ORGANIZATION_ID . '/facilities', true];
    yield 'children' => ['/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::BUILDING_ID . '/children', true];
    yield 'descendants' => ['/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::BUILDING_ID . '/descendants', true];
  }

  /**
   * Creating a basement stores both independent physical dimensions.
   */
  #[Test]
  public function creationPersistsPhysicalDimensions(): void
  {
    $client = $this->client(withFloor: false);
    $client->request(
      'POST',
      '/api/organizations/' . self::ORGANIZATION_ID . '/facilities',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'type' => 'floor', 'name' => 'Second basement', 'parentFacilityId' => self::BUILDING_ID,
        'elevationMeters' => -6.0, 'heightMeters' => 3.0,
      ]),
    );
    self::assertResponseStatusCodeSame(201);
    $payload = $this->payload($client);
    self::assertSame(-6.0, $this->metric($payload, 'elevationMeters'));
    self::assertSame(3.0, $this->metric($payload, 'heightMeters'));
    self::assertIsString($payload['id']);
    $record = $this->record($payload['id']);
    self::assertSame(-6.0, $record->elevationMeters);
    self::assertSame(3.0, $record->heightMeters);
  }

  /**
   * Legacy patches also distinguish an absent field from an explicit clear.
   */
  #[Test]
  public function legacyPatchPreservesAbsentMetric(): void
  {
    $client = $this->client();
    $client->request(
      'PATCH',
      '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::FLOOR_ID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode(['heightMeters' => null]),
    );
    self::assertResponseIsSuccessful();
    $record = $this->record();
    self::assertSame(-3.0, $record->elevationMeters);
    self::assertNull($record->heightMeters);
  }

  /**
   * Method invalidPatchIsRejected
   *
   * Invalid ranges, non-floor metrics and optimistic locking fail before persistence.
   *
   * @param array<string, mixed> $patch supplied changes
   */
  #[Test]
  #[DataProvider('rejectedPatches')]
  public function invalidPatchIsRejected(array $patch, int $status, string $revision): void
  {
    $client = $this->client();
    $this->patch($client, $patch, $revision);

    self::assertResponseStatusCodeSame($status);
    $record = $this->record();
    self::assertSame('floor', $record->type);
    self::assertSame(-3.0, $record->elevationMeters);
    self::assertSame(2.5, $record->heightMeters);
    self::assertSame(1, $record->revision);
  }

  /**
   * @return iterable<string, array{array<string, mixed>, int, string}>
   */
  public static function rejectedPatches(): iterable
  {
    yield 'zero height' => [['heightMeters' => 0], 422, '"revision-1"'];
    yield 'high elevation' => [['elevationMeters' => 10001], 422, '"revision-1"'];
    yield 'metrics on non-floor' => [['type' => 'building', 'parent' => '/api/facilities/' . self::SITE_ID, 'heightMeters' => 3], 400, '"revision-1"'];
    yield 'stale revision' => [['heightMeters' => 3], 412, '"revision-0"'];
  }

  /**
   * Facilities write permission and organization boundaries remain enforced.
   */
  #[Test]
  #[DataProvider('deniedUsers')]
  public function metricUpdatesPreserveAuthorization(string $userId, int $status): void
  {
    $client = $this->client($userId);
    $this->patch($client, ['heightMeters' => 3.0]);
    self::assertResponseStatusCodeSame($status);
    self::assertSame(2.5, $this->record()->heightMeters);
  }

  /**
   * @return iterable<string, array{string, int}>
   */
  public static function deniedUsers(): iterable
  {
    yield 'reader' => [self::READER_ID, 403];
    yield 'outsider' => [self::OUTSIDER_ID, 404];
  }

  /**
   * Creates the HTTP client and seeds a floor with separate physical and ordinal data.
   */
  private function client(string $userId = self::ADMIN_ID, bool $withFloor = true): KernelBrowser
  {
    $client = static::createClient();
    /** @var EntityManagerInterface $manager */
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Floor metrics test';
    $organization->slug = 'floor-metrics-test';
    $organization->ownerUserId = self::ADMIN_ID;
    $organization->createdByUserId = self::ADMIN_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $organization->updatedAt = $now;
    $plan = new PlanRecord();
    $plan->id = '940e8400-e29b-41d4-a716-446655442009';
    $plan->key = 'floor-metric-contract-tests';
    $plan->name = 'Floor metric contract test plan';
    $plan->limits = ['facilities' => 10];
    $plan->createdAt = $plan->updatedAt = $now;
    $manager->persist($plan);
    $organization->planId = $plan->id;
    $manager->persist($organization);
    foreach ([self::ADMIN_ID => ['*'], self::READER_ID => ['organization.facilities.read']] as $memberId => $permissions) {
      $role = new OrganizationRoleRecord();
      $role->id = $memberId;
      $role->organization = $organization;
      $role->name = 'metrics-' . $memberId;
      $role->permissions = $permissions;
      $role->createdAt = $now;
      $manager->persist($role);
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $organization;
      $member->userId = $memberId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $manager->persist($member);
      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $role;
      $assignment->assignedAt = $now;
      $manager->persist($assignment);
    }
    $site = new FacilityRecord();
    $site->id = self::SITE_ID;
    $site->organization = $organization;
    $site->type = 'site';
    $site->name = 'Metric campus';
    $site->createdAt = $site->updatedAt = $now;
    $manager->persist($site);
    $building = new FacilityRecord();
    $building->id = self::BUILDING_ID;
    $building->organization = $organization;
    $building->parentFacility = $site;
    $building->type = 'building';
    $building->name = 'Building';
    $building->status = 'active';
    $building->createdAt = $building->updatedAt = $now;
    $manager->persist($building);
    $floor = new FacilityRecord();
    $floor->id = self::FLOOR_ID;
    $floor->organization = $organization;
    $floor->parentFacility = $building;
    $floor->type = 'floor';
    $floor->name = 'Basement';
    $floor->status = 'active';
    $floor->elevationMeters = -3.0;
    $floor->heightMeters = 2.5;
    $floor->createdAt = $floor->updatedAt = $now;
    if ($withFloor) {
      $manager->persist($floor);
    }
    $manager->flush();
    $manager->clear();
    $client->loginUser(new SecurityUser(id: $userId, email: 'metrics@example.com', password: 'unused', roles: ['ROLE_USER']), 'api');

    return $client;
  }

  /**
   * Method patch
   *
   * Sends one canonical optimistic merge patch.
   *
   * @param array<string, mixed> $payload supplied changes
   */
  private function patch(KernelBrowser $client, array $payload, string $revision = '"revision-1"'): void
  {
    $client->request(
      'PATCH',
      '/api/facilities/' . self::FLOOR_ID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => $revision],
      content: (string) json_encode($payload),
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function payload(KernelBrowser $client): array
  {
    /** @var array<string, mixed> $payload */
    $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $payload;
  }

  /**
   * Method metric
   *
   * Reads a numeric JSON dimension while accepting integers and floats.
   *
   * @access private
   *
   * @param array<array-key, mixed> $payload the decoded response
   * @param string $field the numeric dimension name
   *
   * @return float physical dimension in meters
   */
  private function metric(array $payload, string $field): float
  {
    $value = $payload[$field] ?? null;
    self::assertTrue(is_int($value) || is_float($value));

    return (float) $value;
  }

  /**
   * Reloads the actual persisted metric values after a request.
   */
  private function record(string $id = self::FLOOR_ID): FacilityRecord
  {
    /** @var EntityManagerInterface $manager */
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $manager->clear();
    $record = $manager->find(FacilityRecord::class, $id);
    self::assertInstanceOf(FacilityRecord::class, $record);

    return $record;
  }
  // #endregion
}
