<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Inventory\Application\Contract\Stock\{InventoryReceiptRequest,InventoryReceiptReturnRequest};
use Inventory\Application\Port\Inbound\{InventoryInterventionResourcesPort, InventoryStockReceiptPort};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;
use function str_pad;
use function strtoupper;

use const JSON_THROW_ON_ERROR;
use const STR_PAD_LEFT;

/** PostgreSQL API contracts, replay, retained shortages, valuation masks and organization refusals. @category Functional Tests */
final class InventoryApiTest extends WebTestCase
{
  private const string ORG = 'beb10000-0000-4000-8000-000000000001';

  private const string USER = 'beb10000-0000-4000-8000-000000000002';

  private const string INTERVENTION = 'beb10000-0000-4000-8000-000000000003';

  private const string ROLE = 'beb10000-0000-4000-8000-000000000004';

  private int $operation = 100;

  #[Test]
  public function supplierReturnsAreValuedAtCurrentCumpWhileBoundedByOriginalQuantity(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $port = self::getContainer()->get(InventoryStockReceiptPort::class);
    self::assertInstanceOf(InventoryStockReceiptPort::class, $port);
    $original = $port->receive(new InventoryReceiptRequest(self::ORG, $part, $warehouse, '2', '1', 'EUR', $this->op(), $this->op(), self::USER));
    $port->receive(new InventoryReceiptRequest(self::ORG, $part, $warehouse, '2', '10', 'EUR', $this->op(), $this->op(), self::USER));
    $first = $port->reverse(new InventoryReceiptReturnRequest(self::ORG, $original->movementId, '1', 'Supplier return first', $this->op(), self::USER));
    $last = $port->reverse(new InventoryReceiptReturnRequest(self::ORG, $original->movementId, '1', 'Supplier return last', $this->op(), self::USER));
    self::assertSame('5.500000', $first->totalValue);
    self::assertSame('5.500000', $last->totalValue);
    $balance = $this->balance($client);
    self::assertSame('2.000000', $balance['quantity']);
    self::assertIsArray($balance['valuation']);
    self::assertSame('11.000000', $balance['valuation']['totalValue']);
  }

  #[Test]
  public function shortagesRemainPendingThenReconcileWithoutChangingTheReplaySnapshot(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $this->correct($client, $part, $warehouse, '1', '3');
    $payload = $this->consumption($part, $warehouse, '2');
    $pending = $this->request($client, 'POST', 'inventory-consumptions', $payload, 201);
    self::assertSame('received_pending', $pending['status']);
    self::assertSame('insufficient_stock', $pending['reason']);
    self::assertSame('2.000000', $pending['quantity']);
    self::assertSame('1.000000', $this->balance($client)['quantity']);
    $gate = self::getContainer()->get(InventoryInterventionResourcesPort::class);
    self::assertInstanceOf(InventoryInterventionResourcesPort::class, $gate);
    self::assertSame(['inventory_pending_declarations'], $gate->publicationBlockers(self::ORG, self::INTERVENTION));
    $this->correct($client, $part, $warehouse, '1', '5');
    self::assertIsString($pending['id']);
    $confirmed = $this->request($client, 'POST', 'inventory-consumptions/' . $pending['id'] . '/reconcile', [], 200);
    self::assertSame('confirmed', $confirmed['status']);
    self::assertSame('0.000000', $this->balance($client)['quantity']);
    self::assertSame([], $gate->publicationBlockers(self::ORG, self::INTERVENTION));
    $caseReplay = $payload;
    self::assertIsString($caseReplay['clientOperationId']);
    self::assertIsString($caseReplay['partId']);
    $caseReplay['clientOperationId'] = strtoupper($caseReplay['clientOperationId']);
    $caseReplay['partId'] = strtoupper($caseReplay['partId']);
    $replay = $this->request($client, 'POST', 'inventory-consumptions', $caseReplay, 201);
    self::assertTrue($replay['replayed']);
    self::assertSame('received_pending', $replay['status']);
    self::assertSame($pending['id'], $replay['id']);
    $current = $this->request($client, 'GET', 'inventory-consumptions/' . $pending['id']);
    self::assertSame('confirmed', $current['status']);
    $payload['quantity'] = '3';
    $this->request($client, 'POST', 'inventory-consumptions', $payload, 409);
  }

