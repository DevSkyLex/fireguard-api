<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Exception;

use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantThreadNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantThreadNotFoundException::class)]
final class AssistantThreadNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsADescriptiveMessage(): void
  {
    $exception = AssistantThreadNotFoundException::withId('thread-42');

    self::assertInstanceOf(AssistantThreadNotFoundException::class, $exception);
    self::assertSame('Assistant thread with ID "thread-42" not found.', $exception->getMessage());
  }
}
