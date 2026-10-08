<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\ValueObject;

use DateTimeImmutable;
use Maintenance\Domain\ValueObject\{MaintenanceOccurrenceAttempt, MaintenanceOperationKind};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class MaintenanceOccurrenceAttemptTest
 *
 * Verifies work ownership and result receipts without conflating failed servicing with completion.
 *
 * @category Tests
 */
#[CoversClass(MaintenanceOccurrenceAttempt::class)]
final class MaintenanceOccurrenceAttemptTest extends TestCase
{
  // #region Methods
  /**
   * Method testUnreservedOccurrenceHasNoAttemptWorkOrReceipt
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnreservedOccurrenceHasNoAttemptWorkOrReceipt(): void
  {
    $attempt = new MaintenanceOccurrenceAttempt(0, null, null, null);

    self::assertSame(0, $attempt->number);
    self::assertNull($attempt->interventionId);
    self::assertNull($attempt->completedAt);
    self::assertNull($attempt->resultId);
  }

  /**
   * Method testReservedWorkAndRetryNeedNoReceiptBeforePublication
   *
   * @access public
   *
   * @param int $number the initial or retry attempt number
   *
   * @return void
   */
  #[Test]
  #[DataProvider('reservedAttemptNumbers')]
  public function testReservedWorkAndRetryNeedNoReceiptBeforePublication(int $number): void
  {
    $attempt = new MaintenanceOccurrenceAttempt($number, 'a1111111-1111-4111-8111-111111111111', null, null);

    self::assertSame($number, $attempt->number);
    self::assertSame('a1111111-1111-4111-8111-111111111111', $attempt->interventionId);
    self::assertNull($attempt->completedAt);
    self::assertNull($attempt->resultId);
  }

  /**
   * Method reservedAttemptNumbers
   *
   * @access public
   *
   * @return iterable<string, array{int}> work reservation and retry examples
   */
  public static function reservedAttemptNumbers(): iterable
  {
    yield 'first work reservation' => [1];
    yield 'retry' => [2];
  }

  /**
   * Method testFailedServicingRetainsAReceiptWithoutCompletingTheOccurrence
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFailedServicingRetainsAReceiptWithoutCompletingTheOccurrence(): void
  {
    $attempt = new MaintenanceOccurrenceAttempt(
      2,
      'a1111111-1111-4111-8111-111111111111',
      null,
      'b2222222-2222-4222-9222-222222222222',
    );

    self::assertSame(2, $attempt->number);
    self::assertSame('a1111111-1111-4111-8111-111111111111', $attempt->interventionId);
    self::assertNull($attempt->completedAt);
    self::assertSame('b2222222-2222-4222-9222-222222222222', $attempt->resultId);
  }

  /**
   * Method testCompletedAttemptRetainsItsReceiptAndExactCompletionInstant
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCompletedAttemptRetainsItsReceiptAndExactCompletionInstant(): void
  {
    $completedAt = new DateTimeImmutable('2026-10-08T14:15:16.123456+02:00');
    $attempt = new MaintenanceOccurrenceAttempt(
      1,
      'a1111111-1111-4111-8111-111111111111',
      $completedAt,
      'b2222222-2222-4222-9222-222222222222',
    );

    self::assertSame(1, $attempt->number);
    self::assertSame('a1111111-1111-4111-8111-111111111111', $attempt->interventionId);
    self::assertSame($completedAt, $attempt->completedAt);
    self::assertSame('b2222222-2222-4222-9222-222222222222', $attempt->resultId);
  }

  /**
   * Method testInconsistentWorkAndReceiptStatesAreRejected
   *
   * @access public
   *
   * @param int $number the supplied attempt number
   * @param ?string $interventionId the optional work identifier
   * @param ?string $completedAt the optional completion instant
   * @param ?string $resultId the optional publication receipt identifier
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidAttemptStates')]
  public function testInconsistentWorkAndReceiptStatesAreRejected(int $number, ?string $interventionId, ?string $completedAt, ?string $resultId): void
  {
    $this->expectException(InvalidValueException::class);

    new MaintenanceOccurrenceAttempt($number, $interventionId, null === $completedAt ? null : new DateTimeImmutable($completedAt), $resultId);
  }

  /**
   * Method invalidAttemptStates
   *
   * @access public
   *
   * @return iterable<string, array{int, ?string, ?string, ?string}> rejected attempt state combinations
   */
  public static function invalidAttemptStates(): iterable
  {
    $work = 'a1111111-1111-4111-8111-111111111111';
    $receipt = 'b2222222-2222-4222-9222-222222222222';
    $completedAt = '2026-10-08T14:15:16+02:00';

    yield 'negative number without work' => [-1, null, null, null];
    yield 'negative number with work' => [-1, $work, null, null];
    yield 'zero number with work' => [0, $work, null, null];
    yield 'positive number without work' => [1, null, null, null];
    yield 'retry number without work' => [2, null, null, null];
    yield 'receipt without work' => [0, null, null, $receipt];
    yield 'completion without work or receipt' => [0, null, $completedAt, null];
    yield 'completion and receipt without work' => [0, null, $completedAt, $receipt];
    yield 'completion without receipt' => [1, $work, $completedAt, null];
    yield 'malformed work identifier' => [1, 'not-a-uuid', null, null];
    yield 'empty work identifier' => [1, '', null, null];
    yield 'malformed failed receipt identifier' => [1, $work, null, 'not-a-uuid'];
    yield 'empty failed receipt identifier' => [1, $work, null, ''];
    yield 'malformed completed receipt identifier' => [1, $work, $completedAt, 'not-a-uuid'];
  }

