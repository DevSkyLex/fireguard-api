<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Exception;

use Facility\Domain\Exception\FacilityCodeAlreadyExistsException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test FacilityCodeAlreadyExistsException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityCodeAlreadyExistsException::class)]
final class FacilityCodeAlreadyExistsExceptionTest extends TestCase
{
  #[Test]
  public function testWithCodeBuildsMessage(): void
  {
    $exception = FacilityCodeAlreadyExistsException::withCode('SITE-A');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Facility code "SITE-A" already exists for this organization.', $exception->getMessage());
  }
}
