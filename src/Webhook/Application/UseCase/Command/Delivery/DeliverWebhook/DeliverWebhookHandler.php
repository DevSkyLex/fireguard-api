<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\DeliverWebhook;

use DateTimeImmutable;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{LoggerPort, TransactionManagerPort, UuidGeneratorPort};
use Throwable;
use Webhook\Application\Port\Outbound\Internal\{WebhookDeliveryRepositoryPort, WebhookDeliveryWorkPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\Port\Outbound\{WebhookDeliverySchedulePort, WebhookHttpClientPort, WebhookSecretCipherPort};
use Webhook\Domain\Exception\WebhookValidationException;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\Service\WebhookUrlPolicy;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus};

use function hash_hmac;
use function json_encode;
use function max;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Class DeliverWebhookHandler
 *
 * Owns a short durable lease, performs HTTP without a database transaction and
 * confirms only its generation and attempt token. HTTP retries use durable wakes;
 * Messenger's five retries are reserved for unexpected technical exceptions.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeliverWebhookHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   * @since 1.0.0
   *
   * @param WebhookDeliveryRepositoryPort $deliveryRepository delivery lookup
   * @param WebhookSubscriptionRepositoryPort $subscriptionRepository target lookup
   * @param WebhookSecretCipherPort $cipher signing secret decryption
   * @param WebhookHttpClientPort $httpClient bounded, guarded HTTP delivery
   * @param LoggerPort $logger safe diagnostics
   * @param WebhookDeliveryWorkPort $work durable fenced attempt state
   * @param WebhookDeliverySchedulePort $schedule durable wake scheduling
   * @param UuidGeneratorPort $ids attempt identifiers
   * @param TransactionManagerPort $transactionManager main transaction boundary
   * @param WebhookUrlPolicy $urlPolicy pure destination policy before consuming HTTP
   * @param int $timeoutSeconds outbound timeout, bounded to 60 seconds by the adapter
   *
   * @return void
   */
  public function __construct(
    private WebhookDeliveryRepositoryPort $deliveryRepository,
    private WebhookSubscriptionRepositoryPort $subscriptionRepository,
    private WebhookSecretCipherPort $cipher,
    private WebhookHttpClientPort $httpClient,
    private LoggerPort $logger,
    private WebhookDeliveryWorkPort $work,
    private WebhookDeliverySchedulePort $schedule,
    private UuidGeneratorPort $ids,
    private TransactionManagerPort $transactionManager,
    private WebhookUrlPolicy $urlPolicy,
    private int $timeoutSeconds = 10,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Claims work before sending and retains a durable wake for busy/future deliveries.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DeliverWebhookCommand $command generation-bound delivery command
   *
   * @return VoidResult
   */
  public function __invoke(DeliverWebhookCommand $command): VoidResult
  {
    $now = new DateTimeImmutable();
    $token = $this->ids->generate();
    $generation = $command->generation();
    $delivery = $this->work->claim($command->deliveryId, $generation, $command->wakeToken(), $token, $now);

    if (null === $delivery) {
      $current = $this->deliveryRepository->findById(WebhookDeliveryId::fromString($command->deliveryId));

      if (null === $current) {
        $this->logger->error('Webhook delivery not found for delivery attempt.', ['delivery_id' => $command->deliveryId]);
      } elseif ($current->generation() === $generation && WebhookDeliveryStatus::PENDING === $current->status() && !$current->quarantined() && $current->attempts() < 5) {
        $due = max($now->getTimestamp(), $current->leaseUntil()?->getTimestamp() ?? 0, $current->nextRetryAt()?->getTimestamp() ?? 0);
        $this->schedule->enqueue($command->deliveryId, $generation, $now->setTimestamp($due));
      }

      return new VoidResult();
    }

    try {
      $this->attemptDelivery($delivery, $generation, $token, $now);
    } catch (Throwable $exception) {
      try {
        $this->work->technicalFailure($command->deliveryId, $generation, $token, new DateTimeImmutable());
      } catch (Throwable) {
        $this->logger->error('Could not release the webhook delivery lease after a technical failure.', ['delivery_id' => $command->deliveryId]);
      }

      throw $exception;
    }

    return new VoidResult();
  }

  /**
   * Method attemptDelivery
   *
   * Builds the signed request before consuming an HTTP attempt; confirms and
   * enqueues the next HTTP wake together after the response.
   *
   * @access private
   * @since 1.0.0
   *
   * @param WebhookDelivery $delivery exclusively claimed delivery
   * @param int $generation owning generation
   * @param string $token owning attempt token
   * @param DateTimeImmutable $now signing timestamp
   *
   * @return void
   */
  private function attemptDelivery(WebhookDelivery $delivery, int $generation, string $token, DateTimeImmutable $now): void
  {
    $subscription = $this->subscriptionRepository->findById($delivery->subscriptionId());

    if (null === $subscription) {
      $delivery->markFailed(null, 'The target subscription no longer exists.', $now, false);
      $this->work->finish($delivery, $generation, $token);

      return;
    }

    try {
      $this->urlPolicy->assertValidUrl($subscription->url());
    } catch (WebhookValidationException) {
      $delivery->markFailed(null, 'The target URL is invalid or disallowed.', $now, false);
      $this->work->finish($delivery, $generation, $token);

      return;
    }

    $body = json_encode($delivery->payload(), JSON_THROW_ON_ERROR);
    $timestamp = (string) $now->getTimestamp();
    $secret = $this->cipher->decrypt($subscription->secretCiphertext());
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $headers = [
      'Content-Type' => 'application/json',
      'User-Agent' => 'Fireguard-Webhooks/1.0',
      'X-Fireguard-Webhook-Id' => (string) $delivery->id(),
      'X-Fireguard-Webhook-Event' => $delivery->eventType(),
      'X-Fireguard-Webhook-Timestamp' => $timestamp,
      'X-Fireguard-Webhook-Signature' => 'sha256=' . $signature,
    ];

    if (!$this->work->startHttpAttempt((string) $delivery->id(), $generation, $token)) {
      return;
    }
    $delivery->beginHttpAttempt();
    $response = $this->httpClient->post($subscription->url(), $headers, $body, $this->timeoutSeconds);
    $completedAt = new DateTimeImmutable();

    if ($response->isSuccessful()) {
      $delivery->markDelivered((int) $response->statusCode, $completedAt);
    } else {
      $error = $response->transportError ?? sprintf('Unexpected HTTP status %d.', $response->statusCode ?? 0);

      if ($delivery->attempts() >= 5 || 'The target URL is invalid or disallowed.' === $error) {
        $delivery->markFailed($response->statusCode, $error, $completedAt);
      } else {
        $delivery->recordRetryableFailure($response->statusCode, $error, $completedAt->modify('+' . (3 ** ($delivery->attempts() - 1)) . ' seconds'), $completedAt);
      }
    }

    $this->transactionManager->transactional(function () use ($delivery, $generation, $token): void {
      if ($this->work->finish($delivery, $generation, $token) && null !== $delivery->nextRetryAt()) {
        $this->schedule->enqueue((string) $delivery->id(), $generation, $delivery->nextRetryAt());
      }
    });
  }
  // #endregion
}
