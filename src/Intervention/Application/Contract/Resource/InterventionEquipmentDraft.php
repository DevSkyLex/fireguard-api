<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

/**
 * Resource InterventionEquipmentDraft.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionEquipmentDraft
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionEquipmentDraft class.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param ?string $facilityId the facility id value
   * @param ?string $serialNumber the serial number value
   */
  public function __construct(
    public string $id,
    public ?string $facilityId,
    public ?string $serialNumber,
  ) {
  }
}
