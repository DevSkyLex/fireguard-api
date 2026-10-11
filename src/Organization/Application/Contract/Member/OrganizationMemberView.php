<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Member;

/**
 * Contract OrganizationMemberView.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMemberView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the membership identifier
   * @param string $organizationId the owning organization identifier
   * @param string $userId the account identifier
   * @param bool $active whether membership is currently active
   */
  public function __construct(public string $id, public string $organizationId, public string $userId, public bool $active)
  {
  }
  // #endregion
}
