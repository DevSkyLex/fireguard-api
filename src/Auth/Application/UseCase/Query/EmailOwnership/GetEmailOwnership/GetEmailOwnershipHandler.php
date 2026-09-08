<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\EmailOwnership\GetEmailOwnership;

use Shared\Application\Message\QueryHandler;
use User\Application\Port\Inbound\EmailOwnershipPort;

/**
 * Handler GetEmailOwnershipHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEmailOwnershipHandler implements QueryHandler
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param EmailOwnershipPort $ownership current identity and proof capability
   */
  public function __construct(private EmailOwnershipPort $ownership)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param GetEmailOwnershipQuery $query the authenticated request
   *
   * @return GetEmailOwnershipResult the public result
   */
  public function __invoke(GetEmailOwnershipQuery $query): GetEmailOwnershipResult
  {
    return new GetEmailOwnershipResult($this->ownership->get($query->userId)->verified);
  }
  // #endregion
}
