<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Import;

use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Import\Application\Contract\{ImportKindDescriptor, ImportSessionContext};
use Import\Application\Port\Outbound\{ImportKindProcessorPort, ImportRowSessionPort};

/**
 * Class EquipmentImportKindProcessorAdapter
 *
 * Equipment contributes its CSV metadata and isolated quota simulation session.
 *
 * @category Adapter
 */
final readonly class EquipmentImportKindProcessorAdapter implements ImportKindProcessorPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EquipmentProvisioningPort $provisioning ordinary owner provisioning capability
   *
   * @return void
   */
  public function __construct(private EquipmentProvisioningPort $provisioning)
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
      'equipment',
      'organization.equipment.read',
      'organization.equipment.write',
      'fireguard-equipment-template.csv',
      "type,subType,brand,model,serialNumber,locationLabel,facilityCode\r\n",
    );
  }

  /**
   * Method startSession
   *
   * @access public
   *
   * @param ImportSessionContext $context immutable authorized execution context
   *
   * @return ImportRowSessionPort fresh equipment row session
   */
  public function startSession(ImportSessionContext $context): ImportRowSessionPort
  {
    return new EquipmentImportRowSessionAdapter($context, $this->provisioning);
  }
  // #endregion
}
