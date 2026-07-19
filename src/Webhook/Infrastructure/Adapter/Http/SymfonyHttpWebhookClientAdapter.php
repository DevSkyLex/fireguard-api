<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Adapter\Http;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Webhook\Application\Contract\Http\WebhookHttpResponse;
use Webhook\Application\Port\Outbound\WebhookHttpClientPort;
use Webhook\Domain\Service\WebhookUrlPolicy;

use function gethostbyname;
use function is_string;
use function parse_url;

use const PHP_URL_HOST;

/**
 * Adapter SymfonyHttpWebhookClientAdapter.
 *
 * Sends the signed outbound delivery POST via `symfony/http-client`.
 * Re-validates the DNS-resolved address just before connecting (guards
 * against DNS rebinding between subscription creation and delivery time —
 * see `Webhook\Domain\Service\WebhookUrlPolicy`) and disables redirect
 * following (`max_redirects: 0`) so a malicious 3xx cannot retarget the
 * request at an internal host.
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
   * @param HttpClientInterface $httpClient the Symfony HTTP client
   * @param WebhookUrlPolicy $urlPolicy the SSRF hardening URL policy
   */
  public function __construct(
    private HttpClientInterface $httpClient,
    private WebhookUrlPolicy $urlPolicy,
  ) {
  }
  // #endregion

  // #region Methods
  public function post(string $url, array $headers, string $body, int $timeoutSeconds): WebhookHttpResponse
  {
    $host = parse_url($url, PHP_URL_HOST);

    if (!is_string($host) || '' === $host) {
      return new WebhookHttpResponse(null, 'Invalid target URL.');
    }

    // gethostbyname() returns the original hostname unchanged when
    // resolution fails, which is itself later rejected as "not delivered"
    // by a connection failure — safe either way.
    $resolvedAddress = gethostbyname($host);

    if ($this->urlPolicy->isPrivateOrReservedIp($resolvedAddress)) {
      return new WebhookHttpResponse(null, 'Blocked: the target host resolves to a private, loopback, or reserved address.');
    }

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => $headers,
        'body' => $body,
        'timeout' => $timeoutSeconds,
        'max_redirects' => 0,
      ]);

      return new WebhookHttpResponse($response->getStatusCode());
    } catch (TransportExceptionInterface $exception) {
      return new WebhookHttpResponse(null, $exception->getMessage());
    }
  }
  // #endregion
}
