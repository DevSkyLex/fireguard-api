<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Http;

use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\{TimeoutExceptionInterface, TransportExceptionInterface};
use Webhook\Application\Contract\Http\WebhookHttpResponse;
use Webhook\Application\Port\Outbound\WebhookHttpClientPort;
use Webhook\Domain\Exception\WebhookValidationException;
use Webhook\Domain\Service\WebhookUrlPolicy;

use function max;
use function min;

/**
 * Adapter SymfonyHttpWebhookClientAdapter.
 *
 * Sends the signed outbound delivery POST via `symfony/http-client`.
 * The native guarded client pins DNS and validates the actual connected IP.
 * Redirects are disabled and both idle and total delivery duration are bounded.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SymfonyHttpWebhookClientAdapter implements WebhookHttpClientPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NoPrivateNetworkHttpClient $httpClient the guarded Symfony HTTP client
   * @param WebhookUrlPolicy $urlPolicy the SSRF hardening URL policy
   */
  public function __construct(
    private NoPrivateNetworkHttpClient $httpClient,
    private WebhookUrlPolicy $urlPolicy,
  ) {
  }
  // #endregion

  // #region Methods
  public function post(string $url, array $headers, string $body, int $timeoutSeconds): WebhookHttpResponse
  {
    try {
      $this->urlPolicy->assertValidUrl($url);
      $timeout = max(1, min(60, $timeoutSeconds));
      $response = $this->httpClient->request('POST', $url, [
        'headers' => $headers,
        'body' => $body,
        'timeout' => $timeout,
        'max_duration' => $timeout,
        'max_redirects' => 0,
      ]);

      return new WebhookHttpResponse($response->getStatusCode());
    } catch (WebhookValidationException) {
      return new WebhookHttpResponse(null, 'The target URL is invalid or disallowed.');
    } catch (TimeoutExceptionInterface) {
      return new WebhookHttpResponse(null, 'The delivery timed out.');
    } catch (TransportExceptionInterface) {
      return new WebhookHttpResponse(null, 'The destination is unreachable or disallowed.');
    }
  }
  // #endregion
}
