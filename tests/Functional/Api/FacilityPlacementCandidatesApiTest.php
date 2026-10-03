<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function array_column;
use function json_decode;
use function sprintf;

/**
 * Class FacilityPlacementCandidatesApiTest.
 *
 * Exercises recursive placement candidates, bounded pages, and independent read permissions over HTTP.
 *
 * @category Functional Tests
 */
final class FacilityPlacementCandidatesApiTest extends WebTestCase
{
  // #region Properties
  /**
   * Property currentUser.
   *
   * Restores the stateless test authentication between HTTP requests.
   */
  private int $currentUser = 2;
  // #endregion

  // #region Methods
  #[Test]
  public function testEquipmentCandidatesReachNestedRoomsAndPagesPastTwoHundred(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, 2);
    $path = '/api/organizations/' . self::id(1) . '/facilities/' . self::id(10) . '/equipment';

    $direct = $this->get($client, $path);
    self::assertSame(1, $direct['totalItems']);

    $first = $this->get($client, $path . '?includeDescendants=true&search=placement-candidate&page=1&itemsPerPage=100');
    self::assertSame(205, $first['totalItems']);
    self::assertCount(100, $first['member']);
    self::assertSame(self::id(1000), $first['member'][0]['facilityId']);
    self::assertSame(['attachmentId' => self::id(90), 'x' => 0.25, 'y' => 0.75], $first['member'][0]['planPosition']);

