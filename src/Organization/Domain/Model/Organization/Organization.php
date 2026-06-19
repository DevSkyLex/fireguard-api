<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use DateTimeImmutable;
use Organization\Domain\ValueObject\{
  OrganizationId,
  OrganizationName,
  OrganizationNotificationSettings,
  OrganizationRegionalSettings,
  OrganizationSettings,
  OrganizationSlug,
  OrganizationStatus
};

use function trim;

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
  // #region Properties
  /**
   * Property settings.
   *
   * Structured organization preferences (notifications, regional).
   *
   * @since 1.0.0
   */
  private OrganizationSettings $settings;
  // #endregion

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
   * @param ?string $description the optional organization description
   * @param ?string $logoUrl the optional organization logo URL
   * @param ?OrganizationSettings $settings the structured organization settings
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
    private ?string $description = null,
    private ?string $logoUrl = null,
    ?OrganizationSettings $settings = null,
  ) {
    $this->settings = $settings ?? OrganizationSettings::default();
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
   * @param ?string $description the optional organization description
   * @param ?string $logoUrl the optional organization logo URL
   * @param ?OrganizationSettings $settings the optional structured organization settings
   *
   * @return self the created organization aggregate
   */
  public static function create(
    OrganizationId $id,
    OrganizationName $name,
    string $ownerUserId,
    ?OrganizationSlug $slug = null,
    ?string $createdByUserId = null,
    ?string $description = null,
    ?string $logoUrl = null,
    ?OrganizationSettings $settings = null,
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
      description: $description,
      logoUrl: $logoUrl,
      settings: $settings,
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
   * @param ?string $description the optional organization description
   * @param ?string $logoUrl the optional organization logo URL
   * @param ?OrganizationSettings $settings the optional structured organization settings
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
    ?string $description = null,
    ?string $logoUrl = null,
    ?OrganizationSettings $settings = null,
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
      description: $description,
      logoUrl: $logoUrl,
      settings: $settings,
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
   * Method description.
   *
   * Returns the organization description.
   *
   * @since 1.0.0
   *
   * @return ?string the organization description when set
   */
  public function description(): ?string
  {
    return $this->description;
  }

  /**
   * Method logoUrl.
   *
   * Returns the organization logo URL.
   *
   * @since 1.0.0
   *
   * @return ?string the organization logo URL when set
   */
  public function logoUrl(): ?string
  {
    return $this->logoUrl;
  }

  /**
   * Method changeDescription.
   *
   * Updates the organization description, normalizing empty values to null.
   *
   * @since 1.0.0
   *
   * @param ?string $description the new organization description
   */
  public function changeDescription(?string $description): void
  {
    $normalized = null !== $description ? trim($description) : null;
    $this->description = '' === $normalized ? null : $normalized;
    $this->touch();
  }

  /**
   * Method setLogoUrl.
   *
   * Updates the organization logo URL.
   *
   * @since 1.0.0
   *
   * @param ?string $logoUrl the new organization logo URL
   */
  public function setLogoUrl(?string $logoUrl): void
  {
    $this->logoUrl = $logoUrl;
    $this->touch();
  }

  /**
   * Method removeLogo.
   *
   * Clears the organization logo URL.
   *
   * @since 1.0.0
   */
  public function removeLogo(): void
  {
    $this->logoUrl = null;
    $this->touch();
  }

  /**
   * Method settings.
   *
   * Returns the structured organization settings.
   *
   * @since 1.0.0
   *
   * @return OrganizationSettings the organization settings
   */
  public function settings(): OrganizationSettings
  {
    return $this->settings;
  }

  /**
   * Method updateNotificationSettings.
   *
   * Replaces the organization notification policy sub-section.
   *
   * @since 1.0.0
   *
   * @param OrganizationNotificationSettings $notifications the new notification settings
   */
  public function updateNotificationSettings(OrganizationNotificationSettings $notifications): void
  {
    $this->settings = $this->settings->withNotifications($notifications);
    $this->touch();
  }

  /**
   * Method updateRegionalSettings.
   *
   * Replaces the organization regional and formatting sub-section.
   *
   * @since 1.0.0
   *
   * @param OrganizationRegionalSettings $regional the new regional settings
   */
  public function updateRegionalSettings(OrganizationRegionalSettings $regional): void
  {
    $this->settings = $this->settings->withRegional($regional);
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
