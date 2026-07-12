<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Equipment;

use DateTimeImmutable;
use Equipment\Domain\Exception\EquipmentAlreadyDecommissionedException;
use Equipment\Domain\ValueObject\{
  EquipmentFacilityId,
  EquipmentId,
  EquipmentOrganizationId,
  EquipmentStatus,
  EquipmentType
};
use InvalidArgumentException;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * Model Equipment.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Equipment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Equipment class.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param EquipmentType $type the equipment type
   * @param EquipmentStatus $status the equipment status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?EquipmentFacilityId $facilityId the optional facility identifier
   * @param ?string $subType the optional equipment sub-type
   * @param ?string $brand the optional brand
   * @param ?string $model the optional model
   * @param ?string $serialNumber the optional serial number
   * @param ?string $locationLabel the optional location label
   * @param ?DateTimeImmutable $installedAt the optional installation timestamp
   * @param ?DateTimeImmutable $commissionedAt the optional commissioning timestamp
   */
  private function __construct(
    private EquipmentId $id,
    private EquipmentOrganizationId $organizationId,
    private EquipmentType $type,
    private EquipmentStatus $status,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?EquipmentFacilityId $facilityId = null,
    private ?string $subType = null,
    private ?string $brand = null,
    private ?string $model = null,
    private ?string $serialNumber = null,
    private ?string $locationLabel = null,
    private ?DateTimeImmutable $installedAt = null,
    private ?DateTimeImmutable $commissionedAt = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new equipment aggregate.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param EquipmentType $type the equipment type
   * @param ?string $subType the optional sub-type
   * @param ?string $brand the optional brand
   * @param ?string $model the optional model
   * @param ?string $serialNumber the optional serial number
   * @param ?string $locationLabel the optional location label
   *
   * @return self the created equipment aggregate
   */
  public static function create(
    EquipmentId $id,
    EquipmentOrganizationId $organizationId,
    EquipmentType $type,
    ?string $subType = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $serialNumber = null,
    ?string $locationLabel = null,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      type: $type,
      status: EquipmentStatus::IN_STOCK,
      createdAt: $now,
      updatedAt: $now,
      facilityId: null,
      subType: self::normalizeShortString($subType, 'sub-type', 100),
      brand: self::normalizeShortString($brand, 'brand', 100),
      model: self::normalizeShortString($model, 'model', 100),
      serialNumber: self::normalizeShortString($serialNumber, 'serial number', 100),
      locationLabel: self::normalizeShortString($locationLabel, 'location label', 255),
      installedAt: null,
      commissionedAt: null,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an equipment aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param EquipmentType $type the equipment type
   * @param EquipmentStatus $status the equipment status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?EquipmentFacilityId $facilityId the optional facility identifier
   * @param ?string $subType the optional sub-type
   * @param ?string $brand the optional brand
   * @param ?string $model the optional model
   * @param ?string $serialNumber the optional serial number
   * @param ?string $locationLabel the optional location label
   * @param ?DateTimeImmutable $installedAt the optional installation timestamp
   * @param ?DateTimeImmutable $commissionedAt the optional commissioning timestamp
   *
   * @return self the reconstituted equipment aggregate
   */
  public static function reconstitute(
    EquipmentId $id,
    EquipmentOrganizationId $organizationId,
    EquipmentType $type,
    EquipmentStatus $status,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?EquipmentFacilityId $facilityId = null,
    ?string $subType = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $serialNumber = null,
    ?string $locationLabel = null,
    ?DateTimeImmutable $installedAt = null,
    ?DateTimeImmutable $commissionedAt = null,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      type: $type,
      status: $status,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      facilityId: $facilityId,
      subType: $subType,
      brand: $brand,
      model: $model,
      serialNumber: $serialNumber,
      locationLabel: $locationLabel,
      installedAt: $installedAt,
      commissionedAt: $commissionedAt,
    );
  }

  /**
   * Method update.
   *
   * Updates mutable fields of the equipment.
   *
   * @since 1.0.0
   *
   * @param EquipmentType $type the equipment type
   * @param ?string $subType the optional sub-type
   * @param ?string $brand the optional brand
   * @param ?string $model the optional model
   * @param ?string $serialNumber the optional serial number
   * @param ?string $locationLabel the optional location label
   */
  public function update(
    EquipmentType $type,
    ?string $subType = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $serialNumber = null,
    ?string $locationLabel = null,
  ): void {
    $this->type = $type;
    $this->subType = self::normalizeShortString($subType, 'sub-type', 100);
    $this->brand = self::normalizeShortString($brand, 'brand', 100);
    $this->model = self::normalizeShortString($model, 'model', 100);
    $this->serialNumber = self::normalizeShortString($serialNumber, 'serial number', 100);
    $this->locationLabel = self::normalizeShortString($locationLabel, 'location label', 255);
    $this->touch();
  }

  /**
   * Method assignToFacility.
   *
   * Assigns the equipment to a facility.
   *
   * @since 1.0.0
   *
   * @param EquipmentFacilityId $facilityId the facility identifier
   * @param DateTimeImmutable $installedAt the installation timestamp
   */
  public function assignToFacility(EquipmentFacilityId $facilityId, DateTimeImmutable $installedAt): void
  {
    if (EquipmentStatus::DECOMMISSIONED === $this->status) {
      throw EquipmentAlreadyDecommissionedException::withId((string) $this->id);
    }

    if (null !== $this->facilityId) {
      throw new InvalidArgumentException('Equipment is already assigned to a facility. Unassign it first.');
    }

    $this->facilityId = $facilityId;
    $this->installedAt = $installedAt;
    $this->touch();
  }

  /**
   * Method unassignFromFacility.
   *
   * Removes the facility assignment.
   *
   * @since 1.0.0
   */
  public function unassignFromFacility(): void
  {
    // Decommissioning is terminal: a retired asset can never be unassigned back
    // into stock, otherwise it could be re-assigned and re-commissioned.
    if (EquipmentStatus::DECOMMISSIONED === $this->status) {
      throw EquipmentAlreadyDecommissionedException::withId((string) $this->id);
    }

    $this->facilityId = null;
    $this->installedAt = null;
    // Unassigning always resets the status to IN_STOCK regardless of prior state,
    // since the equipment can no longer be operational or under maintenance without a facility.
    $this->status = EquipmentStatus::IN_STOCK;
    $this->touch();
  }

  /**
   * Method commission.
   *
   * Commissions the equipment.
   *
   * @since 1.0.0
   */
  public function commission(): void
  {
    if (EquipmentStatus::DECOMMISSIONED === $this->status) {
      throw EquipmentAlreadyDecommissionedException::withId((string) $this->id);
    }

    if (null === $this->facilityId) {
      throw new InvalidArgumentException('Equipment must be assigned to a facility before commissioning.');
    }

    // Preserve the original commissioning date: coming back from maintenance
    // (re-commission) must not reset the first in-service date.
    $this->commissionedAt ??= new DateTimeImmutable();
    $this->status = EquipmentStatus::OPERATIONAL;
    $this->touch();
  }

  /**
   * Method putUnderMaintenance.
   *
   * Puts the equipment under maintenance.
   *
   * @since 1.0.0
   */
  public function putUnderMaintenance(): void
  {
    if (EquipmentStatus::DECOMMISSIONED === $this->status) {
      throw EquipmentAlreadyDecommissionedException::withId((string) $this->id);
    }

    // Maintenance applies to in-service equipment only (operational ↔
    // under_maintenance). In-stock equipment has never been commissioned, so it
    // cannot go straight into maintenance; it must be commissioned first.
    if (EquipmentStatus::IN_STOCK === $this->status) {
      throw new InvalidArgumentException('In-stock equipment must be commissioned before it can be put under maintenance.');
    }

    if (null === $this->facilityId) {
      throw new InvalidArgumentException('Equipment must be assigned to a facility before putting it under maintenance.');
    }

    $this->status = EquipmentStatus::UNDER_MAINTENANCE;
    $this->touch();
  }

  /**
   * Method decommission.
   *
   * Decommissions the equipment.
   *
   * @since 1.0.0
   */
  public function decommission(): void
  {
    if (EquipmentStatus::DECOMMISSIONED === $this->status) {
      throw EquipmentAlreadyDecommissionedException::withId((string) $this->id);
    }

    $this->status = EquipmentStatus::DECOMMISSIONED;
    $this->touch();
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): EquipmentId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): EquipmentOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method facilityId.
   *
   * @since 1.0.0
   */
  public function facilityId(): ?EquipmentFacilityId
  {
    return $this->facilityId;
  }

  /**
   * Method type.
   *
   * @since 1.0.0
   */
  public function type(): EquipmentType
  {
    return $this->type;
  }

  /**
   * Method subType.
   *
   * @since 1.0.0
   */
  public function subType(): ?string
  {
    return $this->subType;
  }

  /**
   * Method brand.
   *
   * @since 1.0.0
   */
  public function brand(): ?string
  {
    return $this->brand;
  }

  /**
   * Method model.
   *
   * @since 1.0.0
   */
  public function model(): ?string
  {
    return $this->model;
  }

  /**
   * Method serialNumber.
   *
   * @since 1.0.0
   */
  public function serialNumber(): ?string
  {
    return $this->serialNumber;
  }

  /**
   * Method locationLabel.
   *
   * @since 1.0.0
   */
  public function locationLabel(): ?string
  {
    return $this->locationLabel;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): EquipmentStatus
  {
    return $this->status;
  }

  /**
   * Method installedAt.
   *
   * @since 1.0.0
   */
  public function installedAt(): ?DateTimeImmutable
  {
    return $this->installedAt;
  }

  /**
   * Method commissionedAt.
   *
   * @since 1.0.0
   */
  public function commissionedAt(): ?DateTimeImmutable
  {
    return $this->commissionedAt;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method touch.
   *
   * Updates the last modification timestamp.
   *
   * @since 1.0.0
   */
  private function touch(): void
  {
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method normalizeShortString.
   *
   * @since 1.0.0
   *
   * @param ?string $value the raw value
   * @param string $fieldName the field name for error messages
   * @param int $maxLength the maximum allowed length
   *
   * @return ?string the normalized value
   */
  private static function normalizeShortString(?string $value, string $fieldName, int $maxLength): ?string
  {
    if (null === $value) {
      return null;
    }

    $normalized = trim($value);
    if ('' === $normalized) {
      return null;
    }

    if (mb_strlen($normalized) > $maxLength) {
      throw new InvalidArgumentException(
        sprintf('Equipment %s must be at most %d characters.', $fieldName, $maxLength),
      );
    }

    return $normalized;
  }
  // #endregion
}
