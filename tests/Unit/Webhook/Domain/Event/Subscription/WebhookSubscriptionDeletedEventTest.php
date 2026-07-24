<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\Event\Subscription;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Domain\Event\Subscription\WebhookSubscriptionDeletedEvent;

/**
 * Test WebhookSubscriptionDeletedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookSubscriptionDeletedEvent::class)]
final class WebhookSubscriptionDeletedEventTest extends TestCase
{
  #[Test]
  public function itCarriesItsPayloadAndStampsAnOccurredAt(): void
  {
    $event = new WebhookSubscriptionDeletedEvent(
      organizationId: 'org-1',
      subscriptionId: 'sub-1',
      actorUserId: 'user-1',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('sub-1', $event->subscriptionId);
    self::assertSame('user-1', $event->actorUserId);
    self::assertNotSame('', $event->occurredAt->format('c'));
  }

  #[Test]
  public function theActorUserIdDefaultsToNull(): void
  {
    $event = new WebhookSubscriptionDeletedEvent(
      organizationId: 'org-1',
      subscriptionId: 'sub-1',
    );

    self::assertNull($event->actorUserId);
  }
}
