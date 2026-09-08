<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Provisioning;

/**
 * Contract ProvisionMemberInvitationResult.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ProvisionMemberInvitationResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ProvisionOutcome $outcome the provisioning outcome
   * @param ?string $resourceId the created invitation identifier, when `CREATED` on a real run
   * @param ?string $message the failure reason, when not `CREATED`
   */
  public function __construct(
    public ProvisionOutcome $outcome,
    public ?string $resourceId = null,
    public ?string $message = null,
  ) {
  }
  // #endregion
}
