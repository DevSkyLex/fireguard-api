<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Infrastructure\Adapter\Export;

use DateTimeImmutable;
use Intervention\Application\Contract\Cost\InterventionCostContext;
use Intervention\Application\Contract\Publication\{InterventionEquipmentSnapshot,InterventionPublicationFacts,InterventionPublishedWorkFact};
use Intervention\Application\Port\Inbound\{InterventionCostSourceFactsPort,InterventionPublicationFactsPort};
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem,MaintenanceCostPlanning,MaintenanceCostSnapshot,MaintenanceCostTotals,MaintenanceCostView};
use MaintenanceCost\Application\Port\Inbound\MaintenanceCostReadPort;
use MaintenanceExport\Application\Port\Outbound\MaintenanceExportRepositoryPort;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\Model\ExternalReference;
use MaintenanceExport\Infrastructure\Adapter\Export\ExportSourceAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class ExportSourceAdapterTest
 * Individual prestation and direct material scopes never inherit another target's customer.
 *
 * @category Test
 */
final class ExportSourceAdapterTest extends TestCase
{
  private const string ORG = 'bb0e8400-e29b-41d4-a716-446655449001';

  private const string WORK = 'bb0e8400-e29b-41d4-a716-446655449002';

  private const string TASK = 'bb0e8400-e29b-41d4-a716-446655449003';

  private const string PUB = 'bb0e8400-e29b-41d4-a716-446655449004';

  private const string SITE_A = 'bb0e8400-e29b-41d4-a716-446655449005';

  private const string CUSTOMER_A = 'bb0e8400-e29b-41d4-a716-446655449006';

  private const string EQUIPMENT_B = 'bb0e8400-e29b-41d4-a716-446655449007';

  private const string SITE_B = 'bb0e8400-e29b-41d4-a716-446655449008';

  private const string CUSTOMER_B = 'bb0e8400-e29b-41d4-a716-446655449009';

  #[Test]
  public function explicitNullPerTaskSiteAndCustomerStayUnknownInMultiTargetDossier(): void
  {
    $capture = $this->adapter($this->publication())->capture(self::ORG, [self::WORK], 'erp', false, false);
    $row = $capture->baseline['work:' . self::WORK . ':' . self::TASK];
    foreach (['siteId', 'siteName', 'siteReference', 'customerId', 'customerName', 'customerReference'] as $field) {
      self::assertNull($row[$field]);
    }
  }

  #[Test]
  public function directMaterialRetainsItsOwnEquipmentSiteCustomerAndErpReferences(): void
  {
    $item = new MaintenanceCostItem('material:receipt', 'material', null, 'receipt', null, '12.500001', 'EUR', 'Direct consumption', '2026-10-07T12:00:00Z', equipmentId:self::EQUIPMENT_B, allocation:['identityState' => 'captured', 'equipment' => ['id' => self::EQUIPMENT_B, 'name' => 'Equipment B', 'assetReference' => 'B-02'], 'site' => ['id' => self::SITE_B, 'name' => 'Site B'], 'customer' => ['id' => self::CUSTOMER_B, 'name' => 'Customer B']]);
    $capture = $this->adapter($this->publication(), [$item])->capture(self::ORG, [self::WORK], 'erp', true, false);
    $row = $capture->baseline['cost:' . self::WORK . ':material:receipt'];
    self::assertSame(self::EQUIPMENT_B, $row['equipmentId']);
    self::assertSame('Equipment B', $row['equipmentName']);
    self::assertSame(self::SITE_B, $row['siteId']);
    self::assertSame('Site B', $row['siteName']);
    self::assertSame('ERP-site-' . self::SITE_B, $row['siteReference']);
    self::assertSame(self::CUSTOMER_B, $row['customerId']);
    self::assertSame('Customer B', $row['customerName']);
    self::assertSame('ERP-customer-' . self::CUSTOMER_B, $row['customerReference']);
    self::assertSame('12.500001', $row['amount']);
    self::assertTrue($row['identityComplete']);
  }

