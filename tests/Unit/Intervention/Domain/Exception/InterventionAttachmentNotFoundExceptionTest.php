<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Exception;

use Intervention\Domain\Exception\InterventionAttachmentNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test InterventionAttachmentNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionAttachmentNotFoundException::class)]
final class InterventionAttachmentNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsADescriptiveRuntimeException(): void
  {
    $exception = InterventionAttachmentNotFoundException::withId('att-42');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Intervention attachment with ID "att-42" not found.', $exception->getMessage());
  }
}