  #[Test]
  public function consumptionAndReturnsKeepMotivatedLinkedMovementsAndExactCost(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $this->correct($client, $part, $warehouse, '3', '1.666667');
    $consumed = $this->request($client, 'POST', 'inventory-consumptions', $this->consumption($part, $warehouse, '2'), 201);
    self::assertSame('confirmed', $consumed['status']);
    self::assertIsString($consumed['id']);
    $returned = $this->request($client, 'POST', 'inventory-returns', ['clientOperationId' => $this->op(), 'consumptionId' => $consumed['id'], 'quantity' => '1', 'reason' => 'Unused spare returned'], 201);
    self::assertSame('1.000000', $returned['quantity']);
    self::assertSame($consumed['movementId'], $returned['correctionOf']);
    self::assertSame('Unused spare returned', $returned['reason']);
    self::assertSame('2.000000', $this->balance($client)['quantity']);
    $this->request($client, 'POST', 'inventory-returns', ['clientOperationId' => $this->op(), 'consumptionId' => $consumed['id'], 'quantity' => '2', 'reason' => 'Too many'], 409);
    $facts = self::getContainer()->get(InventoryInterventionResourcesPort::class);
    self::assertInstanceOf(InventoryInterventionResourcesPort::class, $facts);
    $cost = $facts->costFacts(self::ORG, self::INTERVENTION);
    self::assertCount(2, $cost);
    self::assertSame('2.000000', $cost[0]->quantity);
    self::assertSame('-1.000000', $cost[1]->quantity);
    self::assertSame($cost[0]->factId, $cost[1]->correctionOf);
  }

