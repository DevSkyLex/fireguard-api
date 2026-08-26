<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use Equipment\Domain\Exception\CanonicalEquipmentValidationException;

/**
 * ValueObject CanonicalEquipmentPatch.
 *
 * One JSON Merge Patch over the canonical equipment surface, expressed as
 * present/value pairs.
 *
 * The `has*` flags are the whole point: merge-patch distinguishes "the key
 * was absent" from "the key was sent as null", and the two mean opposite
 * things here. An absent `brand` leaves the stored brand alone; `"brand":
 * null` erases it. An absent `status` is a no-op; `"status": null` is a
 * rejection. The flags come from the raw request body, read by
 * `Shared\Presentation\Api\Http\MergePatchFields` — a deserialized DTO
 * cannot carry the distinction, both cases arriving as a null property.
 *
 * `facilityId` is an identifier, not the IRI the client sent: the processor
 * parses it and the handler checks it belongs to the same organization
 * before the patch reaches the model.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalEquipmentPatch
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $hasType whether the `type` key was present
   * @param ?string $type the requested type
   * @param bool $hasStatus whether the `status` key was present
   * @param ?string $status the requested status
   * @param bool $hasSubType whether the `subType` key was present
   * @param ?string $subType the requested sub-type
   * @param bool $hasBrand whether the `brand` key was present
   * @param ?string $brand the requested brand
   * @param bool $hasModel whether the `model` key was present
   * @param ?string $model the requested model
   * @param bool $hasSerialNumber whether the `serialNumber` key was present
   * @param ?string $serialNumber the requested serial number
   * @param bool $hasLocationLabel whether the `locationLabel` key was present
   * @param ?string $locationLabel the requested location label
   * @param bool $hasFacility whether the `facility` key was present
   * @param ?string $facilityId the resolved facility identifier, null detaching it
   */
  public function __construct(
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

  // #region Methods
  /**
   * Method assertNonNullableFieldsArePresent.
   *
   * Rejects `type` or `status` explicitly sent as null, in that order.
   *
   * Called BEFORE the facility is validated, because that is the order the
   * processor ran them in: a patch carrying both a null `status` and a
   * foreign facility is told about the status. The two messages are the
   * published `hydra:description` of this endpoint.
   *
   * @since 1.0.0
   *
   * @throws CanonicalEquipmentValidationException when a non-nullable field is null
   */
  public function assertNonNullableFieldsArePresent(): void
  {
    if ($this->hasType && null === $this->type) {
      throw CanonicalEquipmentValidationException::fieldCannotBeNull('type');
    }

    if ($this->hasStatus && null === $this->status) {
      throw CanonicalEquipmentValidationException::fieldCannotBeNull('status');
    }
  }
  // #endregion
}
