<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Federation;

use League\OAuth2\Client\Provider\GenericProvider;

/**
 * Provider OidcPkceProviderAdapter.
 *
 * Keeps the generic OIDC client compatible with modern providers by removing
 * League's legacy `approval_prompt` authorization parameter.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OidcPkceProviderAdapter extends GenericProvider
{
  /**
   * Builds modern authorization parameters without the obsolete prompt alias.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $options caller-provided authorization values
   *
   * @return array<string, mixed> normalized authorization parameters
   */
  protected function getAuthorizationParameters(array $options): array
  {
    /** @var array<string, mixed> $parameters */
    $parameters = parent::getAuthorizationParameters($options);
    unset($parameters['approval_prompt']);

    return $parameters;
  }
}
