<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Exception;

use DomainException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerExceptionUnwrapperTrait;
use Throwable;

/**
 * Test MessengerExceptionUnwrapperTraitTest.
 *
 * @category Trait Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(MessengerExceptionUnwrapperTrait::class)]
final class MessengerExceptionUnwrapperTraitTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItReturnsTheExceptionItselfWhenItAlreadyMatches(): void
  {
    $exception = new RuntimeException('Boom.');

    self::assertSame($exception, $this->find($exception, RuntimeException::class));
  }

  #[Test]
  public function testItWalksThePreviousChain(): void
  {
    $root = new DomainException('Root cause.');
    $wrapped = new RuntimeException('Handler failed.', 0, $root);

    self::assertSame($root, $this->find($wrapped, DomainException::class));
  }

  #[Test]
  public function testItWalksSeveralLevelsDeep(): void
  {
    $root = new InvalidArgumentException('Deep cause.');
    $middle = new DomainException('Middle.', 0, $root);
    $outer = new RuntimeException('Outer.', 0, $middle);

    self::assertSame($root, $this->find($outer, InvalidArgumentException::class));
  }

  #[Test]
  public function testItReturnsNullWhenNothingMatches(): void
  {
    $wrapped = new RuntimeException('Outer.', 0, new DomainException('Inner.'));

    self::assertNull($this->find($wrapped, InvalidArgumentException::class));
  }

  #[Test]
  public function testItMatchesOnAParentClass(): void
  {
    $exception = new InvalidArgumentException('Specific.');

    self::assertSame($exception, $this->find($exception, LogicException::class));
  }
  // #endregion

  // #region Helpers
  /**
   * @param class-string<Throwable> $expectedClass
   */
  private function find(Throwable $exception, string $expectedClass): ?Throwable
  {
    $unwrapper = new class () {
      use MessengerExceptionUnwrapperTrait;

      /**
       * @param class-string<Throwable> $expectedClass
       */
      public function __invoke(Throwable $exception, string $expectedClass): ?Throwable
      {
        return $this->findException($exception, $expectedClass);
      }
    };

    return $unwrapper($exception, $expectedClass);
  }
  // #endregion
}