  #[Test]
  public function directMaterialWithoutCapturedAllocationDoesNotInventThePrimaryCustomer(): void
  {
    $item = new MaintenanceCostItem('material:receipt', 'material', null, 'receipt', null, null, 'EUR', 'Direct consumption', '2026-10-07T12:00:00Z', equipmentId:self::EQUIPMENT_B);
    $capture = $this->adapter($this->publication(), [$item])->capture(self::ORG, [self::WORK], 'erp', true, false);
    $row = $capture->baseline['cost:' . self::WORK . ':material:receipt'];
    self::assertNull($row['siteId']);
    self::assertNull($row['customerId']);
    self::assertFalse($row['identityComplete']);
    self::assertSame('incomplete', $row['equipmentIdentityState']);
    self::assertFalse($capture->costsComplete);
    self::assertSame(1, $capture->incompleteCostCount);
  }

  #[Test]
  public function skippedTaskFinancialRowsKeepTheirCapturedAllocationWithoutExportingItsPrestation(): void
  {
    $publication = $this->publication();
    $skippedId = 'bb0e8400-e29b-41d4-a716-446655449010';
    $equipment = new InterventionEquipmentSnapshot(self::EQUIPMENT_B, 'Equipment B', 'B-02', 'fire_extinguisher', null, null, null, self::SITE_B, ['id' => self::SITE_B, 'name' => 'Site B'], ['id' => self::CUSTOMER_B, 'name' => 'Customer B']);
    $skipped = new InterventionPublishedWorkFact($skippedId, 'repair', 'skipped', null, null, self::EQUIPMENT_B, $equipment->site, $equipment->customer, $equipment, ['state' => 'skipped'], false, 18, 0, null, null);
    $mixed = new InterventionPublicationFacts($publication->id, $publication->organizationId, $publication->number, $publication->name, $publication->type, $publication->status, $publication->revision, $publication->createdAt, null, null, $publication->publishedAt, $publication->publicationId, 'available', 2, true, $publication->site, $publication->customer, [...$publication->workItems, $skipped], $publication->dossier);
    $allocation = ['identityState' => 'captured', 'equipment' => ['id' => self::EQUIPMENT_B, 'name' => 'Cost-captured equipment B', 'assetReference' => 'B-02'], 'site' => $equipment->site, 'customer' => $equipment->customer];
    $items = [
      new MaintenanceCostItem('expense:one', 'expense', $skippedId, 'one', null, '12.500001', 'EUR', 'External expense', '2026-10-07T12:00:00Z', equipmentId:self::EQUIPMENT_B, allocation:$allocation),
      new MaintenanceCostItem('time:two', 'time', $skippedId, 'two', 1, '18.000000', 'EUR', 'Work time', '2026-10-07', equipmentId:self::EQUIPMENT_B, allocation:$allocation),
    ];
    $capture = $this->adapter($mixed, $items)->capture(self::ORG, [self::WORK], 'erp', true, false);
    self::assertCount(3, $capture->baseline);
    self::assertArrayNotHasKey('work:' . self::WORK . ':' . $skippedId, $capture->baseline);
    foreach ($items as $item) {
      $row = $capture->baseline['cost:' . self::WORK . ':' . $item->kind . ':' . $item->sourceId];
      self::assertSame($skippedId, $row['workItemId']);
      self::assertSame(self::EQUIPMENT_B, $row['equipmentId']);
      self::assertSame('Cost-captured equipment B', $row['equipmentName']);
      self::assertSame(self::SITE_B, $row['siteId']);
      self::assertSame(self::CUSTOMER_B, $row['customerId']);
      self::assertSame('ERP-equipment-' . self::EQUIPMENT_B, $row['equipmentReference']);
      self::assertSame('ERP-site-' . self::SITE_B, $row['siteReference']);
      self::assertSame('ERP-customer-' . self::CUSTOMER_B, $row['customerReference']);
      self::assertTrue($row['identityComplete']);
    }
  }

