<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Exception;

use Approval\Domain\Exception\DeferredActionNoLongerApplicableException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test DeferredActionNoLongerApplicableException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeferredActionNoLongerApplicableException::class)]
final class DeferredActionNoLongerApplicableExceptionTest extends TestCase
{
  #[Test]
  public function testBecauseSubjectChangedBuildsMessage(): void
  {
    $exception = DeferredActionNoLongerApplicableException::becauseSubjectChanged('equipment already deleted');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('The deferred action can no longer be applied: equipment already deleted', $exception->getMessage());
  }
}
