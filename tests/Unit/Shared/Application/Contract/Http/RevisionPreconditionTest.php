<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Contract\Http;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{RevisionMismatchException, RevisionMissingException, RevisionPrecondition};

use const PHP_INT_MAX;

/**
 * Class RevisionPreconditionTest.
 *
 * Verifies typed parsing failures and historical numeric conversion independently of HTTP.
 *
 * @category Contract Tests
 */
#[CoversClass(RevisionPrecondition::class)]
final class RevisionPreconditionTest extends TestCase
{
  // #region Methods
  /**
   * Method testAbsentRevisionsUseTheMissingConditionContract.
   */
  #[DataProvider('missingConditions')]
  public function testAbsentRevisionsUseTheMissingConditionContract(?string $ifMatch): void
  {
    $this->expectException(RevisionMissingException::class);
    $this->expectExceptionMessage('If-Match is required for this mutation.');
    RevisionPrecondition::expectedRevision($ifMatch);
  }

  /**
   * Method missingConditions.
   *
   * @return iterable<string, array{?string}> the missing header cases
   */
  public static function missingConditions(): iterable
  {
    yield 'absent' => [null];
    yield 'empty' => [''];
  }

  /**
   * Method testWildcardUsesTheMismatchContract.
   */
  public function testWildcardUsesTheMismatchContract(): void
  {
    $this->expectException(RevisionMismatchException::class);
    $this->expectExceptionMessage('The resource revision is stale.');
    RevisionPrecondition::expectedRevision('*');
  }

  /**
   * Method testOriginalRegexAndIntegerConversionRemainCompatible.
   */
  public function testOriginalRegexAndIntegerConversionRemainCompatible(): void
  {
    self::assertSame(7, RevisionPrecondition::expectedRevision("\"revision-7\"\n"));
    self::assertSame(PHP_INT_MAX, RevisionPrecondition::expectedRevision('"revision-99999999999999999999999999999999999999"'));
    self::assertTrue(RevisionPrecondition::matches(0, 0));
    self::assertFalse(RevisionPrecondition::matches(0, 1));
  }
  // #endregion
}
