<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Model\Replacement;

use Equipment\Domain\Exception\EquipmentReplacementConflictException;
use Equipment\Domain\Model\Replacement\{EquipmentReplacement, ReplacementEquipment};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class EquipmentReplacementTest
 *
 * Freezes replacement lifecycle and chain invariants without persistence.
 *
 * @category Unit Tests
 */
final class EquipmentReplacementTest extends TestCase
{
  // #region Methods
  /**
   * Method invalidCandidates
   *
   * @access public
   *
   * @return iterable<string, array{ReplacementEquipment, ReplacementEquipment}> invalid candidate pairs
   */
  public static function invalidCandidates(): iterable
  {
    $old = new ReplacementEquipment('old', 'org', 'published', 'operational', 'site', 'Room');
    $stock = new ReplacementEquipment('new', 'org', 'published', 'in_stock', null, null);
    yield 'same asset' => [$old, $old];
    yield 'foreign successor' => [$old, new ReplacementEquipment('new', 'other', 'published', 'in_stock', null, null)];
    yield 'draft predecessor' => [new ReplacementEquipment('old', 'org', 'draft', 'operational', 'site', null), $stock];
    yield 'retired predecessor' => [new ReplacementEquipment('old', 'org', 'published', 'decommissioned', 'site', null), $stock];
    yield 'already replaced predecessor' => [new ReplacementEquipment('old', 'org', 'published', 'operational', 'site', null, successorId: 'previous'), $stock];
    yield 'draft successor' => [$old, new ReplacementEquipment('new', 'org', 'draft', 'in_stock', null, null)];
    yield 'deployed successor' => [$old, new ReplacementEquipment('new', 'org', 'published', 'operational', 'site', null)];
    yield 'previous successor' => [$old, new ReplacementEquipment('new', 'org', 'published', 'in_stock', null, null, predecessorId: 'previous')];
    yield 'replacement chain reused' => [$old, new ReplacementEquipment('new', 'org', 'published', 'in_stock', null, null, successorId: 'previous')];
    yield 'deployed predecessor without site' => [new ReplacementEquipment('old', 'org', 'published', 'operational', null, null), $stock];
  }

  /**
   * Method refusesInvalidCandidates
   *
   * @access public
   *
   * @param ReplacementEquipment $old the invalid predecessor
   * @param ReplacementEquipment $successor the invalid successor
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidCandidates')]
  public function refusesInvalidCandidates(ReplacementEquipment $old, ReplacementEquipment $successor): void
  {
    $this->expectException(EquipmentReplacementConflictException::class);
    new EquipmentReplacement($old, $successor);
  }

  /**
   * Method preservesChainsAndCommissionsAReplacementForMaintenance
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function preservesChainsAndCommissionsAReplacementForMaintenance(): void
  {
    $replacement = new EquipmentReplacement(
      new ReplacementEquipment('old', 'org', 'published', 'under_maintenance', 'site', 'Room', predecessorId: 'earlier'),
      new ReplacementEquipment('new', 'org', 'published', 'in_stock', null, null),
    );
    self::assertSame('operational', $replacement->successorStatus());
    self::assertSame('earlier', $replacement->predecessor->predecessorId);
  }

  /**
   * Method replacingAReserveKeepsItsSuccessorInStock
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function replacingAReserveKeepsItsSuccessorInStock(): void
  {
    $replacement = new EquipmentReplacement(
      new ReplacementEquipment('old', 'org', 'published', 'in_stock', null, null),
      new ReplacementEquipment('new', 'org', 'published', 'in_stock', null, null),
    );
    self::assertSame('in_stock', $replacement->successorStatus());
  }
  // #endregion
}
