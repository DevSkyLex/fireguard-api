<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Contract\Publication\{InterventionEconomicContext, InterventionEquipmentSnapshot, InterventionPublishedWorkFact};
use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use Intervention\Application\Port\Outbound\InterventionEquipmentSnapshotPort;
use MaintenanceCost\Application\Contract\Cost\MaintenanceCostItem;
use MaintenanceCost\Application\Service\MaintenanceCostAllocationResolver;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceCostAllocationResolverTest
 *
 * Direct stock facts retain ownership while published identity survives later park changes.
 *
 * @category Test
 */
final class MaintenanceCostAllocationResolverTest extends TestCase
{
  public function testDirectMaterialWithoutTaskUsesItsOwnRootSiteAndClient(): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context());
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::once())->method('snapshots')->with('org', ['equipment'])->willReturn(['equipment' => $this->equipment()]);
    $item = new MaintenanceCostItem('material', 'material', null, 'movement', null, '20.000000', 'EUR', 'Seal', '2026-10-07T12:00:00Z', equipmentId: 'equipment');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$item], []);
    self::assertNotNull($result[0]->allocation);
    self::assertSame('equipment', $result[0]->equipmentId);
    self::assertSame(['id' => 'site', 'name' => 'Asset site'], $result[0]->allocation['site']);
    self::assertSame(['id' => 'customer', 'name' => 'Asset client'], $result[0]->allocation['customer']);
    self::assertSame('live', $result[0]->allocation['identityState']);
  }

  public function testTimeUsesTheWorkTargetInsteadOfDuplicatingTheDossier(): void
  {
    $identity = $this->equipment();
    $task = new InterventionPublishedWorkFact('task', 'repair', 'planned', null, null, 'equipment', $identity->site, $identity->customer, $identity, null, false, 0, 0, null, null);
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context([$task]));
    $equipment = $this->createStub(InterventionEquipmentSnapshotPort::class);
    $equipment->method('snapshots')->willReturn(['equipment' => $identity]);
    $item = new MaintenanceCostItem('time', 'time', 'task', 'time-entry', 1, '10.000000', 'EUR', 'Time', '2026-10-07');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$item], []);
    self::assertNotNull($result[0]->allocation);
    self::assertNotNull($result[0]->allocation['equipment']);
    self::assertSame('equipment', $result[0]->equipmentId);
    self::assertSame('Asset name', $result[0]->allocation['equipment']['name']);
  }

  public function testCorrectionPreservesPrivateCapturedIdentityAfterParkRelocation(): void
  {
    $allocation = ['identityState' => 'captured', 'equipment' => ['id' => 'equipment', 'name' => 'Original asset', 'assetReference' => 'ORIGINAL'], 'site' => ['id' => 'old-site', 'name' => 'Original site'], 'customer' => ['id' => 'old-client', 'name' => 'Original client']];
    $original = new MaintenanceCostItem('material-original', 'material', null, 'movement', null, '20.000000', 'EUR', 'Seal', '2026-10-07', equipmentId: 'equipment', allocation: $allocation);
    $corrected = new MaintenanceCostItem('material-corrected', 'material', null, 'movement', null, '25.000000', 'EUR', 'Seal', '2026-10-07', correctionOf: 'material-original', equipmentId: 'equipment');
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context());
    $equipment = $this->createStub(InterventionEquipmentSnapshotPort::class);
    $equipment->method('snapshots')->willReturn(['equipment' => $this->equipment()]);
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$corrected], ['material:movement' => $original]);
    self::assertSame($allocation, $result[0]->allocation);
    self::assertSame('25.000000', $result[0]->amount);
    self::assertSame('20.000000', $original->amount);
  }

  public function testGlobalExpenseDoesNotAcquireTheDossierSiteOrClient(): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context());
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::never())->method('snapshots');
    $item = new MaintenanceCostItem('expense', 'expense', null, 'expense', null, '50.000000', 'EUR', 'Visit', '2026-10-07');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$item], []);
    self::assertNotNull($result[0]->allocation);
    self::assertNull($result[0]->allocation['site']);
    self::assertNull($result[0]->allocation['customer']);
    self::assertNull($result[0]->allocation['equipment']);
  }

  public function testUnresolvedEquipmentRetainsIdWithExplicitlyIncompleteIdentity(): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context());
    $equipment = $this->createStub(InterventionEquipmentSnapshotPort::class);
    $equipment->method('snapshots')->willReturn([]);
    $item = new MaintenanceCostItem('material', 'material', null, 'movement', null, '20.000000', 'EUR', 'Seal', '2026-10-07', equipmentId: 'equipment');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$item], []);
    self::assertNotNull($result[0]->allocation);
    self::assertNotNull($result[0]->allocation['equipment']);
    self::assertSame('equipment', $result[0]->allocation['equipment']['id']);
    self::assertNull($result[0]->allocation['equipment']['name']);
    self::assertSame('incomplete', $result[0]->allocation['identityState']);
    self::assertNull($result[0]->allocation['site']);
  }

  public function testForeignOperationalContextFailsBeforeEquipmentLookup(): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context(organization: 'foreign'));
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::never())->method('snapshots');
    $this->expectException(MaintenanceCostException::class);
    new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [], []);
  }

  public function testMaterialReturnInheritsTheOriginalCapturedAllocation(): void
  {
    $allocation = ['identityState' => 'captured', 'equipment' => ['id' => 'equipment', 'name' => 'Original asset', 'assetReference' => 'ORIGINAL'], 'site' => ['id' => 'old-site', 'name' => 'Original site'], 'customer' => ['id' => 'old-client', 'name' => 'Original client']];
    $original = new MaintenanceCostItem('material:movement', 'material', null, 'movement', null, '20.000000', 'EUR', 'Seal', '2026-10-07', equipmentId: 'equipment', allocation: $allocation);
    $return = new MaintenanceCostItem('material:return', 'material', null, 'return', null, '-5.000000', 'EUR', 'Returned seal', '2026-10-08', correctionOf: 'material:movement', equipmentId: 'equipment');
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context());
    $equipment = $this->createStub(InterventionEquipmentSnapshotPort::class);
    $equipment->method('snapshots')->willReturn(['equipment' => $this->equipment()]);
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$return], ['material:movement' => $original]);
    self::assertSame($allocation, $result[0]->allocation);
    self::assertSame('-5.000000', $result[0]->amount);
  }

  public function testLegacyCapturedTaskWithoutIdentityDoesNotUseCurrentParkLabels(): void
  {
    $task = new InterventionPublishedWorkFact('task', 'repair', 'completed', null, null, 'equipment', null, null, null, null, true, 30, 0, null, null);
    $context = new InterventionEconomicContext('work', 'org', 1, 'Repair', 'corrective_maintenance', 'published', 2, new DateTimeImmutable('2026-10-07'), null, null, new DateTimeImmutable('2026-10-07'), 'publication', 'available', 1, false, null, null, [$task]);
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($context);
    $equipment = $this->createStub(InterventionEquipmentSnapshotPort::class);
    $equipment->method('snapshots')->willReturn(['equipment' => $this->equipment()]);
    $item = new MaintenanceCostItem('time:entry:1', 'time', 'task', 'entry', 1, '20.000000', 'EUR', 'Work', '2026-10-07');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$item], []);
    self::assertNotNull($result[0]->allocation);
    self::assertNotNull($result[0]->allocation['equipment']);
    self::assertSame('equipment', $result[0]->equipmentId);
    self::assertSame('incomplete', $result[0]->allocation['identityState']);
    self::assertNull($result[0]->allocation['equipment']['name']);
    self::assertNull($result[0]->allocation['site']);
    self::assertNull($result[0]->allocation['customer']);
  }

  /**
   * @param 'available'|'snapshot_missing' $snapshotState retained publication provenance
   */
  #[DataProvider('historicalSnapshotStates')]
  public function testPublishedDirectMaterialWithoutFinancialSnapshotRetainsIncompleteIdentity(string $snapshotState): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context(snapshotState: $snapshotState, publicationId: 'publication'));
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::never())->method('snapshots');
    $item = new MaintenanceCostItem('material:movement', 'material', null, 'movement', null, '20.000000', 'EUR', 'Seal', '2026-10-07', equipmentId: 'equipment');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$item], []);
    self::assertSame('equipment', $result[0]->equipmentId);
    self::assertSame(['identityState' => 'incomplete', 'equipment' => ['id' => 'equipment', 'name' => null, 'assetReference' => null], 'site' => null, 'customer' => null], $result[0]->allocation);
    self::assertSame('20.000000', $result[0]->amount);
  }

  /**
   * @param 'available'|'snapshot_missing' $snapshotState retained publication provenance
   */
  #[DataProvider('historicalSnapshotStates')]
  public function testProvenLateMaterialUsesCurrentIdentityWithoutChangingCapturedSource(string $snapshotState): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context(snapshotState: $snapshotState, publicationId: 'publication'));
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::once())->method('snapshots')->with('org', ['equipment'])->willReturn(['equipment' => $this->equipment()]);
    $original = new MaintenanceCostItem('material:earlier', 'material', null, 'earlier', null, '10.000000', 'EUR', 'Earlier seal', '2026-10-07', equipmentId: 'equipment');
    $late = new MaintenanceCostItem('material:late', 'material', null, 'late', null, '20.000000', 'EUR', 'Late seal', '2026-10-08', correctionOf: 'publication:publication', equipmentId: 'equipment');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$original, $late], ['material:earlier' => $original]);
    self::assertNotNull($result[0]->allocation);
    self::assertSame('incomplete', $result[0]->allocation['identityState']);
    self::assertNull($result[0]->allocation['site']);
    self::assertNotNull($result[1]->allocation);
    self::assertSame('live', $result[1]->allocation['identityState']);
    self::assertSame(['id' => 'site', 'name' => 'Asset site'], $result[1]->allocation['site']);
    self::assertSame(['id' => 'customer', 'name' => 'Asset client'], $result[1]->allocation['customer']);
    self::assertSame('publication:publication', $result[1]->correctionOf);
    self::assertSame('20.000000', $result[1]->amount);
    self::assertNull($original->allocation);
    self::assertSame('10.000000', $original->amount);
  }

  #[DataProvider('unprovenPublicationReferences')]
  public function testUnprovenLateMaterialRemainsIncomplete(?string $publicationId): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context(snapshotState: 'snapshot_missing', publicationId: $publicationId));
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::never())->method('snapshots');
    $item = new MaintenanceCostItem('material:late', 'material', null, 'late', null, '20.000000', 'EUR', 'Seal', '2026-10-08', correctionOf: 'publication:publication', equipmentId: 'equipment');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$item], []);
    self::assertSame(['identityState' => 'incomplete', 'equipment' => ['id' => 'equipment', 'name' => null, 'assetReference' => null], 'site' => null, 'customer' => null], $result[0]->allocation);
  }

  public function testCorrectionOfLegacyCapturedMaterialDoesNotAcquireCurrentIdentity(): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context(snapshotState: 'snapshot_missing', publicationId: 'publication'));
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::never())->method('snapshots');
    $original = new MaintenanceCostItem('material:movement', 'material', null, 'movement', null, '20.000000', 'EUR', 'Seal', '2026-10-07', equipmentId: 'equipment');
    $return = new MaintenanceCostItem('material:return', 'material', null, 'return', null, '-5.000000', 'EUR', 'Returned seal', '2026-10-08', correctionOf: 'material:movement', equipmentId: 'equipment');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$return], ['material:movement' => $original]);
    self::assertSame(['identityState' => 'incomplete', 'equipment' => ['id' => 'equipment', 'name' => null, 'assetReference' => null], 'site' => null, 'customer' => null], $result[0]->allocation);
    self::assertSame('-5.000000', $result[0]->amount);
    self::assertSame('material:movement', $result[0]->correctionOf);
    self::assertNull($original->allocation);
  }

  public function testLateTargetedFactUsesCapturedTaskIdentity(): void
  {
    $identity = $this->equipment();
    $task = new InterventionPublishedWorkFact('task', 'repair', 'completed', null, null, 'equipment', $identity->site, $identity->customer, $identity, null, true, 30, 0, null, null);
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context([$task], snapshotState: 'available', publicationId: 'publication'));
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::never())->method('snapshots');
    $item = new MaintenanceCostItem('time:late:1', 'time', 'task', 'late', 1, '20.000000', 'EUR', 'Late work', '2026-10-08', correctionOf: 'publication:publication');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$item], []);
    self::assertSame(['identityState' => 'captured', 'equipment' => ['id' => 'equipment', 'name' => 'Asset name', 'assetReference' => 'ASSET-01'], 'site' => $identity->site, 'customer' => $identity->customer], $result[0]->allocation);
  }

  public function testCorrectionsOfLateMaterialInheritSourceIdentityRegardlessOfOrder(): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context(snapshotState: 'snapshot_missing', publicationId: 'publication'));
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::once())->method('snapshots')->with('org', ['equipment'])->willReturn(['equipment' => $this->equipment()]);
    $late = new MaintenanceCostItem('material:late', 'material', null, 'late', null, '20.000000', 'EUR', 'Late seal', '2026-10-08', correctionOf: 'publication:publication', equipmentId: 'equipment');
    $return = new MaintenanceCostItem('material:return', 'material', null, 'return', null, '-5.000000', 'EUR', 'Returned seal', '2026-10-09', correctionOf: 'material:late');
    $adjustment = new MaintenanceCostItem('material:adjustment', 'material', null, 'adjustment', null, '1.000000', 'EUR', 'Return adjustment', '2026-10-10', correctionOf: 'material:return');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$adjustment, $return, $late], []);
    self::assertNotNull($result[2]->allocation);
    self::assertSame('live', $result[2]->allocation['identityState']);
    self::assertSame($result[2]->allocation, $result[0]->allocation);
    self::assertSame($result[2]->allocation, $result[1]->allocation);
    self::assertSame('equipment', $result[0]->equipmentId);
    self::assertSame('equipment', $result[1]->equipmentId);
    self::assertSame('1.000000', $result[0]->amount);
    self::assertSame('-5.000000', $result[1]->amount);
    self::assertSame('material:return', $result[0]->correctionOf);
    self::assertSame('material:late', $result[1]->correctionOf);
  }

  public function testCyclicCurrentCorrectionsRetainTheirOwnIncompleteIdentities(): void
  {
    $work = $this->createStub(InterventionPublicationFactsPort::class);
    $work->method('economicContext')->willReturn($this->context(snapshotState: 'snapshot_missing', publicationId: 'publication'));
    $equipment = $this->createMock(InterventionEquipmentSnapshotPort::class);
    $equipment->expects(self::never())->method('snapshots');
    $first = new MaintenanceCostItem('material:first', 'material', null, 'first', null, '20.000000', 'EUR', 'First correction', '2026-10-08', correctionOf: 'material:second', equipmentId: 'first-equipment');
    $second = new MaintenanceCostItem('material:second', 'material', null, 'second', null, '-5.000000', 'EUR', 'Second correction', '2026-10-09', correctionOf: 'material:first', equipmentId: 'second-equipment');
    $self = new MaintenanceCostItem('material:self', 'material', null, 'self', null, '1.000000', 'EUR', 'Self correction', '2026-10-10', correctionOf: 'material:self', equipmentId: 'self-equipment');
    $result = new MaintenanceCostAllocationResolver($work, $equipment)->resolve('org', 'work', [$first, $second, $self], []);
    foreach ($result as $index => $item) {
      self::assertNotNull($item->allocation);
      self::assertSame('incomplete', $item->allocation['identityState']);
      self::assertSame([$first, $second, $self][$index]->equipmentId, $item->equipmentId);
      self::assertNotNull($item->allocation['equipment']);
      self::assertSame($item->equipmentId, $item->allocation['equipment']['id']);
      self::assertNull($item->allocation['site']);
      self::assertNull($item->allocation['customer']);
    }
  }

  /**
   * @return iterable<string,array{'available'|'snapshot_missing'}>
   */
  public static function historicalSnapshotStates(): iterable
  {
    yield 'missing operational dossier' => ['snapshot_missing'];
    yield 'available operational dossier' => ['available'];
  }

  /**
   * @return iterable<string,array{?string}>
   */
  public static function unprovenPublicationReferences(): iterable
  {
    yield 'missing publication anchor' => [null];
    yield 'different publication anchor' => ['other-publication'];
  }

  /**
   * @param list<InterventionPublishedWorkFact> $tasks
   * @param 'available'|'snapshot_missing'|'live' $snapshotState identity provenance
   */
  private function context(array $tasks = [], string $organization = 'org', string $snapshotState = 'live', ?string $publicationId = null): InterventionEconomicContext
  {
    return new InterventionEconomicContext('work', $organization, 1, 'Repair', 'corrective_maintenance', 'live' === $snapshotState ? 'draft' : 'published', 1, new DateTimeImmutable('2026-10-07'), null, null, 'live' === $snapshotState ? null : new DateTimeImmutable('2026-10-07'), $publicationId, $snapshotState, 'available' === $snapshotState ? 1 : null, 'snapshot_missing' !== $snapshotState, ['id' => 'dossier-site', 'name' => 'Dossier site'], ['id' => 'dossier-client', 'name' => 'Dossier client'], $tasks);
  }

  private function equipment(): InterventionEquipmentSnapshot
  {
    return new InterventionEquipmentSnapshot('equipment', 'Asset name', 'ASSET-01', 'extinguisher', null, null, null, 'location', ['id' => 'site', 'name' => 'Asset site'], ['id' => 'customer', 'name' => 'Asset client']);
  }
}
