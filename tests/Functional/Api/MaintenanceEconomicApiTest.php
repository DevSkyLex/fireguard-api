<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Customer\Infrastructure\Persistence\Doctrine\Record\CustomerRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, PublicationRecord};
use Inventory\Infrastructure\Persistence\Doctrine\Record\InventoryMovementRecord;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostPlanning, MaintenanceCostSnapshot, MaintenanceCostTotals};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCostReadPort;
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Infrastructure\Persistence\Doctrine\Record\MaintenanceExpenseRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class MaintenanceEconomicApiTest
 *
 * Proves exact private report contracts and independent financial authorization.
 *
 * @category Test
 */
final class MaintenanceEconomicApiTest extends WebTestCase
{
  private const string ORG = '850e8400-e29b-41d4-a716-448040000001';

  private const string OWNER = '850e8400-e29b-41d4-a716-448040000002';

  private const string READER = '850e8400-e29b-41d4-a716-448040000003';

  private const string USER = '850e8400-e29b-41d4-a716-448040000004';

  private const string WORK = '850e8400-e29b-41d4-a716-448040000020';

  #[Test]
  public function financeReaderCanReadExactGlobalCostsWithoutOperationalPermissions(): void
  {
    $client = $this->client();
    $this->expense('850e8400-e29b-41d4-a716-448040000030', '12.123456');
    $body = $this->get($client, '/reports?from=2026-10-01&to=2026-10-31&groupBy=equipment');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('EUR', $body['currency']);
    self::assertSame(1, $body['interventionCount']);
    self::assertSame(0, $body['totalItems']);
    self::assertIsArray($body['current']);
    self::assertSame('12.123456', $body['current']['total']);
    self::assertIsArray($body['unallocated']);
    self::assertNull($body['unallocated']['id']);
    self::assertIsArray($body['unallocated']['current']);
    self::assertSame('12.123456', $body['unallocated']['current']['total']);
    self::assertIsArray($body['planned']);
    self::assertFalse($body['planned']['complete']);
    self::assertNull($body['variance']);
    self::assertIsArray($body['reconciliation']);
    self::assertTrue($body['reconciliation']['reconciled']);
    $client->request('GET', '/api/interventions/' . self::WORK, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function privateDirectoryKeepsOnlyMinimalLabelsAndUsesServerSearch(): void
  {
    $client = $this->client();
    $body = $this->get($client, '/dossiers?search=Economic&page=1&itemsPerPage=1');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $body['totalItems']);
    self::assertIsArray($body['member']);
    self::assertCount(1, $body['member']);
    self::assertIsArray($body['member'][0]);
    self::assertSame('Economic fire park repair', $body['member'][0]['name']);
    self::assertSame('live', $body['member'][0]['snapshotState']);
    foreach (['contacts', 'dossier', 'closureSnapshot', 'current', 'frozen', 'attachments', 'description'] as $key) {
      self::assertArrayNotHasKey($key, $body['member'][0]);
    }
    $empty = $this->get($client, '/dossiers?search=Absent');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(0, $empty['totalItems']);
    self::assertSame([], $empty['member']);
  }

  #[Test]
  public function missingHistoricPublicationIsExplicitlyIncomplete(): void
  {
    $client = $this->client();
    $work = $this->main()->find(InterventionRecord::class, self::WORK);
    self::assertInstanceOf(InterventionRecord::class, $work);
    $work->status = 'published';
    $publication = new PublicationRecord();
    $publication->id = '850e8400-e29b-41d4-a716-448040000091';
    $publication->intervention = $work;
    $publication->status = 'completed';
    $publication->interventionRevision = 1;
    $publication->createdAt = new DateTimeImmutable('2026-10-07T14:00:00Z');
    $publication->completedAt = new DateTimeImmutable('2026-10-07T15:00:00Z');
    $this->main()->persist($publication);
    $this->main()->flush();
    $body = $this->get($client, '/reports?from=2026-10-01&to=2026-10-31');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $body['missingSnapshotCount']);
    self::assertSame(1, $body['publishedInterventionCount']);
    self::assertIsArray($body['frozen']);
    self::assertNull($body['frozen']['total']);
    self::assertFalse($body['frozen']['complete']);
    self::assertSame('0.000000', $body['frozen']['knownTotal']);
  }

