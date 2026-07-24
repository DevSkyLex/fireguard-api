<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Exception;

use Messaging\Domain\Exception\MessagingSubjectNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MessagingSubjectNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingSubjectNotFoundException::class)]
final class MessagingSubjectNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function withIdBuildsAMessageCarryingTheTypeAndId(): void
  {
    $exception = MessagingSubjectNotFoundException::withId('facility', 'sub-7');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('facility subject with ID "sub-7" not found.', $exception->getMessage());
  }
}
