<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\GeocodeAddress;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GeocodeAddressQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GeocodeAddressQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the lookup is scoped to
   * @param string $address the free-form address to resolve
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $address,
  ) {
  }
  // #endregion
}
