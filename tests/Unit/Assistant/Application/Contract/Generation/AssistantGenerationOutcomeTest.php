<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Contract\Generation;

use Assistant\Application\Contract\Generation\AssistantGenerationOutcome;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantGenerationOutcome.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantGenerationOutcome::class)]
final class AssistantGenerationOutcomeTest extends TestCase
{
  #[Test]
  public function testSuccessfulOutcomeCarriesBodyAndTokenCount(): void
  {
    $outcome = new AssistantGenerationOutcome(body: 'Here is the answer.', tokenCount: 42);

    self::assertSame('Here is the answer.', $outcome->body);
    self::assertSame(42, $outcome->tokenCount);
    self::assertNull($outcome->errorCode);
    self::assertNull($outcome->errorMessage);
    self::assertTrue($outcome->isSuccessful());
  }

  #[Test]
  public function testFailedOutcomeIsNotSuccessful(): void
  {
    $outcome = new AssistantGenerationOutcome(
      body: '',
      tokenCount: null,
      errorCode: 'timeout',
      errorMessage: 'Upstream generation timed out.',
    );

    self::assertSame('timeout', $outcome->errorCode);
    self::assertSame('Upstream generation timed out.', $outcome->errorMessage);
    self::assertFalse($outcome->isSuccessful());
  }
}
