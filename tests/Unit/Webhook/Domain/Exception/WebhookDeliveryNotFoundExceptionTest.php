<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webhook\Domain\Exception\WebhookDeliveryNotFoundException;

/**
 * Test WebhookDeliveryNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookDeliveryNotFoundException::class)]
final class WebhookDeliveryNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function withIdBuildsAMessageCarryingTheIdentifier(): void
  {
    $exception = WebhookDeliveryNotFoundException::withId('delivery-42');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Webhook delivery with ID "delivery-42" not found.', $exception->getMessage());
  }
}
