<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Federation;

use Auth\Application\Contract\Federation\FederatedConnection;
use Symfony\Component\Serializer\Attribute\SerializedName;

use const DATE_ATOM;

/**
 * Class FederatedConnectionOutput
 *
 * Exposes one linked provider identity and its connection timestamps in the account API response.
 *
 * @category Output DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedConnectionOutput
{
  // #region Properties
  /**
   * Property provider
   *
   * Provider key exposed to the account-connection response.
   *
   * @access public
   */
  public string $provider;

  /**
   * Property email
   *
   * Email address associated with the external identity.
   *
   * @access public
   */
  public string $email;

  /**
   * Property connectedAt
   *
   * Connection creation time serialized as an ISO 8601 timestamp.
   *
   * @access public
   */
  #[SerializedName('connected_at')]
  public string $connectedAt;

  /**
   * Property lastUsedAt
   *
   * Most recent use time serialized as an ISO 8601 timestamp.
   *
   * @access public
   */
  #[SerializedName('last_used_at')]
  public string $lastUsedAt;
  // #endregion

  // #region Methods
  /**
   * Method fromContract
   *
   * Maps a federated connection contract to its API representation and formats both timestamps with DATE_ATOM.
   *
   * @access public
   *
   * @param FederatedConnection $connection the connection to expose
   *
   * @return self the serialized connection output
   */
  public static function fromContract(FederatedConnection $connection): self
  {
    $output = new self();
    $output->provider = $connection->provider->value;
    $output->email = $connection->email;
    $output->connectedAt = $connection->connectedAt->format(DATE_ATOM);
    $output->lastUsedAt = $connection->lastUsedAt->format(DATE_ATOM);

    return $output;
  }
  // #endregion
}
