<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Federation;

use League\OAuth2\Client\Provider\{AbstractProvider, Google};

/**
 * Provider GooglePkceProviderAdapter.
 *
 * Enables PKCE S256 on League's Google provider while retaining its removal
 * of Google's obsolete and conflicting `approval_prompt` parameter.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GooglePkceProviderAdapter extends Google
{
  /**
   * Selects the S256 challenge method for every authorization request.
   *
   * @since 1.0.0
   *
   * @return string PKCE method understood by the base OAuth client
   */
  protected function getPkceMethod(): string
  {
    return AbstractProvider::PKCE_METHOD_S256;
  }
}
