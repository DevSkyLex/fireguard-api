<?php

declare(strict_types=1);

namespace Tests\Integration\Procurement\Infrastructure\Adapter\Reporting;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Procurement\Application\Contract\Reporting\ProcurementEconomicOverviewUnavailable;
use Procurement\Infrastructure\Adapter\Reporting\ProcurementEconomicOverviewAdapter;

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Class ProcurementEconomicOverviewAdapterTest
 *
 * Proves exact purchase economics, physical-fact counting and organization scope against PostgreSQL.
 *
 * @category Test
 */
final class ProcurementEconomicOverviewAdapterTest extends TestCase
{
  private const string ORG = 'a602e840-e29b-41d4-a716-446655447001';

  private const string FOREIGN = 'a602e840-e29b-41d4-a716-446655447002';

  private Connection $connection;

  private ProcurementEconomicOverviewAdapter $adapter;

  private int $sequence = 10;

  protected function setUp(): void
  {
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->connection = DriverManager::getConnection(['url' => $url]);
    $this->connection->beginTransaction();
    $currencies = $this->createMock(MaintenanceCurrencyPort::class);
    $currencies->method('forOrganization')->willReturn('EUR');
    $currencies->expects(self::never())->method('lock');
    $this->adapter = new ProcurementEconomicOverviewAdapter($this->connection, $currencies);
  }

  protected function tearDown(): void
  {
    $this->connection->rollBack();
    $this->connection->close();
    parent::tearDown();
  }

  #[Test]
  public function fractionalPartialDeliveryAndPhysicalReturnHaveIndependentExactAmounts(): void
  {
    $line = $this->line('2.500000', '1.250000', '4.250000');
    $order = $this->order([$line], 'partial_received');
    $this->receipt($order, $line['id'], '1.250000', '4.250000', '0.250000');
    $view = $this->adapter->overview(self::ORG, $this->from(), $this->to());
    self::assertSame('10.625000', $view->ordered->total);
    self::assertSame('5.312500', $view->received->total);
    self::assertSame('5.312500', $view->outstanding->total);
    self::assertSame('1.062500', $view->returned->total);
    self::assertSame(1, $view->orderCount);
    self::assertSame(1, $view->receiptCount);
    self::assertSame(0, $view->pendingIndividualizationCount);
    self::assertSame('order_created_at', $view->basis);
    self::assertSame('selected_orders_all_history', $view->receiptScope);
  }

  #[Test]
  public function unknownPricesPreserveKnownSubtotalsWithoutMakingZeroRemaindersUnknown(): void
  {
    $known = $this->line('2.500000', '1.250000', '4.250000');
    $unknown = $this->line('1.000000', '1.000000', null);
    $order = $this->order([$known, $unknown], 'partial_received');
    $this->receipt($order, $known['id'], '1.250000', '4.250000', '0.250000');
    $this->receipt($order, $unknown['id'], '1.000000', null);
    $view = $this->adapter->overview(self::ORG, $this->from(), $this->to());
    self::assertNull($view->ordered->total);
    self::assertFalse($view->ordered->complete);
    self::assertSame('10.625000', $view->ordered->knownTotal);
    self::assertNull($view->received->total);
    self::assertSame('5.312500', $view->received->knownTotal);
    self::assertTrue($view->outstanding->complete);
    self::assertSame('5.312500', $view->outstanding->total);
    self::assertTrue($view->returned->complete);
    self::assertSame('1.062500', $view->returned->total);
  }

  #[Test]
  public function cancellationRemovesOnlyUndeliveredCommitmentAndReturnsNeverReopenIt(): void
  {
    $line = $this->line('10.000000', '3.000000', '2.000000');
    $order = $this->order([$line], 'cancelled');
    $this->receipt($order, $line['id'], '3.000000', '2.000000', '1.000000');
    $this->order([$this->line('100.000000', '0.000000', null)], 'cancelled');
    $view = $this->adapter->overview(self::ORG, $this->from(), $this->to());
    self::assertSame('6.000000', $view->ordered->total);
    self::assertTrue($view->ordered->complete);
    self::assertSame('6.000000', $view->received->total);
    self::assertSame('0.000000', $view->outstanding->total);
    self::assertSame('2.000000', $view->returned->total);
    self::assertSame(2, $view->orderCount);
  }

