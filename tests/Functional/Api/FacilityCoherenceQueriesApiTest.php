<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

use function array_column;
use function json_decode;
use function json_encode;
use function sprintf;

/**
 * Test FacilityCoherenceQueriesApiTest.
 *
 * Exercises server parent selection, global breadcrumbs and exact equipment
 * scope totals on real PostgreSQL data beyond the former client page limits.
 *
 * @category Functional Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityCoherenceQueriesApiTest extends WebTestCase
{
  // #region Constants
  private const string ORGANIZATION = '970e8400-e29b-41d4-a716-446655470001';

  private const string USER = '970e8400-e29b-41d4-a716-446655470002';

  private const string OTHER_ORGANIZATION = '970e8400-e29b-41d4-a716-446655470003';
  // #endregion

  // #region Methods
  #[Test]
  public function interventionParentsAreScopedPaginatedAndRetainDraftMetadata(): void
  {
    $client = $this->client(['organization.facilities.read', 'organization.interventions.plan']);
    $intervention = $this->intervention(8000);
    $other = $this->intervention(8001);
    for ($index = 0; $index < 205; ++$index) {
      $draft = $this->facility(3000 + $index, 'site', sprintf('DraftParent %03d', $index));
      $draft->recordStatus = 'draft';
      $draft->interventionId = $intervention->id;
    }
    $draftSite = $this->manager()->find(FacilityRecord::class, $this->id(3000));
    self::assertInstanceOf(FacilityRecord::class, $draftSite);
    $draftBuilding = $this->facility(70, 'building', 'Draft building', $draftSite);
    $draftBuilding->recordStatus = 'draft';
    $draftBuilding->interventionId = $intervention->id;
    $foreignDraft = $this->facility(71, 'site', 'DraftParent another intervention');
    $foreignDraft->recordStatus = 'draft';
    $foreignDraft->interventionId = $other->id;
    $this->manager()->flush();
    $this->manager()->getConnection()->executeStatement('UPDATE facilities SET revision = 7 WHERE organization_id = :organizationId AND record_status = :state AND intervention_id = :interventionId', ['organizationId' => self::ORGANIZATION, 'state' => 'draft', 'interventionId' => $intervention->id]);
    $url = $this->collection() . '?interventionId=' . $intervention->id . '&parentForType=building&search=DraftParent&itemsPerPage=200&includePath=true';
    $this->request($client, $url);
    $first = $this->collectionBody($client);
    self::assertSame(205, $first['totalItems']);
    self::assertCount(200, $first['member']);
    self::assertSame('draft', $first['member'][0]['recordStatus']);
    self::assertSame(7, $first['member'][0]['revision']);
    self::assertSame('/api/interventions/' . $intervention->id, $first['member'][0]['intervention'] ?? null);
    $this->request($client, $url . '&page=2');
    self::assertCount(5, $this->collectionBody($client)['member']);
    $this->request($client, $this->collection() . '?interventionId=' . $intervention->id . '&parentForType=floor&includePath=true');
    $floors = $this->collectionBody($client);
    self::assertSame([$this->id(3000)], array_column(array_column($floors['member'], 'path', 'id')[$draftBuilding->id], 'id'));
    self::assertContains($this->id(20), array_column($floors['member'], 'id'));
    $this->request($client, $this->collection() . '?interventionId=' . $intervention->id . '&parentForFacilityId=' . $draftBuilding->id . '&itemsPerPage=200');
    self::assertSame(206, $this->collectionBody($client)['totalItems']);
    $this->request($client, $this->collection() . '?interventionId=' . $intervention->id . '&parentForFacilityId=' . $foreignDraft->id);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function interventionPlanningAccessAloneOnlyReadsItsDraftParentsAndDraftAncestors(): void
  {
    $client = $this->client(['organization.interventions.plan']);
    $intervention = $this->intervention(8000);
    $publishedSite = $this->manager()->find(FacilityRecord::class, $this->id(10));
    self::assertInstanceOf(FacilityRecord::class, $publishedSite);
    $draft = $this->facility(70, 'building', 'Preparation building', $publishedSite);
    $draft->recordStatus = 'draft';
    $draft->interventionId = $intervention->id;
    $this->manager()->flush();
    $this->request($client, $this->collection() . '?interventionId=' . $intervention->id . '&parentForType=floor&includePath=true');
    $body = $this->collectionBody($client);
    self::assertSame(1, $body['totalItems']);
    self::assertSame($draft->id, $body['member'][0]['id']);
    self::assertSame([], $body['member'][0]['path']);
    $this->request($client, $this->collection() . '?interventionId=' . $intervention->id . '&parentForFacilityId=' . $this->id(20));
    self::assertSame(404, $client->getResponse()->getStatusCode());
    $this->request($client, $this->collection());
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function interventionParentPreparationRejectsMalformedForeignAndUnauthorizedContexts(): void
  {
    $client = $this->client(['organization.facilities.read']);
    $intervention = $this->intervention(8000);
    $foreign = $this->intervention(8001, organizationId: self::OTHER_ORGANIZATION);
    $this->manager()->flush();
    foreach (['interventionId=invalid&parentForType=building', 'interventionId=' . $intervention->id] as $query) {
      $this->request($client, $this->collection() . '?' . $query);
      self::assertSame(400, $client->getResponse()->getStatusCode());
    }
    $this->request($client, $this->collection() . '?parentForType=building&interventionId=' . $foreign->id);
    self::assertSame(404, $client->getResponse()->getStatusCode());
    $this->request($client, $this->collection() . '?parentForType=building&interventionId=' . $intervention->id);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function executionParentsRequireParticipationAndMutableIntervention(): void
  {
    $client = $this->client(['organization.facilities.read', 'organization.interventions.execute']);
    $intervention = $this->intervention(8000, 'in_progress');
    $this->manager()->flush();
    $url = $this->collection() . '?parentForType=building&interventionId=' . $intervention->id;
    $this->request($client, $url);
    self::assertSame(403, $client->getResponse()->getStatusCode());
    $record = $this->manager()->find(InterventionRecord::class, $intervention->id);
    self::assertInstanceOf(InterventionRecord::class, $record);
    $record->participants = [$this->id(901)];
    $this->manager()->flush();
    $this->request($client, $url);
    self::assertSame(1, $this->collectionBody($client)['totalItems']);
    $record = $this->manager()->find(InterventionRecord::class, $intervention->id);
    self::assertInstanceOf(InterventionRecord::class, $record);
    $record->status = 'published';
    $this->manager()->flush();
    $this->request($client, $url);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function everyCollectionReturnsTheCurrentRevisionNeededToMove(): void
  {
    $client = $this->client(['organization.facilities.read', 'organization.facilities.write']);
    $room = $this->manager()->find(FacilityRecord::class, $this->id(40));
    self::assertInstanceOf(FacilityRecord::class, $room);
    $this->manager()->getConnection()->executeStatement('UPDATE facilities SET revision = 9 WHERE id = :id', ['id' => $room->id]);
    foreach ([$this->collection(), $this->collection() . '/' . $this->id(30) . '/children', $this->collection() . '/' . $this->id(20) . '/descendants?pagination=true', '/api/facilities?organization=/api/organizations/' . self::ORGANIZATION . '&includePath=true'] as $url) {
      $this->request($client, $url);
      self::assertSame(9, array_column($this->collectionBody($client)['member'], 'revision', 'id')[$room->id]);
    }
    static::ensureKernelShutdown();
    $client = static::createClient();
    $client->loginUser(new SecurityUser(self::USER, 'coherence-queries@example.com', 'hashed-password', ['ROLE_USER']), 'api');
    $client->request('POST', $this->collection() . '/' . $room->id . '/move', server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-9"'], content: (string) json_encode(['parentFacilityId' => $this->id(21)]));
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(10, $this->body($client)['revision']);
  }

  #[Test]
  public function summaryRequiresAuthentication(): void
  {
    $client = static::createClient();
    $client->request('GET', $this->collection() . '/' . $this->id(20) . '/equipment-summary');
    self::assertSame(401, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function branchesRemainPaginatedBeyondOneHundred(): void
  {
    $client = $this->client();
    $floor = $this->manager()->find(FacilityRecord::class, $this->id(30));
    self::assertInstanceOf(FacilityRecord::class, $floor);
    for ($index = 0; $index < 205; ++$index) {
      $this->facility(1000 + $index, 'area', sprintf('Paginated branch %03d', $index), $floor);
    }
    $this->manager()->flush();
    $url = $this->collection() . '/' . $this->id(30) . '/children?search=Paginated&itemsPerPage=100&includePath=true';
    $this->request($client, $url);
    $first = $this->collectionBody($client);
    self::assertSame(205, $first['totalItems']);
    self::assertCount(100, $first['member']);
    $this->request($client, $url . '&page=3');
    $last = $this->collectionBody($client);
    self::assertSame(205, $last['totalItems']);
    self::assertCount(5, $last['member']);
    self::assertSame([$this->id(10), $this->id(20), $this->id(30)], array_column($last['member'][0]['path'], 'id'));
  }

  #[Test]
  public function eligibleParentsRemainSearchableAndPaginatedBeyondTwoHundred(): void
  {
    $client = $this->client();
    $this->seedCandidates();
    $this->request($client, $this->collection() . '?parentForType=building&search=Candidate&itemsPerPage=200&includePath=true');
    $body = $this->collectionBody($client);
    self::assertSame(205, $body['totalItems']);
    self::assertCount(200, $body['member']);
    $this->request($client, $this->collection() . '?parentForType=building&search=Candidate&itemsPerPage=200&page=2&includePath=true');
    $body = $this->collectionBody($client);
    self::assertSame(205, $body['totalItems']);
    self::assertCount(5, $body['member']);
    foreach ($body['member'] as $facility) {
      self::assertSame('site', $facility['type']);
      self::assertSame([], $facility['path']);
    }
    $this->request($client, $this->collection() . '?parentForType=site');
    self::assertSame(0, $this->collectionBody($client)['totalItems']);
  }

  #[Test]
  public function moveCandidatesExcludeItsSubtreeAndHistoricalInvalidAncestry(): void
  {
    $client = $this->client();
    $this->request($client, $this->collection() . '?parentForFacilityId=' . $this->id(40) . '&includeArchived=true&itemsPerPage=200');
    $body = $this->collectionBody($client);
    $ids = array_column($body['member'], 'id');
    self::assertContains($this->id(10), $ids);
    self::assertContains($this->id(20), $ids);
    self::assertNotContains($this->id(40), $ids);
    self::assertNotContains($this->id(41), $ids);
    self::assertNotContains($this->id(50), $ids);
    self::assertNotContains($this->id(51), $ids);
    self::assertNotContains($this->id(52), $ids);
    self::assertNotContains($this->id(53), $ids);
  }

  #[Test]
  public function globalSearchAndBothTreeCollectionsReturnUnambiguousPaths(): void
  {
    $client = $this->client();
    $this->request($client, $this->collection() . '?search=Room&includePath=true');
    $body = $this->collectionBody($client);
    self::assertSame(2, $body['totalItems']);
    $paths = array_column($body['member'], 'path', 'id');
    self::assertSame([$this->id(10), $this->id(20), $this->id(30)], array_column($paths[$this->id(40)], 'id'));
    self::assertSame([$this->id(10), $this->id(21)], array_column($paths[$this->id(42)], 'id'));
    $this->request($client, $this->collection() . '/' . $this->id(30) . '/children?includePath=true');
    $body = $this->collectionBody($client);
    self::assertSame([$this->id(10), $this->id(20), $this->id(30)], array_column($body['member'][0]['path'], 'id'));
    $this->request($client, $this->collection() . '/' . $this->id(20) . '/descendants?includePath=true&pagination=true&search=Room');
    $body = $this->collectionBody($client);
    self::assertSame([$this->id(10), $this->id(20), $this->id(30)], array_column($body['member'][0]['path'], 'id'));
    $this->request($client, $this->collection() . '?search=Room');
    self::assertSame([], $this->collectionBody($client)['member'][0]['path']);
    $this->request($client, '/api/facilities?organization=/api/organizations/' . self::ORGANIZATION . '&includePath=true');
    $body = $this->collectionBody($client);
    $paths = array_column($body['member'], 'path', 'id');
    self::assertSame([$this->id(10), $this->id(20), $this->id(30)], array_column($paths[$this->id(40)], 'id'));
  }

  #[Test]
  public function parentFiltersAreExclusiveAndUnknownOrForeignMoveRootsAreMasked(): void
  {
    $client = $this->client();
    foreach (['parentForType=invalid', 'parentForFacilityId=invalid', 'parentForType=zone&parentForFacilityId=' . $this->id(40), 'includePath=invalid'] as $query) {
      $this->request($client, $this->collection() . '?' . $query);
      self::assertSame(400, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }
    foreach ([$this->id(54), $this->id(99999)] as $id) {
      $this->request($client, $this->collection() . '?parentForFacilityId=' . $id);
      self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }
  }

  #[Test]
  public function summaryMatchesThePublishedCollectionBeyondTwoHundredAndAcrossDescendants(): void
  {
    $client = $this->client();
    $this->seedEquipment();
    $url = $this->collection() . '/' . $this->id(20);
    $this->request($client, $url . '/equipment-summary');
    $summary = $this->summaryBody($client);
    self::assertSame('subtree', $summary['scope']);
    self::assertSame(208, $summary['totalItems']);
    self::assertSame(['in_stock' => 1, 'operational' => 205, 'under_maintenance' => 1, 'decommissioned' => 1], $summary['byStatus']);
    self::assertSame(2, $summary['needingAttentionCount']);
    $this->request($client, $url . '/equipment?includeDescendants=true&itemsPerPage=100');
    $page = $this->equipmentCollectionBody($client);
    self::assertSame($summary['totalItems'], $page['totalItems']);
    self::assertCount(100, $page['member']);
    $this->request($client, $url . '/equipment-summary?includeDescendants=false');
    $summary = $this->summaryBody($client);
    self::assertSame('direct', $summary['scope']);
    self::assertSame(1, $summary['totalItems']);
    self::assertSame(['in_stock' => 0, 'operational' => 0, 'under_maintenance' => 1, 'decommissioned' => 0], $summary['byStatus']);
    $this->request($client, $url . '/equipment');
    self::assertSame($summary['totalItems'], $this->equipmentCollectionBody($client)['totalItems']);
  }

  #[Test]
  public function summaryRespectsSeparateEquipmentPermissionAndMasksForeignFacilities(): void
  {
    $client = $this->client(['organization.equipment.read']);
    $this->request($client, $this->collection() . '/' . $this->id(20) . '/equipment-summary');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    foreach ([$this->id(54), $this->id(99999)] as $facilityId) {
      $this->request($client, $this->collection() . '/' . $facilityId . '/equipment-summary');
      self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }
    $this->request($client, '/api/organizations/' . self::OTHER_ORGANIZATION . '/facilities/' . $this->id(54) . '/equipment-summary');
    self::assertSame(404, $client->getResponse()->getStatusCode());
    $this->request($client, $this->collection() . '/' . $this->id(20) . '/equipment-summary?includeDescendants=invalid');
    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function facilitiesReadDoesNotGrantSummaryAccess(): void
  {
    $client = $this->client(['organization.facilities.read']);
    $this->request($client, $this->collection() . '/' . $this->id(20) . '/equipment-summary');
    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  private function request(KernelBrowser &$client, string $url): void
  {
    static::ensureKernelShutdown();
    $client = static::createClient();
    $client->loginUser(new SecurityUser(self::USER, 'coherence-queries@example.com', 'hashed-password', ['ROLE_USER']), 'api');
    $client->request('GET', $url);
  }

  /**
   * @return array<string, mixed>
   */
  private function body(KernelBrowser $client): array
  {
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $decoded = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($decoded);
    /** @var array<string, mixed> $body */
    $body = $decoded;

    return $body;
  }

  /**
   * @return array{totalItems: int, member: list<array{id: string, type: string, recordStatus: string, revision: int, intervention?: string, path: list<array{id: string, name: string, type: string}>}>}
   */
  private function collectionBody(KernelBrowser $client): array
  {
    /** @var array{totalItems: int, member: list<array{id: string, type: string, recordStatus: string, revision: int, intervention?: string, path: list<array{id: string, name: string, type: string}>}>} $body */
    $body = $this->body($client);

    return $body;
  }

  /**
   * @return array{totalItems: int, member: list<array<string, mixed>>}
   */
  private function equipmentCollectionBody(KernelBrowser $client): array
  {
    /** @var array{totalItems: int, member: list<array<string, mixed>>} $body */
    $body = $this->body($client);

    return $body;
  }

  /**
   * @return array{scope: string, totalItems: int, byStatus: array{in_stock: int, operational: int, under_maintenance: int, decommissioned: int}, needingAttentionCount: int}
   */
  private function summaryBody(KernelBrowser $client): array
  {
    /** @var array{scope: string, totalItems: int, byStatus: array{in_stock: int, operational: int, under_maintenance: int, decommissioned: int}, needingAttentionCount: int} $body */
    $body = $this->body($client);

    return $body;
  }

  private function id(int $suffix): string
  {
    return sprintf('970e8400-e29b-41d4-a716-%012d', $suffix);
  }

  private function collection(): string
  {
    return '/api/organizations/' . self::ORGANIZATION . '/facilities';
  }

  private function manager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface */
    return static::getContainer()->get('doctrine.orm.main_entity_manager');
  }

  /**
   * @param list<string> $permissions
   */
  private function client(array $permissions = ['organization.facilities.read', 'organization.equipment.read']): KernelBrowser
  {
    $client = static::createClient();
    $manager = $this->manager();
    foreach ([self::ORGANIZATION, self::OTHER_ORGANIZATION] as $organizationId) {
      $existing = $manager->find(OrganizationRecord::class, $organizationId);
      if (null !== $existing) {
        $manager->remove($existing);
        $manager->flush();
      }
    }
    $now = new DateTimeImmutable('2026-10-03T12:00:00+00:00');
    foreach ([self::ORGANIZATION, self::OTHER_ORGANIZATION] as $id) {
      $organization = new OrganizationRecord();
      $organization->id = $id;
      $organization->name = 'Coherent locations';
      $organization->slug = 'coherent-locations-' . $id;
      $organization->ownerUserId = $this->id(900);
      $organization->createdByUserId = $this->id(900);
      $organization->createdAt = $now;
      $organization->updatedAt = $now;
      $manager->persist($organization);
    }
    $organization = $manager->find(OrganizationRecord::class, self::ORGANIZATION);
    self::assertInstanceOf(OrganizationRecord::class, $organization);
    $member = new OrganizationMemberRecord();
    $member->id = $this->id(901);
    $member->organization = $organization;
    $member->userId = self::USER;
    $member->isActive = true;
    $member->joinedAt = $now;
    $manager->persist($member);
    $role = new OrganizationRoleRecord();
    $role->id = $this->id(902);
    $role->organization = $organization;
    $role->name = 'coherence-query-role';
    $role->permissions = $permissions;
    $role->createdAt = $now;
    $manager->persist($role);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $manager->persist($assignment);
    $manager->flush();
    $site = $this->facility(10, 'site', 'Campus West');
    $building = $this->facility(20, 'building', 'Building A', $site);
    $otherBuilding = $this->facility(21, 'building', 'Building B', $site);
    $floor = $this->facility(30, 'floor', 'Ground floor', $building);
    $room = $this->facility(40, 'zone', 'Room', $floor);
    $this->facility(41, 'area', 'Shelf', $room);
    $this->facility(42, 'zone', 'Room', $otherBuilding);
    $invalidRoot = $this->facility(50, 'building', 'Historical root building');
    $this->facility(51, 'zone', 'Historical orphan zone', $invalidRoot);
    $this->facility(52, 'site', 'Archived root')->status = 'archived';
    $this->facility(53, 'site', 'Draft root')->recordStatus = 'draft';
    $foreign = $this->facility(54, 'site', 'Foreign root');
    $foreign->organization = $manager->getReference(OrganizationRecord::class, self::OTHER_ORGANIZATION);
    $manager->flush();
    $client->loginUser(new SecurityUser(self::USER, 'coherence-queries@example.com', 'hashed-password', ['ROLE_USER']), 'api');

    return $client;
  }

  private function facility(int $suffix, string $type, string $name, ?FacilityRecord $parent = null): FacilityRecord
  {
    $record = new FacilityRecord();
    $record->id = $this->id($suffix);
    $record->organization = $this->manager()->getReference(OrganizationRecord::class, self::ORGANIZATION);
    $record->type = $type;
    $record->name = $name;
    $record->parentFacility = $parent;
    $record->createdAt = new DateTimeImmutable('2026-10-03');
    $record->updatedAt = $record->createdAt;
    $this->manager()->persist($record);

    return $record;
  }

  private function intervention(int $suffix, string $status = 'draft', string $organizationId = self::ORGANIZATION): InterventionRecord
  {
    $record = new InterventionRecord();
    $record->id = $this->id($suffix);
    $record->organization = $this->manager()->getReference(OrganizationRecord::class, $organizationId);
    $record->name = 'Prepare hierarchy';
    $record->number = $suffix;
    $record->status = $status;
    $record->createdAt = new DateTimeImmutable('2026-10-03');
    $record->updatedAt = $record->createdAt;
    $this->manager()->persist($record);

    return $record;
  }

  private function seedCandidates(): void
  {
    for ($index = 0; $index < 205; ++$index) {
      $this->facility(1000 + $index, 'site', sprintf('Candidate Parent %03d', $index));
    }
    $this->manager()->flush();
  }

  private function seedEquipment(): void
  {
    for ($index = 0; $index < 209; ++$index) {
      $record = new EquipmentRecord();
      $record->id = $this->id(5000 + $index);
      $record->organization = $this->manager()->getReference(OrganizationRecord::class, self::ORGANIZATION);
      $record->type = 'fire_extinguisher';
      $record->facilityId = $this->id(205 === $index ? 20 : 40);
      $record->status = match ($index) {
        205 => 'under_maintenance',
        206 => 'decommissioned',
        207 => 'in_stock',
        default => 'operational',
      };
      $record->recordStatus = 208 === $index ? 'draft' : 'published';
      $record->createdAt = new DateTimeImmutable('2026-10-03');
      $record->updatedAt = $record->createdAt;
      $this->manager()->persist($record);
    }
    $this->manager()->flush();
  }
  // #endregion
}
