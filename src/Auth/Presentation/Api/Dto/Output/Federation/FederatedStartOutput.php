<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Federation;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO FederatedStartOutput.
 *
 * @category Output DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedStartOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the authorization URL that starts a federated sign-in flow.
   *
   * @access public
   *
   * @param string $authorizationUrl provider URL used to begin the authorization redirect
   *
   * @return void
   */
  public function __construct(
    #[SerializedName('authorization_url')]
    public string $authorizationUrl,
  ) {
  }
  // #endregion
}
