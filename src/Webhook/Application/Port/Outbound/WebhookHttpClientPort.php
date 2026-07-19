<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Outbound;

use Webhook\Application\Contract\Http\WebhookHttpResponse;

/**
 * Port WebhookHttpClientPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface WebhookHttpClientPort
{
  // #region Methods
  /**
   * Method post.
   *
   * Sends a signed outbound delivery POST. Implementations must disable
   * redirect following (SSRF hardening — a redirect could point at a
   * private/internal host) and enforce the given timeout.
   *
   * @since 1.0.0
   *
   * @param string $url the target URL
   * @param array<string, string> $headers the request headers
   * @param string $body the raw JSON request body (already the exact bytes that were signed)
   * @param int $timeoutSeconds the request timeout, in seconds
   *
   * @return WebhookHttpResponse the attempt outcome
   */
  public function post(string $url, array $headers, string $body, int $timeoutSeconds): WebhookHttpResponse;
  // #endregion
}
