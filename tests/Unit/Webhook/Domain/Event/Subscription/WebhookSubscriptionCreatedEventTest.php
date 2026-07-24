<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\Event\Subscription;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Domain\Event\Subscription\WebhookSubscriptionCreatedEvent;

/**
 * Test WebhookSubscriptionCreatedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookSubscriptionCreatedEvent::class)]
final class WebhookSubscriptionCreatedEventTest extends TestCase
{
  #[Test]
  public function itCarriesItsPayloadAndStampsAnOccurredAt(): void
  {
    $event = new WebhookSubscriptionCreatedEvent(
      organizationId: 'org-1',
      subscriptionId: 'sub-1',
      urlHost: 'example.com',
      eventTypes: ['intervention.published'],
      actorUserId: 'user-1',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('sub-1', $event->subscriptionId);
    self::assertSame('example.com', $event->urlHost);
    self::assertSame(['intervention.published'], $event->eventTypes);
    self::assertSame('user-1', $event->actorUserId);
    self::assertNotSame('', $event->occurredAt->format('c'));
  }

  #[Test]
  public function theActorUserIdDefaultsToNull(): void
  {
    $event = new WebhookSubscriptionCreatedEvent(
      organizationId: 'org-1',
      subscriptionId: 'sub-1',
      urlHost: 'example.com',
      eventTypes: [],
    );

    self::assertNull($event->actorUserId);
  }
}