    $second = $this->get($client, $path . '?includeDescendants=true&search=placement-candidate&page=2&itemsPerPage=100');
    $third = $this->get($client, $path . '?includeDescendants=true&search=placement-candidate&page=3&itemsPerPage=100');
    self::assertCount(100, $second['member']);
    self::assertCount(5, $third['member']);
    self::assertSame(self::id(2204), $third['member'][4]['id']);
    self::assertNotContains($first['member'][0]['id'], array_column($second['member'], 'id'));
  }

  #[Test]
  public function testDescendantsKeepBulkCompatibilityAndSupportServerSearchAndPages(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, 2);
    $path = '/api/organizations/' . self::id(1) . '/facilities/' . self::id(10) . '/descendants';

    $bulk = $this->get($client, $path . '?search=Candidate');
    self::assertCount(205, $bulk['member']);
    $first = $this->get($client, $path . '?pagination=true&search=Candidate&page=1&itemsPerPage=100');
    $third = $this->get($client, $path . '?pagination=true&search=Candidate&page=3&itemsPerPage=100');
    self::assertSame(205, $first['totalItems']);
    self::assertCount(100, $first['member']);
    self::assertCount(5, $third['member']);
    self::assertSame(self::id(1204), $third['member'][4]['id']);
    self::assertNotContains(self::id(11), array_column($first['member'], 'id'), 'Archived intermediate nodes are traversed but omitted.');
  }

  #[Test]
  public function testCandidateReadsPreserveSeparateFacilityAndEquipmentPermissions(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, 3);
    $path = '/api/organizations/' . self::id(1) . '/facilities/' . self::id(10);
    $this->get($client, $path . '/descendants?pagination=true');
    $this->request($client, $path . '/equipment?includeDescendants=true');
    self::assertSame(403, $client->getResponse()->getStatusCode());

    $this->login($client, 4);
    $this->get($client, $path . '/equipment?includeDescendants=true');
    $this->request($client, $path . '/descendants?pagination=true');
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testUnknownAndForeignSubtreeRootsReturnTheSameNotFoundResponse(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, 2);
    foreach ([self::id(50), self::id(80), self::id(999)] as $root) {
      foreach (['equipment?includeDescendants=true', 'descendants?pagination=true'] as $endpoint) {
        $this->request($client, '/api/organizations/' . self::id(1) . '/facilities/' . $root . '/' . $endpoint);
        self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
      }
    }
  }

  /**
   * Method get.
   *
   * @access private
   *
   * @param KernelBrowser $client the authenticated HTTP client
   * @param string $path the collection URL
   *
   * @return array{member: list<array<string, mixed>>, totalItems: int} the decoded Hydra collection
   */
  private function get(KernelBrowser &$client, string $path): array
  {
    $this->request($client, $path);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $decoded = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    self::assertIsArray($decoded['member'] ?? null);
    self::assertIsInt($decoded['totalItems'] ?? null);

    /** @var array{member: list<array<string, mixed>>, totalItems: int} $decoded */
    return $decoded;
  }

  /**
   * Method request.
   *
   * Recreates the browser so stateless authentication is present on every request.
   *
   * @access private
   *
   * @param KernelBrowser $client the browser client to renew
   * @param string $path the requested collection path
   *
   * @return void
   */
  private function request(KernelBrowser &$client, string $path): void
  {
    static::ensureKernelShutdown();
    $client = static::createClient();
    $this->login($client, $this->currentUser);
    $client->request('GET', $path, server: ['HTTP_ACCEPT' => 'application/ld+json']);
  }

  /**
   * Method login.
   *
   * @access private
   *
   * @param KernelBrowser $client the browser client
   * @param int $user the user fixture suffix
   *
   * @return void
   */
  private function login(KernelBrowser $client, int $user): void
  {
    $this->currentUser = $user;
    $client->loginUser(new SecurityUser(
      id: self::id($user),
      email: 'candidate-' . $user . '@example.com',
      password: 'unused',
      roles: ['ROLE_USER'],
    ), 'api');
  }

  /**
   * Method seed.
   *
   * Creates more than two hundred room-assigned assets below an archived intermediate node.
   *
   * @access private
   *
   * @return void
   */
  private function seed(): void
  {
    /** @var EntityManagerInterface $manager */
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable('2026-10-03T10:00:00+00:00');
    $organization = $this->organization($manager, 1, $now);
    $foreign = $this->organization($manager, 40, $now);

    foreach ([2 => ['*'], 3 => ['organization.facilities.read'], 4 => ['organization.equipment.read']] as $user => $permissions) {
      $role = new OrganizationRoleRecord();
      $role->id = self::id(60 + $user);
      $role->organization = $organization;
      $role->name = 'placement-' . $user;
      $role->permissions = $permissions;
      $role->isSystem = false;
      $role->createdAt = $now;
      $manager->persist($role);
      $member = new OrganizationMemberRecord();
      $member->id = self::id(70 + $user);
      $member->organization = $organization;
      $member->userId = self::id($user);
      $member->isActive = true;
      $member->joinedAt = $now;
      $manager->persist($member);
      $assignment = new OrganizationMemberRoleRecord();
      $assignment->member = $member;
      $assignment->role = $role;
      $assignment->assignedAt = $now;
      $manager->persist($assignment);
    }

    $floor = $this->facility($manager, $organization, 10, 'Floor', 'floor', null, $now);
    $zone = $this->facility($manager, $organization, 11, 'Archived intermediate', 'zone', $floor, $now);
    $zone->status = 'archived';
    $this->facility($manager, $foreign, 50, 'Foreign root', 'floor', null, $now);
    $draft = $this->facility($manager, $organization, 80, 'Draft root', 'floor', null, $now);
    $draft->recordStatus = 'draft';
    $this->equipment($manager, $organization, 30, $floor->id, 'root-direct', $now);
    for ($index = 0; $index < 205; ++$index) {
      $room = $this->facility($manager, $organization, 1000 + $index, sprintf('Candidate %03d', $index), 'area', $zone, $now);
      $equipment = $this->equipment($manager, $organization, 2000 + $index, $room->id, 'placement-candidate-' . $index, $now);
      if (0 === $index) {
        $equipment->planPosition = ['attachmentId' => self::id(90), 'x' => 0.25, 'y' => 0.75];
      }
    }
    $manager->flush();
  }

  /**
   * Method organization.
   *
   * @access private
   *
   * @param EntityManagerInterface $manager main persistence
   * @param int $suffix the fixture identifier suffix
   * @param DateTimeImmutable $now the shared timestamp
   *
   * @return OrganizationRecord the persisted organization fixture
   */
  private function organization(EntityManagerInterface $manager, int $suffix, DateTimeImmutable $now): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::id($suffix);
    $organization->name = 'Placement Candidates ' . $suffix;
    $organization->slug = 'placement-candidates-' . $suffix;
    $organization->ownerUserId = self::id(2);
    $organization->createdByUserId = self::id(2);
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $manager->persist($organization);

    return $organization;
  }

  /**
   * Method facility.
   *
   * @access private
   *
   * @param EntityManagerInterface $manager main persistence
   * @param OrganizationRecord $organization the organization fixture
   * @param int $suffix the identifier suffix
   * @param string $name the display name
   * @param string $type the hierarchy type
   * @param ?FacilityRecord $parent the optional parent
   * @param DateTimeImmutable $now the shared timestamp
   *
   * @return FacilityRecord the persisted facility fixture
   */
  private function facility(EntityManagerInterface $manager, OrganizationRecord $organization, int $suffix, string $name, string $type, ?FacilityRecord $parent, DateTimeImmutable $now): FacilityRecord
  {
    $facility = new FacilityRecord();
    $facility->id = self::id($suffix);
    $facility->organization = $organization;
    $facility->parentFacility = $parent;
    $facility->name = $name;
    $facility->type = $type;
    $facility->status = 'active';
    $facility->metadata = [];
    $facility->createdAt = $now;
    $facility->updatedAt = $now;
    $manager->persist($facility);

    return $facility;
  }

  /**
   * Method equipment.
   *
   * @access private
   *
   * @param EntityManagerInterface $manager main persistence
   * @param OrganizationRecord $organization the organization fixture
   * @param int $suffix the identifier suffix
   * @param string $facilityId the assigned room or floor
   * @param string $serial the searchable serial number
   * @param DateTimeImmutable $now the shared timestamp
   *
   * @return EquipmentRecord the persisted equipment fixture
   */
  private function equipment(EntityManagerInterface $manager, OrganizationRecord $organization, int $suffix, string $facilityId, string $serial, DateTimeImmutable $now): EquipmentRecord
  {
    $equipment = new EquipmentRecord();
    $equipment->id = self::id($suffix);
    $equipment->organization = $organization;
    $equipment->facilityId = $facilityId;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'operational';
    $equipment->serialNumber = $serial;
    $equipment->recordStatus = 'published';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $manager->persist($equipment);

    return $equipment;
  }

  /**
   * Method id.
   *
   * @access private
   *
   * @param int $suffix the deterministic fixture suffix
   *
   * @return string the fixture UUID
   */
  private static function id(int $suffix): string
  {
    return sprintf('980e8400-e29b-41d4-a716-%012d', $suffix);
  }
  // #endregion
}