  #[Test]
  public function laterFactsChangeCurrentTotalWithoutReplacingPrivatePublication(): void
  {
    $client = $this->client();
    $this->expense('850e8400-e29b-41d4-a716-448040000030', '50.000001');
    $item = new MaintenanceCostItem('expense:850e8400-e29b-41d4-a716-448040000030', 'expense', null, '850e8400-e29b-41d4-a716-448040000030', null, '50.000001', 'EUR', 'External repair', '2026-10-07T12:00:00+00:00');
    $store = self::getContainer()->get(MaintenanceCostStorePort::class);
    self::assertInstanceOf(MaintenanceCostStorePort::class, $store);
    $store->saveSnapshot(self::ORG, self::WORK, new MaintenanceCostSnapshot(1, '2026-10-07T15:00:00Z', '850e8400-e29b-41d4-a716-448040000090', 1, 'EUR', new MaintenanceCostTotals('50.000001', '50.000001', true, [$item]), new MaintenanceCostPlanning('100.000000')));
    $this->expense('850e8400-e29b-41d4-a716-448040000031', '20.000002');
    $body = $this->get($client, '/reports?from=2026-10-01&to=2026-10-31');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertIsArray($body['current']);
    self::assertSame('70.000003', $body['current']['total']);
    self::assertIsArray($body['frozen']);
    self::assertSame('50.000001', $body['frozen']['total']);
    self::assertSame('50.000001', $store->snapshot(self::ORG, self::WORK)?->totals->total);
  }

  /**
   * @return iterable<string,array{string,string,string,string}>
   */
  public static function directMaterialFilters(): iterable
  {
    yield 'asset without work item' => ['equipment', 'equipmentId', '850e8400-e29b-41d4-a716-448040000061', 'Directly maintained asset'];
    yield 'actual asset root site' => ['site', 'siteId', '850e8400-e29b-41d4-a716-448040000062', 'Direct material site'];
    yield 'actual asset internal client' => ['customer', 'customerId', '850e8400-e29b-41d4-a716-448040000063', 'Direct material client'];
  }