  #[Test]
  public function legacyFinancialItemOnSkippedTaskUsesItsRetainedIdentityWithoutPrimaryFallback(): void
  {
    $publication = $this->publication();
    $skippedId = 'bb0e8400-e29b-41d4-a716-446655449010';
    $equipment = new InterventionEquipmentSnapshot(self::EQUIPMENT_B, 'Equipment B', 'B-02', 'fire_extinguisher', null, null, null, self::SITE_B, ['id' => self::SITE_B, 'name' => 'Site B'], null);
    $skipped = new InterventionPublishedWorkFact($skippedId, 'repair', 'skipped', null, null, self::EQUIPMENT_B, $equipment->site, null, $equipment, null, false, 18, 0, null, null);
    $mixed = new InterventionPublicationFacts($publication->id, $publication->organizationId, $publication->number, $publication->name, $publication->type, $publication->status, $publication->revision, $publication->createdAt, null, null, $publication->publishedAt, $publication->publicationId, 'available', 2, true, $publication->site, $publication->customer, [...$publication->workItems, $skipped], $publication->dossier);
    $item = new MaintenanceCostItem('expense:one', 'expense', $skippedId, 'one', null, '12.500001', 'EUR', 'External expense', '2026-10-07T12:00:00Z');
    $capture = $this->adapter($mixed, [$item])->capture(self::ORG, [self::WORK], 'erp', true, false);
    $row = $capture->baseline['cost:' . self::WORK . ':expense:one'];
    self::assertSame(self::EQUIPMENT_B, $row['equipmentId']);
    self::assertSame(self::SITE_B, $row['siteId']);
    self::assertNull($row['customerId']);
    self::assertNull($row['customerReference']);
  }

  #[Test]
  public function sourceWithoutValidatedPrestationIsRejectedEvenWhenItHasFinancialRows(): void
  {
    $item = new MaintenanceCostItem('expense:one', 'expense', null, 'one', null, '10.000000', 'EUR', 'External expense', '2026-10-07T12:00:00Z');
    $adapter = $this->adapter($this->publication(false), [$item]);
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('no validated prestation');
    $adapter->capture(self::ORG, [self::WORK], 'erp', true, false);
  }

  /**
   * Method adapter
   *
   * @param list<MaintenanceCostItem> $items captured private costs
   *
   * @return ExportSourceAdapter owner-port projection
   */
  private function adapter(InterventionPublicationFacts $publication, array $items = []): ExportSourceAdapter
  {
    $publications = $this->createStub(InterventionPublicationFactsPort::class);
    $publications->method('publishedBatch')->willReturn([$publication]);
    $times = $this->createStub(InterventionCostSourceFactsPort::class);
    $times->method('context')->willReturn(new InterventionCostContext(self::WORK, self::ORG, 'published', 2, 'Dossier', self::SITE_A, [self::TASK]));
    $costs = $this->createStub(MaintenanceCostReadPort::class);
    $total = new MaintenanceCostTotals(null, '0.000000', false, $items);
    $frozen = new MaintenanceCostSnapshot(1, '2026-10-07T12:00:00Z', self::PUB, 1, 'EUR', $total);
    $costs->method('view')->willReturn(new MaintenanceCostView(self::WORK, self::ORG, 'EUR', new MaintenanceCostPlanning(), $total, $frozen));
    $repository = $this->createStub(MaintenanceExportRepositoryPort::class);
    $repository->method('reference')->willReturnCallback(static fn (string $org, string $system, string $type, string $id): ExternalReference => new ExternalReference('bb0e8400-e29b-41d4-a716-446655449099', $org, $system, $type, $id, 'ERP-' . $type . '-' . $id, 1, new DateTimeImmutable('2026-10-07T12:00:00Z')));

    return new ExportSourceAdapter($publications, $times, $costs, $repository);
  }

  /**
   * Method publication
   *
   * @return InterventionPublicationFacts multi-target immutable dossier
   */
  private function publication(bool $validated = true): InterventionPublicationFacts
  {
    $now = new DateTimeImmutable('2026-10-07T12:00:00Z');
    $work = new InterventionPublishedWorkFact(self::TASK, 'repair', 'completed', null, null, null, null, null, null, ['state' => 'validated'], $validated, 0, 0, $now, $now);

    return new InterventionPublicationFacts(self::WORK, self::ORG, 1, 'Dossier', 'corrective_maintenance', 'published', 2, $now, null, null, $now, self::PUB, 'available', 2, true, ['id' => self::SITE_A, 'name' => 'Site A'], ['id' => self::CUSTOMER_A, 'name' => 'Customer A'], [$work], ['version' => 2, 'timeEntries' => []]);
  }
}
