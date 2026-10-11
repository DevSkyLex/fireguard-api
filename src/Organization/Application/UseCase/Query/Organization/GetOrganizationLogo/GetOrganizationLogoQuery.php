<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationLogo;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetOrganizationLogoQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationLogoQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the public organization identifier
   */
  public function __construct(public string $organizationId)
  {
  }
  // #endregion
}
