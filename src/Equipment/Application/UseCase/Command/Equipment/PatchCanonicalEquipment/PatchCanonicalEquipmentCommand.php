<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PatchCanonicalEquipmentCommand.
 *
 * The `has*` flags carry the merge-patch distinction the deserialized DTO
 * cannot: an absent key and an explicit `null` both arrive as a null
 * property, and they mean opposite things. `MergePatchFields` reads the raw
 * body in the processor and fills them.
 *
 * `facilityId` is already an identifier: the processor parses the IRI, and
 * the handler checks it belongs to the same organization.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries only submitted equipment fields; each has-field flag distinguishes an omitted field from an explicit null value.
   *
   * @access public
   *
   * @param string $equipmentId equipment to update
   * @param int $expectedRevision revision the caller read before submitting changes
   * @param bool $hasType whether the type field was included in the request
   * @param ?string $type new type value, or null when explicitly cleared
   * @param bool $hasStatus whether the status field was included in the request
   * @param ?string $status new status value, or null when explicitly cleared
   * @param bool $hasSubType whether the subtype field was included in the request
   * @param ?string $subType new subtype value, or null when explicitly cleared
   * @param bool $hasBrand whether the brand field was included in the request
   * @param ?string $brand new brand value, or null when explicitly cleared
   * @param bool $hasModel whether the model field was included in the request
   * @param ?string $model new model value, or null when explicitly cleared
   * @param bool $hasSerialNumber whether the serial number field was included in the request
   * @param ?string $serialNumber new serial number, or null when explicitly cleared
   * @param bool $hasLocationLabel whether the location label field was included in the request
   * @param ?string $locationLabel new location label, or null when explicitly cleared
   * @param bool $hasFacility whether the facility field was included in the request
   * @param ?string $facilityId new facility identifier, or null when explicitly unassigned
   * @param list<array{key: string, value: string, unit: ?string}> $technicalProperties descriptive properties
   *
   * @return void
   */
  public function __construct(
    public string $equipmentId,
    public int $expectedRevision,
    public bool $hasType = false,
    public ?string $type = null,
    public bool $hasStatus = false,
    public ?string $status = null,
    public bool $hasSubType = false,
    public ?string $subType = null,
    public bool $hasBrand = false,
    public ?string $brand = null,
    public bool $hasModel = false,
    public ?string $model = null,
    public bool $hasSerialNumber = false,
    public ?string $serialNumber = null,
    public bool $hasLocationLabel = false,
    public ?string $locationLabel = null,
    public bool $hasFacility = false,
    public ?string $facilityId = null,
    public bool $hasName = false,
    public ?string $name = null,
    public bool $hasAssetCode = false,
    public ?string $assetCode = null,
    public bool $hasCriticality = false,
    public ?string $criticality = null,
    public bool $hasTechnicalProperties = false,
    public array $technicalProperties = [],
  ) {
  }
  // #endregion
}
