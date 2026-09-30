<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Federation;

/**
 * DTO FederatedProviderOutput.
 *
 * @category Output DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedProviderOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Exposes a federated provider identifier and whether it is enabled for login.
   *
   * @access public
   *
   * @param string $provider provider identifier returned to the client
   * @param bool $enabled whether this provider is enabled for federated sign-in
   *
   * @return void
   */
  public function __construct(
    public string $provider,
    public bool $enabled = true,
  ) {
  }
  // #endregion
}
