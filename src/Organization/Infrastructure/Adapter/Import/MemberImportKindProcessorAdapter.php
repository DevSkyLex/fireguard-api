<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Import;

use Import\Application\Contract\{ImportKindDescriptor, ImportSessionContext};
use Import\Application\Port\Outbound\{ImportKindProcessorPort, ImportRowSessionPort};
use Organization\Application\Port\Inbound\MemberInvitationProvisioningPort;

/**
 * Class MemberImportKindProcessorAdapter
 *
 * Organization contributes member-invitation CSV metadata and sessions.
 *
 * @category Adapter
 */
final readonly class MemberImportKindProcessorAdapter implements ImportKindProcessorPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param MemberInvitationProvisioningPort $provisioning ordinary owner provisioning capability
   *
   * @return void
   */
  public function __construct(private MemberInvitationProvisioningPort $provisioning)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method descriptor
   *
   * @access public
   *
   * @return ImportKindDescriptor persisted key, permissions and empty template
   */
  public function descriptor(): ImportKindDescriptor
  {
    return new ImportKindDescriptor(
      'member',
      'organization.members.read',
      'organization.members.manage',
      'fireguard-member-template.csv',
      "email,roles\r\n",
    );
  }

  /**
   * Method startSession
   *
   * @access public
   *
   * @param ImportSessionContext $context immutable authorized execution context
   *
   * @return ImportRowSessionPort fresh member row session
   */
  public function startSession(ImportSessionContext $context): ImportRowSessionPort
  {
    return new MemberImportRowSessionAdapter($context, $this->provisioning);
  }
  // #endregion
}
