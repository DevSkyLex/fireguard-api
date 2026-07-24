<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Exception;

use Equipment\Domain\Exception\TagNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test TagNotFoundExceptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TagNotFoundException::class)]
final class TagNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsWithId(): void
  {
    $exception = TagNotFoundException::withId('tag-1');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), 'tag-1'));
  }

  #[Test]
  public function itBuildsWithName(): void
  {
    $exception = TagNotFoundException::withName('critical');

    self::assertTrue(str_contains($exception->getMessage(), 'critical'));
  }
}