  #[Test]
  public function orderCreationWindowIsHalfOpenAndIncludesLaterReceiptsWithoutForeignFacts(): void
  {
    $line = $this->line('1.000000', '1.000000', '3.000000', 'equipment_to_individualize');
    $selected = $this->order([$line], 'received', self::ORG, 'EUR', '2026-10-01 00:00:00');
    $this->receipt($selected, $line['id'], '1.000000', '3.000000', '0.000000', 'equipment_to_individualize', self::ORG, 'EUR', '2026-12-01 00:00:00');
    $this->receipt($selected, $line['id'], '500.000000', '100.000000', '0.000000', 'part', self::FOREIGN);
    $this->order([$line], 'received', self::ORG, 'EUR', '2026-09-30 23:59:59');
    $this->order([$line], 'received', self::ORG, 'EUR', '2026-11-01 00:00:00');
    $this->order([$line], 'draft');
    $foreign = $this->order([$line], 'received', self::FOREIGN);
    $this->receipt($foreign, $line['id'], '500.000000', '100.000000', '0.000000', 'part', self::FOREIGN);
    $view = $this->adapter->overview(self::ORG, $this->from(), $this->to());
    self::assertSame(1, $view->orderCount);
    self::assertSame(1, $view->receiptCount);
    self::assertSame(1, $view->pendingIndividualizationCount);
    self::assertSame('3.000000', $view->received->total);
    self::assertSame('3.000000', $view->ordered->total);
  }

  #[Test]
  public function fullyReturnedEquipmentIsNoLongerAwaitingIndividualizationAndUnknownReturnStaysIncomplete(): void
  {
    $line = $this->line('1.000000', '1.000000', null, 'equipment_to_individualize');
    $order = $this->order([$line], 'received');
    $this->receipt($order, $line['id'], '1.000000', null, '1.000000', 'equipment_to_individualize');
    $view = $this->adapter->overview(self::ORG, $this->from(), $this->to());
    self::assertSame(0, $view->pendingIndividualizationCount);
    self::assertNull($view->returned->total);
    self::assertFalse($view->returned->complete);
    self::assertSame('0.000000', $view->returned->knownTotal);
    self::assertSame('0.000000', $view->outstanding->total);
  }

  #[Test]
  public function pendingReturnReconciliationDoesNotCreateAnotherReturnOrReceiptValue(): void
  {
    $line = $this->line('2.000000', '2.000000', '4.000000');
    $order = $this->order([$line], 'received');
    $receipt = $this->receipt($order, $line['id'], '2.000000', '4.000000', '1.000000');
    $this->connection->update('procurement_receipts', ['pending_return_quantity' => '1.000000'], ['id' => $receipt]);
    $this->connection->insert('procurement_returns', ['id' => $this->id(), 'organization_id' => self::ORG, 'receipt_id' => $receipt, 'client_operation_id' => $this->id(), 'quantity' => '1.000000', 'reason' => 'Physical return awaiting stock reconciliation', 'actor_id' => $this->id(), 'created_at' => '2026-10-07 00:00:00', 'status' => 'pending_reconciliation', 'revision' => 1]);
    $view = $this->adapter->overview(self::ORG, $this->from(), $this->to());
    self::assertSame('8.000000', $view->received->total);
    self::assertSame('4.000000', $view->returned->total);
    self::assertSame(1, $view->receiptCount);
  }

  #[Test]
  public function unknownEmptyScopeReturnsExplicitKnownZero(): void
  {
    $view = $this->adapter->overview(self::FOREIGN, $this->from(), $this->to());
    self::assertSame('EUR', $view->currency);
    self::assertSame(0, $view->orderCount);
    self::assertSame('0.000000', $view->ordered->total);
    self::assertTrue($view->ordered->complete);
    self::assertSame('0.000000', $view->received->total);
    self::assertSame('0.000000', $view->outstanding->total);
    self::assertSame('0.000000', $view->returned->total);
  }

  #[Test]
  public function oversizedSelectionRefusesInsteadOfReturningFirstFiveHundredTotals(): void
  {
    for ($index = 0; $index < 501; ++$index) {
      $this->order([$this->line('1.000000', '0.000000', '1.000000')], 'ordered');
    }
    $this->assertUnavailable('order_limit');
  }

