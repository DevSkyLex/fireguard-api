<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;

/**
 * Test WebhookSubscriptionNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookSubscriptionNotFoundException::class)]
final class WebhookSubscriptionNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function withIdBuildsAMessageCarryingTheIdentifier(): void
  {
    $exception = WebhookSubscriptionNotFoundException::withId('sub-42');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Webhook subscription with ID "sub-42" not found.', $exception->getMessage());
  }
}
