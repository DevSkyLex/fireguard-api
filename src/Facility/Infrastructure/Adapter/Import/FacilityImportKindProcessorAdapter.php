<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Import;

use Facility\Application\Port\Inbound\FacilityProvisioningPort;
use Import\Application\Contract\{ImportKindDescriptor, ImportSessionContext};
use Import\Application\Port\Outbound\{ImportKindProcessorPort, ImportRowSessionPort};

/**
 * Class FacilityImportKindProcessorAdapter
 *
 * Facility contributes its CSV metadata and job-local hierarchy simulation.
 *
 * @category Adapter
 */
final readonly class FacilityImportKindProcessorAdapter implements ImportKindProcessorPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param FacilityProvisioningPort $provisioning ordinary owner provisioning capability
   *
   * @return void
   */
  public function __construct(private FacilityProvisioningPort $provisioning)
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
      'facility',
      'organization.facilities.read',
      'organization.facilities.write',
      'fireguard-facility-template.csv',
      "type,name,code,address,latitude,longitude,parentCode\r\n",
    );
  }

  /**
   * Method startSession
   *
   * @access public
   *
   * @param ImportSessionContext $context immutable authorized execution context
   *
   * @return ImportRowSessionPort fresh facility row session
   */
  public function startSession(ImportSessionContext $context): ImportRowSessionPort
  {
    return new FacilityImportRowSessionAdapter($context, $this->provisioning);
  }
  // #endregion
}
