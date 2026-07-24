<?php

declare(strict_types=1);

namespace Webhook\Domain\Model\Subscription;

use DateTimeImmutable;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

use function in_array;

/**
 * Model WebhookSubscription.
 *
 * An organization-scoped outbound webhook subscription: a target URL, a
 * curated allowlist of event types it wants delivered, and the HMAC
 * signing secret (stored here only as ciphertext — encryption/decryption
 * is a `WebhookSecretCipherPort` concern, kept out of the domain). Mirrors
 * {@see \Organization\Domain\Model\Team\Team}: a mutable aggregate with
 * `create()`/`reconstitute()` factories.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookSubscription
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the WebhookSubscription class.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionId $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $url the target URL deliveries are POSTed to
   * @param string $secretCiphertext the encrypted-at-rest HMAC signing secret
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param bool $isActive whether deliveries are currently enqueued for this subscription
   * @param string $description the free-form description
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  private function __construct(
    private WebhookSubscriptionId $id,
    private string $organizationId,
    private string $url,
    private string $secretCiphertext,
    private array $eventTypes,
    private bool $isActive,
    private string $description,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new webhook subscription aggregate.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionId $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $url the target URL
   * @param string $secretCiphertext the encrypted-at-rest signing secret
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param string $description the free-form description
   *
   * @return self the created webhook subscription
   */
  public static function create(
    WebhookSubscriptionId $id,
    string $organizationId,
    string $url,
    string $secretCiphertext,
    array $eventTypes,
    string $description = '',
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      url: $url,
      secretCiphertext: $secretCiphertext,
      eventTypes: $eventTypes,
      isActive: true,
      description: $description,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes a webhook subscription aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionId $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $url the target URL
   * @param string $secretCiphertext the encrypted-at-rest signing secret
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param bool $isActive whether deliveries are currently enqueued
   * @param string $description the free-form description
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   *
   * @return self the reconstituted webhook subscription
   */
  public static function reconstitute(
    WebhookSubscriptionId $id,
    string $organizationId,
    string $url,
    string $secretCiphertext,
    array $eventTypes,
    bool $isActive,
    string $description,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      url: $url,
      secretCiphertext: $secretCiphertext,
      eventTypes: $eventTypes,
      isActive: $isActive,
      description: $description,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return WebhookSubscriptionId the subscription identifier
   */
  public function id(): WebhookSubscriptionId
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
   * Method url.
   *
   * @since 1.0.0
   *
   * @return string the target URL
   */
  public function url(): string
  {
    return $this->url;
  }

  /**
   * Method secretCiphertext.
   *
   * @since 1.0.0
   *
   * @return string the encrypted-at-rest signing secret
   */
  public function secretCiphertext(): string
  {
    return $this->secretCiphertext;
  }

  /**
   * Method eventTypes.
   *
   * @since 1.0.0
   *
   * @return list<string> the subscribed public event type allowlist
   */
  public function eventTypes(): array
  {
    return $this->eventTypes;
  }

  /**
   * Method isActive.
   *
   * @since 1.0.0
   *
   * @return bool whether deliveries are currently enqueued
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method description.
   *
   * @since 1.0.0
   *
   * @return string the free-form description
   */
  public function description(): string
  {
    return $this->description;
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
   * Method subscribesTo.
   *
   * Reports whether this active subscription should receive a delivery
   * for the given public event type.
   *
   * @since 1.0.0
   *
   * @param string $eventType the public event type
   *
   * @return bool true when the subscription is active and subscribed
   */
  public function subscribesTo(string $eventType): bool
  {
    return $this->isActive && in_array($eventType, $this->eventTypes, true);
  }

  /**
   * Method update.
   *
   * Updates the mutable subscription fields (all optional — pass the
   * current value to leave a field unchanged).
   *
   * @since 1.0.0
   *
   * @param string $url the target URL
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param bool $isActive whether deliveries are currently enqueued
   * @param string $description the free-form description
   */
  public function update(string $url, array $eventTypes, bool $isActive, string $description): void
  {
    $this->url = $url;
    $this->eventTypes = $eventTypes;
    $this->isActive = $isActive;
    $this->description = $description;
    $this->touch();
  }

  /**
   * Method rotateSecret.
   *
   * Replaces the stored ciphertext with a newly encrypted secret.
   *
   * @since 1.0.0
   *
   * @param string $secretCiphertext the new encrypted-at-rest signing secret
   */
  public function rotateSecret(string $secretCiphertext): void
  {
    $this->secretCiphertext = $secretCiphertext;
    $this->touch();
  }

  /**
   * Method touch.
   *
   * Refreshes the last update timestamp.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }
  // #endregion
}