  /**
   * Method repeatedTinyValueReturnsRestoreStockWithoutOvercreditAndReplayWithoutAnotherMovement
   *
   * Verifies exact return allocation and immutable replay through the PostgreSQL HTTP flow.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function repeatedTinyValueReturnsRestoreStockWithoutOvercreditAndReplayWithoutAnotherMovement(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $this->correct($client, $part, $warehouse, '2', '0.000001');
    $this->correct($client, $part, $warehouse, '2', '0');
    $consumed = $this->request($client, 'POST', 'inventory-consumptions', $this->consumption($part, $warehouse, '4'), 201);
    self::assertSame('confirmed', $consumed['status']);
    foreach (['0.000001', '0.000000', '0.000001', '0.000000'] as $value) {
      $payload = ['clientOperationId' => $this->op(), 'consumptionId' => $consumed['id'], 'quantity' => '1', 'reason' => 'Unused tiny-value part returned'];
      $returned = $this->request($client, 'POST', 'inventory-returns', $payload, 201);
      self::assertSame('1.000000', $returned['quantity']);
      self::assertSame($consumed['movementId'], $returned['correctionOf']);
      self::assertSame('Unused tiny-value part returned', $returned['reason']);
      self::assertIsArray($returned['valuation']);
      self::assertSame($value, $returned['valuation']['totalValue']);
      $replayed = $this->request($client, 'POST', 'inventory-returns', $payload, 201);
      self::assertTrue($replayed['replayed']);
      self::assertSame($returned['id'], $replayed['id']);
      self::assertIsArray($replayed['valuation']);
      foreach (['unitCost', 'totalValue', 'currency', 'incomplete'] as $field) {
        self::assertSame($returned['valuation'][$field], $replayed['valuation'][$field]);
      }
    }
    $balance = $this->balance($client);
    self::assertSame('4.000000', $balance['quantity']);
    self::assertIsArray($balance['valuation']);
    self::assertSame('0.000002', $balance['valuation']['totalValue']);
    $movements = $this->request($client, 'GET', 'inventory-movements?interventionId=' . self::INTERVENTION);
    self::assertSame(5, $movements['totalItems']);
    $this->request($client, 'POST', 'inventory-returns', ['clientOperationId' => $this->op(), 'consumptionId' => $consumed['id'], 'quantity' => '0.000001', 'reason' => 'Above original quantity'], 409);
    $unchanged = $this->balance($client);
    self::assertSame($balance['id'], $unchanged['id']);
    self::assertSame($balance['quantity'], $unchanged['quantity']);
    self::assertIsArray($unchanged['valuation']);
    self::assertSame($balance['valuation']['totalValue'], $unchanged['valuation']['totalValue']);
  }

  #[Test]
  public function unknownPurchaseValueRemainsIncompleteAndNeverBecomesZero(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $port = self::getContainer()->get(InventoryStockReceiptPort::class);
    self::assertInstanceOf(InventoryStockReceiptPort::class, $port);
    $port->receive(new InventoryReceiptRequest(self::ORG, $part, $warehouse, '2', null, 'EUR', $this->op(), $this->op(), self::USER));
    $balance = $this->balance($client);
    self::assertIsArray($balance['valuation']);
    self::assertTrue($balance['valuation']['incomplete']);
    self::assertArrayNotHasKey('totalValue', $balance['valuation']);
    $this->request($client, 'POST', 'inventory-consumptions', $this->consumption($part, $warehouse, '1'), 201);
    $facts = self::getContainer()->get(InventoryInterventionResourcesPort::class);
    self::assertInstanceOf(InventoryInterventionResourcesPort::class, $facts);
    $cost = $facts->costFacts(self::ORG, self::INTERVENTION);
    self::assertCount(1, $cost);
    self::assertNull($cost[0]->exactAmount);
  }

  #[Test]
  public function archivedReferencesRetainHistoryAndPhysicalDeclarations(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $this->correct($client, $part, $warehouse, '2', '4');
    $this->request($client, 'PATCH', 'inventory-parts/' . $part, ['archived' => true], 200);
    $declaration = $this->request($client, 'POST', 'inventory-consumptions', $this->consumption($part, $warehouse, '1'), 201);
    self::assertSame('received_pending', $declaration['status']);
    self::assertSame('archived_reference', $declaration['reason']);
    self::assertSame('2.000000', $this->balance($client)['quantity']);
    $partOutput = $this->request($client, 'GET', 'inventory-parts/' . $part);
    self::assertTrue($partOutput['archived']);
  }

  #[Test]
  public function internalValuesRequireDedicatedReadAndCorrectionsRequireDedicatedManage(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $this->correct($client, $part, $warehouse, '1', '17');
    $this->permissions(['organization.inventory.read', 'organization.inventory.consume', 'organization.interventions.execute']);
    self::assertArrayNotHasKey('valuation', $this->balance($client));
    $this->request($client, 'POST', 'inventory-corrections', ['clientOperationId' => $this->op(), 'partId' => $part, 'warehouseId' => $warehouse, 'quantity' => '1', 'reason' => 'Denied finance', 'unitCost' => '1'], 403);
    $consumed = $this->request($client, 'POST', 'inventory-consumptions', $this->consumption($part, $warehouse, '1'), 201);
    self::assertSame('confirmed', $consumed['status']);
    $this->permissions(['organization.inventory.consume']);
    $this->request($client, 'POST', 'inventory-consumptions', $this->consumption($part, $warehouse, '1'), 403);
    $this->request($client, 'GET', 'inventory-balances', null, 403);
  }

  #[Test]
  public function unknownAndForeignReferencesAndOrganizationsAreHidden(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $connection = $this->manager()->getConnection();
    $foreign = $this->op();
    $connection->insert('inventory_parts', ['id' => $foreign, 'organization_id' => $this->op(), 'code' => 'FOREIGN', 'label' => 'Foreign', 'unit' => 'piece', 'kind' => 'part', 'archived' => false], ['archived' => \Doctrine\DBAL\ParameterType::BOOLEAN]);
    foreach ([$foreign, $this->op()] as $id) {
      $this->request($client, 'GET', 'inventory-parts/' . $id, null, 404);
      $this->request($client, 'POST', 'inventory-consumptions', $this->consumption($id, $warehouse, '1'), 404);
    }
    $this->request($client, 'GET', 'inventory-balances?warehouseId=' . $this->op(), null, 404);
    $client->request('GET', '/api/organizations/' . $this->op() . '/inventory-parts', server:['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function lateDeclarationsChangeCurrentStockWithoutRewritingThePublishedDossier(): void
  {
    $client = $this->client();
    [$part,$warehouse] = $this->catalog($client);
    $this->correct($client, $part, $warehouse, '1', '2');
    $manager = $this->manager();
    $intervention = $manager->find(InterventionRecord::class, self::INTERVENTION);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    $intervention->status = 'published';
    $intervention->closureSnapshot = ['version' => 1, 'original' => 'preserve'];
    $manager->flush();
    $declaration = $this->request($client, 'POST', 'inventory-consumptions', $this->consumption($part, $warehouse, '1'), 201);
    self::assertTrue($declaration['late']);
    self::assertSame('confirmed', $declaration['status']);
    $manager->clear();
    $stored = $manager->find(InterventionRecord::class, self::INTERVENTION);
    self::assertInstanceOf(InterventionRecord::class, $stored);
    self::assertSame(['version' => 1, 'original' => 'preserve'], $stored->closureSnapshot);
  }

  #[Test]
  public function searchArchiveAndPaginationCountTheSameServerScope(): void
  {
    $client = $this->client();
    foreach (['A', 'B', 'C'] as $code) {
      $this->request($client, 'POST', 'inventory-parts', ['code' => $code, 'label' => 'Filter ' . $code, 'unit' => 'piece', 'kind' => 'part'], 201);
    }
    $page = $this->request($client, 'GET', 'inventory-parts?search=Filter&archived=false&itemsPerPage=2&page=2');
    self::assertSame(3, $page['totalItems']);
    self::assertIsArray($page['member']);
    self::assertCount(1, $page['member']);
    $literal = $this->request($client, 'GET', 'inventory-parts?search=%25');
    self::assertSame(0, $literal['totalItems']);
    $this->request($client, 'GET', 'inventory-parts?archived=invalid', null, 422);
  }

  /**
   * @param list<string> $permissions
   */
  private function client(array $permissions = ['*']): KernelBrowser
  {
    $client = self::createClient();
    $client->disableReboot();
    $users = $this->createStub(\User\Application\Port\Outbound\UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (\User\Domain\ValueObject\UserId $id) => \Tests\Support\Factory\UserTestFactory::createActive((string) $id, (string) $id . '@corp.example'));
    self::getContainer()->set(\User\Application\Port\Outbound\UserRepositoryPort::class, $users);
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . \Tests\Support\Auth\InteractiveTokenFactory::issue(self::getContainer(), self::USER, 'inventory@corp.example'));
    $em = $this->manager();
    $now = new DateTimeImmutable();
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Inventory contract';
    $org->slug = 'inventory-contract';
    $org->ownerUserId = self::USER;
    $org->createdByUserId = self::USER;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = $org->updatedAt = $now;
    $em->persist($org);
    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE;
    $role->organization = $org;
    $role->name = 'inventory-contract';
    $role->permissions = $permissions;
    $role->isSystem = false;
    $role->createdAt = $now;
    $em->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = 'beb10000-0000-4000-8000-000000000005';
    $member->organization = $org;
    $member->userId = self::USER;
    $member->isActive = true;
    $member->joinedAt = $now;
    $em->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $em->persist($assignment);
    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION;
    $intervention->organization = $org;
    $intervention->name = 'Stock intervention';
    $intervention->type = 'maintenance';
    $intervention->status = 'in_progress';
    $intervention->number = 1;
    $intervention->responsibleId = 'beb10000-0000-4000-8000-000000000005';
    $intervention->createdAt = $intervention->updatedAt = $now;
    $em->persist($intervention);
    $em->flush();

