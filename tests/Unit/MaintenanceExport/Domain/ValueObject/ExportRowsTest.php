<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Domain\ValueObject;

use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\ValueObject\{ExportArtifact, ExportRows};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_fill_keys;
use function array_map;
use function range;

/**
 * Class ExportRowsTest
 *
 * Corrections are explicit compensating facts rather than rewrites of previously exported rows.
 *
 * @category UnitTest
 */
final class ExportRowsTest extends TestCase
{
  // #region Properties
  private const string ADJUSTMENT = 'cab50000-0000-4000-8000-000000000001';
  // #endregion

  // #region Methods
  #[Test]
  public function initialRowsHaveStableSourceOrderAndNoCorrectionLink(): void
  {
    $rows = ExportRows::initial(['z' => ['id' => 'z-source', 'minutes' => 30], 'a' => ['id' => 'a-source', 'minutes' => 15]]);
    self::assertSame([['id' => 'a-source', 'minutes' => 15, 'change' => 'add', 'correctionOf' => null], ['id' => 'z-source', 'minutes' => 30, 'change' => 'add', 'correctionOf' => null]], $rows);
  }

  #[Test]
  public function changedTimeAndCostCreateAnExactReversalAndFullReplacement(): void
  {
    $before = ['time:one' => ['id' => 'original-row', 'minutes' => 30, 'amount' => '123456789012345678.123456', 'sourceRevision' => 1]];
    $after = ['time:one' => ['id' => 'captured-new-row', 'minutes' => 60, 'amount' => '246913578024691356.246912', 'sourceRevision' => 2]];
    $rows = ExportRows::adjustment($before, $after, self::ADJUSTMENT);
    self::assertCount(2, $rows);
    self::assertSame('reverse', $rows[0]['change']);
    self::assertSame(-30, $rows[0]['minutes']);
    self::assertSame('-123456789012345678.123456', $rows[0]['amount']);
    self::assertSame('original-row', $rows[0]['correctionOf']);
    self::assertSame(1, $rows[0]['sourceRevision']);
    self::assertSame('add', $rows[1]['change']);
    self::assertSame(60, $rows[1]['minutes']);
    self::assertSame('246913578024691356.246912', $rows[1]['amount']);
    self::assertSame('original-row', $rows[1]['correctionOf']);
    self::assertSame(ExportArtifact::rowId(self::ADJUSTMENT . '|reverse|time:one'), $rows[0]['id']);
    self::assertSame(ExportArtifact::rowId(self::ADJUSTMENT . '|add|time:one'), $rows[1]['id']);
    self::assertSame('123456789012345678.123456', $before['time:one']['amount']);
  }

  #[Test]
  public function unknownCostRemainsUnknownInACompensatingRow(): void
  {
    $rows = ExportRows::adjustment(['cost:one' => ['id' => 'unknown-original', 'amount' => null, 'costComplete' => false]], ['cost:one' => ['id' => 'resolved-capture', 'amount' => '10.000000', 'costComplete' => true]], self::ADJUSTMENT);
    self::assertNull($rows[0]['amount']);
    self::assertFalse($rows[0]['costComplete']);
    self::assertSame('10.000000', $rows[1]['amount']);
    self::assertTrue($rows[1]['costComplete']);
  }

  #[Test]
  public function addedAndRemovedFactsDoNotFabricateReplacementRows(): void
  {
    $rows = ExportRows::adjustment(['removed' => ['id' => 'removed-source', 'amount' => '-2.000000']], ['added' => ['id' => 'added-source', 'amount' => '3.000000']], self::ADJUSTMENT);
    self::assertCount(2, $rows);
    self::assertSame('add', $rows[0]['change']);
    self::assertNull($rows[0]['correctionOf']);
    self::assertSame('reverse', $rows[1]['change']);
    self::assertSame('removed-source', $rows[1]['correctionOf']);
    self::assertSame('2.000000', $rows[1]['amount']);
  }

  #[Test]
  public function aTransferredArtifactRowIdentityAloneDoesNotInventACorrection(): void
  {
    try {
      ExportRows::adjustment(['same' => ['id' => 'earlier-artifact-row', 'minutes' => 60]], ['same' => ['id' => 'later-artifact-row', 'minutes' => 60]], self::ADJUSTMENT);
      self::fail('Only an actual fact change can create an adjustment.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('conflict', $error->reason);
    }
  }

  #[Test]
  public function anUnchangedBaselineHasAnExplicitConflict(): void
  {
    $facts = ['same' => ['id' => 'source-row', 'amount' => '10.000000']];

    try {
      ExportRows::adjustment($facts, $facts, self::ADJUSTMENT);
      self::fail('A no-change adjustment must be rejected.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('conflict', $error->reason);
    }
  }

  #[Test]
  public function jsonbObjectKeyOrderingCannotInventACorrection(): void
  {
    $before = ['same' => ['id' => 'preserved-row', 'amount' => '10.000000', 'minutes' => 60, 'context' => ['label' => 'Extincteur', 'status' => 'service']]];
    $after = ['same' => ['context' => ['status' => 'service', 'label' => 'Extincteur'], 'minutes' => 60, 'amount' => '10.000000', 'id' => 'captured-row']];

    try {
      ExportRows::adjustment($before, $after, self::ADJUSTMENT);
      self::fail('JSONB ordering is not a changed source fact.');
    } catch (MaintenanceExportException $error) {
      self::assertSame('conflict', $error->reason);
    }
  }

  #[Test]
  public function anExportCannotHaveNoRows(): void
  {
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('An export requires between one and 10000 rows.');
    ExportRows::initial([]);
  }

  #[Test]
  public function expandedCorrectionWorkCannotExceedTheSynchronousRowLimit(): void
  {
    $keys = array_map(static fn (int $index): string => 'row:' . $index, range(0, 5000));
    $before = array_fill_keys($keys, ['id' => 'source-row', 'minutes' => 1]);
    $after = array_fill_keys($keys, ['id' => 'new-row', 'minutes' => 2]);
    $this->expectException(MaintenanceExportException::class);
    $this->expectExceptionMessage('An export requires between one and 10000 rows.');
    ExportRows::adjustment($before, $after, self::ADJUSTMENT);
  }
  // #endregion
}
