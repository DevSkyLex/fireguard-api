<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\SuggestAddresses;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase SuggestAddressesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SuggestAddressesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the lookup is scoped to
   * @param string $query the partial postal address to search
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $query,
  ) {
  }
  // #endregion
}
