<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\DeliverWebhook;

use InvalidArgumentException;
use Shared\Application\Message\CommandMessage;

use function is_int;
use function is_string;

/**
 * UseCase DeliverWebhookCommand.
 *
 * Dispatched by `DispatchWebhookEventHandler` (a real curated event) or
 * `PingWebhookSubscriptionHandler` (a test delivery), and re-dispatched by
 * `RedeliverWebhookHandler` for a manual redelivery. Routed to the
 * `webhook` transport (see `config/packages/messenger.yaml`).
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeliverWebhookCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   * Constructor.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $deliveryId the webhook delivery identifier to attempt
   * @param int $generation
   * @param ?string $wakeToken
   *
   * @return void
   */
  public function __construct(
    public string $deliveryId,
    public int $generation = 0,
    public ?string $wakeToken = null,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method __unserialize
   *
   * @access public
   *
   * @param array<string, mixed> $data historical serialized public properties
   *
   * @return void
   */
  public function __unserialize(array $data): void
  {
    $id = $data['deliveryId'] ?? null;
    $generation = $data['generation'] ?? 0;
    $wakeToken = $data['wakeToken'] ?? null;

    if (!is_string($id) || !is_int($generation) || $generation < 0 || (null !== $wakeToken && !is_string($wakeToken))) {
      throw new InvalidArgumentException('Invalid serialized webhook delivery command.');
    }
    $this->deliveryId = $id;
    $this->generation = $generation;
    $this->wakeToken = $wakeToken;
  }

  /**
   * Method generation
   * Returns generation 0 for historical commands decoded without a generation.
   *
   * @access public
   *
   * @return int
   */
  public function generation(): int
  {
    return $this->generation;
  }

  /**
   * Method wakeToken
   * Historical envelopes never own a wake reservation.
   *
   * @access public
   *
   * @return ?string
   */
  public function wakeToken(): ?string
  {
    return $this->wakeToken;
  }
  // #endregion
}
