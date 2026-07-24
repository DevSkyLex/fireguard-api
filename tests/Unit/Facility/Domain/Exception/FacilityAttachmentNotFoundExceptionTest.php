<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Exception;

use Facility\Domain\Exception\FacilityAttachmentNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test FacilityAttachmentNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityAttachmentNotFoundException::class)]
final class FacilityAttachmentNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsMessage(): void
  {
    $exception = FacilityAttachmentNotFoundException::withId('att-3');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Facility attachment with ID "att-3" not found.', $exception->getMessage());
  }
}
