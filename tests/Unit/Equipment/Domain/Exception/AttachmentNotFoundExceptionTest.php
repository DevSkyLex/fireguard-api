<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Exception;

use Equipment\Domain\Exception\AttachmentNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test AttachmentNotFoundExceptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AttachmentNotFoundException::class)]
final class AttachmentNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsWithId(): void
  {
    $exception = AttachmentNotFoundException::withId('att-1');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), 'att-1'));
  }
}