  #[Test]
  public function exactlyFiveHundredOrdersAndLargeExactValuesRemainSupported(): void
  {
    $this->order([$this->line('100000.000000', '0.000000', '999999999999999999.000000')], 'ordered');
    for ($index = 1; $index < 500; ++$index) {
      $this->order([$this->line('1.000000', '0.000000', '0.000000')], 'ordered');
    }
    $view = $this->adapter->overview(self::ORG, $this->from(), $this->to());
    self::assertSame(500, $view->orderCount);
    self::assertSame('99999999999999999900000.000000', $view->ordered->total);
    self::assertSame($view->ordered->total, $view->outstanding->total);
  }

  #[Test]
  public function configuredCurrencyMismatchInAnOrderIsRefused(): void
  {
    $this->order([$this->line('1.000000', '0.000000', '1.000000')], 'ordered', self::ORG, 'USD');
    $this->assertUnavailable('mixed_currency');
  }

  #[Test]
  public function retainedReceiptCurrencyMismatchIsRefused(): void
  {
    $line = $this->line('1.000000', '1.000000', '1.000000');
    $order = $this->order([$line], 'received');
    $this->receipt($order, $line['id'], '1.000000', '1.000000', '0.000000', 'part', self::ORG, 'USD');
    $this->assertUnavailable('mixed_currency');
  }

  #[Test]
  public function invalidHalfOpenPeriodIsRefused(): void
  {
    $this->expectException(ProcurementEconomicOverviewUnavailable::class);
    $this->expectExceptionMessage('exclusive end after its start');
    $this->adapter->overview(self::ORG, $this->to(), $this->from());
  }

  /**
   * @return array{id:string,kind:string,partId:?string,typeCode:?string,identityTemplate:array<never,never>,quantity:string,unitCost:?string,receivedQuantity:string,returnedQuantity:string}
   */
  private function line(string $quantity, string $received, ?string $price, string $kind = 'part'): array
  {
    return ['id' => $this->id(), 'kind' => $kind, 'partId' => 'part' === $kind ? $this->id() : null, 'typeCode' => 'part' === $kind ? null : 'extinguisher', 'identityTemplate' => [], 'quantity' => $quantity, 'unitCost' => $price, 'receivedQuantity' => $received, 'returnedQuantity' => '0.000000'];
  }

  /**
   * @param list<array<string,mixed>> $lines
   */
  private function order(array $lines, string $status, string $organization = self::ORG, string $currency = 'EUR', string $createdAt = '2026-10-07 00:00:00'): string
  {
    $id = $this->id();
    $this->connection->insert('procurement_orders', ['id' => $id, 'organization_id' => $organization, 'supplier_id' => $this->id(), 'name' => 'Economic overview order', 'currency' => $currency, 'status' => $status, 'lines' => json_encode($lines, JSON_THROW_ON_ERROR), 'revision' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt]);

    return $id;
  }

  private function receipt(string $order, string $line, string $quantity, ?string $price, string $returned = '0.000000', string $kind = 'part', string $organization = self::ORG, string $currency = 'EUR', string $receivedAt = '2026-10-07 00:00:00'): string
  {
    $id = $this->id();
    $this->connection->insert('procurement_receipts', ['id' => $id, 'organization_id' => $organization, 'order_id' => $order, 'line_id' => $line, 'kind' => $kind, 'quantity' => $quantity, 'unit_cost' => $price, 'currency' => $currency, 'received_at' => $receivedAt, 'actor_id' => $this->id(), 'created_at' => $receivedAt, 'equipment_ids' => '[]', 'returned_quantity' => $returned, 'revision' => 1]);

    return $id;
  }

  private function assertUnavailable(string $reason): void
  {
    try {
      $this->adapter->overview(self::ORG, $this->from(), $this->to());
      self::fail('An unreliable economic overview must be refused.');
    } catch (ProcurementEconomicOverviewUnavailable $failure) {
      self::assertSame($reason, $failure->reason);
    }
  }

  private function id(): string
  {
    return sprintf('a602e840-e29b-41d4-a716-%012d', $this->sequence++);
  }

  private function from(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-10-01T00:00:00Z');
  }

  private function to(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-11-01T00:00:00Z');
  }
}
