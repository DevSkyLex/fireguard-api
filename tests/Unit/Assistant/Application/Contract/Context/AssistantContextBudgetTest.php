<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Contract\Context;

use Assistant\Application\Contract\Context\AssistantContextBudget;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantContextBudget.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantContextBudget::class)]
final class AssistantContextBudgetTest extends TestCase
{
  #[Test]
  public function testExposesRemainingCharacters(): void
  {
    $budget = new AssistantContextBudget(remainingCharacters: 1_500);

    self::assertSame(1_500, $budget->remainingCharacters);
    self::assertFalse($budget->isExhausted());
  }

  #[Test]
  public function testIsExhaustedWhenNoBudgetRemains(): void
  {
    self::assertTrue(new AssistantContextBudget(0)->isExhausted());
    self::assertTrue(new AssistantContextBudget(-10)->isExhausted());
  }
}
