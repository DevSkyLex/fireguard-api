<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationQuota;

use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationQuotaHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationQuotaHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationQuotaHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationQuotaPort $quota the organization quota port
   */
  public function __construct(
    private OrganizationQuotaPort $quota,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns the per-resource quota usage for the organization.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationQuotaQuery $query the query payload
   *
   * @return GetOrganizationQuotaResult the quota usage result
   */
  public function __invoke(GetOrganizationQuotaQuery $query): GetOrganizationQuotaResult
  {
    return new GetOrganizationQuotaResult(
      items: $this->quota->getQuotaSummary($query->organizationId),
    );
  }
  // #endregion
}
