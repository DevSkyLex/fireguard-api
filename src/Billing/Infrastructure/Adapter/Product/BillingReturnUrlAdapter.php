<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Adapter\Product;

use Billing\Application\Port\Outbound\BillingReturnUrlPort;
use InvalidArgumentException;
use Shared\Application\Port\Outbound\ProductConfigPort;

use function http_build_query;
use function parse_url;
use function preg_match;
use function rawurlencode;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function substr_count;

use const PHP_QUERY_RFC3986;

/**
 * Class BillingReturnUrlAdapter
 *
 * Combines the trusted frontend base with a deployment-owned relative route.
 * Identifiers are encoded as data and cannot supply an alternate redirect target.
 *
 * @category Adapter
 */
final readonly class BillingReturnUrlAdapter implements BillingReturnUrlPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Rejects absolute, protocol-relative or malformed routes before any Stripe session is created.
   *
   * @access public
   *
   * @param ProductConfigPort $product the trusted product configuration
   * @param string $settingsPath the relative route with exactly one organization placeholder
   *
   * @return void
   *
   * @throws InvalidArgumentException when the route could change the redirect origin or is malformed
   */
  public function __construct(private ProductConfigPort $product, private string $settingsPath)
  {
    $parts = parse_url($settingsPath);
    if (!str_starts_with($settingsPath, '/')
      || str_starts_with($settingsPath, '//')
      || false === $parts
      || isset($parts['scheme'])
      || isset($parts['host'])
      || isset($parts['fragment'])
      || 1 !== substr_count($settingsPath, '{organizationId}')
      || !str_contains($parts['path'] ?? '', '{organizationId}')
      || 1 === preg_match('/[\x00-\x20\\\\]/', $settingsPath)
      || 1 === preg_match('/[{}]/', str_replace('{organizationId}', '', $settingsPath))
    ) {
      throw new InvalidArgumentException('The Billing settings path must be a relative frontend route with one organization placeholder.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method checkoutSuccess
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param string $planKey the selected plan key
   * @param string $interval the validated billing cadence
   *
   * @return string the success return URL with expected subscription markers
   */
  public function checkoutSuccess(string $organizationId, string $planKey, string $interval): string
  {
    return $this->withQuery($this->portal($organizationId), [
      'checkout' => 'success',
      'checkoutPlan' => $planKey,
      'checkoutInterval' => $interval,
    ]);
  }

  /**
   * Method checkoutCancel
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return string the cancellation return URL
   */
  public function checkoutCancel(string $organizationId): string
  {
    return $this->withQuery($this->portal($organizationId), ['checkout' => 'cancel']);
  }

  /**
   * Method portal
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return string the trusted settings return URL
   */
  public function portal(string $organizationId): string
  {
    return rtrim($this->product->frontendUrl(), '/') . str_replace('{organizationId}', rawurlencode($organizationId), $this->settingsPath);
  }

  /**
   * Method withQuery
   *
   * @access private
   *
   * @param string $url the trusted URL
   * @param array<string, string> $parameters the encoded payment navigation markers
   *
   * @return string the URL with appended RFC3986 query parameters
   */
  private function withQuery(string $url, array $parameters): string
  {
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
  }
  // #endregion
}
