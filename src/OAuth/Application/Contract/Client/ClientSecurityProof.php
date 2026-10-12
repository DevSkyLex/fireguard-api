<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Client;

/**
 * Class ClientSecurityProof
 *
 * Publishes current client credential authorization without its secret or a private client model.
 *
 * @category Contract
 */
final readonly class ClientSecurityProof
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $clientId verified client identity
   * @param int $securityRevision durable secret/configuration revision
   * @param list<string> $grantTypes currently allowed grants
   * @param list<string> $scopes currently allowed scopes
   * @param list<string> $redirectUris currently allowed redirects
   *
   * @return void
   */
  public function __construct(public string $clientId, public int $securityRevision, public array $grantTypes, public array $scopes, public array $redirectUris)
  {
  }
  // #endregion
}
