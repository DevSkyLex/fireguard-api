<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Webhook\Domain\ValueObject\WebhookDeliveryId;

/**
 * Test WebhookDeliveryId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookDeliveryId::class)]
final class WebhookDeliveryIdTest extends TestCase
{
  private const string UUID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function itBuildsFromAValidUuidString(): void
  {
    $id = WebhookDeliveryId::fromString(self::UUID);

    self::assertSame(self::UUID, $id->value);
    self::assertSame(self::UUID, (string) $id);
  }

  #[Test]
  public function itRejectsAnInvalidUuidString(): void
  {
    $this->expectException(InvalidValueException::class);

    WebhookDeliveryId::fromString('not-a-uuid');
  }
}
