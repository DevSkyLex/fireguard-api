<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use DateTimeImmutable;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationSlug, OrganizationStatus};

/**
 * Model Organization.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Organization
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Organization class.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $id the organization identifier
   * @param OrganizationName $name the organization name
   * @param OrganizationSlug $slug the organization slug
   * @param string $ownerUserId the owner user identifier
   * @param string $createdByUserId the creator user identifier
   * @param OrganizationStatus $status the current organization status
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  private function __construct(
    private OrganizationId $id,
    private OrganizationName $name,
    private OrganizationSlug $slug,
    private string $ownerUserId,
    private string $createdByUserId,
    private OrganizationStatus $status,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new organization aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $id the organization identifier
   * @param OrganizationName $name the organization name
   * @param string $ownerUserId the owner user identifier
   * @param ?OrganizationSlug $slug the optional organization slug
   * @param ?string $createdByUserId the optional creator user identifier
   *
   * @return self the created organization aggregate
   */
  public static function create(
    OrganizationId $id,
    OrganizationName $name,
    string $ownerUserId,
    ?OrganizationSlug $slug = null,
    ?string $createdByUserId = null,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      name: $name,
      slug: $slug ?? OrganizationSlug::fromName((string) $name),
      ownerUserId: $ownerUserId,
      createdByUserId: $createdByUserId ?? $ownerUserId,
      status: OrganizationStatus::ACTIVE,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an organization aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $id the organization identifier
   * @param OrganizationName $name the organization name
   * @param string $createdByUserId the creator user identifier
   * @param bool $isActive whether the organization is active
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param ?DateTimeImmutable $updatedAt the last update timestamp
   * @param ?string $ownerUserId the optional owner user identifier
   * @param ?OrganizationSlug $slug the optional organization slug
   * @param ?OrganizationStatus $status the optional explicit status
   *
   * @return self the reconstituted organization aggregate
   */
  public static function reconstitute(
    OrganizationId $id,
    OrganizationName $name,
    string $createdByUserId,
    bool $isActive,
    DateTimeImmutable $createdAt,
    ?DateTimeImmutable $updatedAt = null,
    ?string $ownerUserId = null,
    ?OrganizationSlug $slug = null,
    ?OrganizationStatus $status = null,
  ): self {
    return new self(
      id: $id,
      name: $name,
      slug: $slug ?? OrganizationSlug::fromName((string) $name),
      ownerUserId: $ownerUserId ?? $createdByUserId,
      createdByUserId: $createdByUserId,
      status: $status ?? OrganizationStatus::fromIsActive($isActive),
      createdAt: $createdAt,
      updatedAt: $updatedAt ?? $createdAt,
    );
  }

  /**
   * Method id.
   *
   * Returns the organization identifier.
   *
   * @since 1.0.0
   *
   * @return OrganizationId the organization identifier
   */
  public function id(): OrganizationId
  {
    return $this->id;
  }

  /**
   * Method name.
   *
   * Returns the organization display name.
   *
   * @since 1.0.0
   *
   * @return OrganizationName the organization name
   */
  public function name(): OrganizationName
  {
    return $this->name;
  }

  /**
   * Method slug.
   *
   * Returns the organization slug.
   *
   * @since 1.0.0
   *
   * @return OrganizationSlug the organization slug
   */
  public function slug(): OrganizationSlug
  {
    return $this->slug;
  }

  /**
   * Method ownerUserId.
   *
   * Returns the owner user identifier.
   *
   * @since 1.0.0
   *
   * @return string the owner user identifier
   */
  public function ownerUserId(): string
  {
    return $this->ownerUserId;
  }

  /**
   * Method createdByUserId.
   *
   * Returns the creator user identifier.
   *
   * @since 1.0.0
   *
   * @return string the creator user identifier
   */
  public function createdByUserId(): string
  {
    return $this->createdByUserId;
  }

  /**
   * Method status.
   *
   * Returns the current organization status.
   *
   * @since 1.0.0
   *
   * @return OrganizationStatus the organization status
   */
  public function status(): OrganizationStatus
  {
    return $this->status;
  }

  /**
   * Method isActive.
   *
   * Checks whether the organization is active.
   *
   * @since 1.0.0
   *
   * @return bool true when active, false otherwise
   */
  public function isActive(): bool
  {
    return $this->status->isActive();
  }

  /**
   * Method createdAt.
   *
   * Returns the organization creation timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * Returns the organization last update timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the last update timestamp
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method rename.
   *
   * Updates the organization name.
   *
   * @since 1.0.0
   *
   * @param OrganizationName $name the new organization name
   */
  public function rename(OrganizationName $name): void
  {
    $this->name = $name;
    $this->touch();
  }

  /**
   * Method changeSlug.
   *
   * Updates the organization slug.
   *
   * @since 1.0.0
   *
   * @param OrganizationSlug $slug the new organization slug
   */
  public function changeSlug(OrganizationSlug $slug): void
  {
    $this->slug = $slug;
    $this->touch();
  }

  /**
   * Method deactivate.
   *
   * Moves the organization to suspended status.
   *
   * @since 1.0.0
   */
  public function deactivate(): void
  {
    $this->status = OrganizationStatus::SUSPENDED;
    $this->touch();
  }

  /**
   * Method activate.
   *
   * Moves the organization to active status.
   *
   * @since 1.0.0
   */
  public function activate(): void
  {
    $this->status = OrganizationStatus::ACTIVE;
    $this->touch();
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
