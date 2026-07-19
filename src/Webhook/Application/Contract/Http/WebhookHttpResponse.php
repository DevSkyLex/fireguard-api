<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Http;

/**
 * Contract WebhookHttpResponse.
 *
 * The outcome of a single outbound delivery attempt through
 * {@see \Webhook\Application\Port\Outbound\WebhookHttpClientPort}.
 * `statusCode` is null when the request never received an HTTP response at
 * all (DNS failure, connection refused, timeout) — `transportError` then
 * carries the reason.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookHttpResponse
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ?int $statusCode the received HTTP status code, when a response was received
   * @param ?string $transportError the transport-level failure reason, when no response was received
   */
  public function __construct(
    public ?int $statusCode,
    public ?string $transportError = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isSuccessful.
   *
   * @since 1.0.0
   *
   * @return bool true when a 2xx response was received
   */
  public function isSuccessful(): bool
  {
    return null !== $this->statusCode && $this->statusCode >= 200 && $this->statusCode < 300;
  }
  // #endregion
}