  /**
   * Method testBeginningWorkPreservesTheUnattemptedSnapshotAndReplaysItsIdentity
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testBeginningWorkPreservesTheUnattemptedSnapshotAndReplaysItsIdentity(): void
  {
    $unattempted = MaintenanceOccurrenceAttempt::unattempted();

    $started = $unattempted->beginAttempt('a1111111-1111-4111-8111-111111111111');

    self::assertNotSame($unattempted, $started);
    self::assertSame(0, $unattempted->number);
    self::assertNull($unattempted->interventionId);
    self::assertNull($unattempted->completedAt);
    self::assertNull($unattempted->resultId);
    self::assertSame(1, $started->number);
    self::assertSame('a1111111-1111-4111-8111-111111111111', $started->interventionId);
    self::assertNull($started->completedAt);
    self::assertNull($started->resultId);
    self::assertSame($started, $started->beginAttempt('a1111111-1111-4111-8111-111111111111'));
  }

  /**
   * Method testExistingWorkRequiresAnExplicitRetryToChangeIdentity
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testExistingWorkRequiresAnExplicitRetryToChangeIdentity(): void
  {
    $attempt = new MaintenanceOccurrenceAttempt(1, 'a1111111-1111-4111-8111-111111111111', null, null);
    $this->expectException(InvalidValueException::class);

    $attempt->beginAttempt('b2222222-2222-4222-9222-222222222222');
  }

  /**
   * Method testRetryClearsTheFailedReceiptAndPreservesThePreviousSnapshot
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRetryClearsTheFailedReceiptAndPreservesThePreviousSnapshot(): void
  {
    $failed = new MaintenanceOccurrenceAttempt(
      1,
      'a1111111-1111-4111-8111-111111111111',
      null,
      'c3333333-3333-4333-a333-333333333333',
    );
    self::assertSame($failed, $failed->retryAttempt('a1111111-1111-4111-8111-111111111111'));

    $retry = $failed->retryAttempt('b2222222-2222-4222-9222-222222222222');

    self::assertNotSame($failed, $retry);
    self::assertSame(1, $failed->number);
    self::assertSame('a1111111-1111-4111-8111-111111111111', $failed->interventionId);
    self::assertSame('c3333333-3333-4333-a333-333333333333', $failed->resultId);
    self::assertNull($failed->completedAt);
    self::assertSame(2, $retry->number);
    self::assertSame('b2222222-2222-4222-9222-222222222222', $retry->interventionId);
    self::assertNull($retry->resultId);
    self::assertNull($retry->completedAt);
    self::assertSame($retry, $retry->retryAttempt('b2222222-2222-4222-9222-222222222222'));
  }

  /**
   * Method testValidationRetainsItsReceiptAndReplaysTheOriginalOutcome
   *
   * @access public
   *
   * @param MaintenanceOperationKind $kind the owning operation kind
   * @param bool $successful the original publication result
   * @param bool $completes whether that result completes the occurrence
   *
   * @return void
   */
  #[Test]
  #[DataProvider('validationOutcomes')]
  public function testValidationRetainsItsReceiptAndReplaysTheOriginalOutcome(MaintenanceOperationKind $kind, bool $successful, bool $completes): void
  {
    $work = new MaintenanceOccurrenceAttempt(1, 'a1111111-1111-4111-8111-111111111111', null, null);
    $validatedAt = new DateTimeImmutable('2026-10-08T14:15:16.123456+02:00');
    $receiptId = 'b2222222-2222-4222-9222-222222222222';

    $validated = $work->validateResult($kind, $successful, $receiptId, $validatedAt);

    self::assertNotSame($work, $validated);
    self::assertNull($work->resultId);
    self::assertNull($work->completedAt);
    self::assertSame($work->number, $validated->number);
    self::assertSame($work->interventionId, $validated->interventionId);
    self::assertSame($receiptId, $validated->resultId);
    self::assertSame($completes ? $validatedAt : null, $validated->completedAt);
    self::assertSame($validated, $validated->validateResult($kind, !$successful, $receiptId, $validatedAt->modify('+1 day')));
    self::assertSame($completes ? $validatedAt : null, $validated->completedAt);
  }

