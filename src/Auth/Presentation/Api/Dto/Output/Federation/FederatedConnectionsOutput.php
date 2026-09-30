<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Federation;

use Auth\Application\Contract\Federation\FederatedConnections;
use Symfony\Component\Serializer\Attribute\SerializedName;

use function array_map;

/**
 * DTO FederatedConnectionsOutput.
 *
 * @category Output DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedConnectionsOutput
{
  // #region Properties
  /**
   * Property passwordConfigured.
   *
   * Whether the account has a password credential configured.
   *
   * @access public
   */
  #[SerializedName('password_configured')]
  public bool $passwordConfigured;

  /**
   * Property lastSignInMethod.
   *
   * The last authentication method reported for the account, when available.
   *
   * @access public
   */
  #[SerializedName('last_sign_in_method')]
  public ?string $lastSignInMethod;

  /**
   * @var list<FederatedConnectionOutput>
   */
  public array $connections;
  // #endregion

  // #region Methods
  /**
   * Method fromContract
   *
   * Maps federated connection and account credential state to API output.
   *
   * @access public
   *
   * @static
   *
   * @param FederatedConnections $connections the federated connection contract
   *
   * @return self the API output representation
   */
  public static function fromContract(FederatedConnections $connections): self
  {
    $output = new self();
    $output->passwordConfigured = $connections->passwordConfigured;
    $output->lastSignInMethod = $connections->lastSignInMethod;
    $output->connections = array_map(FederatedConnectionOutput::fromContract(...), $connections->connections);

    return $output;
  }
  // #endregion
}
