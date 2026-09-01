<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\ValueObject\{
  FacilityCoordinates,
  FacilityId,
  FacilityName,
  FacilityOrganizationId,
  FacilityStatus,
  FacilityType,
  PlanGeometry
};
use InvalidArgumentException;
use Shared\Domain\Exception\InvalidValueException;

use function is_array;
use function is_string;
use function mb_strlen;
use function trim;

/**
 * Model Facility.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Facility
{
  // #region Constants
  /**
   * The lowest legal stacking order for a floor.
   *
   * @since 1.0.0
   */
  private const int MIN_LEVEL_INDEX = -100;

  /**
   * The highest legal stacking order for a floor.
   *
   * @since 1.0.0
   */
  private const int MAX_LEVEL_INDEX = 200;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Facility class.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityType $type the facility type
   * @param FacilityName $name the facility name
   * @param FacilityStatus $status the facility status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?FacilityId $parentFacilityId the optional parent facility identifier
   * @param ?string $code the optional facility code
   * @param ?string $address the optional address
   * @param array<string, mixed> $metadata the optional metadata
   * @param ?FacilityCoordinates $coordinates the optional geographic coordinates
   * @param ?PlanGeometry $planGeometry the optional spatial geometry bound to an ancestor's floor plan
   * @param ?int $levelIndex the optional stacking order of the floor (ground floor = 0, first basement = -1)
   */
  private function __construct(
    private FacilityId $id,
    private FacilityOrganizationId $organizationId,
    private FacilityType $type,
    private FacilityName $name,
    private FacilityStatus $status,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?FacilityId $parentFacilityId = null,
    private ?string $code = null,
    private ?string $address = null,
    private array $metadata = [],
    private ?FacilityCoordinates $coordinates = null,
    private ?PlanGeometry $planGeometry = null,
    private ?int $levelIndex = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new facility aggregate.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityType $type the facility type
   * @param FacilityName $name the facility name
   * @param ?FacilityId $parentFacilityId the optional parent facility identifier
   * @param ?string $code the optional facility code
   * @param ?string $address the optional address
   * @param array<string, mixed> $metadata the optional metadata
   * @param ?FacilityCoordinates $coordinates the optional geographic coordinates
   * @param ?int $levelIndex the optional stacking order of the floor (ground floor = 0, first basement = -1)
   *
   * @return self the created facility aggregate
   */
  public static function create(
    FacilityId $id,
    FacilityOrganizationId $organizationId,
    FacilityType $type,
    FacilityName $name,
    ?FacilityId $parentFacilityId = null,
    ?string $code = null,
    ?string $address = null,
    array $metadata = [],
    ?FacilityCoordinates $coordinates = null,
    ?int $levelIndex = null,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      type: $type,
      name: $name,
      status: FacilityStatus::ACTIVE,
      createdAt: $now,
      updatedAt: $now,
      parentFacilityId: $parentFacilityId,
      code: self::normalizeCode($code),
      address: self::normalizeAddress($address),
      metadata: self::normalizeMetadata($metadata),
      coordinates: $coordinates,
      planGeometry: null,
      levelIndex: self::normalizeLevelIndex($levelIndex),
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a facility aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityType $type the facility type
   * @param FacilityName $name the facility name
   * @param FacilityStatus $status the facility status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?FacilityId $parentFacilityId the optional parent facility identifier
   * @param ?string $code the optional facility code
   * @param ?string $address the optional address
   * @param array<string, mixed> $metadata the optional metadata
   * @param ?FacilityCoordinates $coordinates the optional geographic coordinates
   * @param ?PlanGeometry $planGeometry the optional spatial geometry bound to an ancestor's floor plan
   * @param ?int $levelIndex the optional stacking order of the floor (ground floor = 0, first basement = -1)
   *
   * @return self the reconstituted facility aggregate
   */
  public static function reconstitute(
    FacilityId $id,
    FacilityOrganizationId $organizationId,
    FacilityType $type,
    FacilityName $name,
    FacilityStatus $status,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?FacilityId $parentFacilityId = null,
    ?string $code = null,
    ?string $address = null,
    array $metadata = [],
    ?FacilityCoordinates $coordinates = null,
    ?PlanGeometry $planGeometry = null,
    ?int $levelIndex = null,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      type: $type,
      name: $name,
      status: $status,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      parentFacilityId: $parentFacilityId,
      code: self::normalizeCode($code),
      address: self::normalizeAddress($address),
      metadata: self::normalizeMetadata($metadata),
      coordinates: $coordinates,
      planGeometry: $planGeometry,
      levelIndex: self::normalizeLevelIndex($levelIndex),
    );
  }

  /**
   * Method rename.
   *
   * @since 1.0.0
   */
  public function rename(FacilityName $name): void
  {
    $this->name = $name;
    $this->touch();
  }

  /**
   * Method changeType.
   *
   * @since 1.0.0
   */
  public function changeType(FacilityType $type): void
  {
    $this->type = $type;
    $this->touch();
  }

  /**
   * Method moveTo.
   *
   * @since 1.0.0
   */
  public function moveTo(?FacilityId $parentFacilityId): void
  {
    $this->parentFacilityId = $parentFacilityId;
    $this->touch();
  }

  /**
   * Method changeCode.
   *
   * @since 1.0.0
   */
  public function changeCode(?string $code): void
  {
    $this->code = self::normalizeCode($code);
    $this->touch();
  }

  /**
   * Method changeAddress.
   *
   * @since 1.0.0
   */
  public function changeAddress(?string $address): void
  {
    $this->address = self::normalizeAddress($address);
    $this->touch();
  }

  /**
   * Method changeCoordinates.
   *
   * @since 1.0.0
   */
  public function changeCoordinates(?FacilityCoordinates $coordinates): void
  {
    $this->coordinates = $coordinates;
    $this->touch();
  }

  /**
   * Method changeLevelIndex.
   *
   * @since 1.0.0
   *
   * @param ?int $levelIndex the new stacking order of the floor, null clearing it
   */
  public function changeLevelIndex(?int $levelIndex): void
  {
    $this->levelIndex = self::normalizeLevelIndex($levelIndex);
    $this->touch();
  }

  /**
   * Method assignPlanGeometry.
   *
   * Binds this facility (a spatial zone) to a polygon drawn over an
   * ancestor's floor plan attachment.
   *
   * @since 1.0.0
   */
  public function assignPlanGeometry(PlanGeometry $planGeometry): void
  {
    $this->planGeometry = $planGeometry;
    $this->touch();
  }

  /**
   * Method clearPlanGeometry.
   *
   * Removes this facility's spatial geometry, if any. Idempotent: clearing
   * an already-unset geometry still touches the record, mirroring
   * `changeCoordinates(null)`.
   *
   * @since 1.0.0
   */
  public function clearPlanGeometry(): void
  {
    $this->planGeometry = null;
    $this->touch();
  }

  /**
   * Method changeMetadata.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $metadata the new metadata
   */
  public function changeMetadata(array $metadata): void
  {
    $this->metadata = self::normalizeMetadata($metadata);
    $this->touch();
  }

  /**
   * Method archive.
   *
   * @since 1.0.0
   */
  public function archive(): void
  {
    if (FacilityStatus::ARCHIVED === $this->status) {
      return;
    }

    $this->status = FacilityStatus::ARCHIVED;
    $this->touch();
  }

  /**
   * Method restore.
   *
   * @since 1.0.0
   */
  public function restore(): void
  {
    if (FacilityStatus::ACTIVE === $this->status) {
      return;
    }

    $this->status = FacilityStatus::ACTIVE;
    $this->touch();
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): FacilityId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): FacilityOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method parentFacilityId.
   *
   * @since 1.0.0
   */
  public function parentFacilityId(): ?FacilityId
  {
    return $this->parentFacilityId;
  }

  /**
   * Method type.
   *
   * @since 1.0.0
   */
  public function type(): FacilityType
  {
    return $this->type;
  }

  /**
   * Method name.
   *
   * @since 1.0.0
   */
  public function name(): FacilityName
  {
    return $this->name;
  }

  /**
   * Method code.
   *
   * @since 1.0.0
   */
  public function code(): ?string
  {
    return $this->code;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): FacilityStatus
  {
    return $this->status;
  }

  /**
   * Method address.
   *
   * @since 1.0.0
   */
  public function address(): ?string
  {
    return $this->address;
  }

  /**
   * Method coordinates.
   *
   * @since 1.0.0
   */
  public function coordinates(): ?FacilityCoordinates
  {
    return $this->coordinates;
  }

  /**
   * Method planGeometry.
   *
   * @since 1.0.0
   */
  public function planGeometry(): ?PlanGeometry
  {
    return $this->planGeometry;
  }

  /**
   * Method levelIndex.
   *
   * @since 1.0.0
   */
  public function levelIndex(): ?int
  {
    return $this->levelIndex;
  }

  /**
   * Method metadata.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the metadata
   */
  public function metadata(): array
  {
    return $this->metadata;
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
   * Method normalizeCode.
   *
   * @since 1.0.0
   */
  private static function normalizeCode(?string $code): ?string
  {
    if (null === $code) {
      return null;
    }

    $normalized = trim($code);
    if ('' === $normalized) {
      return null;
    }

    if (mb_strlen($normalized) > 80) {
      throw new InvalidArgumentException('Facility code must be at most 80 characters.');
    }

    return $normalized;
  }

  /**
   * Method normalizeAddress.
   *
   * @since 1.0.0
   */
  private static function normalizeAddress(?string $address): ?string
  {
    if (null === $address) {
      return null;
    }

    $normalized = trim($address);
    if ('' === $normalized) {
      return null;
    }

    if (mb_strlen($normalized) > 255) {
      throw new InvalidArgumentException('Facility address must be at most 255 characters.');
    }

    return $normalized;
  }

  /**
   * Method normalizeLevelIndex.
   *
   * Enforces the -100..200 stacking-order bound in the domain, not only in
   * the presentation DTOs.
   *
   * @since 1.0.0
   *
   * @throws InvalidValueException when the level index is out of range
   */
  private static function normalizeLevelIndex(?int $levelIndex): ?int
  {
    if (null === $levelIndex) {
      return null;
    }

    if ($levelIndex < self::MIN_LEVEL_INDEX || $levelIndex > self::MAX_LEVEL_INDEX) {
      throw InvalidValueException::because('Facility level index must be between -100 and 200.');
    }

    return $levelIndex;
  }

  /**
   * Method normalizeMetadata.
   *
   * @since 1.0.0
   *
   * @param mixed $metadata the metadata to normalize
   *
   * @return array<string, mixed> normalized metadata
   */
  private static function normalizeMetadata(mixed $metadata): array
  {
    if (!is_array($metadata)) {
      return [];
    }

    $normalized = [];

    foreach ($metadata as $key => $value) {
      if (!is_string($key) || '' === $key) {
        continue;
      }

      $normalized[$key] = $value;
    }

    return $normalized;
  }
  // #endregion
}
