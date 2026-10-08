<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\Model;

use DateTimeImmutable;
use Maintenance\Domain\Model\MaintenanceOccurrence;
use Maintenance\Domain\ValueObject\{MaintenanceOccurrenceAttempt, MaintenanceOperationKind};
use PHPUnit\Framework\Attributes\{CoversClass, Test, UsesClass};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class MaintenanceOccurrenceTest
 *
 * Verifies that planned work and failed maintenance do not falsely clear a due date.
 *
 * @category Tests
 */
#[CoversClass(MaintenanceOccurrence::class)]
#[UsesClass(MaintenanceOperationKind::class)]
#[UsesClass(MaintenanceOccurrenceAttempt::class)]
final class MaintenanceOccurrenceTest extends TestCase
{
  // #region Methods
  /**
   * Method testGeneratedWorkDoesNotCompleteOccurrenceAndCanBeReplayed
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGeneratedWorkDoesNotCompleteOccurrenceAndCanBeReplayed(): void
  {
    $occurrence = $this->occurrence();
    $interventionId = '018fa004-1111-7111-8111-111111111111';

    $occurrence->beginAttempt($interventionId);
    $occurrence->beginAttempt($interventionId);

    self::assertSame('open', $occurrence->state());
    self::assertNull($occurrence->completedAt());
    self::assertSame(1, $occurrence->attempt());
    self::assertSame($interventionId, $occurrence->interventionId());
    self::assertSame('2026-01-31', $occurrence->dueAt->format('Y-m-d'));
  }

  /**
   * Method testDefectiveControlCompletesAndReplayRetainsOriginalValidation
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDefectiveControlCompletesAndReplayRetainsOriginalValidation(): void
  {
    $occurrence = $this->occurrence();
    $occurrence->beginAttempt('018fa004-1111-7111-8111-111111111111');
    $resultId = '018fa005-1111-7111-8111-111111111111';

    self::assertTrue($occurrence->validateResult(MaintenanceOperationKind::CONTROL, false, $resultId, new DateTimeImmutable('2026-02-02T00:00:00+00:00')));
    self::assertTrue($occurrence->validateResult(MaintenanceOperationKind::CONTROL, false, $resultId, new DateTimeImmutable('2026-02-10T00:00:00+00:00')));
    self::assertSame('completed', $occurrence->state());
    self::assertSame('2026-02-02', $occurrence->completedAt()?->format('Y-m-d'));
    self::assertSame($resultId, $occurrence->resultId());
  }

  /**
   * Method testFailedMaintenanceRemainsOpenUntilExplicitRetrySucceeds
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFailedMaintenanceRemainsOpenUntilExplicitRetrySucceeds(): void
  {
    $occurrence = $this->occurrence();
    $occurrence->beginAttempt('018fa004-1111-7111-8111-111111111111');

    self::assertFalse($occurrence->validateResult(MaintenanceOperationKind::MAINTENANCE, false, '018fa005-1111-7111-8111-111111111111', new DateTimeImmutable('2026-02-01')));
    self::assertSame('open', $occurrence->state());
    self::assertNull($occurrence->completedAt());

    $retryId = '018fa006-1111-7111-8111-111111111111';
    $occurrence->retryAttempt($retryId);
    $occurrence->retryAttempt($retryId);

    self::assertSame(2, $occurrence->attempt());
    self::assertSame($retryId, $occurrence->interventionId());
    self::assertNull($occurrence->resultId());
    self::assertSame('2026-01-31', $occurrence->dueAt->format('Y-m-d'));
    self::assertTrue($occurrence->validateResult(MaintenanceOperationKind::MAINTENANCE, true, '018fa007-1111-7111-8111-111111111111', new DateTimeImmutable('2026-02-04')));
    self::assertSame('completed', $occurrence->state());
  }

  /**
   * Method testAnotherGeneratedWorkNeedsExplicitRetry
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testAnotherGeneratedWorkNeedsExplicitRetry(): void
  {
    $occurrence = $this->occurrence();
    $occurrence->beginAttempt('018fa004-1111-7111-8111-111111111111');
    $this->expectException(InvalidValueException::class);

    $occurrence->beginAttempt('018fa006-1111-7111-8111-111111111111');
  }

  /**
   * Method testCompletedOccurrenceCannotBeRetried
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCompletedOccurrenceCannotBeRetried(): void
  {
    $occurrence = $this->occurrence();
    $occurrence->beginAttempt('018fa004-1111-7111-8111-111111111111');
    $occurrence->validateResult(MaintenanceOperationKind::CONTROL, true, '018fa005-1111-7111-8111-111111111111', new DateTimeImmutable('2026-02-01'));
    $this->expectException(InvalidValueException::class);

    $occurrence->retryAttempt('018fa006-1111-7111-8111-111111111111');
  }

  /**
   * Method testResultRequiresGeneratedWork
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testResultRequiresGeneratedWork(): void
  {
    $this->expectException(InvalidValueException::class);

    $this->occurrence()->validateResult(MaintenanceOperationKind::CONTROL, true, '018fa005-1111-7111-8111-111111111111', new DateTimeImmutable('2026-02-01'));
  }

  /**
   * Method testSecondResultCannotOverwriteFailedAttempt
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSecondResultCannotOverwriteFailedAttempt(): void
  {
    $occurrence = $this->occurrence();
    $occurrence->beginAttempt('018fa004-1111-7111-8111-111111111111');
    $occurrence->validateResult(MaintenanceOperationKind::MAINTENANCE, false, '018fa005-1111-7111-8111-111111111111', new DateTimeImmutable('2026-02-01'));
    $this->expectException(InvalidValueException::class);

    $occurrence->validateResult(MaintenanceOperationKind::MAINTENANCE, true, '018fa007-1111-7111-8111-111111111111', new DateTimeImmutable('2026-02-02'));
  }

  /**
   * Method testResultCannotPredateReservation
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testResultCannotPredateReservation(): void
  {
    $occurrence = $this->occurrence();
    $occurrence->beginAttempt('018fa004-1111-7111-8111-111111111111');
    $this->expectException(InvalidValueException::class);

    $occurrence->validateResult(MaintenanceOperationKind::CONTROL, true, '018fa005-1111-7111-8111-111111111111', new DateTimeImmutable('2025-12-31'));
  }

  /**
   * Method testRestorationKeepsAttemptAndCompletionReceipt
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationKeepsAttemptAndCompletionReceipt(): void
  {
    $occurrence = MaintenanceOccurrence::reconstitute(
      '018fa001-1111-7111-8111-111111111111',
      '018fa002-1111-7111-8111-111111111111',
      new DateTimeImmutable('2026-01-31'),
      new DateTimeImmutable('2026-01-01'),
      new MaintenanceOccurrenceAttempt(
        2,
        '018fa004-1111-7111-8111-111111111111',
        new DateTimeImmutable('2026-02-02'),
        '018fa005-1111-7111-8111-111111111111',
      ),
    );

    self::assertSame('completed', $occurrence->state());
    self::assertSame(2, $occurrence->attempt());
    self::assertSame('2026-01-31', $occurrence->dueAt->format('Y-m-d'));
    self::assertSame('2026-02-02', $occurrence->completedAt()?->format('Y-m-d'));
  }

  /**
   * Method testRestorationRejectsUnlinkedCompletion
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationRejectsUnlinkedCompletion(): void
  {
    $this->expectException(InvalidValueException::class);

    MaintenanceOccurrence::reconstitute(
      '018fa001-1111-7111-8111-111111111111',
      '018fa002-1111-7111-8111-111111111111',
      new DateTimeImmutable('2026-01-31'),
      new DateTimeImmutable('2026-01-01'),
      new MaintenanceOccurrenceAttempt(0, null, new DateTimeImmutable('2026-02-02'), null),
    );
  }

  /**
   * Method testRestorationKeepsFailedReceiptAndRetryRetainsOriginalDueDate
   *
   * A failed receipt survives reload and replay until an explicit retry replaces
   * the work, without replacing the occurrence or its originally reserved date.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationKeepsFailedReceiptAndRetryRetainsOriginalDueDate(): void
  {
    $dueAt = new DateTimeImmutable('2026-01-31');
    $createdAt = new DateTimeImmutable('2026-01-01');
    $resultId = '018fa005-1111-7111-8111-111111111111';
    $occurrence = MaintenanceOccurrence::reconstitute(
      '018fa001-1111-7111-8111-111111111111',
      '018fa002-1111-7111-8111-111111111111',
      $dueAt,
      $createdAt,
      new MaintenanceOccurrenceAttempt(2, '018fa004-1111-7111-8111-111111111111', null, $resultId),
    );

    self::assertSame('open', $occurrence->state());
    self::assertSame($resultId, $occurrence->resultId());
    self::assertNull($occurrence->completedAt());
    self::assertFalse($occurrence->validateResult(MaintenanceOperationKind::MAINTENANCE, true, $resultId, new DateTimeImmutable('2025-12-31')));

    $occurrence->retryAttempt('018fa006-1111-7111-8111-111111111111');

    self::assertSame(3, $occurrence->attempt());
    self::assertSame('018fa006-1111-7111-8111-111111111111', $occurrence->interventionId());
    self::assertNull($occurrence->resultId());
    self::assertSame('018fa001-1111-7111-8111-111111111111', $occurrence->id);
    self::assertSame($dueAt, $occurrence->dueAt);
    self::assertSame($createdAt, $occurrence->createdAt);
  }

  /**
   * Method testRestorationRejectsCompletionBeforeReservation
   *
   * The aggregate checks the receipt against its own reservation time after
   * the attempt object validates the linked work and completion receipt.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationRejectsCompletionBeforeReservation(): void
  {
    $this->expectException(InvalidValueException::class);

    MaintenanceOccurrence::reconstitute(
      '018fa001-1111-7111-8111-111111111111',
      '018fa002-1111-7111-8111-111111111111',
      new DateTimeImmutable('2026-01-31'),
      new DateTimeImmutable('2026-01-01'),
      new MaintenanceOccurrenceAttempt(
        1,
        '018fa004-1111-7111-8111-111111111111',
        new DateTimeImmutable('2025-12-31'),
        '018fa005-1111-7111-8111-111111111111',
      ),
    );
  }

  /**
   * Method occurrence
   *
   * @access private
   *
   * @return MaintenanceOccurrence an unattempted overdue occurrence
   */
  private function occurrence(): MaintenanceOccurrence
  {
    return MaintenanceOccurrence::open(
      '018fa001-1111-7111-8111-111111111111',
      '018fa002-1111-7111-8111-111111111111',
      new DateTimeImmutable('2026-01-31T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