  /**
   * Method validationOutcomes
   *
   * @access public
   *
   * @return iterable<string, array{MaintenanceOperationKind, bool, bool}> receipt and completion outcomes
   */
  public static function validationOutcomes(): iterable
  {
    yield 'successful control' => [MaintenanceOperationKind::CONTROL, true, true];
    yield 'adverse control still completes' => [MaintenanceOperationKind::CONTROL, false, true];
    yield 'successful servicing' => [MaintenanceOperationKind::MAINTENANCE, true, true];
    yield 'failed servicing stays open' => [MaintenanceOperationKind::MAINTENANCE, false, false];
  }

  /**
   * Method testAnotherReceiptCannotReplaceAFailedOrCompletedResult
   *
   * @access public
   *
   * @param bool $completed whether the first receipt completed its occurrence
   *
   * @return void
   */
  #[Test]
  #[DataProvider('receiptStates')]
  public function testAnotherReceiptCannotReplaceAFailedOrCompletedResult(bool $completed): void
  {
    $attempt = new MaintenanceOccurrenceAttempt(
      1,
      'a1111111-1111-4111-8111-111111111111',
      $completed ? new DateTimeImmutable('2026-10-08T14:15:16+02:00') : null,
      'b2222222-2222-4222-9222-222222222222',
    );
    $this->expectException(InvalidValueException::class);

    $attempt->validateResult(MaintenanceOperationKind::MAINTENANCE, true, 'c3333333-3333-4333-a333-333333333333', new DateTimeImmutable('2026-10-09T14:15:16+02:00'));
  }

  /**
   * Method receiptStates
   *
   * @access public
   *
   * @return iterable<string, array{bool}> retained receipt states
   */
  public static function receiptStates(): iterable
  {
    yield 'failed servicing receipt' => [false];
    yield 'completed receipt' => [true];
  }

  /**
   * Method testUnattemptedWorkCannotBeValidatedOrRetried
   *
   * @access public
   *
   * @param bool $retry whether the attempted transition is a retry
   *
   * @return void
   */
  #[Test]
  #[DataProvider('unattemptedTransitions')]
  public function testUnattemptedWorkCannotBeValidatedOrRetried(bool $retry): void
  {
    $attempt = MaintenanceOccurrenceAttempt::unattempted();
    $this->expectException(InvalidValueException::class);

    if ($retry) {
      $attempt->retryAttempt('a1111111-1111-4111-8111-111111111111');
    } else {
      $attempt->validateResult(MaintenanceOperationKind::CONTROL, true, 'b2222222-2222-4222-9222-222222222222', new DateTimeImmutable('2026-10-08T14:15:16+02:00'));
    }
  }

  /**
   * Method unattemptedTransitions
   *
   * @access public
   *
   * @return iterable<string, array{bool}> transitions requiring existing work
   */
  public static function unattemptedTransitions(): iterable
  {
    yield 'retry' => [true];
    yield 'validation' => [false];
  }

  /**
   * Method testCompletedWorkCannotBeRetriedEvenWithTheSameIdentity
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCompletedWorkCannotBeRetriedEvenWithTheSameIdentity(): void
  {
    $attempt = new MaintenanceOccurrenceAttempt(
      1,
      'a1111111-1111-4111-8111-111111111111',
      new DateTimeImmutable('2026-10-08T14:15:16+02:00'),
      'b2222222-2222-4222-9222-222222222222',
    );
    $this->expectException(InvalidValueException::class);

    $attempt->retryAttempt('a1111111-1111-4111-8111-111111111111');
  }
  // #endregion
}
