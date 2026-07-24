<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\Model\Subscription;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Test WebhookSubscription.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookSubscription::class)]
final class WebhookSubscriptionTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function itCreatesAnActiveSubscription(): void
  {
    $subscription = $this->newSubscription();

    self::assertSame(self::SUBSCRIPTION_ID, (string) $subscription->id());
    self::assertSame(self::ORGANIZATION_ID, $subscription->organizationId());
    self::assertSame('https://example.com/hook', $subscription->url());
    self::assertSame('CIPHER', $subscription->secretCiphertext());
    self::assertSame(['intervention.published'], $subscription->eventTypes());
    self::assertTrue($subscription->isActive());
    self::assertSame('My hook', $subscription->description());
    self::assertEquals($subscription->createdAt(), $subscription->updatedAt());
  }

  #[Test]
  public function itDefaultsToAnEmptyDescription(): void
  {
    $subscription = WebhookSubscription::create(
      id: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      url: 'https://example.com/hook',
      secretCiphertext: 'CIPHER',
      eventTypes: ['intervention.published'],
    );

    self::assertSame('', $subscription->description());
  }

  #[Test]
  public function subscribesToReturnsTrueForAnActiveAllowedEventType(): void
  {
    $subscription = $this->newSubscription();

    self::assertTrue($subscription->subscribesTo('intervention.published'));
    self::assertFalse($subscription->subscribesTo('inspection.closed'));
  }

  #[Test]
  public function anInactiveSubscriptionSubscribesToNothing(): void
  {
    $subscription = $this->newSubscription();
    $subscription->update('https://example.com/hook', ['intervention.published'], false, 'My hook');

    self::assertFalse($subscription->subscribesTo('intervention.published'));
  }

  #[Test]
  public function itUpdatesMutableFields(): void
  {
    $subscription = $this->newSubscription();

    $subscription->update(
      url: 'https://new.example.com/hook',
      eventTypes: ['inspection.closed', 'facility.archived'],
      isActive: false,
      description: 'Updated',
    );

    self::assertSame('https://new.example.com/hook', $subscription->url());
    self::assertSame(['inspection.closed', 'facility.archived'], $subscription->eventTypes());
    self::assertFalse($subscription->isActive());
    self::assertSame('Updated', $subscription->description());
  }

  #[Test]
  public function itRotatesTheStoredCiphertext(): void
  {
    $subscription = $this->newSubscription();

    $subscription->rotateSecret('NEW_CIPHER');

    self::assertSame('NEW_CIPHER', $subscription->secretCiphertext());
  }

  #[Test]
  public function itReconstitutesFromPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    $subscription = WebhookSubscription::reconstitute(
      id: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      url: 'https://example.com/hook',
      secretCiphertext: 'CIPHER',
      eventTypes: ['inspection.closed'],
      isActive: false,
      description: 'Reconstituted',
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    self::assertFalse($subscription->isActive());
    self::assertSame(['inspection.closed'], $subscription->eventTypes());
    self::assertSame($createdAt, $subscription->createdAt());
    self::assertSame($updatedAt, $subscription->updatedAt());
  }

  /**
   * Method newSubscription.
   *
   * @return WebhookSubscription a fresh active subscription under test
   */
  private function newSubscription(): WebhookSubscription
  {
    return WebhookSubscription::create(
      id: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      url: 'https://example.com/hook',
      secretCiphertext: 'CIPHER',
      eventTypes: ['intervention.published'],
      description: 'My hook',
    );
  }
}
