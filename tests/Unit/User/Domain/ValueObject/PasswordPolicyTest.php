<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use User\Domain\ValueObject\PasswordPolicy;

use function str_repeat;

/**
 * Class PasswordPolicyTest
 *
 * Pins the historical default and configurable character and length rules.
 *
 * @category ValueObject Tests
 */
#[CoversClass(PasswordPolicy::class)]
final class PasswordPolicyTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testDefaultsPreserveHistoricalRequirements(): void
  {
    $policy = new PasswordPolicy();
    self::assertSame('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@$!%*?&#', $policy->allowedInitialCharacters());
    self::assertSame([], $policy->violations('Abcdef1!'));
    self::assertSame(['minimum_length', 'complexity'], $policy->violations('short'));
    self::assertSame(['complexity'], $policy->violations('Abcdef12'));
    self::assertSame(['complexity'], $policy->violations('éAbcdef1!'));
    self::assertSame(['maximum_length'], $policy->violations('Abcdef1!' . str_repeat('a', 121)));
  }

  #[Test]
  public function testCustomPolicyCanDisableComplexityAndCountsCharacters(): void
  {
    $policy = new PasswordPolicy(4, 8, false, false, false, '');
    self::assertNull($policy->allowedInitialCharacters());
    self::assertSame([], $policy->violations('éééé'));
    self::assertSame([], $policy->violations('abcd'));
    self::assertSame(['minimum_length'], $policy->violations('abc'));
    self::assertSame(['maximum_length'], $policy->violations('abcdefghi'));
  }

  #[Test]
  public function testCustomSpecialCharactersAreLiteral(): void
  {
    $policy = new PasswordPolicy(4, 12, false, false, false, '[]-');
    self::assertSame([], $policy->violations('abc['));
    self::assertSame([], $policy->violations('abc-'));
    self::assertSame(['complexity'], $policy->violations('abc!'));
  }

  #[Test]
  public function testCustomUnicodeAlphabetMatchesWholeCharacters(): void
  {
    $policy = new PasswordPolicy(4, 12, false, false, false, 'é');
    self::assertSame([], $policy->violations('abcdé'));
    self::assertSame(['complexity'], $policy->violations('abcdà'));
    self::assertNull($policy->allowedInitialCharacters());
  }

  #[Test]
  #[DataProvider('invalidBounds')]
  public function testInvalidConfigurationIsRejected(int $minimum, int $maximum): void
  {
    $this->expectException(InvalidValueException::class);
    new PasswordPolicy($minimum, $maximum);
  }

  /**
   * Method invalidBounds
   *
   * @access public
   *
   * @return iterable<string, array{int, int}> invalid deployment bounds
   */
  public static function invalidBounds(): iterable
  {
    yield 'empty minimum' => [0, 128];
    yield 'inverted range' => [12, 8];
    yield 'unbounded maximum' => [8, 4097];
  }
  // #endregion
}
