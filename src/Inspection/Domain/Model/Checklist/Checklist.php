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
   *
   * @return self the created checklist
   */
  public static function create(
    ChecklistId $id,
    ChecklistOrganizationId $organizationId,
    string $name,
    string $version,
    array $items = [],
  ): self {
    $normalizedName = trim($name);
    if ('' === $normalizedName) {
      throw new InvalidArgumentException('Checklist name must not be empty.');
    }

    if (mb_strlen($normalizedName) > 255) {
      throw new InvalidArgumentException(
        sprintf('Checklist name must be at most %d characters.', 255),
      );
    }

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
      name: $normalizedName,
      version: $normalizedVersion,
      status: ChecklistStatus::ACTIVE,
      items: $items,
      createdAt: $now,
      updatedAt: $now,
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
  // #endregion
}
