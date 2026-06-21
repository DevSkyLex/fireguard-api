<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Persistence\Doctrine\Mapper;

use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use Billing\Infrastructure\Persistence\Doctrine\Record\SubscriptionRecord;

/**
 * Mapper SubscriptionMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SubscriptionMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine subscription record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param SubscriptionRecord $record the persistence record
   *
   * @return Subscription the domain aggregate
   */
  public static function toDomain(SubscriptionRecord $record): Subscription
  {
    return Subscription::reconstitute(
      id: SubscriptionId::fromString($record->id),
      organizationId: $record->organizationId,
      stripeCustomerId: $record->stripeCustomerId,
      status: SubscriptionStatus::tryFrom($record->status) ?? SubscriptionStatus::INCOMPLETE,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      stripeSubscriptionId: $record->stripeSubscriptionId,
      planKey: $record->planKey,
      interval: null !== $record->interval ? BillingInterval::fromString($record->interval) : null,
      currentPeriodEnd: $record->currentPeriodEnd,
      cancelAtPeriodEnd: $record->cancelAtPeriodEnd,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps a subscription domain aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param Subscription $subscription the domain aggregate
   *
   * @return SubscriptionRecord the persistence record
   */
  public static function toRecord(Subscription $subscription): SubscriptionRecord
  {
    $record = new SubscriptionRecord();
    self::applyTo($record, $subscription);

    return $record;
  }

  /**
   * Method applyTo.
   *
   * Copies the aggregate state onto an existing or new record.
   *
   * @since 1.0.0
   *
   * @param SubscriptionRecord $record the persistence record to fill
   * @param Subscription $subscription the domain aggregate
   */
  public static function applyTo(SubscriptionRecord $record, Subscription $subscription): void
  {
    $record->id = (string) $subscription->id();
    $record->organizationId = $subscription->organizationId();
    $record->stripeCustomerId = $subscription->stripeCustomerId();
    $record->stripeSubscriptionId = $subscription->stripeSubscriptionId();
    $record->status = $subscription->status()->value;
    $record->planKey = $subscription->planKey();
    $record->interval = $subscription->interval()?->value;
    $record->currentPeriodEnd = $subscription->currentPeriodEnd();
    $record->cancelAtPeriodEnd = $subscription->cancelAtPeriodEnd();
    $record->createdAt = $subscription->createdAt();
    $record->updatedAt = $subscription->updatedAt();
  }
  // #endregion
}
