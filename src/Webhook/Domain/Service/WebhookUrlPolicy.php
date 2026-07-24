<?php

declare(strict_types=1);

namespace Webhook\Domain\Service;

use Webhook\Domain\Exception\WebhookValidationException;

use function filter_var;
use function is_string;
use function parse_url;
use function sprintf;
use function strtolower;

use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

/**
 * Service WebhookUrlPolicy.
 *
 * Pure, I/O-free SSRF hardening policy shared by
 * `Presentation\Api\Validator\ValidWebhookUrl\ValidWebhookUrlValidator` (input-time
 * UX validation), `CreateWebhookSubscriptionHandler`/`UpdateWebhookSubscriptionHandler`
 * (the authoritative business-rule gate — never trust the API layer alone),
 * and `Infrastructure\Adapter\Http\SymfonyHttpWebhookClientAdapter` (send-time
 * re-validation of the DNS-resolved address, guarding against DNS rebinding).
 *
 * Only a literal IP host can be validated without I/O; a DNS hostname is
 * accepted here (scheme-only check) and MUST be re-validated by the caller
 * once resolved — see {@see isPrivateOrReservedIp()}.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookUrlPolicy
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $allowInsecureUrls whether a plain `http://` target is accepted (dev only)
   */
  public function __construct(
    private bool $allowInsecureUrls = false,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertValidUrl.
   *
   * @since 1.0.0
   *
   * @param string $url the candidate target URL
   *
   * @throws WebhookValidationException when the URL is malformed, uses a
   *                                    disallowed scheme, or its host is a
   *                                    literal private/loopback/reserved IP
   */
  public function assertValidUrl(string $url): void
  {
    $scheme = parse_url($url, PHP_URL_SCHEME);
    $host = parse_url($url, PHP_URL_HOST);

    if (!is_string($scheme) || !is_string($host) || '' === $host) {
      throw new WebhookValidationException(sprintf('"%s" is not a valid webhook URL.', $url));
    }

    $scheme = strtolower($scheme);
    $schemeAllowed = 'https' === $scheme || ($this->allowInsecureUrls && 'http' === $scheme);

    if (!$schemeAllowed) {
      throw new WebhookValidationException('Webhook URLs must use https.');
    }

    if ($this->isPrivateOrReservedIp($host)) {
      throw new WebhookValidationException('Webhook URLs may not target a private, loopback, or reserved address.');
    }
  }

  /**
   * Method isPrivateOrReservedIp.
   *
   * Reports whether the given literal IP address falls in a private
   * (RFC 1918 / RFC 4193), loopback, link-local (which covers the
   * `169.254.169.254` cloud metadata endpoint), or otherwise reserved
   * range. Returns `false` for a value that is not a literal IP at all
   * (a DNS hostname) — callers resolving a hostname must pass the
   * resolved address here, not the original host.
   *
   * @since 1.0.0
   *
   * @param string $ip the candidate address
   *
   * @return bool true when the address must be rejected
   */
  public function isPrivateOrReservedIp(string $ip): bool
  {
    $filtered = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

    return false === $filtered && false !== filter_var($ip, FILTER_VALIDATE_IP);
  }
  // #endregion
}
