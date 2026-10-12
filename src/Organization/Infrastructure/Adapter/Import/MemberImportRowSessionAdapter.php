<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Import;

use Import\Application\Contract\{ImportRowInput, ImportRowOutcome, ImportSessionContext};
use Import\Application\Port\Outbound\ImportRowSessionPort;
use Organization\Application\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionOutcome};
use Organization\Application\Port\Inbound\MemberInvitationProvisioningPort;
use Organization\Infrastructure\Import\MemberImportRowFactory;

/**
 * Class MemberImportRowSessionAdapter
 *
 * Uses the current authorized actor and preserves member simulation's validation-only contract.
 *
 * @category Adapter
 */
final readonly class MemberImportRowSessionAdapter implements ImportRowSessionPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ImportSessionContext $context immutable job context
   * @param MemberInvitationProvisioningPort $provisioning owner invitation capability
   * @param MemberImportRowFactory $rows source-row structural validation
   *
   * @return void
   */
  public function __construct(
    private ImportSessionContext $context,
    private MemberInvitationProvisioningPort $provisioning,
    private MemberImportRowFactory $rows = new MemberImportRowFactory(),
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method processRow
   *
   * @access public
   *
   * @param ImportRowInput $input decoded original CSV row
   *
   * @return ImportRowOutcome safe owner provisioning result
   */
  public function processRow(ImportRowInput $input): ImportRowOutcome
  {
    $request = $this->rows->map($this->context->organizationId, $this->context->actorId, $input->row);
    if ($this->context->dryRun) {
      $request = new ProvisionMemberInvitationRequest(
        organizationId: $request->organizationId,
        email: $request->email,
        invitedByUserId: $request->invitedByUserId,
        roleNames: $request->roleNames,
        dryRun: true,
      );
    }
    $result = $this->provisioning->provision($request);
    if (ProvisionOutcome::CREATED === $result->outcome) {
      return $this->context->dryRun
        ? new ImportRowOutcome(true, code: 'would_create', message: 'Would invite this member.')
        : new ImportRowOutcome(true, $result->resourceId);
    }

    return match ($result->outcome) {
      ProvisionOutcome::QUOTA_EXCEEDED => new ImportRowOutcome(false, code: 'quota_exceeded', message: $result->message ?? 'The plan quota for members has been reached.'),
      ProvisionOutcome::ALREADY_MEMBER => new ImportRowOutcome(false, code: 'already_member', message: $result->message ?? 'User is already an active member of this organization.', column: 'email'),
      ProvisionOutcome::ALREADY_INVITED => new ImportRowOutcome(false, code: 'already_invited', message: $result->message ?? 'A pending invitation already exists for this email.', column: 'email'),
      ProvisionOutcome::UNKNOWN_ROLE => new ImportRowOutcome(false, code: 'unknown_role', message: $result->message ?? 'Unknown organization role name.', column: 'roles'),
      ProvisionOutcome::INVALID => new ImportRowOutcome(false, code: 'invalid', message: $result->message ?? 'Invalid member row.'),
    };
  }

  /**
   * Method restoreConfirmedSimulationRow
   *
   * Member simulation has no quota or reference projection to reconstruct.
   *
   * @access public
   *
   * @param ImportRowInput $input retained successful source row
   *
   * @return void
   */
  public function restoreConfirmedSimulationRow(ImportRowInput $input): void
  {
  }
  // #endregion
}
