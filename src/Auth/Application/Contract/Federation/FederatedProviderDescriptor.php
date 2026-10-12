<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

/**
 * Contract FederatedProviderDescriptor
 *
 * Safe public provider metadata; issuer, endpoints and credentials stay server-side.
 *
 * @category Contract
 */
final readonly class FederatedProviderDescriptor
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ProviderId $id stable configured provider identifier
   * @param string $label display name
   * @param bool $enabled current server-side availability
   *
   * @return void
   */
  public function __construct(public ProviderId $id, public string $label, public bool $enabled)
  {
  }
  // #endregion
}
