<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationQuota;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationQuotaQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationQuotaQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationQuotaQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
