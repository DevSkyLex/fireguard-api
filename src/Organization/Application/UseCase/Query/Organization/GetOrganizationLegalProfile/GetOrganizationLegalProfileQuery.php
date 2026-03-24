<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationLegalProfile;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationLegalProfileQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationLegalProfileQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationLegalProfileQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
