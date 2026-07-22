<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganization;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationCallerRoleResult.
 *
 * Lightweight projection of one organization role assigned to the
 * REQUESTING user (the caller) inside an organization. Carried by
 * `GetOrganizationResult::$roles` for use cases that resolve caller
 * membership (currently ListUserOrganizations).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationCallerRoleResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationCallerRoleResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the organization role ID
   * @param string $label the organization role display label (role name)
   */
  public function __construct(
    public string $id,
    public string $label,
  ) {
  }
  // #endregion
}
