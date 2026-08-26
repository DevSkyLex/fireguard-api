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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalEquipmentCommand implements CommandMessage
{
  // #region Constructor
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
  ) {
  }
  // #endregion
}
