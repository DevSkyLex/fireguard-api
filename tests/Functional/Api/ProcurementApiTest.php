<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inventory\Infrastructure\Persistence\Doctrine\Record\{InventoryPartRecord, InventoryWarehouseRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, PlanRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function array_column;
use function json_decode;
use function json_encode;
use function strtoupper;
use function substr;

use const JSON_THROW_ON_ERROR;

/** Real HTTP, main PostgreSQL stock and park bridges prove the complete procurement contract. */
final class ProcurementApiTest extends WebTestCase
{
  private const string ORG = '790e8400-e29b-41d4-a716-446655448001';

  private const string ADMIN = '790e8400-e29b-41d4-a716-446655448002';

  private const string RECEIVER = '790e8400-e29b-41d4-a716-446655448003';

  private const string READER = '790e8400-e29b-41d4-a716-446655448004';

  private const string OUTSIDER = '790e8400-e29b-41d4-a716-446655448005';

  private const string PART = '790e8400-e29b-41d4-a716-446655448006';

  private const string WAREHOUSE = '790e8400-e29b-41d4-a716-446655448007';

  private const string LINE = '790e8400-e29b-41d4-a716-446655448008';

  private const string PLAN = '790e8400-e29b-41d4-a716-446655448009';

  private const string OPERATION = '790e8400-e29b-41d4-a716-446655448010';

  private const string SECOND_OPERATION = '790e8400-e29b-41d4-a716-446655448011';

  private const string THIRD_OPERATION = '790e8400-e29b-41d4-a716-446655448012';

  private ?string $loggedUserId = null;

  #[Test]
  public function suppliersRetainContactsAndHistoryWithOptimisticRevision(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $id = $this->id($supplier);
    self::assertSame('"revision-1"', $client->getResponse()->headers->get('ETag'));
    $this->request($client, 'PATCH', '/suppliers/' . $id, ['name' => 'Revised supplier']);
    self::assertSame(428, $client->getResponse()->getStatusCode());
    $changed = $this->request($client, 'PATCH', '/suppliers/' . $id, ['name' => 'Revised supplier'], 1);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(2, $changed['revision']);
    $this->request($client, 'PATCH', '/suppliers/' . $id, ['name' => 'Stale write'], 1);
    self::assertSame(412, $client->getResponse()->getStatusCode());
    $archived = $this->request($client, 'POST', '/suppliers/' . $id . '/archive', null, 2);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertNotNull($archived['archivedAt']);
    $saved = $this->request($client, 'GET', '/suppliers/' . $id);
    self::assertSame('Revised supplier', $saved['name']);
    self::assertSame([['name' => 'Store contact', 'email' => 'store@example.com', 'phone' => null, 'role' => 'warehouse']], $saved['contacts']);
  }

  /**
   * Checks all suppliers is explicit and retains backward-compatible active defaults.
   */
  #[Test]
  public function supplierArchiveFiltersUseExactScopedCollections(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $archived = $this->supplier($client);
    $archivedId = $this->id($archived);
    $this->request($client, 'POST', '/suppliers/' . $archivedId . '/archive', null, 1);
    $activeId = $this->id($this->supplier($client));

    foreach (['' => [$activeId], '?archived=false' => [$activeId], '?archived=true' => [$archivedId]] as $filter => $ids) {
      $result = $this->request($client, 'GET', '/suppliers' . $filter);
      self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
      self::assertSame(1, $result['totalItems']);
      self::assertIsArray($result['member']);
      self::assertSame($ids, array_column($result['member'], 'id'));
    }
    $all = $this->request($client, 'GET', '/suppliers?archived=all&search=Supply&itemsPerPage=1&page=2');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(2, $all['totalItems']);
    self::assertIsArray($all['member']);
    self::assertCount(1, $all['member']);
    $this->login($client, self::OUTSIDER);
    $this->request($client, 'GET', '/suppliers?archived=all');
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function fractionalPartialReceiptReplaysAndReturnMovesStockExactlyOnce(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    $order = $this->purchase($client, $this->id($supplier), 'part', '2.500000', '4.250000');
    $orderId = $this->id($order);
    $this->request($client, 'POST', '/orders/' . $orderId . '/order', null, 1);
    $this->request($client, 'POST', '/suppliers/' . $this->id($supplier) . '/archive', null, 1);
    $input = $this->receiptInput('1.250000');
    $first = $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $input, 2);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $receiptId = $this->id($first);
    self::assertSame('stock_received', $first['status']);
    self::assertSame('1.250000', $first['quantity']);
    $replay = $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $input, 2);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertTrue($replay['replayed']);
    self::assertSame($receiptId, $replay['id']);
    $stored = $this->request($client, 'GET', '/orders/' . $orderId);
    self::assertSame('partial_received', $stored['status']);
    self::assertIsArray($stored['lines']);
    self::assertIsArray($stored['lines'][0]);
    self::assertSame('1.250000', $stored['lines'][0]['remainingQuantity']);
    $return = ['clientOperationId' => self::SECOND_OPERATION, 'quantity' => '0.250000', 'reason' => 'Damaged packaging'];
    $returned = $this->request($client, 'POST', '/receipts/' . $receiptId . '/returns', $return, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('0.250000', $returned['returnedQuantity']);
    $this->request($client, 'POST', '/receipts/' . $receiptId . '/returns', $return, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame('1.000000', $this->main()->getConnection()->fetchOne('SELECT quantity FROM inventory_balances WHERE organization_id = ? AND part_id = ? AND warehouse_id = ?', [self::ORG, self::PART, self::WAREHOUSE]));
    self::assertSame('4.250000', $this->main()->getConnection()->fetchOne('SELECT total_value FROM inventory_balances WHERE organization_id = ? AND part_id = ? AND warehouse_id = ?', [self::ORG, self::PART, self::WAREHOUSE]));
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM procurement_receipts WHERE organization_id = ?', [self::ORG]));
  }

  #[Test]
  public function duplicateMismatchAndOverReceiptDoNotCreatePhysicalOrStockRows(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    $order = $this->purchase($client, $this->id($supplier), 'part', '1.000000', null);
    $orderId = $this->id($order);
    $this->request($client, 'POST', '/orders/' . $orderId . '/order', null, 1);
    $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $this->receiptInput('1.000000'), 2);
    $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $this->receiptInput('0.500000'), 2);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $input = $this->receiptInput('1.000000');
    $input['clientOperationId'] = self::SECOND_OPERATION;
    $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $input, 3);
    self::assertSame(409, $client->getResponse()->getStatusCode());
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM procurement_receipts WHERE organization_id = ?', [self::ORG]));
    self::assertSame('1.000000', $this->main()->getConnection()->fetchOne('SELECT quantity FROM inventory_balances WHERE organization_id = ?', [self::ORG]));
  }

  #[Test]
  public function hardwareReceiptSurvivesQuotaThenAnExplicitRetryCreatesOneReserveEquipment(): void
  {
    $client = static::createClient();
    $this->seed(0);
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    $order = $this->purchase($client, $this->id($supplier), 'equipment_to_individualize', '1.000000', null);
    $orderId = $this->id($order);
    $this->request($client, 'POST', '/orders/' . $orderId . '/order', null, 1);
    $input = $this->receiptInput('1.000000');
    unset($input['warehouseId']);
    $receipt = $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $input, 2);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $receiptId = $this->id($receipt);
    self::assertSame('awaiting_individualization', $receipt['status']);
    $attempt = ['clientOperationId' => self::SECOND_OPERATION];
    $blocked = $this->request($client, 'POST', '/receipts/' . $receiptId . '/individualize', $attempt, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('quota_exceeded', $blocked['blockedReason']);
    $sameAttempt = $this->request($client, 'POST', '/receipts/' . $receiptId . '/individualize', $attempt, 1);
    self::assertTrue($sameAttempt['replayed']);
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM equipment WHERE organization_id = ?', [self::ORG]));
    $plan = $this->main()->find(PlanRecord::class, self::PLAN);
    self::assertInstanceOf(PlanRecord::class, $plan);
    $plan->limits = ['equipment' => 1];
    $this->main()->flush();
    $newAttempt = ['clientOperationId' => self::THIRD_OPERATION];
    $individualized = $this->request($client, 'POST', '/receipts/' . $receiptId . '/individualize', $newAttempt, 2);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('individualized', $individualized['status']);
    self::assertIsArray($individualized['equipmentIds']);
    self::assertCount(1, $individualized['equipmentIds']);
    $this->request($client, 'POST', '/receipts/' . $receiptId . '/individualize', $newAttempt, 2);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM equipment WHERE organization_id = ?', [self::ORG]));
    self::assertSame('in_stock', $this->main()->getConnection()->fetchOne('SELECT status FROM equipment WHERE organization_id = ?', [self::ORG]));
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM inventory_balances WHERE organization_id = ?', [self::ORG]));
  }

  #[Test]
  public function aQuantityReceiverCanReadAndReceiveWithoutSeeingInternalCosts(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    $order = $this->purchase($client, $this->id($supplier), 'part', '2.500000', '4.250000');
    $orderId = $this->id($order);
    $this->request($client, 'POST', '/orders/' . $orderId . '/order', null, 1);
    $this->login($client, self::RECEIVER);
    $hidden = $this->request($client, 'GET', '/orders/' . $orderId);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertFalse($hidden['financialVisible']);
    self::assertIsArray($hidden['lines']);
    self::assertIsArray($hidden['lines'][0]);
    self::assertArrayNotHasKey('unitCost', $hidden['lines'][0]);
    $receipt = $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $this->receiptInput('1.250000'), 2);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertFalse($receipt['financialVisible']);
    self::assertArrayNotHasKey('unitCost', $receipt);
    $this->purchase($client, $this->id($supplier), 'part', '1.000000', '0.000000');
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  #[DataProvider('denials')]
  public function denialDoesNotCreateASupplier(string $actorId, int $expectedStatus): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, $actorId);
    $this->supplier($client);
    self::assertSame($expectedStatus, $client->getResponse()->getStatusCode());
    self::assertSame(0, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM procurement_suppliers WHERE organization_id = ?', [self::ORG]));
  }

  /**
   * @return iterable<string,array{string,int}>
   */
  public static function denials(): iterable
  {
    yield 'reader missing manage' => [self::READER, 403];
    yield 'outsider' => [self::OUTSIDER, 404];
  }

  #[Test]
  public function cancellationKeepsPartialReceiptsAndRefusesMoreDeliveries(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    $orderId = $this->id($this->purchase($client, $this->id($supplier), 'part', '2.500000', null));
    $this->request($client, 'POST', '/orders/' . $orderId . '/order', null, 1);
    $first = $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $this->receiptInput('1.000000'), 2);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $cancelled = $this->request($client, 'POST', '/orders/' . $orderId . '/cancel-remaining', null, 3);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('cancelled', $cancelled['status']);
    $input = $this->receiptInput('1.000000');
    $input['clientOperationId'] = self::SECOND_OPERATION;
    $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $input, 4);
    self::assertSame(409, $client->getResponse()->getStatusCode());
    $saved = $this->request($client, 'GET', '/receipts/' . $this->id($first));
    self::assertSame('1.000000', $saved['quantity']);
  }

  #[Test]
  public function aPhysicalReturnWithComputerStockShortageIsRetainedAndExplicitlyReconciled(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    $orderId = $this->id($this->purchase($client, $this->id($supplier), 'part', '2.500000', '4.250000'));
    $this->request($client, 'POST', '/orders/' . $orderId . '/order', null, 1);
    $receipt = $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $this->receiptInput('2.500000'), 2);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $receiptId = $this->id($receipt);
    $this->main()->getConnection()->update('inventory_balances', ['quantity' => '0.000000', 'total_value' => '0.000000'], ['organization_id' => self::ORG]);
    $input = ['clientOperationId' => self::SECOND_OPERATION, 'quantity' => '1.000000', 'reason' => 'Physical return with an inaccurate computer balance'];
    $pending = $this->request($client, 'POST', '/receipts/' . $receiptId . '/returns', $input, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('1.000000', $pending['returnedQuantity']);
    self::assertSame('1.000000', $pending['pendingReturnQuantity']);
    $list = $this->request($client, 'GET', '/receipts/' . $receiptId . '/returns');
    self::assertSame(200, $client->getResponse()->getStatusCode());
    $items = $list['member'] ?? $list['hydra:member'] ?? null;
    self::assertIsArray($items);
    self::assertCount(1, $items);
    self::assertIsArray($items[0]);
    self::assertArrayHasKey('id', $items[0]);
    self::assertIsString($items[0]['id']);
    $returnId = $items[0]['id'];
    self::assertSame('awaiting_reconciliation', $items[0]['status']);
    self::assertSame('stock_insufficient', $items[0]['blockedReason']);
    self::assertSame('0.000000', $this->main()->getConnection()->fetchOne('SELECT quantity FROM inventory_balances WHERE organization_id = ?', [self::ORG]));
    $this->main()->getConnection()->update('inventory_balances', ['quantity' => '1.000000', 'total_value' => '4.250000'], ['organization_id' => self::ORG]);
    $reconcile = ['clientOperationId' => self::THIRD_OPERATION];
    $confirmed = $this->request($client, 'POST', '/returns/' . $returnId . '/reconcile', $reconcile, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('confirmed', $confirmed['status']);
    $replay = $this->request($client, 'POST', '/returns/' . $returnId . '/reconcile', $reconcile, 1);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertTrue($replay['replayed']);
    $saved = $this->request($client, 'GET', '/receipts/' . $receiptId);
    self::assertSame('1.000000', $saved['returnedQuantity']);
    self::assertSame('0.000000', $saved['pendingReturnQuantity']);
    $declaration = $this->request($client, 'GET', '/returns/' . $returnId);
    self::assertSame($input['reason'], $declaration['reason']);
    self::assertSame(self::SECOND_OPERATION, $declaration['clientOperationId']);
    self::assertSame('0.000000', $this->main()->getConnection()->fetchOne('SELECT quantity FROM inventory_balances WHERE organization_id = ?', [self::ORG]));
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM procurement_returns WHERE organization_id = ?', [self::ORG]));
  }

  #[Test]
  public function uppercaseUuidAliasesReplayTheSamePhysicalDelivery(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    $orderId = $this->id($this->purchase($client, $this->id($supplier), 'part', '2.500000', null));
    $this->request($client, 'POST', '/orders/' . $orderId . '/order', null, 1);
    $input = $this->receiptInput('1.000000');
    $first = $this->request($client, 'POST', '/orders/' . $orderId . '/receipts', $input, 2);
    self::assertSame(201, $client->getResponse()->getStatusCode());
    foreach (['lineId', 'warehouseId', 'clientOperationId'] as $field) {
      self::assertIsString($input[$field]);
      $input[$field] = strtoupper($input[$field]);
    }
    $replay = $this->request($client, 'POST', '/orders/' . strtoupper($orderId) . '/receipts', $input, 2);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertTrue($replay['replayed']);
    self::assertSame($first['id'], $replay['id']);
    self::assertSame(1, $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM procurement_receipts WHERE organization_id = ?', [self::ORG]));
    self::assertSame('1.000000', $this->main()->getConnection()->fetchOne('SELECT quantity FROM inventory_balances WHERE organization_id = ?', [self::ORG]));
  }

  #[Test]
  public function orderedArticleIdentityAndUnitRemainVisibleWithoutFinancialRights(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->login($client, self::ADMIN);
    $supplier = $this->supplier($client);
    $orderId = $this->id($this->purchase($client, $this->id($supplier), 'part', '2.500000', null));
    self::assertSame(201, $client->getResponse()->getStatusCode());
    $this->request($client, 'POST', '/orders/' . $orderId . '/order', null, 1);
    $part = $this->main()->find(InventoryPartRecord::class, self::PART);
    self::assertInstanceOf(InventoryPartRecord::class, $part);
    $part->label = 'Renamed after ordering';
    $part->unit = 'kilogram';
    $this->main()->flush();
    $this->login($client, self::RECEIVER);
    $saved = $this->request($client, 'GET', '/orders/' . $orderId);
    self::assertSame(200, $client->getResponse()->getStatusCode());
    self::assertIsArray($saved['lines']);
    self::assertIsArray($saved['lines'][0]);
    self::assertSame('REF-PART', $saved['lines'][0]['partCode']);
    self::assertSame('Consumable', $saved['lines'][0]['partLabel']);
    self::assertSame('litre', $saved['lines'][0]['partUnit']);
    self::assertFalse($saved['financialVisible']);
    self::assertArrayNotHasKey('unitCost', $saved['lines'][0]);
  }

  /**
   * @return array<string,mixed>
   */
  private function supplier(KernelBrowser &$client): array
  {
    return $this->request($client, 'POST', '/suppliers', ['name' => 'Supply company', 'contacts' => [['name' => 'Store contact', 'email' => 'store@example.com', 'role' => 'warehouse']]]);
  }

  /**
   * @return array<string,mixed>
   */
  private function purchase(KernelBrowser &$client, string $supplierId, string $kind, string $quantity, ?string $unitCost): array
  {
    $line = ['id' => self::LINE, 'kind' => $kind, 'quantity' => $quantity];
    if (null !== $unitCost) {
      $line['unitCost'] = $unitCost;
    }
    if ('part' === $kind) {
      $line['partId'] = self::PART;
    } else {
      $line['typeCode'] = 'fire_extinguisher';
      $line['identityTemplate'] = ['name' => 'Reserve extinguisher', 'brand' => 'Test brand'];
    }

    return $this->request($client, 'POST', '/orders', ['name' => 'Internal purchase', 'supplierId' => $supplierId, 'lines' => [$line]]);
  }

  /**
   * @return array<string,mixed>
   */
  private function receiptInput(string $quantity): array
  {
    return ['lineId' => self::LINE, 'warehouseId' => self::WAREHOUSE, 'quantity' => $quantity, 'receivedAt' => '2026-10-05T12:00:00Z', 'clientOperationId' => self::OPERATION];
  }

  /**
   * @param array<string,mixed> $data
   */
  private function id(array $data): string
  {
    self::assertIsString($data['id'] ?? null);

    return $data['id'];
  }

  /**
   * @param array<string,mixed>|null $body
   *
   * @return array<string,mixed>
   */
  private function request(KernelBrowser &$client, string $method, string $path, ?array $body = null, ?int $revision = null): array
  {
    self::ensureKernelShutdown();
    $client = static::createClient();
    if (null !== $this->loggedUserId) {
      $this->login($client, $this->loggedUserId);
    }
    $server = ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    if (null !== $revision) {
      $server['HTTP_IF_MATCH'] = '"revision-' . $revision . '"';
    }
    $client->request($method, '/api/organizations/' . self::ORG . '/procurement' . $path, server: $server, content: null === $body ? null : json_encode((object) $body, JSON_THROW_ON_ERROR));
    $content = (string) $client->getResponse()->getContent();
    /** @var array<string,mixed> $decoded */
    $decoded = '' === $content ? [] : json_decode($content, true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
  }

  private function login(KernelBrowser $client, string $id): void
  {
    $this->loggedUserId = $id;
    $client->disableReboot();
    $client->loginUser(new SecurityUser($id, 'procurement-' . $id . '@example.com', 'hashed-password', ['ROLE_USER']), 'api');
  }

  private function main(): EntityManagerInterface
  {
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager;
  }

  private function seed(int $equipmentLimit = 100): void
  {
    $manager = $this->main();
    $now = new DateTimeImmutable('2026-10-06T00:00:00Z');
    $plan = new PlanRecord();
    $plan->id = self::PLAN;
    $plan->key = 'procurement-test';
    $plan->name = 'Procurement test';
    $plan->limits = ['equipment' => $equipmentLimit];
    $plan->createdAt = $now;
    $plan->updatedAt = $now;
    $manager->persist($plan);
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Procurement test';
    $organization->slug = 'procurement-test';
    $organization->ownerUserId = self::ADMIN;
    $organization->createdByUserId = self::ADMIN;
    $organization->planId = self::PLAN;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $manager->persist($organization);
    foreach ([self::ADMIN => ['*'], self::RECEIVER => ['organization.procurement.read', 'organization.procurement.manage', 'organization.inventory.manage'], self::READER => ['organization.procurement.read']] as $userId => $permissions) {
      $role = new OrganizationRoleRecord();
      $role->id = '790e8401' . substr($userId, 8);
      $role->organization = $organization;
      $role->name = 'test-' . $userId;
      $role->permissions = $permissions;
      $role->createdAt = $now;
      $manager->persist($role);
      $member = new OrganizationMemberRecord();
      $member->id = '790e8402' . substr($userId, 8);
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
    $part = new InventoryPartRecord();
    $part->id = self::PART;
    $part->organizationId = self::ORG;
    $part->code = 'REF-PART';
    $part->label = 'Consumable';
    $part->unit = 'litre';
    $part->kind = 'consumable';
    $manager->persist($part);
    $warehouse = new InventoryWarehouseRecord();
    $warehouse->id = self::WAREHOUSE;
    $warehouse->organizationId = self::ORG;
    $warehouse->code = 'WAREHOUSE';
    $warehouse->label = 'Main warehouse';
    $manager->persist($warehouse);
    $manager->flush();
  }
}
