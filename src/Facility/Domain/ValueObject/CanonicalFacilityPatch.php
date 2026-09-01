<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\CanonicalFacilityValidationException;

/**
 * ValueObject CanonicalFacilityPatch.
 *
 * One JSON Merge Patch over the canonical facility surface, expressed as
 * present/value pairs.
 *
 * The `has*` flags are the whole point: merge-patch distinguishes "the key
 * was absent" from "the key was sent as null", and the two mean opposite
 * things here. An absent `code` leaves the stored code alone; `"code": null`
 * erases it. An absent `status` is a no-op; `"status": null` is a rejection.
 * The flags come from the raw request body, read by
 * `Shared\Presentation\Api\Http\MergePatchFields`.
 *
 * `parentFacilityId` is an identifier, not the IRI the client sent: the
 * processor parses it, and the handler resolves and validates it before the
 * patch reaches the model.
 *
 * The two assertion methods exist because the processor's validation order
 * alternated between pure checks and external calls — descriptive fields,
 * then the organization's metadata schema, then `status`, then the parent.
 * Splitting them is what keeps that order, and therefore keeps the message a
 * client sending several invalid fields at once observes.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalFacilityPatch
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $hasType whether the `type` key was present
   * @param ?string $type the requested type
   * @param bool $hasName whether the `name` key was present
   * @param ?string $name the requested name
   * @param bool $hasCode whether the `code` key was present
   * @param ?string $code the requested code
   * @param bool $hasAddress whether the `address` key was present
   * @param ?string $address the requested address
   * @param bool $hasLatitude whether the `latitude` key was present
   * @param ?float $latitude the requested latitude
   * @param bool $hasLongitude whether the `longitude` key was present
   * @param ?float $longitude the requested longitude
   * @param bool $hasMetadata whether the `metadata` key was present
   * @param ?array<string, mixed> $metadata the requested metadata, null meaning an empty map
   * @param bool $hasStatus whether the `status` key was present
   * @param ?string $status the requested status
   * @param bool $hasParent whether the `parent` key was present
   * @param ?string $parentFacilityId the resolved parent identifier, null detaching it
   * @param bool $hasLevelIndex whether the `levelIndex` key was present
   * @param ?int $levelIndex the requested stacking order, null erasing it
   */
  public function __construct(
    public bool $hasType = false,
    public ?string $type = null,
    public bool $hasName = false,
    public ?string $name = null,
    public bool $hasCode = false,
    public ?string $code = null,
    public bool $hasAddress = false,
    public ?string $address = null,
    public bool $hasLatitude = false,
    public ?float $latitude = null,
    public bool $hasLongitude = false,
    public ?float $longitude = null,
    public bool $hasMetadata = false,
    public ?array $metadata = null,
    public bool $hasStatus = false,
    public ?string $status = null,
    public bool $hasParent = false,
    public ?string $parentFacilityId = null,
    public bool $hasLevelIndex = false,
    public ?int $levelIndex = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertDescriptiveFieldsAreValid.
   *
   * Rejects a null `type`, then a null `name`, then an unpaired coordinate.
   *
   * Runs BEFORE the metadata schema guard and before `status`, because that
   * is the order the processor ran them in.
   *
   * @since 1.0.0
   *
   * @throws CanonicalFacilityValidationException on a null non-nullable field or a half-supplied coordinate pair
   */
  public function assertDescriptiveFieldsAreValid(): void
  {
    if ($this->hasType && null === $this->type) {
      throw CanonicalFacilityValidationException::fieldCannotBeNull('type');
    }

    if ($this->hasName && null === $this->name) {
      throw CanonicalFacilityValidationException::fieldCannotBeNull('name');
    }

    // Coordinates move as a pair or not at all — twice: the keys must both be
    // present or both absent, and their values must both be null or both set.
    // A facility with a latitude and no longitude is on no map.
    if ($this->hasLatitude !== $this->hasLongitude) {
      throw CanonicalFacilityValidationException::coordinatesMustBePaired();
    }

    if (($this->hasLatitude || $this->hasLongitude) && (null === $this->latitude) !== (null === $this->longitude)) {
      throw CanonicalFacilityValidationException::coordinatesMustBePaired();
    }
  }

  /**
   * Method assertStatusIsPresent.
   *
   * Rejects `status` explicitly sent as null. Runs AFTER the metadata schema
   * guard, matching the processor.
   *
   * @since 1.0.0
   *
   * @throws CanonicalFacilityValidationException when `status` is null
   */
  public function assertStatusIsPresent(): void
  {
    if ($this->hasStatus && null === $this->status) {
      throw CanonicalFacilityValidationException::fieldCannotBeNull('status');
    }
  }
  // #endregion
}
