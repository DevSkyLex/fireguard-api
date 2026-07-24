<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Service;

use Assistant\Domain\Exception\AssistantValidationException;
use Assistant\Domain\Service\AssistantModelPolicy;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantModelPolicyTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantModelPolicy::class)]
final class AssistantModelPolicyTest extends TestCase
{
  #[Test]
  public function testIsAllowedAcceptsAModelInTheAllowlist(): void
  {
    $policy = new AssistantModelPolicy(['llama3', 'mistral']);

    self::assertTrue($policy->isAllowed('llama3'));
  }

  #[Test]
  public function testIsAllowedRejectsAModelOutsideTheAllowlist(): void
  {
    $policy = new AssistantModelPolicy(['llama3', 'mistral']);

    self::assertFalse($policy->isAllowed('gpt-4'));
  }

  #[Test]
  public function testIsAllowedTrimsWhitespaceInTheConfiguredAllowlist(): void
  {
    $policy = new AssistantModelPolicy([' llama3 ', '', 'mistral']);

    self::assertTrue($policy->isAllowed('llama3'));
  }

  #[Test]
  public function testAnEmptyAllowlistDeniesEveryModelRatherThanPermittingAny(): void
  {
    $policy = new AssistantModelPolicy([]);

    self::assertFalse($policy->isAllowed('llama3'));
  }

  #[Test]
  public function testAssertAllowedThrowsWhenNotAllowed(): void
  {
    $policy = new AssistantModelPolicy(['llama3']);

    $this->expectException(AssistantValidationException::class);

    $policy->assertAllowed('gpt-4');
  }

  #[Test]
  public function testAssertAllowedDoesNotThrowWhenAllowed(): void
  {
    $policy = new AssistantModelPolicy(['llama3']);

    $policy->assertAllowed('llama3');

    self::assertTrue($policy->isAllowed('llama3'));
  }
}
