<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging;

use Shared\Application\Port\Outbound\Event\IntegrationEventDecoderPort;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\{MiddlewareInterface, StackInterface};

/**
 * Class IntegrationEventCompatibilityMiddleware
 *
 * Converts already-deserialized historical event messages while preserving all transport stamps.
 *
 * @category Messaging
 */
final readonly class IntegrationEventCompatibilityMiddleware implements MiddlewareInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the owner-composed compatibility decoder.
   *
   * @access public
   *
   * @param IntegrationEventDecoderPort $decoder normalizes supported historical event payloads
   *
   * @return void
   */
  public function __construct(private IntegrationEventDecoderPort $decoder)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method handle
   *
   * Reuses stamp instances, including retry and transport identity, when replacing a legacy payload.
   *
   * @access public
   *
   * @param Envelope $envelope incoming message and transport metadata
   * @param StackInterface $stack remaining middleware pipeline
   *
   * @return Envelope the downstream result
   */
  public function handle(Envelope $envelope, StackInterface $stack): Envelope
  {
    $message = $envelope->getMessage();
    $decoded = $this->decoder->decode($message);
    if ($message !== $decoded) {
      $stamps = [];
      foreach ($envelope->all() as $group) {
        foreach ($group as $stamp) {
          $stamps[] = $stamp;
        }
      }
      $envelope = new Envelope($decoded, $stamps);
    }

    return $stack->next()->handle($envelope, $stack);
  }
  // #endregion
}
