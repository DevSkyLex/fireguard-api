<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Exception;

use Intervention\Domain\Exception\PublicationNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test PublicationNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PublicationNotFoundException::class)]
final class PublicationNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsADescriptiveRuntimeException(): void
  {
    $exception = PublicationNotFoundException::withId('pub-3');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Publication with ID "pub-3" not found.', $exception->getMessage());
  }
}
