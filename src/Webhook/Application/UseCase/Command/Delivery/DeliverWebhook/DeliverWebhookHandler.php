<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\DeliverWebhook;

use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webhook\Application\Port\Outbound\{WebhookDeliveryRepositoryPort, WebhookHttpClientPort, WebhookSecretCipherPort, WebhookSubscriptionRepositoryPort};
use Webhook\Domain\Exception\WebhookDeliveryAttemptFailedException;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus};

use function hash_hmac;
use function json_encode;
use function min;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * UseCase DeliverWebhookHandler.
 *
 * The actual outbound POST stage: decrypts the subscription's signing
 * secret just-in-time, computes the HMAC-SHA256 signature over
 * `{timestamp}.{rawBody}` (never logging the plaintext secret), sends the
 * request via `WebhookHttpClientPort` (redirect-following disabled,
 * host re-validated at send time by the adapter — SSRF/DNS-rebinding
 * hardening), and records the outcome. Idempotent: a delivery already
 * `delivered` or terminally `failed` is a safe no-op on Messenger
 * redelivery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeliverWebhookHandler implements CommandHandler
{
  // #region Constants
  /**
   * Constant MAX_ATTEMPTS.
   *
   * Mirrors the `webhook` Messenger transport's `retry_strategy.max_retries`
   * (5): once this many attempts have been made, the delivery is marked
   * terminally `failed` instead of throwing to request another retry.
   *
   * @since 1.0.0
   *
   * @var int MAX_ATTEMPTS
   */
  private const int MAX_ATTEMPTS = 5;

  /**
   * Constant RETRY_BASE_DELAY_SECONDS.
   *
   * Mirrors the `webhook` transport's `retry_strategy.delay` (1000ms),
   * used only to compute the informational `nextRetryAt` column — the
   * actual redelivery timing is owned by Messenger, not by this estimate.
   *
   * @since 1.0.0
   *
   * @var int RETRY_BASE_DELAY_SECONDS
   */
  private const int RETRY_BASE_DELAY_SECONDS = 1;

  /**
   * Constant RETRY_MULTIPLIER.
   *
   * Mirrors the `webhook` transport's `retry_strategy.multiplier` (3).
   *
   * @since 1.0.0
   *
   * @var int RETRY_MULTIPLIER
   */
  private const int RETRY_MULTIPLIER = 3;

  /**
   * Constant RETRY_MAX_DELAY_SECONDS.
   *
   * Mirrors the `webhook` transport's `retry_strategy.max_delay` (3600000ms).
   *
   * @since 1.0.0
   *
   * @var int RETRY_MAX_DELAY_SECONDS
   */
  private const int RETRY_MAX_DELAY_SECONDS = 3600;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookDeliveryRepositoryPort $deliveryRepository the webhook delivery repository port
   * @param WebhookSubscriptionRepositoryPort $subscriptionRepository the webhook subscription repository port
   * @param WebhookSecretCipherPort $cipher the signing secret cipher port
   * @param WebhookHttpClientPort $httpClient the outbound HTTP client port
   * @param LoggerInterface $logger the logger
   * @param int $timeoutSeconds the outbound request timeout, in seconds
   */
  public function __construct(
    private WebhookDeliveryRepositoryPort $deliveryRepository,
    private WebhookSubscriptionRepositoryPort $subscriptionRepository,
    private WebhookSecretCipherPort $cipher,
    private WebhookHttpClientPort $httpClient,
    private LoggerInterface $logger,
    #[Autowire('%env(int:WEBHOOK_HTTP_TIMEOUT)%')]
    private int $timeoutSeconds = 10,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeliverWebhookCommand $command the command payload
   *
   * @return VoidResult the command result
   */
  public function __invoke(DeliverWebhookCommand $command): VoidResult
  {
    $delivery = $this->deliveryRepository->findById(WebhookDeliveryId::fromString($command->deliveryId));

    if (null === $delivery) {
      $this->logger->error('Webhook delivery not found for delivery attempt.', ['delivery_id' => $command->deliveryId]);

      return new VoidResult();
    }

    if (WebhookDeliveryStatus::PENDING !== $delivery->status()) {
      // Already delivered or terminally failed: a routine, safe no-op on
      // Messenger redelivery.
      return new VoidResult();
    }

    $subscription = $this->subscriptionRepository->findById($delivery->subscriptionId());
    $now = new DateTimeImmutable();

    if (null === $subscription) {
      $delivery->markFailed(null, 'The target subscription no longer exists.', $now);
      $this->deliveryRepository->save($delivery);

      return new VoidResult();
    }

    $body = json_encode($delivery->payload(), JSON_THROW_ON_ERROR);
    $timestamp = (string) $now->getTimestamp();
    $secret = $this->cipher->decrypt($subscription->secretCiphertext());
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

    $headers = [
      'Content-Type' => 'application/json',
      'User-Agent' => 'FireGuard-Webhooks/1.0',
      'X-FireGuard-Webhook-Id' => (string) $delivery->id(),
      'X-FireGuard-Webhook-Event' => $delivery->eventType(),
      'X-FireGuard-Webhook-Timestamp' => $timestamp,
      'X-FireGuard-Webhook-Signature' => 'sha256=' . $signature,
    ];

    $response = $this->httpClient->post($subscription->url(), $headers, $body, $this->timeoutSeconds);

    if ($response->isSuccessful()) {
      $delivery->markDelivered((int) $response->statusCode, $now);
      $this->deliveryRepository->save($delivery);

      return new VoidResult();
    }

    $error = $response->transportError ?? sprintf('Unexpected HTTP status %d.', $response->statusCode ?? 0);
    $nextAttemptNumber = $delivery->attempts() + 1;

    if ($nextAttemptNumber >= self::MAX_ATTEMPTS) {
      $delivery->markFailed($response->statusCode, $error, $now);
      $this->deliveryRepository->save($delivery);

      return new VoidResult();
    }

    $delivery->recordRetryableFailure($response->statusCode, $error, self::estimateNextRetryAt($nextAttemptNumber, $now), $now);
    $this->deliveryRepository->save($delivery);

    throw new WebhookDeliveryAttemptFailedException($error);
  }

  /**
   * Method estimateNextRetryAt.
   *
   * @static
   *
   * Informational only — Messenger's own `retry_strategy` owns the actual
   * redelivery schedule for the `webhook` transport.
   *
   * @since 1.0.0
   *
   * @param int $attemptNumber the attempt number about to be retried
   * @param DateTimeImmutable $now the current time
   *
   * @return DateTimeImmutable the estimated next retry time
   */
  private static function estimateNextRetryAt(int $attemptNumber, DateTimeImmutable $now): DateTimeImmutable
  {
    $delaySeconds = self::RETRY_BASE_DELAY_SECONDS * (self::RETRY_MULTIPLIER ** ($attemptNumber - 1));
    $delaySeconds = min($delaySeconds, self::RETRY_MAX_DELAY_SECONDS);

    return $now->add(new DateInterval('PT' . $delaySeconds . 'S'));
  }
  // #endregion
}
