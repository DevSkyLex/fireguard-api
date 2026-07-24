<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Exception;

use Assistant\Domain\Exception\AssistantMessageIllegalStatusTransitionException;
use Assistant\Domain\ValueObject\AssistantMessageStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantMessageIllegalStatusTransitionException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessageIllegalStatusTransitionException::class)]
final class AssistantMessageIllegalStatusTransitionExceptionTest extends TestCase
{
  #[Test]
  public function testForTransitionBuildsADescriptiveMessage(): void
  {
    $exception = AssistantMessageIllegalStatusTransitionException::forTransition(
      AssistantMessageStatus::COMPLETE,
      AssistantMessageStatus::STREAMING,
      'msg-123',
    );

    self::assertInstanceOf(AssistantMessageIllegalStatusTransitionException::class, $exception);
    self::assertSame(
      'Assistant message "msg-123" cannot transition from "complete" to "streaming".',
      $exception->getMessage(),
    );
  }
}
