<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Exception;

use Assistant\Domain\Exception\AssistantValidationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantValidationException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantValidationException::class)]
final class AssistantValidationExceptionTest extends TestCase
{
  #[Test]
  public function testBlankBodyBuildsAMessage(): void
  {
    $exception = AssistantValidationException::blankBody();

    self::assertInstanceOf(AssistantValidationException::class, $exception);
    self::assertSame('An assistant message body cannot be blank.', $exception->getMessage());
  }

  #[Test]
  public function testModelNotAllowedNamesTheRejectedModel(): void
  {
    $exception = AssistantValidationException::modelNotAllowed('gpt-forbidden');

    self::assertSame(
      'The model "gpt-forbidden" is not permitted by the configured allowlist.',
      $exception->getMessage(),
    );
  }
}
