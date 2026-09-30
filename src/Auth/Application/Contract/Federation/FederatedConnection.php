<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use Auth\Domain\ValueObject\Federation\FederatedProvider;
use DateTimeImmutable;

/**
 * Contract FederatedConnection.
 *
 * A provider identity linked to one Fireguard user.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedConnection
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Describes a provider identity linked to one Fireguard user, including its provider subject and connection timestamps.
   *
   * @access public
   *
   * @param string $id identifier of this stored provider connection
   * @param string $userId Fireguard user linked to the provider identity
   * @param FederatedProvider $provider federated provider that issued the identity
   * @param string $subject provider-scoped subject identifier
   * @param string $email email address reported for the connected identity
   * @param DateTimeImmutable $connectedAt time this provider identity was linked
   * @param DateTimeImmutable $lastUsedAt time the connection was last used
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $userId,
    public FederatedProvider $provider,
    public string $subject,
    public string $email,
    public DateTimeImmutable $connectedAt,
    public DateTimeImmutable $lastUsedAt,
  ) {
  }
  // #endregion
}