  #[Test]
  #[DataProvider('directMaterialFilters')]
  public function directMaterialWithoutOperationalTaskRemainsInEveryApplicableFinancialFilter(string $group, string $filter, string $id, string $name): void
  {
    $client = $this->client();
    $this->directMaterial();
    $unrelated = new InterventionRecord();
    $unrelated->id = '850e8400-e29b-41d4-a716-448040000065';
    $unrelated->organization = $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $unrelated->number = 2;
    $unrelated->name = 'Unrelated global repair';
    $unrelated->type = 'corrective_maintenance';
    $unrelated->status = 'draft';
    $unrelated->createdAt = $unrelated->updatedAt = new DateTimeImmutable('2026-10-07T12:00:00Z');
    $this->main()->persist($unrelated);
    $this->main()->flush();
    $this->expense('850e8400-e29b-41d4-a716-448040000066', '7.000000', $unrelated->id);
    $body = $this->get($client, '/reports?from=2026-10-01&to=2026-10-31&groupBy=' . $group . '&' . $filter . '=' . $id);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $body['interventionCount']);
    self::assertIsArray($body['current']);
    self::assertSame('10.625000', $body['current']['total']);
    self::assertIsArray($body['rows']);
    self::assertCount(1, $body['rows']);
    self::assertIsArray($body['rows'][0]);
    self::assertSame($id, $body['rows'][0]['id']);
    self::assertSame($name, $body['rows'][0]['name']);
    self::assertIsArray($body['rows'][0]['current']);
    self::assertSame('10.625000', $body['rows'][0]['current']['total']);
    self::assertIsArray($body['reconciliation']);
    self::assertTrue($body['reconciliation']['reconciled']);
  }

  #[Test]
  #[DataProvider('directMaterialFilters')]
  public function directMaterialFinancialDirectoryHasMinimalNamedTargetsAndExactFilteredPages(string $group, string $filter, string $id, string $name): void
  {
    $client = $this->client();
    $this->directMaterial();
    $directory = $this->get($client, '/dossiers?search=Economic&from=2026-10-01&to=2026-10-31&itemsPerPage=1&' . $filter . '=' . $id);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $directory['totalItems']);
    self::assertIsArray($directory['member']);
    self::assertCount(1, $directory['member']);
    self::assertIsArray($directory['member'][0]);
    self::assertSame(self::WORK, $directory['member'][0]['id']);
    self::assertIsArray($directory['member'][0]['equipment']);
    self::assertCount(1, $directory['member'][0]['equipment']);
    self::assertIsArray($directory['member'][0]['equipment'][0]);
    $equipment = $directory['member'][0]['equipment'][0];
    self::assertSame('850e8400-e29b-41d4-a716-448040000061', $equipment['id']);
    self::assertSame('Directly maintained asset', $equipment['name']);
    if ('equipment' !== $group) {
      self::assertIsArray($equipment[$group]);
      self::assertSame($id, $equipment[$group]['id']);
      self::assertSame($name, $equipment[$group]['name']);
    }
    foreach (['current', 'frozen', 'amount', 'description', 'contacts'] as $key) {
      self::assertArrayNotHasKey($key, $directory['member'][0]);
      self::assertArrayNotHasKey($key, $equipment);
    }
    $next = $this->get($client, '/dossiers?page=2&itemsPerPage=1&' . $filter . '=' . $id);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(1, $next['totalItems']);
    self::assertSame([], $next['member']);
    foreach (['search=Absent', 'from=2026-09-01&to=2026-09-30'] as $otherFilter) {
      $empty = $this->get($client, '/dossiers?' . $otherFilter . '&' . $filter . '=' . $id);
      self::assertSame(200, $client->getResponse()->getStatusCode());
      self::assertSame(0, $empty['totalItems']);
      self::assertSame([], $empty['member']);
    }
    $all = $this->get($client, '/dossiers');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertIsArray($all['member']);
    self::assertIsArray($all['member'][0]);
    self::assertSame([$equipment], $all['member'][0]['equipment']);
  }

  #[Test]
  public function frozenDirectMaterialDirectoryRetainsCapturedTargetsAfterTheEquipmentMoves(): void
  {
    $client = $this->client();
    $this->directMaterial();
    $costs = self::getContainer()->get(MaintenanceCostReadPort::class);
    self::assertInstanceOf(MaintenanceCostReadPort::class, $costs);
    $current = $costs->view(self::ORG, self::WORK);
    $store = self::getContainer()->get(MaintenanceCostStorePort::class);
    self::assertInstanceOf(MaintenanceCostStorePort::class, $store);
    $store->saveSnapshot(self::ORG, self::WORK, new MaintenanceCostSnapshot(1, '2026-10-07T15:00:00Z', '850e8400-e29b-41d4-a716-448040000091', 1, 'EUR', $current->current, $current->planning));
    $this->publishWithoutOperationalSnapshot();
    $equipment = $this->main()->find(EquipmentRecord::class, '850e8400-e29b-41d4-a716-448040000061');
    self::assertInstanceOf(EquipmentRecord::class, $equipment);
    $equipment->facilityId = null;
    $equipment->name = 'Moved and renamed asset';
    $this->main()->flush();
    foreach (['siteId=850e8400-e29b-41d4-a716-448040000062', 'customerId=850e8400-e29b-41d4-a716-448040000063', 'equipmentId=850e8400-e29b-41d4-a716-448040000061'] as $filter) {
      $directory = $this->get($client, '/dossiers?' . $filter);
      self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
      self::assertSame(1, $directory['totalItems']);
      self::assertIsArray($directory['member']);
      self::assertIsArray($directory['member'][0]);
      self::assertIsArray($directory['member'][0]['equipment']);
      self::assertIsArray($directory['member'][0]['equipment'][0]);
      self::assertSame('Directly maintained asset', $directory['member'][0]['equipment'][0]['name']);
      self::assertIsArray($directory['member'][0]['equipment'][0]['site']);
      self::assertSame('Direct material site', $directory['member'][0]['equipment'][0]['site']['name']);
    }
    $report = $this->get($client, '/reports?from=2026-10-01&to=2026-10-31&groupBy=site&siteId=850e8400-e29b-41d4-a716-448040000062');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(1, $report['interventionCount']);
    self::assertIsArray($report['current']);
    self::assertIsArray($report['frozen']);
    self::assertSame('10.625000', $report['current']['total']);
    self::assertSame('10.625000', $report['frozen']['total']);
    self::assertSame($current->current->items[0]->allocation, $store->snapshot(self::ORG, self::WORK)?->totals->items[0]->allocation);
  }

  #[Test]
  public function historicalDirectMaterialWithNoFinancialSnapshotNeverAcquiresCurrentSiteOrClient(): void
  {
    $client = $this->client();
    $this->directMaterial();
    $this->publishWithoutOperationalSnapshot();
    $report = $this->get($client, '/reports?from=2026-10-01&to=2026-10-31&groupBy=site');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(0, $report['totalItems']);
    self::assertSame([], $report['rows']);
    self::assertIsArray($report['unallocated']);
    self::assertIsArray($report['unallocated']['current']);
    self::assertSame('10.625000', $report['unallocated']['current']['total']);
    $directory = $this->get($client, '/dossiers');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertIsArray($directory['member']);
    self::assertIsArray($directory['member'][0]);
    self::assertIsArray($directory['member'][0]['equipment']);
    self::assertIsArray($directory['member'][0]['equipment'][0]);
    self::assertNull($directory['member'][0]['equipment'][0]['name']);
    self::assertNull($directory['member'][0]['equipment'][0]['site']);
    self::assertNull($directory['member'][0]['equipment'][0]['customer']);
    foreach (['siteId=850e8400-e29b-41d4-a716-448040000062', 'customerId=850e8400-e29b-41d4-a716-448040000063'] as $filter) {
      $filtered = $this->get($client, '/dossiers?' . $filter);
      self::assertSame(200, $client->getResponse()->getStatusCode());
      self::assertSame(0, $filtered['totalItems']);
      self::assertSame([], $filtered['member']);
    }
  }

  /**
   * @return iterable<string,array{string}>
   */
  public static function routes(): iterable
  {
    yield 'report' => ['/reports?from=2026-10-01&to=2026-10-31'];
    yield 'directory' => ['/dossiers'];
  }

  #[Test]
  #[DataProvider('routes')]
  public function financialDenialPrecedesAllPrivateData(string $suffix): void
  {
    $client = $this->client(self::USER);
    $this->get($client, $suffix);
    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  #[DataProvider('routes')]
  public function organizationOutsideScopeHasNoExistenceOracle(string $suffix): void
  {
    $client = $this->client();
    $client->request('GET', '/api/organizations/850e8400-e29b-41d4-a716-448040000099/maintenance-cost' . $suffix, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * @return iterable<string,array{string}>
   */
  public static function invalidWindows(): iterable
  {
    yield 'invalid calendar day' => ['/reports?from=2026-02-30&to=2026-03-01'];
    yield 'reversed' => ['/reports?from=2026-10-31&to=2026-10-01'];
    yield 'too broad' => ['/reports?from=2025-01-01&to=2026-10-31'];
    yield 'unknown group' => ['/reports?from=2026-10-01&to=2026-10-31&groupBy=invoice'];
    yield 'missing second directory date' => ['/dossiers?from=2026-10-01'];
    yield 'oversized row page' => ['/reports?from=2026-10-01&to=2026-10-31&itemsPerPage=101'];
    yield 'invalid report equipment' => ['/reports?from=2026-10-01&to=2026-10-31&equipmentId=invalid'];
    yield 'invalid report site' => ['/reports?from=2026-10-01&to=2026-10-31&siteId=invalid'];
    yield 'invalid directory client' => ['/dossiers?customerId=invalid'];
  }

  #[Test]
  #[DataProvider('invalidWindows')]
  public function invalidReportsFailWithoutPartialTotals(string $suffix): void
  {
    $client = $this->client();
    $body = $this->get($client, $suffix);
    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertArrayNotHasKey('current', $body);
  }

  /**
   * @param non-empty-string $actor authenticated fixture principal
   */
  private function client(string $actor = self::READER): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (UserId $id) => UserTestFactory::createActive((string) $id, (string) $id . '@example.com'));
    self::getContainer()->set(UserRepositoryPort::class, $users);
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(self::getContainer(), $actor, $actor . '@example.com'));
    $now = new DateTimeImmutable('2026-10-07T12:00:00Z');
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Economic API';
    $org->slug = 'economic-api';
    $org->ownerUserId = self::OWNER;
    $org->createdByUserId = self::OWNER;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = $org->updatedAt = $now;
    $this->main()->persist($org);
    foreach ([self::READER, self::USER] as $index => $userId) {
      $member = new OrganizationMemberRecord();
      $member->id = '850e8400-e29b-41d4-a716-44804000000' . (5 + $index);
      $member->organization = $org;
      $member->userId = $userId;
      $member->joinedAt = $now;
      $member->isActive = true;
      $this->main()->persist($member);
      if (self::READER === $userId) {
        $role = new OrganizationRoleRecord();
        $role->id = '850e8400-e29b-41d4-a716-448040000007';
        $role->organization = $org;
        $role->name = 'Private finance only';
        $role->permissions = ['organization.maintenance_cost.read'];
        $role->isSystem = false;
        $role->createdAt = $now;
        $this->main()->persist($role);
        $assignment = new OrganizationMemberRoleRecord();
        $assignment->member = $member;
        $assignment->role = $role;
        $assignment->assignedAt = $now;
        $this->main()->persist($assignment);
      }
    }
    $work = new InterventionRecord();
    $work->id = self::WORK;
    $work->organization = $org;
    $work->name = 'Economic fire park repair';
    $work->number = 1;
    $work->type = 'corrective_maintenance';
    $work->status = 'draft';
    $work->createdAt = $work->updatedAt = $now;
    $this->main()->persist($work);
    $this->main()->flush();

    return $client;
  }

  private function expense(string $id, string $amount, string $workId = self::WORK): void
  {
    $record = new MaintenanceExpenseRecord();
    $record->id = $id;
    $record->organizationId = self::ORG;
    $record->interventionId = $workId;
    $record->clientId = $id;
    $record->amount = $amount;
    $record->currency = 'EUR';
    $record->description = 'External repair';
    $record->incurredAt = $record->createdAt = new DateTimeImmutable('2026-10-07T12:00:00Z');
    $record->createdBy = self::OWNER;
    $record->payloadHash = 'economic-fixture';
    $this->main()->persist($record);
    $this->main()->flush();
  }

  private function publishWithoutOperationalSnapshot(): void
  {
    $work = $this->main()->find(InterventionRecord::class, self::WORK);
    self::assertInstanceOf(InterventionRecord::class, $work);
    $work->status = 'published';
    $publication = new PublicationRecord();
    $publication->id = '850e8400-e29b-41d4-a716-448040000091';
    $publication->intervention = $work;
    $publication->status = 'completed';
    $publication->interventionRevision = 1;
    $publication->createdAt = new DateTimeImmutable('2026-10-07T14:00:00Z');
    $publication->completedAt = new DateTimeImmutable('2026-10-07T15:00:00Z');
    $this->main()->persist($publication);
    $this->main()->flush();
  }

  private function directMaterial(): void
  {
    $now = new DateTimeImmutable('2026-10-07T12:00:00Z');
    $customer = new CustomerRecord();
    $customer->id = '850e8400-e29b-41d4-a716-448040000063';
    $customer->organizationId = self::ORG;
    $customer->name = 'Direct material client';
    $customer->createdAt = $customer->updatedAt = $now;
    $this->main()->persist($customer);
    $site = new FacilityRecord();
    $site->id = '850e8400-e29b-41d4-a716-448040000062';
    $site->organization = $this->main()->getReference(OrganizationRecord::class, self::ORG);
    $site->type = 'site';
    $site->name = 'Direct material site';
    $site->customerId = $customer->id;
    $site->createdAt = $site->updatedAt = $now;
    $this->main()->persist($site);
    $equipment = new EquipmentRecord();
    $equipment->id = '850e8400-e29b-41d4-a716-448040000061';
    $equipment->organization = $site->organization;
    $equipment->facilityId = $site->id;
    $equipment->type = 'extinguisher';
    $equipment->name = 'Directly maintained asset';
    $equipment->createdAt = $equipment->updatedAt = $now;
    $this->main()->persist($equipment);
    $movement = new InventoryMovementRecord();
    $movement->id = '850e8400-e29b-41d4-a716-448040000064';
    $movement->organizationId = self::ORG;
    $movement->interventionId = self::WORK;
    $movement->equipmentId = $equipment->id;
    $movement->warehouseId = '850e8400-e29b-41d4-a716-448040000067';
    $movement->partId = '850e8400-e29b-41d4-a716-448040000068';
    $movement->kind = 'consumption';
    $movement->quantity = '-2.500000';
    $movement->unitCost = '4.250000';
    $movement->totalValue = '-10.625000';
    $movement->currency = 'EUR';
    $movement->reason = 'Direct asset maintenance';
    $movement->actorId = self::OWNER;
    $movement->occurredAt = $now;
    $this->main()->persist($movement);
    $this->main()->flush();
  }

  /**
   * @return array<string,mixed>
   */
  private function get(KernelBrowser $client, string $suffix): array
  {
    $client->request('GET', '/api/organizations/' . self::ORG . '/maintenance-cost' . $suffix, server: ['HTTP_ACCEPT' => 'application/ld+json']);
    /** @var array<string,mixed> $body */
    $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $body;
  }

  private function main(): EntityManagerInterface
  {
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager;
  }
}
