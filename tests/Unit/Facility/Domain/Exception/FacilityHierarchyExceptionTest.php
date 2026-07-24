<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Exception;

use Facility\Domain\Exception\FacilityHierarchyException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityHierarchyException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityHierarchyException::class)]
final class FacilityHierarchyExceptionTest extends TestCase
{
  #[Test]
  public function testCannotUseSelfAsParent(): void
  {
    $exception = FacilityHierarchyException::cannotUseSelfAsParent();

    self::assertInstanceOf(InvalidArgumentException::class, $exception);
    self::assertSame('A facility cannot be its own parent.', $exception->getMessage());
  }

  #[Test]
  public function testParentInAnotherOrganization(): void
  {
    $exception = FacilityHierarchyException::parentInAnotherOrganization();

    self::assertSame('Parent facility must belong to the same organization.', $exception->getMessage());
  }

  #[Test]
  public function testHierarchyCycleDetected(): void
  {
    $exception = FacilityHierarchyException::hierarchyCycleDetected();

    self::assertSame('Cannot move facility: hierarchy cycle detected.', $exception->getMessage());
  }
}
