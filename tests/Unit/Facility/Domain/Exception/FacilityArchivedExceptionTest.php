<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Exception;

use Facility\Domain\Exception\FacilityArchivedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test FacilityArchivedException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityArchivedException::class)]
final class FacilityArchivedExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsMessage(): void
  {
    $exception = FacilityArchivedException::withId('fac-9');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Facility with ID "fac-9" is archived and cannot be used.', $exception->getMessage());
  }
}
