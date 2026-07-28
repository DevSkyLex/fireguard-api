<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;
use Webhook\Infrastructure\Persistence\Doctrine\Mapper\WebhookSubscriptionMapper;
use Webhook\Infrastructure\Persistence\Doctrine\Record\WebhookSubscriptionRecord;

/**
 * Test WebhookSubscriptionMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookSubscriptionMapper::class)]
final class WebhookSubscriptionMapperTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a20';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a21';

  #[Test]
  public function testToDomainReconstitutesTheAggregateFromTheRecord(): void
  {
    $record = $this->record();

    $subscription = WebhookSubscriptionMapper::toDomain($record);

    self::assertSame(self::SUBSCRIPTION_ID, (string) $subscription->id());
    self::assertSame(self::ORGANIZATION_ID, $subscription->organizationId());
    self::assertSame('https://example.com/hook', $subscription->url());
    self::assertSame('cipher', $subscription->secretCiphertext());
    self::assertSame(['intervention.published'], $subscription->eventTypes());
    self::assertTrue($subscription->isActive());
    self::assertSame('Integration', $subscription->description());
    self::assertEquals($record->createdAt, $subscription->createdAt());
    self::assertEquals($record->updatedAt, $subscription->updatedAt());
  }

  #[Test]
  public function testToRecordPopulatesEveryColumn(): void
  {
    $createdAt = new DateTimeImmutable('2026-07-18T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-07-18T01:00:00+00:00');

    $subscription = WebhookSubscription::reconstitute(
      id: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      url: 'https://example.com/other',
      secretCiphertext: 'other-cipher',
      eventTypes: ['intervention.closed'],
      isActive: false,
      description: 'Disabled',
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    $record = new WebhookSubscriptionRecord();

    WebhookSubscriptionMapper::toRecord($subscription, $record);

    self::assertSame(self::SUBSCRIPTION_ID, $record->id);
    self::assertSame(self::ORGANIZATION_ID, $record->organizationId);
    self::assertSame('https://example.com/other', $record->url);
    self::assertSame('other-cipher', $record->secretCiphertext);
    self::assertSame(['intervention.closed'], $record->eventTypes);
    self::assertFalse($record->isActive);
    self::assertSame('Disabled', $record->description);
    self::assertSame($createdAt, $record->createdAt);
    self::assertSame($updatedAt, $record->updatedAt);
  }

  #[Test]
  public function testRoundTripPreservesTheRecordState(): void
  {
    $record = $this->record();

    $roundTripped = new WebhookSubscriptionRecord();
    WebhookSubscriptionMapper::toRecord(WebhookSubscriptionMapper::toDomain($record), $roundTripped);

    self::assertEquals($record, $roundTripped);
  }

  /**
   * Method record.
   *
   * @return WebhookSubscriptionRecord a fully populated persistence record
   */
  private function record(): WebhookSubscriptionRecord
  {
    $record = new WebhookSubscriptionRecord();
    $record->id = self::SUBSCRIPTION_ID;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->url = 'https://example.com/hook';
    $record->secretCiphertext = 'cipher';
    $record->eventTypes = ['intervention.published'];
    $record->isActive = true;
    $record->description = 'Integration';
    $record->createdAt = new DateTimeImmutable('2026-07-18T00:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-07-18T01:00:00+00:00');

    return $record;
  }
}
