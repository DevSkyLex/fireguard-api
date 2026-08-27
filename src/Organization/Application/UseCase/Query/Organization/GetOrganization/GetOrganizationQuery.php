<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganization;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param ?string $callerUserId the requesting user's identifier; when
   *                              provided, the result's `isOwner`/`roles`
   *                              caller-membership fields are resolved,
   *                              mirroring ListUserOrganizations. Null
   *                              (the default) preserves the pre-existing
   *                              behavior of leaving both null
   */
  public function __construct(
    public string $organizationId,
    public ?string $callerUserId = null,
  ) {
  }
  // #endregion
}
