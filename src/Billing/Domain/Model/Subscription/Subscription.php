<?php

declare(strict_types=1);

namespace Billing\Domain\Model\Subscription;

use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use DateTimeImmutable;

/**
 * Model Subscription.
 *
 * Billing subscription aggregate. Holds the link between an organization and its
 * Stripe customer/subscription, the current lifecycle status, the billed plan
 * key and cadence, and the renewal window. There is at most one subscription per
 * organization. The aggregate is the local projection of the Stripe state kept in
 * sync by webhooks; Stripe remains the source of truth.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Subscription
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Subscription class.
   *
   * @since 1.0.0
   *
   * @param SubscriptionId $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $stripeCustomerId the Stripe customer identifier
   * @param SubscriptionStatus $status the current lifecycle status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?string $stripeSubscriptionId the Stripe subscription identifier when active
   * @param ?string $planKey the currently billed plan key
   * @param ?BillingInterval $interval the billing cadence
   * @param ?DateTimeImmutable $currentPeriodEnd the end of the current billing period
   * @param bool $cancelAtPeriodEnd whether cancellation is scheduled at period end
   */
  private function __construct(
    private SubscriptionId $id,
    private string $organizationId,
    private string $stripeCustomerId,
    private SubscriptionStatus $status,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?string $stripeSubscriptionId = null,
    private ?string $planKey = null,
    private ?BillingInterval $interval = null,
    private ?DateTimeImmutable $currentPeriodEnd = null,
    private bool $cancelAtPeriodEnd = false,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method start.
   *
   * Creates the initial subscription record at checkout time, before any payment
   * is confirmed. It only links the organization to its Stripe customer; the plan
   * and active state are filled in later by webhook synchronization.
   *
   * @since 1.0.0
   *
   * @param SubscriptionId $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $stripeCustomerId the Stripe customer identifier
   *
   * @return self the created subscription aggregate
   */
  public static function start(
    SubscriptionId $id,
    string $organizationId,
    string $stripeCustomerId,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      stripeCustomerId: $stripeCustomerId,
      status: SubscriptionStatus::INCOMPLETE,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a subscription aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param SubscriptionId $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $stripeCustomerId the Stripe customer identifier
   * @param SubscriptionStatus $status the current lifecycle status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?string $stripeSubscriptionId the Stripe subscription identifier when active
   * @param ?string $planKey the currently billed plan key
   * @param ?BillingInterval $interval the billing cadence
   * @param ?DateTimeImmutable $currentPeriodEnd the end of the current billing period
   * @param bool $cancelAtPeriodEnd whether cancellation is scheduled at period end
   *
   * @return self the reconstituted subscription aggregate
   */
  public static function reconstitute(
    SubscriptionId $id,
    string $organizationId,
    string $stripeCustomerId,
    SubscriptionStatus $status,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?string $stripeSubscriptionId = null,
    ?string $planKey = null,
    ?BillingInterval $interval = null,
    ?DateTimeImmutable $currentPeriodEnd = null,
    bool $cancelAtPeriodEnd = false,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      stripeCustomerId: $stripeCustomerId,
      status: $status,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      stripeSubscriptionId: $stripeSubscriptionId,
      planKey: $planKey,
      interval: $interval,
      currentPeriodEnd: $currentPeriodEnd,
      cancelAtPeriodEnd: $cancelAtPeriodEnd,
    );
  }

  /**
   * Method syncFromStripe.
   *
   * Applies the latest Stripe subscription state to the aggregate. Called when a
   * `customer.subscription.created` or `customer.subscription.updated` event is
   * received.
   *
   * @since 1.0.0
   *
   * @param string $stripeSubscriptionId the Stripe subscription identifier
   * @param SubscriptionStatus $status the current lifecycle status
   * @param string $planKey the billed plan key
   * @param ?BillingInterval $interval the billing cadence
   * @param ?DateTimeImmutable $currentPeriodEnd the end of the current billing period
   * @param bool $cancelAtPeriodEnd whether cancellation is scheduled at period end
   */
  public function syncFromStripe(
    string $stripeSubscriptionId,
    SubscriptionStatus $status,
    string $planKey,
    ?BillingInterval $interval,
    ?DateTimeImmutable $currentPeriodEnd,
    bool $cancelAtPeriodEnd,
  ): void {
    $this->stripeSubscriptionId = $stripeSubscriptionId;
    $this->status = $status;
    $this->planKey = $planKey;
    $this->interval = $interval;
    $this->currentPeriodEnd = $currentPeriodEnd;
    $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;
    $this->touch();
  }

  /**
   * Method scheduleCancellation.
   *
   * Flags the subscription to cancel at the end of the current billing period.
   * Access is retained until then; the status is left untouched (Stripe keeps it
   * active until the period ends). Mirrors the `cancel_at_period_end` toggle so
   * the UI reflects the change immediately, before the reconciling webhook.
   *
   * @since 1.0.0
   */
  public function scheduleCancellation(): void
  {
    $this->cancelAtPeriodEnd = true;
    $this->touch();
  }

  /**
   * Method resumeCancellation.
   *
   * Clears a scheduled cancellation so the subscription renews normally.
   *
   * @since 1.0.0
   */
  public function resumeCancellation(): void
  {
    $this->cancelAtPeriodEnd = false;
    $this->touch();
  }

  /**
   * Method markCanceled.
   *
   * Flags the subscription as canceled following a
   * `customer.subscription.deleted` event.
   *
   * @since 1.0.0
   */
  public function markCanceled(): void
  {
    $this->status = SubscriptionStatus::CANCELED;
    $this->cancelAtPeriodEnd = false;
    $this->touch();
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return SubscriptionId the subscription identifier
   */
  public function id(): SubscriptionId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @return string the owning organization identifier
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method stripeCustomerId.
   *
   * @since 1.0.0
   *
   * @return string the Stripe customer identifier
   */
  public function stripeCustomerId(): string
  {
    return $this->stripeCustomerId;
  }

  /**
   * Method stripeSubscriptionId.
   *
   * @since 1.0.0
   *
   * @return ?string the Stripe subscription identifier when active
   */
  public function stripeSubscriptionId(): ?string
  {
    return $this->stripeSubscriptionId;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   *
   * @return SubscriptionStatus the current lifecycle status
   */
  public function status(): SubscriptionStatus
  {
    return $this->status;
  }

  /**
   * Method planKey.
   *
   * @since 1.0.0
   *
   * @return ?string the currently billed plan key
   */
  public function planKey(): ?string
  {
    return $this->planKey;
  }

  /**
   * Method interval.
   *
   * @since 1.0.0
   *
   * @return ?BillingInterval the billing cadence
   */
  public function interval(): ?BillingInterval
  {
    return $this->interval;
  }

  /**
   * Method currentPeriodEnd.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the end of the current billing period
   */
  public function currentPeriodEnd(): ?DateTimeImmutable
  {
    return $this->currentPeriodEnd;
  }

  /**
   * Method cancelAtPeriodEnd.
   *
   * @since 1.0.0
   *
   * @return bool true when cancellation is scheduled at period end
   */
  public function cancelAtPeriodEnd(): bool
  {
    return $this->cancelAtPeriodEnd;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the last update timestamp
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method touch.
   *
   * Updates the last modification timestamp.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }
  // #endregion
}