    return $client;
  }

  /**
   * @param list<string> $permissions
   */
  private function permissions(array $permissions): void
  {
    $role = $this->manager()->find(OrganizationRoleRecord::class, self::ROLE);
    self::assertInstanceOf(OrganizationRoleRecord::class, $role);
    $role->permissions = $permissions;
    $this->manager()->flush();
  }

  /**
   * @return array{string,string}
   */
  private function catalog(KernelBrowser $client): array
  {
    $part = $this->request($client, 'POST', 'inventory-parts', ['code' => 'SEAL', 'label' => 'Seal', 'unit' => 'piece', 'kind' => 'part'], 201);
    $warehouse = $this->request($client, 'POST', 'inventory-warehouses', ['code' => 'MAIN', 'name' => 'Main store'], 201);
    self::assertIsString($part['id']);
    self::assertIsString($warehouse['id']);

    return [$part['id'], $warehouse['id']];
  }

  private function correct(KernelBrowser $client, string $part, string $warehouse, string $qty, ?string $price): void
  {
    $this->request($client, 'POST', 'inventory-corrections', ['clientOperationId' => $this->op(), 'partId' => $part, 'warehouseId' => $warehouse, 'quantity' => $qty, 'reason' => 'Counted initial stock', 'unitCost' => $price], 201);
  }

  /**
   * @return array<string,mixed>
   */
  private function consumption(string $part, string $warehouse, string $qty): array
  {
    return ['clientOperationId' => $this->op(), 'partId' => $part, 'warehouseId' => $warehouse, 'quantity' => $qty, 'interventionId' => self::INTERVENTION, 'occurredAt' => '2026-10-06T10:00:00+00:00'];
  }

  /**
   * @return array<string,mixed>
   */
  private function balance(KernelBrowser $client): array
  {
    $list = $this->request($client, 'GET', 'inventory-balances');
    self::assertIsArray($list['member']);
    self::assertCount(1, $list['member']);
    $balance = $list['member'][0];
    self::assertIsArray($balance);
    $typed = [];
    foreach ($balance as $k => $v) {
      self::assertIsString($k);
      $typed[$k] = $v;
    }

    return $typed;
  }

  /**
   * @param array<string,mixed>|null $body request payload
   *
   * @return array<string,mixed> decoded response
   */
  private function request(KernelBrowser $client, string $method, string $path, ?array $body = null, int $status = 200): array
  {
    $client->request($method, '/api/organizations/' . self::ORG . '/' . $path, server:['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'], content:null === $body ? null : json_encode((object) $body, JSON_THROW_ON_ERROR));
    self::assertSame($status, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $output = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($output);
    $typed = [];
    foreach ($output as $k => $v) {
      self::assertIsString($k);
      $typed[$k] = $v;
    }

    return $typed;
  }

  private function op(): string
  {
    return 'beb10000-0000-4000-8000-' . str_pad((string) ++$this->operation, 12, '0', STR_PAD_LEFT);
  }

  private function manager(): EntityManagerInterface
  {
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);

    return $em;
  }
}
