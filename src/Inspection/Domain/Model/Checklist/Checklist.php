<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Checklist;

use DateTimeImmutable;
use Inspection\Domain\Exception\ChecklistArchivedException;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId, ChecklistStatus};
use InvalidArgumentException;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * Model Checklist.
 *
 * A versioned inspection checklist template containing
 * a set of items to verify during an inspection.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Checklist
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ChecklistId $id the checklist identifier
   * @param ChecklistOrganizationId $organizationId the organization identifier
   * @param string $name the checklist name
   * @param string $version the version label
   * @param ChecklistStatus $status the current status
   * @param list<ChecklistItem> $items the checklist items
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?string $referenceCode optional human-facing reference code, unique per organization
   */
  private function __construct(
    private ChecklistId $id,
    private ChecklistOrganizationId $organizationId,
    private string $name,
    private string $version,
    private ChecklistStatus $status,
    private array $items,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?string $referenceCode = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new checklist in ACTIVE status.
   *
   * @since 1.0.0
   *
   * @param ChecklistId $id the checklist identifier
   * @param ChecklistOrganizationId $organizationId the organization identifier
   * @param string $name the checklist name
   * @param string $version the version label
   * @param list<ChecklistItem> $items the checklist items
   * @param ?string $referenceCode optional human-facing reference code, unique per organization
   *
   * @return self the created checklist
   */
  public static function create(
    ChecklistId $id,
    ChecklistOrganizationId $organizationId,
    string $name,
    string $version,
    array $items = [],
    ?string $referenceCode = null,
  ): self {
    $normalizedVersion = trim($version);
    if ('' === $normalizedVersion) {
      throw new InvalidArgumentException('Checklist version must not be empty.');
    }

    if (mb_strlen($normalizedVersion) > 50) {
      throw new InvalidArgumentException(
        sprintf('Checklist version must be at most %d characters.', 50),
      );
    }

    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      name: self::normalizeName($name),
      version: $normalizedVersion,
      status: ChecklistStatus::ACTIVE,
      items: $items,
      createdAt: $now,
      updatedAt: $now,
      referenceCode: self::normalizeReferenceCode($referenceCode),
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a checklist from persisted state.
   *
   * @since 1.0.0
   *
   * @param ChecklistId $id the checklist identifier
   * @param ChecklistOrganizationId $organizationId the organization identifier
   * @param string $name the checklist name
   * @param string $version the version label
   * @param ChecklistStatus $status the current status
   * @param list<ChecklistItem> $items the checklist items
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?string $referenceCode optional human-facing reference code, unique per organization
   *
   * @return self the reconstituted checklist
   */
  public static function reconstitute(
    ChecklistId $id,
    ChecklistOrganizationId $organizationId,
    string $name,
    string $version,
    ChecklistStatus $status,
    array $items,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?string $referenceCode = null,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      name: $name,
      version: $version,
      status: $status,
      items: $items,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      referenceCode: $referenceCode,
    );
  }

  /**
   * Method archive.
   *
   * Archives the checklist, preventing it from being used in new inspections.
   *
   * @since 1.0.0
   */
  public function archive(): void
  {
    if ($this->status->isArchived()) {
      throw ChecklistArchivedException::withId((string) $this->id);
    }

    $this->status = ChecklistStatus::ARCHIVED;
    $this->touch();
  }

  /**
   * Method update.
   *
   * Partially updates the checklist name, reference code, and/or item list.
   * An archived checklist can never be edited. Structural item changes are
   * the caller's responsibility to gate (see
   * `Inspection\Domain\Exception\ChecklistInUseException`): once a checklist
   * is referenced by an existing inspection, its items must stay immutable so
   * that historical inspection evidence is never reinterpreted retroactively.
   * This aggregate only enforces intra-aggregate invariants (archived state,
   * name/reference-code format); the "in use" check requires looking at other
   * aggregates and therefore belongs to the application layer.
   *
   * @since 1.0.0
   *
   * @param ?string $name the new name, when provided
   * @param bool $hasName whether the name field was provided
   * @param ?string $referenceCode the new reference code, when provided
   * @param bool $hasReferenceCode whether the reference code field was provided
   * @param list<ChecklistItem> $items the new item list, when provided
   * @param bool $hasItems whether the items field was provided
   */
  public function update(
    ?string $name = null,
    bool $hasName = false,
    ?string $referenceCode = null,
    bool $hasReferenceCode = false,
    array $items = [],
    bool $hasItems = false,
  ): void {
    if ($this->status->isArchived()) {
      throw ChecklistArchivedException::withId((string) $this->id);
    }

    if ($hasName && null !== $name) {
      $this->name = self::normalizeName($name);
    }

    if ($hasReferenceCode) {
      $this->referenceCode = self::normalizeReferenceCode($referenceCode);
    }

    if ($hasItems) {
      $this->items = $items;
    }

    $this->touch();
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): ChecklistId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): ChecklistOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method name.
   *
   * @since 1.0.0
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * Method referenceCode.
   *
   * @since 1.0.0
   */
  public function referenceCode(): ?string
  {
    return $this->referenceCode;
  }

  /**
   * Method version.
   *
   * @since 1.0.0
   */
  public function version(): string
  {
    return $this->version;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): ChecklistStatus
  {
    return $this->status;
  }

  /**
   * Method items.
   *
   * @since 1.0.0
   *
   * @return list<ChecklistItem> the checklist items
   */
  public function items(): array
  {
    return $this->items;
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
   * Method normalizeName.
   *
   * Trims and validates a checklist name.
   *
   * @since 1.0.0
   *
   * @param string $name the raw name
   *
   * @return string the normalized name
   */
  private static function normalizeName(string $name): string
  {
    $normalized = trim($name);
    if ('' === $normalized) {
      throw new InvalidArgumentException('Checklist name must not be empty.');
    }

    if (mb_strlen($normalized) > 255) {
      throw new InvalidArgumentException(
        sprintf('Checklist name must be at most %d characters.', 255),
      );
    }

    return $normalized;
  }

  /**
   * Method normalizeReferenceCode.
   *
   * Trims a reference code, converting an empty value to null (the reference
   * code is optional metadata, not an identity). Enforces the persisted
   * column length so validation fails fast instead of surfacing as a
   * database error.
   *
   * @since 1.0.0
   *
   * @param ?string $referenceCode the raw reference code
   *
   * @return ?string the normalized reference code
   */
  private static function normalizeReferenceCode(?string $referenceCode): ?string
  {
    if (null === $referenceCode) {
      return null;
    }

    $normalized = trim($referenceCode);
    if ('' === $normalized) {
      return null;
    }

    if (mb_strlen($normalized) > 40) {
      throw new InvalidArgumentException(
        sprintf('Checklist reference code must be at most %d characters.', 40),
      );
    }

    return $normalized;
  }
  // #endregion
}
