<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * Query ListTrustedDevicesQuery.
 */
final readonly class ListTrustedDevicesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Requests a page of trusted devices belonging to one user.
   *
   * @access public
   *
   * @param string $userId user whose trusted devices are listed
   * @param Pagination $pagination pagination window for the result
   *
   * @return void
   */
  public function __construct(
    public string $userId,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
