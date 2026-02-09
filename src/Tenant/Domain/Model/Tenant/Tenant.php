<?php

declare(strict_types=1);

namespace Tenant\Domain\Model\Tenant;

use DateTimeImmutable;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};

/**
 * Model Tenant.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Tenant
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param TenantId $id the tenant ID
   * @param TenantName $name the tenant name
   * @param TenantSettings $settings the tenant settings
   * @param bool $isActive whether the tenant is active
   * @param DateTimeImmutable $createdAt the creation timestamp
   */
  private function __construct(
    private TenantId $id,
    private TenantName $name,
    private TenantSettings $settings,
    private bool $isActive,
    private DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new tenant.
   *
   * @since 1.0.0
   *
   * @param TenantId $id the tenant ID
   * @param TenantName $name the tenant name
   * @param TenantSettings $settings the tenant settings
   *
   * @return self the new Tenant instance
   */
  public static function create(
    TenantId $id,
    TenantName $name,
    TenantSettings $settings,
  ): self {
    return new self(
      id: $id,
      name: $name,
      settings: $settings,
      isActive: true,
      createdAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method reconstitute.
   *
   * Rebuilds a tenant from persistence.
   *
   * @since 1.0.0
   *
   * @param TenantId $id the tenant ID
   * @param TenantName $name the tenant name
   * @param TenantSettings $settings the tenant settings
   * @param bool $isActive whether the tenant is active
   * @param DateTimeImmutable $createdAt the creation timestamp
   *
   * @return self the reconstituted tenant
   */
  public static function reconstitute(
    TenantId $id,
    TenantName $name,
    TenantSettings $settings,
    bool $isActive,
    DateTimeImmutable $createdAt,
  ): self {
    return new self(
      id: $id,
      name: $name,
      settings: $settings,
      isActive: $isActive,
      createdAt: $createdAt,
    );
  }

  /**
   * Method id.
   *
   * Returns the tenant ID.
   *
   * @since 1.0.0
   *
   * @return TenantId the tenant ID
   */
  public function id(): TenantId
  {
    return $this->id;
  }

  /**
   * Method name.
   *
   * Returns the tenant name.
   *
   * @since 1.0.0
   *
   * @return TenantName the tenant name
   */
  public function name(): TenantName
  {
    return $this->name;
  }

  /**
   * Method rename.
   *
   * Updates the tenant name.
   *
   * @since 1.0.0
   *
   * @param TenantName $name the new name
   */
  public function rename(TenantName $name): void
  {
    $this->name = $name;
  }

  /**
   * Method settings.
   *
   * Returns the tenant settings.
   *
   * @since 1.0.0
   *
   * @return TenantSettings the tenant settings
   */
  public function settings(): TenantSettings
  {
    return $this->settings;
  }

  /**
   * Method isActive.
   *
   * Returns whether the tenant is active.
   *
   * @since 1.0.0
   *
   * @return bool true if active, false otherwise
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method createdAt.
   *
   * Returns the creation timestamp.
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
   * Method activate.
   *
   * Activates the tenant.
   *
   * @since 1.0.0
   */
  public function activate(): void
  {
    $this->isActive = true;
  }

  /**
   * Method deactivate.
   *
   * Deactivates the tenant.
   *
   * @since 1.0.0
   */
  public function deactivate(): void
  {
    $this->isActive = false;
  }

  /**
   * Method updateSettings.
   *
   * Updates the tenant settings.
   *
   * @since 1.0.0
   *
   * @param TenantSettings $settings the new settings
   */
  public function updateSettings(TenantSettings $settings): void
  {
    $this->settings = $settings;
  }
  // #endregion
}
